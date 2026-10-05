<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Models\Plan;
use App\Models\User;
use App\Services\Ai\Contracts\ImageProvider;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\Providers\GeminiProvider;
use App\Services\Ai\Providers\NvidiaProvider;
use App\Services\Ai\Providers\OpenAiProvider;
use App\Services\Ai\Providers\OpenRouterProvider;

/**
 * Resolves the provider + model + API key for a call. The provider is taken
 * ONLY from the user's active subscription plan (`plans.ai_model`); anything
 * the client sends (provider/model options, user settings) is ignored.
 * Falls back to config('ai.default_provider') when the user has no active plan.
 * API keys come exclusively from the backend environment (config/ai.php).
 */
class ProviderResolver
{
    public const PROVIDERS = ['openai', 'openrouter', 'gemini', 'nvidia'];

    public function text(?User $user, array $options = []): TextProvider
    {
        [$provider, $model] = $this->resolveProviderAndModel($user, $options);

        $config = $this->providerConfig($provider);
        $apiKey = $this->resolveApiKey($provider, $config);
        $timeout = (int) config('ai.timeout', 60);

        return match ($provider) {
            'openai' => new OpenAiProvider(
                baseUrl: $config['base_url'],
                apiKey: $apiKey,
                defaultModel: $model,
                timeout: $timeout,
                imageModel: $config['image_model'] ?? null,
            ),
            'openrouter' => new OpenRouterProvider($config['base_url'], $apiKey, $model, $timeout),
            'gemini' => new GeminiProvider($config['base_url'], $apiKey, $model, $timeout),
            'nvidia' => new NvidiaProvider($config['base_url'], $apiKey, $model, $timeout),
            default => throw AiProviderException::unknownProvider($provider),
        };
    }

    public function image(?User $user, array $options = []): ImageProvider
    {
        [$provider, $model] = $this->resolveProviderAndModel($user, $options, forImage: true);

        $config = $this->providerConfig($provider);
        $apiKey = $this->resolveApiKey($provider, $config);
        $timeout = (int) config('ai.timeout', 60);

        return match ($provider) {
            'openai' => new OpenAiProvider(
                baseUrl: $config['base_url'],
                apiKey: $apiKey,
                defaultModel: $config['default_model'],
                timeout: $timeout,
                imageModel: $model,
            ),
            'gemini' => new GeminiProvider(
                baseUrl: $config['base_url'],
                apiKey: $apiKey,
                defaultModel: $config['default_model'],
                timeout: $timeout,
                imageModel: $model,
            ),
            default => throw AiProviderException::unknownProvider($provider),
        };
    }

    /**
     * Admin-only: live text models per discoverable provider (OpenAI, Gemini).
     *
     * @return array<string, array<int, array{id:string,name:string,capabilities:array<int,string>}>>
     */
    public function discoveredModels(bool $forceRefresh = false): array
    {
        $result = [];

        foreach (['openai', 'gemini'] as $provider) {
            $config = $this->providerConfig($provider);
            $apiKey = $this->resolveApiKey($provider, $config);
            $instance = $provider === 'openai'
                ? new OpenAiProvider($config['base_url'], $apiKey, (string) $config['default_model'], (int) config('ai.timeout', 60))
                : new GeminiProvider($config['base_url'], $apiKey, (string) $config['default_model'], (int) config('ai.timeout', 60));

            $result[$provider] = $instance->getAvailableModels($forceRefresh);
        }

        return $result;
    }

    /**
     * Users must never see which internal model powers their plan.
     *
     * @return array<int, mixed>
     */
    public function availableModels(?User $user): array
    {
        return [];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveProviderAndModel(?User $user, array $options, bool $forImage = false): array
    {
        $provider = $this->planProvider($user);

        $config = $this->providerConfig($provider);
        $model = $forImage ? ($config['image_model'] ?? null) : ($config['default_model'] ?? null);

        if (! $model) {
            throw AiProviderException::unknownProvider($provider);
        }

        return [$provider, (string) $model];
    }

    /** The internal model attached to the user's active plan. */
    public function planProvider(?User $user): string
    {
        $provider = $user?->activeSubscription()->with('plan')->first()?->plan?->ai_model;

        if (! is_string($provider) || ! in_array($provider, Plan::aiModels(), true)) {
            $provider = (string) config('ai.default_provider', 'openai');
        }

        return $provider;
    }

    private function providerConfig(string $provider): array
    {
        $config = config("ai.providers.{$provider}");

        if (! is_array($config)) {
            throw AiProviderException::unknownProvider($provider);
        }

        return $config;
    }

    private function resolveApiKey(string $provider, array $config): ?string
    {
        // Keys live in the backend environment only.
        return $config['api_key'] ?? null;
    }
}
