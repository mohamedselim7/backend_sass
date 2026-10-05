<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\ContentPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_storing_a_generation_persists_plans_and_posts_and_charges_credits(): void
    {
        $user = $this->actingAsUser();
        app(\App\Services\CreditService::class)->grant($user, 100);

        $response = $this->postJson('/api/v1/content/generations', [
            'brand_name' => 'Acme',
            'plans' => [[
                'name' => 'Plan A',
                'posts' => [
                    ['headline' => 'One', 'content' => 'Body one', 'platform' => 'instagram'],
                    ['headline' => 'Two', 'content' => 'Body two', 'platform' => 'facebook'],
                ],
            ]],
        ]);

        $response->assertCreated()->assertJsonCount(1, 'data.plans');

        $this->assertSame(2, ContentPost::where('user_id', $user->getKey())->count());

        // 10 credits for one plan generation, taken from the 100 granted above.
        $this->assertSame(90, (int) $user->fresh()->wallet->balance);
    }

    public function test_generation_is_rejected_without_enough_credits(): void
    {
        $user = $this->actingAsUser();
        app(\App\Services\CreditService::class)->wallet($user)->update(['balance' => 0]);

        $this->postJson('/api/v1/content/generations', ['plans' => [['posts' => []]]])
            ->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits');
    }

    public function test_illegal_status_transition_is_refused(): void
    {
        $user = $this->actingAsUser();
        $post = ContentPost::factory()->create([
            'user_id' => $user->getKey(),
            'status' => PostStatus::Generated->value,
        ]);

        $this->postJson("/api/v1/content/posts/{$post->getKey()}/status", ['status' => 'published'])
            ->assertStatus(409)
            ->assertJsonPath('error', 'invalid_status_transition');
    }

    public function test_approval_is_recorded(): void
    {
        $user = $this->actingAsUser();
        $post = ContentPost::factory()->create([
            'user_id' => $user->getKey(),
            'status' => PostStatus::Generated->value,
        ]);

        $this->postJson("/api/v1/content/posts/{$post->getKey()}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertNotNull($post->fresh()->approved_at);
    }

    public function test_a_user_cannot_change_another_users_post(): void
    {
        $this->actingAsUser();
        $foreign = ContentPost::factory()->create();

        $this->postJson("/api/v1/content/posts/{$foreign->getKey()}/status", ['status' => 'approved'])
            ->assertStatus(403);
    }
}
