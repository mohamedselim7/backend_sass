<?php

namespace Tests\Unit\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Providers\OpenRouterProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenRouterProviderTest extends TestCase
{
    public function test_complete_parses_a_successful_chat_response(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'model' => 'openai/gpt-4o-mini',
                'choices' => [['message' => ['content' => 'hello there']]],
                'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2],
            ], 200),
        ]);

        $provider = new OpenRouterProvider('https://openrouter.ai/api/v1', 'or-secret', 'openai/gpt-4o-mini');

        $result = $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('hello there', $result->text);
        $this->assertSame('openrouter', $result->provider);
    }

    public function test_complete_surfaces_provider_errors_as_ai_provider_exception(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'rate limited'], 429),
        ]);

        $provider = new OpenRouterProvider('https://openrouter.ai/api/v1', 'or-secret', 'openai/gpt-4o-mini');

        $this->expectException(AiProviderException::class);

        $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_error_never_leaks_the_api_key(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response(['error' => 'unauthorized or-secret'], 401),
        ]);

        $provider = new OpenRouterProvider('https://openrouter.ai/api/v1', 'or-secret', 'openai/gpt-4o-mini');

        try {
            $provider->complete('system', [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringNotContainsString('or-secret', $e->getMessage());
        }
    }
}
