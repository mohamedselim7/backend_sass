<?php

namespace App\Services\Ai\Providers;

class OpenRouterProvider extends AbstractChatProvider
{
    public function providerName(): string
    {
        return 'openrouter';
    }

    protected function extraHeaders(): array
    {
        return [
            'HTTP-Referer' => config('app.url', 'https://iden.app'),
            'X-Title' => config('app.name', 'iden'),
        ];
    }
}
