<?php

namespace Tests\Unit\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    public function test_complete_parses_a_successful_chat_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => 'مرحباً']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);

        $provider = new OpenAiProvider('https://api.openai.com/v1', 'sk-secret-key', 'gpt-4o-mini');

        $result = $provider->complete('be helpful', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame('مرحباً', $result->text);
        $this->assertSame(10, $result->promptTokens);
        $this->assertSame(5, $result->completionTokens);
        $this->assertSame('gpt-4o-mini', $result->model);
        $this->assertSame('openai', $result->provider);
    }

    public function test_complete_surfaces_provider_errors_as_ai_provider_exception(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'bad request']], 400),
        ]);

        $provider = new OpenAiProvider('https://api.openai.com/v1', 'sk-secret-key', 'gpt-4o-mini');

        $this->expectException(AiProviderException::class);

        $provider->complete('be helpful', [['role' => 'user', 'content' => 'hi']]);
    }

    public function test_missing_api_key_throws_before_any_http_call(): void
    {
        Http::fake();

        $provider = new OpenAiProvider('https://api.openai.com/v1', null, 'gpt-4o-mini');

        $this->expectException(AiProviderException::class);

        $provider->complete('be helpful', [['role' => 'user', 'content' => 'hi']]);

        Http::assertNothingSent();
    }

    public function test_generate_parses_a_successful_image_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['url' => 'https://example.com/image.png']],
            ], 200),
        ]);

        $provider = new OpenAiProvider('https://api.openai.com/v1', 'sk-secret-key', 'gpt-4o-mini', 60, 'dall-e-3');

        $result = $provider->generate('a cat riding a bike');

        $this->assertTrue($result->isUrl);
        $this->assertSame('https://example.com/image.png', $result->base64OrUrl);
        $this->assertSame('openai', $result->provider);
    }

    public function test_error_message_never_leaks_the_api_key(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'invalid api key sk-secret-key']], 401),
        ]);

        $provider = new OpenAiProvider('https://api.openai.com/v1', 'sk-secret-key', 'gpt-4o-mini');

        try {
            $provider->complete('be helpful', [['role' => 'user', 'content' => 'hi']]);
            $this->fail('Expected AiProviderException.');
        } catch (AiProviderException $e) {
            $this->assertStringNotContainsString('sk-secret-key', $e->getMessage());
        }
    }
}
