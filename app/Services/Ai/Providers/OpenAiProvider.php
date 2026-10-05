<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\ImageProvider;
use App\Services\Ai\Contracts\ModelDiscoveryInterface;
use App\Services\Ai\DTO\ImageResult;
use App\Services\Ai\DTO\TextResult;
use App\Services\Ai\Discovery\DiscoversModels;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiProvider extends AbstractChatProvider implements ImageProvider, ModelDiscoveryInterface
{
    use DiscoversModels;

    /** Model chosen for the in-flight chat attempt (null = configured model). */
    private ?string $attemptModel = null;

    public function __construct(
        string $baseUrl,
        ?string $apiKey,
        string $defaultModel,
        int $timeout = 60,
        protected readonly ?string $imageModel = null,
    ) {
        parent::__construct($baseUrl, $apiKey, $defaultModel, $timeout);
    }

    public function providerName(): string
    {
        return 'openai';
    }

    public function complete(string $systemPrompt, array $messages, array $options = []): TextResult
    {
        if (! $this->apiKey) {
            throw AiProviderException::noKeyConfigured($this->providerName());
        }

        return $this->sendWithModelFallback(function (string $model) use ($systemPrompt, $messages, $options) {
            $this->attemptModel = $model;

            try {
                return parent::complete($systemPrompt, $messages, $options);
            } finally {
                $this->attemptModel = null;
            }
        });
    }

    protected function currentModel(): string
    {
        return $this->attemptModel ?? $this->defaultModel;
    }

    protected function isModelUnavailableResponse(int $status, string $body): bool
    {
        if ($status === 404) {
            return true;
        }

        return $status === 400 && (str_contains($body, 'model_not_found') || preg_match('/model .* does not exist/i', $body) === 1);
    }

    protected function fetchModelsFromApi(): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(min($this->timeout, 20))
            ->get(rtrim($this->baseUrl, '/').'/models');

        if ($response->failed()) {
            throw new \RuntimeException('OpenAI models list failed with HTTP '.$response->status());
        }

        $models = [];
        foreach ((array) $response->json('data', []) as $row) {
            $id = is_array($row) ? ($row['id'] ?? null) : null;
            if (is_string($id) && $id !== '') {
                $models[] = ['id' => $id, 'name' => $id, 'capabilities' => [self::capabilityOf($id)]];
            }
        }

        return $models;
    }

    /** Classifies an OpenAI model id; only "text" models are used for chat. */
    public static function capabilityOf(string $id): string
    {
        $id = strtolower($id);

        return match (true) {
            str_contains($id, 'embedding') => 'embedding',
            str_contains($id, 'dall-e'), str_contains($id, 'image') => 'image',
            (bool) preg_match('/whisper|tts|audio|realtime|transcribe|speech/', $id) => 'audio',
            (bool) preg_match('/moderation|search|instruct|computer-use|codex|deep-research|babbage|davinci|sora/', $id) => 'other',
            (bool) preg_match('/^(gpt-|chatgpt-|o\d)/', $id) => 'text',
            default => 'other',
        };
    }

    protected function rankModel(string $id): int
    {
        $score = (int) (self::versionOf($id) * 10);
        $score += preg_match('/-\d{4}-\d{2}-\d{2}$|-\d{4}$/', $id) ? 0 : 500; // prefer aliases over dated snapshots
        $score += str_contains($id, 'mini') ? 300 : 0;                          // fast/cheap for chat
        $score -= preg_match('/preview|nano|pro|chatgpt-/', $id) ? 400 : 0;
        $score -= preg_match('/^o\d/', $id) ? 200 : 0;                          // reasoning-only families

        return $score;
    }

    public function generate(string $prompt, array $options = []): ImageResult
    {
        if (! $this->apiKey) {
            throw AiProviderException::noKeyConfigured($this->providerName());
        }

        $model = $this->imageModel ?? 'dall-e-3';

        $payload = array_filter([
            'model' => $model,
            'prompt' => $prompt,
            'size' => $options['size'] ?? '1024x1024',
            'quality' => $options['quality'] ?? null,
            'n' => 1,
        ], static fn ($v) => $v !== null);

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->retry(2, 200)
                ->post(rtrim($this->baseUrl, '/').'/images/generations', $payload);
        } catch (ConnectionException|Throwable $e) {
            throw AiProviderException::forProvider($this->providerName());
        }

        if ($response->failed()) {
            throw AiProviderException::forProvider(
                $this->providerName(),
                context: ['status' => $response->status()],
            );
        }

        $url = $response->json('data.0.url');
        $b64 = $response->json('data.0.b64_json');

        if (! $url && ! $b64) {
            throw AiProviderException::forProvider($this->providerName(), context: ['reason' => 'empty_response']);
        }

        return new ImageResult(
            base64OrUrl: $url ?: $b64,
            isUrl: (bool) $url,
            mime: 'image/png',
            model: (string) $model,
            provider: $this->providerName(),
        );
    }
}
