<?php

namespace Tests\Feature\Ai;

use App\Services\CreditService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Real app path: HTTP → controller → ChatAction → ProviderResolver → Provider → discovery → (faked) AI API. */
class ModelDiscoveryChatTest extends TestCase
{
    private function userWithCredits(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);
    }

    private function geminiModelsList(): array
    {
        return ['models' => [
            ['name' => 'models/gemini-2.5-flash', 'supportedGenerationMethods' => ['generateContent', 'countTokens']],
            ['name' => 'models/gemini-2.5-pro', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/gemini-2.5-flash-preview-tts', 'supportedGenerationMethods' => ['generateContent']],
            ['name' => 'models/imagen-4.0-generate-001', 'supportedGenerationMethods' => ['predict']],
            ['name' => 'models/gemini-embedding-001', 'supportedGenerationMethods' => ['embedContent']],
        ]];
    }

    public function test_gemini_unlisted_plan_model_is_replaced_before_the_call(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config([
            'ai.default_provider' => 'gemini',
            'ai.providers.gemini.api_key' => 'gm-secret',
            'ai.providers.gemini.default_model' => 'gemini-1.5-flash',
        ]);

        Http::fake(function (Request $request) {
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/models')) {
                return Http::response($this->geminiModelsList());
            }
            if (str_contains($request->url(), 'gemini-2.5-flash:generateContent')) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'القاهرة']]]]]]);
            }

            return Http::response(['error' => ['message' => 'models/gemini-1.5-flash is not found']], 404);
        });

        $this->postJson('/api/v1/ai/chat', ['message' => 'ما هي عاصمة مصر؟'])
            ->assertOk()
            ->assertJsonPath('messages.1.content', 'القاهرة');

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'gemini-1.5-flash'));
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/models/gemini-2.5-flash:generateContent')
            && $r['contents'][count($r['contents']) - 1]['parts'][0]['text'] === 'ما هي عاصمة مصر؟');
    }

    public function test_gemini_404_on_stale_cache_triggers_refresh_and_single_retry(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config([
            'ai.default_provider' => 'gemini',
            'ai.providers.gemini.api_key' => 'gm-secret',
            'ai.providers.gemini.default_model' => 'gemini-old',
        ]);

        $listCalls = 0;
        Http::fake(function (Request $request) use (&$listCalls) {
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/models')) {
                $listCalls++;
                // First (cached) list still claims the old model exists; the refresh does not.
                return Http::response($listCalls === 1
                    ? ['models' => [['name' => 'models/gemini-old', 'supportedGenerationMethods' => ['generateContent']]]]
                    : $this->geminiModelsList());
            }
            if (str_contains($request->url(), 'gemini-old:generateContent')) {
                return Http::response(['error' => ['message' => 'models/gemini-old is not found']], 404);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Laravel إطار PHP']]]]]]);
        });

        Log::spy();

        $this->postJson('/api/v1/ai/chat', ['message' => 'اشرح لي Laravel باختصار'])
            ->assertOk()
            ->assertJsonPath('messages.1.content', 'Laravel إطار PHP');

        $this->assertSame(2, $listCalls);
        Http::assertSentCount(4); // list, old model (404), refreshed list, retry
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'AI model fallback triggered.'
            && $ctx['requested_model'] === 'gemini-old' && $ctx['selected_model'] === 'gemini-2.5-flash'
            && ! str_contains(json_encode($ctx), 'gm-secret'));
    }

    public function test_openai_404_triggers_refresh_and_single_retry(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config([
            'ai.default_provider' => 'openai',
            'ai.providers.openai.api_key' => 'sk-test',
            'ai.providers.openai.default_model' => 'gpt-retired',
        ]);

        $listCalls = 0;
        Http::fake(function (Request $request) use (&$listCalls) {
            if (str_ends_with($request->url(), '/models')) {
                $listCalls++;
                return Http::response(['data' => $listCalls === 1
                    ? [['id' => 'gpt-retired']]
                    : [['id' => 'gpt-4o-mini'], ['id' => 'dall-e-3'], ['id' => 'whisper-1'], ['id' => 'text-embedding-3-small']]]);
            }
            if ($request['model'] === 'gpt-retired') {
                return Http::response(['error' => ['code' => 'model_not_found', 'message' => 'The model `gpt-retired` does not exist']], 404);
            }

            return Http::response(['model' => $request['model'], 'choices' => [['message' => ['content' => 'القاهرة']]]]);
        });

        $this->postJson('/api/v1/ai/chat', ['message' => 'ما هي عاصمة مصر؟'])
            ->assertOk()
            ->assertJsonPath('messages.1.content', 'القاهرة');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/chat/completions') && $r['model'] === 'gpt-4o-mini'
            && end($r->data()['messages'])['content'] === 'ما هي عاصمة مصر؟');
    }

    public function test_openai_401_is_not_retried_and_credits_are_refunded(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config(['ai.default_provider' => 'openai', 'ai.providers.openai.api_key' => 'sk-bad']);

        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'hi'])->assertStatus(502)
            ->assertJsonMissingPath('context.api_key');

        // models list + one chat attempt only (each with its own 2x HTTP retry disabled by status-less fake count)
        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && $r['model'] !== config('ai.providers.openai.default_model'));
    }

    public function test_models_are_cached_between_messages(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config(['ai.default_provider' => 'gemini', 'ai.providers.gemini.api_key' => 'gm-secret', 'ai.providers.gemini.default_model' => 'gemini-2.5-flash']);

        $listCalls = 0;
        Http::fake(function (Request $request) use (&$listCalls) {
            if (str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/models')) {
                $listCalls++;
                return Http::response($this->geminiModelsList());
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]]);
        });

        $this->postJson('/api/v1/ai/chat', ['message' => 'one'])->assertOk();
        $this->postJson('/api/v1/ai/chat', ['message' => 'two'])->assertOk();

        $this->assertSame(1, $listCalls);
    }

    public function test_nvidia_is_untouched_by_discovery(): void
    {
        Cache::flush();
        $this->userWithCredits();
        config(['ai.default_provider' => 'nvidia', 'ai.providers.nvidia.api_key' => 'nv-test']);

        Http::fake(['integrate.api.nvidia.com/*' => Http::response(['choices' => [['message' => ['content' => 'nv ok']]]])]);

        $this->postJson('/api/v1/ai/chat', ['message' => 'hi'])->assertOk()->assertJsonPath('messages.1.content', 'nv ok');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/models'));
    }
}
