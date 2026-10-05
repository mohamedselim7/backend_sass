<?php

namespace Tests\Feature\Ai;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\CreditService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiControllerTest extends TestCase
{
    private function fakeChat(string $json): void
    {
        config(['ai.providers.openai.api_key' => 'sk-test']);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'model' => 'gpt-4o-mini',
                'choices' => [['message' => ['content' => $json]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ], 200),
        ]);
    }

    public function test_content_generate_requires_auth(): void
    {
        $this->postJson('/api/v1/ai/content/generate', [])->assertStatus(401);
    }

    public function test_content_generate_validation(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/ai/content/generate', [])->assertStatus(422);
    }

    public function test_content_generate_happy_path(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat(json_encode(['plans' => [[
            'name' => 'Plan A',
            'strategy' => 'x',
            'posts' => [['headline' => 'H', 'content' => 'C', 'platform' => 'instagram']],
        ]]]));

        $response = $this->postJson('/api/v1/ai/content/generate', [
            'brief' => 'A test brief',
            'platforms' => ['instagram'],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.plans');
        $this->assertSame(90, (int) $user->fresh()->wallet->balance);
    }

    public function test_content_generate_insufficient_credits(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->wallet($user)->update(['balance' => 0]);

        $this->postJson('/api/v1/ai/content/generate', [
            'brief' => 'A test brief',
            'platforms' => ['instagram'],
        ])->assertStatus(402)->assertJsonPath('error', 'insufficient_credits');
    }

    public function test_content_generate_refunds_on_provider_failure(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        config(['ai.providers.openai.api_key' => 'sk-test']);

        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->postJson('/api/v1/ai/content/generate', [
            'brief' => 'A test brief',
            'platforms' => ['instagram'],
        ])->assertStatus(502);

        $this->assertSame(100, (int) $user->fresh()->wallet->balance);
    }

    public function test_content_generate_async_dispatches_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->postJson('/api/v1/ai/content/generate', [
            'brief' => 'A test brief',
            'platforms' => ['instagram'],
            'async' => true,
        ])->assertStatus(202);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\GenerateContentJob::class);
        // Not charged synchronously; the job charges when it runs.
        $this->assertSame(100, (int) $user->fresh()->wallet->balance);
    }

    public function test_angles_happy_path(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat(json_encode(['angles' => [
            ['title' => 'T1', 'body' => 'B1', 'category' => 'c', 'tags' => []],
        ]]));

        $this->postJson('/api/v1/ai/angles', ['topic' => 'coffee', 'count' => 1])
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_angles_validation(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/ai/angles', [])->assertStatus(422);
    }

    public function test_chat_happy_path(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat('مرحباً بك');

        $response = $this->postJson('/api/v1/ai/chat', ['message' => 'hi']);

        $response->assertOk();
        $this->assertNotNull($response->json('thread_id'));
        $this->assertCount(2, $response->json('messages'));
    }

    public function test_user_can_list_create_and_open_own_chat_threads(): void
    {
        $user = $this->actingAsUser();
        $thread = ChatThread::create([
            'user_id' => $user->getKey(),
            'title' => 'محادثة محفوظة',
            'mode' => 'smart',
            'last_message_at' => now(),
        ]);
        ChatMessage::create([
            'thread_id' => $thread->getKey(),
            'role' => 'user',
            'content' => 'رسالة سابقة',
        ]);

        $this->getJson('/api/v1/ai/chat/threads')
            ->assertOk()
            ->assertJsonPath('data.0.id', $thread->getKey())
            ->assertJsonPath('data.0.message_count', 1)
            ->assertJsonPath('data.0.mode', 'smart');

        $this->getJson('/api/v1/ai/chat/threads/'.$thread->getKey())
            ->assertOk()
            ->assertJsonPath('thread.id', $thread->getKey())
            ->assertJsonPath('messages.0.content', 'رسالة سابقة');

        $this->postJson('/api/v1/ai/chat/threads', ['mode' => 'free'])
            ->assertCreated()
            ->assertJsonPath('data.mode', 'free');
    }

    public function test_user_cannot_open_another_users_chat_thread(): void
    {
        $owner = $this->actingAsUser();
        $thread = ChatThread::create([
            'user_id' => $owner->getKey(),
            'title' => 'خاصة',
            'mode' => 'free',
        ]);

        $this->actingAsUser();
        $this->getJson('/api/v1/ai/chat/threads/'.$thread->getKey())->assertNotFound();
    }

    public function test_design_happy_path(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $plan = Plan::factory()->create(['ai_model' => 'gemini']);
        Subscription::create([
            'user_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'starts_at' => now(),
        ]);

        config([
            'ai.providers.gemini.api_key' => 'gm-test',
            'ai.providers.gemini.default_model' => 'gemini-2.5-flash',
            'ai.providers.gemini.image_model' => 'gemini-3.1-flash-image',
            'ai.model_discovery.enabled' => false,
        ]);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nfoAAAAASUVORK5CYII=';

        Http::fake(function ($request) use ($png) {
            if (str_contains($request->url(), '/models/gemini-3.1-flash-image:generateContent')) {
                return Http::response([
                    'candidates' => [['content' => ['parts' => [[
                        'inlineData' => ['mimeType' => 'image/png', 'data' => $png],
                    ]]]]],
                ], 200);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'فكرة تصميم رائعة']]]]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ], 200);
        });

        $response = $this->postJson('/api/v1/ai/design', ['prompt' => 'design me something']);

        $response->assertCreated()
            ->assertJsonPath('data.versions.0.provider', 'gemini')
            ->assertJsonPath('data.versions.0.model', 'gemini-3.1-flash-image');
        $this->assertNotNull($response->json('data.versions.0.image_url'));
        $version = \App\Models\DesignVersion::query()->findOrFail($response->json('data.versions.0.id'));
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists(
            $version->image_storage_path,
        );
        Http::assertSentCount(2);
    }

    public function test_image_happy_path_stores_media(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        config(['ai.providers.openai.api_key' => 'sk-test']);

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode('fake-bytes')]]], 200),
        ]);

        $response = $this->postJson('/api/v1/ai/image', ['prompt' => 'a cat']);

        $response->assertCreated();
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($response->json('data.path'));
    }

    public function test_gemini_plan_generates_and_stores_image_without_openai_fallback(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $plan = Plan::factory()->create(['ai_model' => 'gemini']);
        Subscription::create([
            'user_id' => $user->getKey(),
            'plan_id' => $plan->getKey(),
            'status' => 'active',
            'starts_at' => now(),
        ]);

        config([
            'ai.providers.gemini.api_key' => 'gm-test',
            'ai.providers.gemini.image_model' => 'gemini-3.1-flash-image',
            'ai.model_discovery.enabled' => false,
        ]);
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nfoAAAAASUVORK5CYII=';

        Http::fake(function ($request) use ($png) {
            if (str_contains($request->url(), 'api.openai.com')) {
                return Http::response(['error' => ['message' => 'OpenAI must not be called']], 500);
            }

            return Http::response([
                'candidates' => [['content' => ['parts' => [[
                    'inlineData' => ['mimeType' => 'image/png', 'data' => $png],
                ]]]]],
            ], 200);
        });

        $response = $this->postJson('/api/v1/ai/image', [
            'prompt' => 'A simple blue circle on a white background',
            'size' => '1024x1024',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.mime', 'image/png')
            ->assertJsonPath('data.size', strlen(base64_decode($png, true)));
        $this->assertDatabaseHas('media', [
            'id' => $response->json('data.id'),
            'user_id' => $user->getKey(),
            'mime' => 'image/png',
        ]);
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists($response->json('data.path'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/models/gemini-3.1-flash-image:generateContent')
            && ! str_contains($request->url(), 'api.openai.com'));
    }

    public function test_reels_happy_path(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat(json_encode([
            'planName' => 'Plan',
            'strategy' => 'Strat',
            'reels' => [['title' => 'T', 'hook' => 'H', 'fullScript' => 'S', 'caption' => 'C', 'sources' => []]],
        ]));

        $response = $this->postJson('/api/v1/ai/reels', ['count' => 1]);

        $response->assertOk();
        $this->assertSame('Plan', $response->json('result.planName'));
        $this->assertCount(1, $response->json('result.reels'));
    }

    public function test_models_endpoint_never_exposes_keys(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/v1/ai/models');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('api_key', $body);
        $this->assertStringNotContainsString(config('ai.providers.openai.api_key') ?: 'no-key-set', $body);
    }

    public function test_unauthenticated_is_rejected_across_endpoints(): void
    {
        $this->postJson('/api/v1/ai/angles', [])->assertStatus(401);
        $this->postJson('/api/v1/ai/chat', [])->assertStatus(401);
        $this->postJson('/api/v1/ai/design', [])->assertStatus(401);
        $this->postJson('/api/v1/ai/image', [])->assertStatus(401);
        $this->postJson('/api/v1/ai/reels', [])->assertStatus(401);
        $this->getJson('/api/v1/ai/models')->assertStatus(401);
    }
}
