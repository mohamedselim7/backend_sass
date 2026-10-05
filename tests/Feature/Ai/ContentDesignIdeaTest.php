<?php

namespace Tests\Feature\Ai;

use App\Models\ContentPost;
use App\Services\CreditService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentDesignIdeaTest extends TestCase
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

    public function test_generated_design_idea_is_saved_with_the_post(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat(json_encode(['plans' => [[
            'name' => 'Plan A',
            'posts' => [['headline' => 'H', 'content' => 'C', 'designIdea' => 'صورة مقربة لفاتورة مشطوبة']],
        ]]]));

        $this->postJson('/api/v1/ai/content/generate', ['brief' => 'brief', 'platforms' => ['instagram']])
            ->assertCreated();

        $this->assertSame('صورة مقربة لفاتورة مشطوبة', ContentPost::query()->latest()->value('design_idea'));
    }

    public function test_mandatory_design_prompt_reaches_the_ai_request(): void
    {
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);

        $this->fakeChat(json_encode(['plans' => [[
            'name' => 'Plan A',
            'posts' => [['headline' => 'H', 'content' => 'C', 'design_idea' => 'فكرة']],
        ]]]));

        $this->postJson('/api/v1/ai/content/generate', [
            'brief' => 'brief',
            'platforms' => ['instagram'],
            'visual_identity' => 'استخدم صورة تشويقية',
        ])->assertCreated();

        Http::assertSent(fn (Request $request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'استخدم صورة تشويقية'));
    }
}
