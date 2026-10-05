<?php

namespace Tests\Unit\Ai;

use App\Exceptions\AiProviderException;
use App\Services\Ai\Contracts\TextProvider;
use App\Services\Ai\DTO\TextResult;
use App\Services\Ai\JsonPrompt;
use Tests\TestCase;

class JsonPromptTest extends TestCase
{
    public function test_ask_decodes_strict_json_from_the_provider(): void
    {
        $provider = $this->fakeProvider('{"plan":"A"}');

        $result = (new JsonPrompt($provider))->ask('system', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['plan' => 'A'], $result['result']);
    }

    public function test_ask_strips_markdown_code_fences_before_decoding(): void
    {
        $provider = $this->fakeProvider("```json\n{\"plan\":\"B\"}\n```");

        $result = (new JsonPrompt($provider))->ask('system', [['role' => 'user', 'content' => 'hi']]);

        $this->assertSame(['plan' => 'B'], $result['result']);
    }

    public function test_ask_throws_ai_provider_exception_on_invalid_json(): void
    {
        $provider = $this->fakeProvider('not json at all');

        $this->expectException(AiProviderException::class);

        (new JsonPrompt($provider))->ask('system', [['role' => 'user', 'content' => 'hi']]);
    }

    private function fakeProvider(string $text): TextProvider
    {
        return new class($text) implements TextProvider
        {
            public function __construct(private readonly string $text) {}

            public function complete(string $systemPrompt, array $messages, array $options = []): TextResult
            {
                return new TextResult($this->text, 5, 5, 'gpt-4o-mini', 'openai');
            }
        };
    }
}
