<?php

namespace App\Services\Ai\Providers;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\DTO\TextResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared logic for OpenAI-compatible `/chat/completions` endpoints
 * (OpenAI itself and OpenRouter both implement this contract).
 */
abstract class AbstractChatProvider implements TextProvider
{
    public function __construct(
        protected readonly string $baseUrl,
        protected readonly ?string $apiKey,
        protected readonly string $defaultModel,
        protected readonly int $timeout = 60,
    ) {}

    abstract public function providerName(): string;

    /** Extra headers a concrete provider needs on top of the bearer token. */
    protected function extraHeaders(): array
    {
        return [];
    }

    /** Hook for providers that need extra request parameters. Default: unchanged. */
    protected function preparePayload(array $payload, array $options): array
    {
        return $payload;
    }

    /**
     * Whether a failed response means "this model is unavailable" (eligible for
     * rediscovery + one retry). Default false: providers without discovery
     * (e.g. NVIDIA) keep their exact previous behaviour.
     */
    protected function isModelUnavailableResponse(int $status, string $body): bool
    {
        return false;
    }

    /** Model used for the current request (discovery-aware providers override per attempt). */
    protected function currentModel(): string
    {
        return $this->defaultModel;
    }

    /** Minimum output token budget (0 = don't send max_tokens unless the caller does). */
    protected function minOutputTokens(): int
    {
        return 0;
    }

    public function complete(string $systemPrompt, array $messages, array $options = []): TextResult
    {
        if (! $this->apiKey) {
            throw AiProviderException::noKeyConfigured($this->providerName());
        }

        // The model is fixed by the backend (from the user's plan); callers cannot override it.
        $model = $this->currentModel();

        $payload = [
            'model' => $model,
            'messages' => array_values(array_filter([
                $systemPrompt !== '' ? ['role' => 'system', 'content' => $systemPrompt] : null,
                ...$messages,
            ])),
        ];

        $payload = $this->preparePayload($payload, $options);

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

     if (isset($options['max_tokens'])) {
        $payload['max_tokens'] = max((int) $options['max_tokens'], $this->minOutputTokens());
        } elseif ($this->minOutputTokens() > 0) {
            $payload['max_tokens'] = $this->minOutputTokens();
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withHeaders($this->extraHeaders())
                ->timeout($this->timeout)
                ->retry(2, 200)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);
        } catch (ConnectionException|Throwable $e) {
            $this->logFailure($model, $e instanceof RequestException ? $e->response->status() : null, $e);

            // Discovery-aware providers get a typed "model unavailable" signal; others (NVIDIA) unchanged.
            if ($e instanceof RequestException && $this->isModelUnavailableResponse($e->response->status(), $e->response->body())) {
                throw AiProviderException::forProvider($this->providerName(), context: [
                    'status' => $e->response->status(),
                    'model' => $model,
                    'reason' => 'model_unavailable',
                ]);
            }

            throw AiProviderException::forProvider($this->providerName());
        }

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

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            // Reasoning models may spend the whole budget on reasoning and return content=null.
            // Never surface the reasoning text as the answer.
            throw AiProviderException::forProvider($this->providerName(), context: [
                'reason' => 'empty_response',
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'model' => $model,
            ]);
        }

        return new TextResult(
            text: $content,
            promptTokens: $response->json('usage.prompt_tokens'),
            completionTokens: $response->json('usage.completion_tokens'),
            model: (string) ($response->json('model') ?? $model),
            provider: $this->providerName(),
        );
    }

    private function logFailure(string $model, ?int $status, ?Throwable $exception = null, ?string $responseBody = null): void
    {
        $sanitizedBody = $responseBody === null
            ? null
            : mb_substr(str_replace((string) $this->apiKey, '[REDACTED]', $responseBody), 0, 2000);
        $sanitizedMessage = $exception === null
            ? null
            : str_replace((string) $this->apiKey, '[REDACTED]', $exception->getMessage());

        Log::warning('AI provider request failed.', array_filter([
            'provider' => $this->providerName(),
            'model' => $model,
            'status' => $status,
            'response' => $sanitizedBody,
            'exception_class' => $exception ? $exception::class : null,
            'exception_message' => $sanitizedMessage,
        ], static fn ($value) => $value !== null));
    }
}
