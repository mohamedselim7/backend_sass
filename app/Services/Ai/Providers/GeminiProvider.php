<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\ImageProvider;
use App\Services\Ai\Contracts\ModelDiscoveryInterface;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\Discovery\DiscoversModels;
use App\Services\Ai\DTO\ImageResult;
use App\Services\Ai\DTO\TextResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Talks to Google's native `generateContent` REST API (not the OpenAI-compat shim). */
class GeminiProvider implements TextProvider, ImageProvider, ModelDiscoveryInterface
{
    use DiscoversModels;

    /** Transient statuses worth a short retry (never 4xx like 400/401/403/404). */
    private const TRANSIENT = [429, 500, 502, 503, 504];

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $defaultModel,
        private readonly int $timeout = 60,
        private readonly ?string $imageModel = null,
    ) {}

    public function providerName(): string
    {
        return 'gemini';
    }

    public function complete(string $systemPrompt, array $messages, array $options = []): TextResult
    {
        if (! $this->apiKey) {
            throw AiProviderException::noKeyConfigured($this->providerName());
        }

        return $this->sendWithModelFallback(
            fn (string $model) => $this->completeWith($model, $systemPrompt, $messages, $options),
        );
    }

    public function generate(string $prompt, array $options = []): ImageResult
    {
        if (! $this->apiKey) {
            throw AiProviderException::noKeyConfigured($this->providerName());
        }

        $requestedModel = $this->imageModel ?? 'gemini-3.1-flash-image';
        $model = $this->resolveImageModel($requestedModel);
        $response = $this->generateImageWith($model, $prompt, $options, $requestedModel);

        if ($this->isModelUnavailableResponse($response->status(), $response->body())) {
            $replacement = $this->resolveImageModel($requestedModel, forceRefresh: true, exclude: $model);
            if ($replacement !== $model) {
                Log::warning('AI image model fallback triggered.', [
                    'provider' => $this->providerName(),
                    'requested_model' => $model,
                    'resolved_model' => $replacement,
                    'capability' => 'image_generation',
                    'reason' => 'requested model unavailable',
                ]);
                $model = $replacement;
                $response = $this->generateImageWith($model, $prompt, $options, $requestedModel);
            }
        }

        if ($response->failed()) {
            $error = $this->errorDetails($response);
            $safeErrorMessage = is_string($error['message'])
                ? str_replace((string) $this->apiKey, '[REDACTED]', $error['message'])
                : 'تعذر توليد الصورة عبر Gemini.';
            $this->logFailure($model, $response->status(), responseBody: $response->body(), requestedModel: $requestedModel, capability: 'image_generation');
            throw AiProviderException::forProvider(
                $this->providerName(),
                safeMessage: $safeErrorMessage,
                status: $response->status() >= 400 && $response->status() < 500 ? $response->status() : 502,
                context: array_filter([
                    'status' => $response->status(),
                    'requested_model' => $requestedModel,
                    'resolved_model' => $model,
                    'capability' => 'image_generation',
                    'error_type' => $error['type'],
                    'error_code' => $error['code'],
                ], static fn ($value) => $value !== null && $value !== ''),
            );
        }

        $parts = (array) $response->json('candidates.0.content.parts', []);
        foreach ($parts as $part) {
            $inline = is_array($part) ? ($part['inlineData'] ?? $part['inline_data'] ?? null) : null;
            $data = is_array($inline) ? ($inline['data'] ?? null) : null;
            $mime = is_array($inline) ? ($inline['mimeType'] ?? $inline['mime_type'] ?? null) : null;

            if (is_string($data) && $data !== '' && is_string($mime) && str_starts_with($mime, 'image/')) {
                $bytes = base64_decode($data, true);
                $imageInfo = is_string($bytes) && $bytes !== '' ? getimagesizefromstring($bytes) : false;

                if (! is_array($imageInfo) || ! is_string($imageInfo['mime'] ?? null) || ! str_starts_with($imageInfo['mime'], 'image/')) {
                    continue;
                }

                return new ImageResult(
                    base64OrUrl: $data,
                    isUrl: false,
                    mime: $imageInfo['mime'],
                    model: $model,
                    provider: $this->providerName(),
                );
            }
        }

        throw AiProviderException::forProvider($this->providerName(), 'لم يُرجع Gemini بيانات صورة صالحة.', context: [
            'reason' => 'empty_image_response',
            'requested_model' => $requestedModel,
            'resolved_model' => $model,
            'capability' => 'image_generation',
        ]);
    }

    private function generateImageWith(string $model, string $prompt, array $options, string $requestedModel): Response
    {
        $generationConfig = ['responseModalities' => ['TEXT', 'IMAGE']];

        // An empty PHP array json_encodes to `[]`, which Gemini rejects with
        // "Invalid JSON payload". Only send imageConfig when it has fields.
        $imageConfig = array_filter([
            'aspectRatio' => $this->aspectRatioFor($options['size'] ?? null),
            'imageSize' => $this->imageSizeFor($options['size'] ?? null),
        ], static fn ($value) => $value !== null);

        if ($imageConfig !== []) {
            $generationConfig['imageConfig'] = $imageConfig;
        }

        $payload = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => $generationConfig,
        ];

        Log::info('AI request started.', [
            'provider' => $this->providerName(),
            'requested_model' => $requestedModel,
            'resolved_model' => $model,
            'capability' => 'image_generation',
        ]);

        return $this->postWithTransientRetry(
            "{$this->apiBaseUrl()}/models/{$model}:generateContent",
            $payload,
            $model,
            $requestedModel,
            'image_generation',
        );
    }

    private function resolveImageModel(string $requested, bool $forceRefresh = false, ?string $exclude = null): string
    {
        $models = $this->availableImageModels($forceRefresh);
        if ($models === []) {
            return $requested;
        }

        $ids = array_values(array_filter(array_column($models, 'id'), static fn ($id) => $id !== $exclude));
        if ($exclude === null && in_array($requested, $ids, true)) {
            return $requested;
        }

        foreach ((array) config('ai.providers.gemini.recommended_image_models', []) as $preferred) {
            if (in_array($preferred, $ids, true)) {
                return $preferred;
            }
        }

        usort($ids, fn ($a, $b) => [$this->rankModel($b), $b] <=> [$this->rankModel($a), $a]);
        $resolved = $ids[0] ?? null;

        if (! is_string($resolved) || $resolved === '') {
            throw AiProviderException::forProvider($this->providerName(), 'لا يوجد موديل Gemini متاح لتوليد الصور.', 422, [
                'reason' => 'no_compatible_image_model',
                'requested_model' => $requested,
                'capability' => 'image_generation',
            ]);
        }

        if ($resolved !== $requested) {
            Log::warning('AI image model fallback triggered.', [
                'provider' => $this->providerName(),
                'requested_model' => $requested,
                'resolved_model' => $resolved,
                'capability' => 'image_generation',
                'reason' => 'requested image model not listed by provider',
            ]);
        }

        return $resolved;
    }

    private function availableImageModels(bool $forceRefresh = false): array
    {
        if (! config('ai.model_discovery.enabled', true)) {
            return [];
        }

        $key = $this->modelsCacheKey().'.image';
        if ($forceRefresh) {
            Cache::forget($key);
        } elseif (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $models = array_values(array_filter(
                $this->fetchModelsFromApi(),
                static fn (array $model) => in_array('image', $model['capabilities'] ?? [], true),
            ));
        } catch (Throwable $e) {
            $this->logFailure($this->imageModel ?? '', null, $e, requestedModel: $this->imageModel, capability: 'image_generation');
            return [];
        }

        if ($models !== []) {
            Cache::put($key, $models, (int) config('ai.model_discovery.cache_ttl', 3600));
        }

        return $models;
    }

    private function completeWith(string $model, string $systemPrompt, array $messages, array $options): TextResult
    {
        $contents = array_map(static fn (array $message) => [
            'role' => ($message['role'] ?? 'user') === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => (string) ($message['content'] ?? '')]],
        ], $messages);

        $payload = ['contents' => $contents];

        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = ['parts' => [['text' => $systemPrompt]]];
        }

        $generationConfig = array_filter([
            'temperature' => $options['temperature'] ?? null,
            'maxOutputTokens' => $options['max_tokens'] ?? null,
        ], static fn ($v) => $v !== null);

        if ($generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        $response = $this->postWithTransientRetry("{$this->apiBaseUrl()}/models/{$model}:generateContent", $payload, $model);

        if ($response->failed()) {
            $this->logFailure($model, $response->status(), responseBody: $response->body());
            throw AiProviderException::forProvider(
                $this->providerName(),
                context: array_filter([
                    'status' => $response->status(),
                    'model' => $model,
                    'reason' => $this->isModelUnavailableResponse($response->status(), $response->body()) ? 'model_unavailable' : null,
                ], static fn ($v) => $v !== null),
            );
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text) || trim($text) === '') {
            throw AiProviderException::forProvider($this->providerName(), context: [
                'reason' => 'empty_response',
                'finish_reason' => $response->json('candidates.0.finishReason'),
                'model' => $model,
            ]);
        }

        return new TextResult(
            text: $text,
            promptTokens: $response->json('usageMetadata.promptTokenCount'),
            completionTokens: $response->json('usageMetadata.candidatesTokenCount'),
            model: $model,
            provider: $this->providerName(),
        );
    }

    private function postWithTransientRetry(string $url, array $payload, string $model, ?string $requestedModel = null, string $capability = 'text'): Response
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                    ->timeout($this->timeout)
                    ->post($url, $payload);
            } catch (ConnectionException|Throwable $e) {
                if ($attempt < 3) {
                    usleep(200_000);
                    continue;
                }
                $this->logFailure($model, null, $e, requestedModel: $requestedModel, capability: $capability);
                throw AiProviderException::forProvider($this->providerName());
            }

            if ($attempt < 3 && in_array($response->status(), self::TRANSIENT, true)) {
                usleep(200_000);
                continue;
            }

            return $response;
        }
    }

    private function apiBaseUrl(): string
    {
        $baseUrl = preg_replace('#/openai/?$#', '', rtrim($this->baseUrl, '/'));

        return is_string($baseUrl) && str_contains($baseUrl, 'generativelanguage.googleapis.com')
            ? $baseUrl
            : 'https://generativelanguage.googleapis.com/v1beta';
    }

    /** 404 "models/x is not found" or 400 "not supported for generateContent" → model unavailable. */
    private function isModelUnavailableResponse(int $status, string $body): bool
    {
        if ($status === 404) {
            return true;
        }

        return $status === 400 && preg_match('/is not found|not supported for generateContent|unsupported model|model.*(deprecated|discontinued)/i', $body) === 1;
    }

    protected function fetchModelsFromApi(): array
    {
        $models = [];
        $pageToken = null;

        for ($page = 0; $page < 10; $page++) {
            $response = Http::withHeaders(['x-goog-api-key' => $this->apiKey])
                ->timeout(min($this->timeout, 20))
                ->get($this->apiBaseUrl().'/models', array_filter(['pageSize' => 1000, 'pageToken' => $pageToken]));

            if ($response->failed()) {
                throw new \RuntimeException('Gemini models list failed with HTTP '.$response->status());
            }

            foreach ((array) $response->json('models', []) as $row) {
                $id = preg_replace('#^models/#', '', (string) ($row['name'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $models[] = [
                    'id' => $id,
                    'name' => (string) ($row['displayName'] ?? $id),
                    'capabilities' => [self::capabilityOf($id, (array) ($row['supportedGenerationMethods'] ?? []))],
                ];
            }

            $pageToken = $response->json('nextPageToken');
            if (! is_string($pageToken) || $pageToken === '') {
                break;
            }
        }

        return $models;
    }

    /** Text = supports generateContent and is not a specialised image/audio/embedding variant. */
    public static function capabilityOf(string $id, array $methods): string
    {
        $id = strtolower($id);

        return match (true) {
            in_array('embedContent', $methods, true) || str_contains($id, 'embedding') => 'embedding',
            (bool) preg_match('/imagen|image|veo/', $id) => 'image',
            (bool) preg_match('/tts|audio|live|speech/', $id) => 'audio',
            (bool) preg_match('/aqa|robotics|computer-use|learnlm/', $id) => 'other',
            in_array('generateContent', $methods, true) => 'text',
            default => 'other',
        };
    }

    protected function rankModel(string $id): int
    {
        $score = (int) (self::versionOf($id) * 100);
        $score += preg_match('/preview|exp|latest|-\d{3}$|-\d{2}-\d{2}/', $id) ? 0 : 500;
        $score += str_contains($id, 'flash') && ! str_contains($id, 'lite') ? 300 : 0;
        $score += str_contains($id, 'pro') ? 150 : 0;
        $score -= str_contains($id, 'gemma') ? 600 : 0;

        return $score;
    }

    private function aspectRatioFor(mixed $size): ?string
    {
        if (! is_string($size) || ! preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return null;
        }

        $width = (int) $matches[1];
        $height = (int) $matches[2];
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $ratios = ['1:1' => 1, '2:3' => 2 / 3, '3:2' => 3 / 2, '3:4' => 3 / 4, '4:3' => 4 / 3, '4:5' => 4 / 5, '5:4' => 5 / 4, '9:16' => 9 / 16, '16:9' => 16 / 9, '21:9' => 21 / 9];
        $target = $width / $height;
        uasort($ratios, static fn ($a, $b) => abs($a - $target) <=> abs($b - $target));

        return array_key_first($ratios);
    }

    private function imageSizeFor(mixed $size): ?string
    {
        if (! is_string($size) || ! preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
            return null;
        }

        $largest = max((int) $matches[1], (int) $matches[2]);

        return match (true) {
            $largest <= 512 => '512',
            $largest <= 1024 => '1K',
            $largest <= 2048 => '2K',
            default => '4K',
        };
    }

    /** @return array{type:?string,code:?string,message:?string} */
    private function errorDetails(Response $response): array
    {
        $code = $response->json('error.code');

        return [
            'type' => is_string($response->json('error.status')) ? $response->json('error.status') : null,
            'code' => is_scalar($code) ? (string) $code : null,
            'message' => is_string($response->json('error.message')) ? $response->json('error.message') : null,
        ];
    }

    private function logFailure(string $model, ?int $status, ?Throwable $exception = null, ?string $responseBody = null, ?string $requestedModel = null, string $capability = 'text'): void
    {
        $decoded = $responseBody === null ? null : json_decode($responseBody, true);
        $bodyError = is_array($decoded) && is_array($decoded['error'] ?? null) ? $decoded['error'] : [];
        $code = $bodyError['code'] ?? null;
        $error = [
            'type' => is_string($bodyError['status'] ?? null) ? $bodyError['status'] : null,
            'code' => is_scalar($code) ? (string) $code : null,
            'message' => is_string($bodyError['message'] ?? null) ? $bodyError['message'] : null,
        ];
        $sanitizedMessage = $exception === null ? $error['message'] : $exception->getMessage();
        $sanitizedMessage = $sanitizedMessage === null ? null : str_replace((string) $this->apiKey, '[REDACTED]', $sanitizedMessage);

        Log::warning('AI provider request failed.', array_filter([
            'provider' => $this->providerName(),
            'requested_model' => $requestedModel ?? $model,
            'resolved_model' => $model,
            'capability' => $capability,
            'status' => $status,
            'error.type' => $error['type'],
            'error.code' => $error['code'],
            'error.message' => $sanitizedMessage,
            'exception_class' => $exception ? $exception::class : null,
        ], static fn ($value) => $value !== null));
    }
}