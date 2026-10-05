<?php

namespace App\Services\Ai\Discovery;

use App\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared discovery / selection / single-retry logic for providers that list
 * their models over HTTP (OpenAI, Gemini). NVIDIA does NOT use this.
 *
 * Host class must provide: providerName(), $apiKey, $defaultModel.
 */
trait DiscoversModels
{
    /**
     * Raw provider call returning normalized models of ALL capabilities:
     * [['id' => ..., 'name' => ..., 'capabilities' => ['text'|'image'|'embedding'|'audio'|'other']]].
     */
    abstract protected function fetchModelsFromApi(): array;

    /** Higher score = better default choice for chat. */
    abstract protected function rankModel(string $id): int;

    public function getAvailableModels(bool $forceRefresh = false): array
    {
        if (! $this->apiKey || ! config('ai.model_discovery.enabled', true)) {
            return [];
        }

        $key = $this->modelsCacheKey();

        if ($forceRefresh) {
            Cache::forget($key);
        } elseif (is_array($cached = Cache::get($key))) {
            return $cached;
        }

        try {
            $models = array_values(array_filter(
                $this->fetchModelsFromApi(),
                static fn (array $m) => in_array('text', $m['capabilities'] ?? [], true),
            ));
        } catch (Throwable $e) {
            Log::warning('AI model discovery failed.', [
                'provider' => $this->providerName(),
                'exception_class' => $e::class,
                'exception_message' => str_replace((string) $this->apiKey, '[REDACTED]', $e->getMessage()),
            ]);

            return []; // failures are never cached
        }

        if ($models !== []) {
            Cache::put($key, $models, (int) config('ai.model_discovery.cache_ttl', 3600));
        }

        return $models;
    }

    public function isModelAvailable(string $model): bool
    {
        return in_array($model, array_column($this->getAvailableModels(), 'id'), true);
    }

    public function getRecommendedModel(): ?string
    {
        return $this->pickCompatibleModel($this->getAvailableModels());
    }

    /**
     * 1. configured/plan model if the API lists it
     * 2. first available entry of config recommended_models
     * 3. best-ranked discovered model
     * If discovery is unavailable (no list), the configured model is used as-is.
     */
    protected function resolveModel(): string
    {
        $requested = $this->defaultModel;
        $models = $this->getAvailableModels();

        if ($models === [] || in_array($requested, array_column($models, 'id'), true)) {
            $resolved = $requested;
        } else {
            $resolved = $this->pickCompatibleModel($models)
                ?? throw $this->noCompatibleModel($requested);
            $this->logFallback($requested, $resolved, 'requested model not listed by provider');
        }

        Log::info('AI request started.', [
            'provider' => $this->providerName(),
            'requested_model' => $requested,
            'resolved_model' => $resolved,
        ]);

        return $resolved;
    }

    /**
     * Runs $send($model); if the provider says the model is unavailable,
     * refreshes the model list, picks another compatible model and retries ONCE.
     */
    protected function sendWithModelFallback(callable $send): mixed
    {
        $model = $this->resolveModel();

        try {
            return $send($model);
        } catch (AiProviderException $e) {
            if (! $e->isModelUnavailable()) {
                throw $e; // 401/403/429/5xx/etc: no rediscovery
            }

            $replacement = $this->pickCompatibleModel($this->getAvailableModels(true), exclude: $model);

            if ($replacement === null) {
                Log::error('AI model fallback failed: no compatible model.', [
                    'provider' => $this->providerName(),
                    'requested_model' => $model,
                ]);
                throw $e;
            }

            $this->logFallback($model, $replacement, 'requested model unavailable');

            return $send($replacement); // single retry; a second failure propagates
        }
    }

    protected function pickCompatibleModel(array $models, ?string $exclude = null): ?string
    {
        $ids = array_values(array_filter(array_column($models, 'id'), static fn ($id) => $id !== $exclude));

        if ($ids === []) {
            return null;
        }

        if (in_array($this->defaultModel, $ids, true)) {
            return $this->defaultModel;
        }

        foreach ((array) config("ai.providers.{$this->providerName()}.recommended_models", []) as $preferred) {
            if (in_array($preferred, $ids, true)) {
                return $preferred;
            }
        }

        usort($ids, fn ($a, $b) => [$this->rankModel($b), $b] <=> [$this->rankModel($a), $a]);

        return $ids[0];
    }

    protected function modelsCacheKey(): string
    {
        // Scoped per key (hashed; the key itself is never stored) so a key change re-discovers.
        return 'ai.models.'.$this->providerName().'.'.substr(hash('sha256', (string) $this->apiKey), 0, 12);
    }

    private function logFallback(string $old, string $new, string $reason): void
    {
        Log::warning('AI model fallback triggered.', [
            'provider' => $this->providerName(),
            'requested_model' => $old,
            'selected_model' => $new,
            'reason' => $reason,
        ]);
    }

    private function noCompatibleModel(string $requested): AiProviderException
    {
        return AiProviderException::forProvider($this->providerName(), context: [
            'reason' => 'no_compatible_model',
            'model' => $requested,
        ]);
    }

    /** Pulls a numeric version out of an id (e.g. gemini-2.5-flash → 2.5, gpt-4.1-mini → 4.1). */
    protected static function versionOf(string $id): float
    {
        return preg_match('/(\d+(?:\.\d+)?)/', $id, $m) ? (float) $m[1] : 0.0;
    }
}
