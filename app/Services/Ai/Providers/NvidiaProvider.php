<?php

namespace App\Services\Ai\Providers;

/**
 * NVIDIA NIM exposes an OpenAI-compatible `/chat/completions` endpoint.
 * Some hosted models (e.g. openai/gpt-oss-20b) are reasoning models: they need a
 * larger output budget, otherwise `content` comes back null with finish_reason=length.
 */
class NvidiaProvider extends AbstractChatProvider
{
    public function providerName(): string
    {
        return 'nvidia';
    }

    protected function minOutputTokens(): int
    {
        return (int) config('ai.providers.nvidia.max_tokens', 4096);
    }

    protected function preparePayload(array $payload, array $options): array
    {
        $effort = config('ai.providers.nvidia.reasoning_effort');
        if ($effort && str_contains($payload['model'], 'gpt-oss')) {
            $payload['reasoning_effort'] = $effort;
        }

        return $payload;
    }
}
