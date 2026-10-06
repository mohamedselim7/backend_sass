<?php

namespace Tests\Feature\Queue;

use App\Jobs\GenerateContentJob;
use App\Models\AiGenerationJob;
use App\Services\CreditService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcileAiJobsTest extends TestCase
{
    public function test_undispatched_job_is_redispatched(): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);
        $id = $this->postJson('/api/v1/ai/content/generate', ['brief' => 'b', 'platforms' => ['instagram'], 'async' => true])->json('data.job_id');
        AiGenerationJob::whereKey($id)->update(['dispatched_at' => null, 'created_at' => now()->subMinutes(5)]);

        $this->artisan('iden:reconcile-ai-jobs')->assertSuccessful();

        Queue::assertPushed(GenerateContentJob::class, 2);
        $this->assertNotNull(AiGenerationJob::find($id)->dispatched_at);
    }

    public function test_stuck_job_fails_and_refunds_once(): void
    {
        Queue::fake();
        $user = $this->actingAsUser();
        app(CreditService::class)->grant($user, 100);
        $id = $this->postJson('/api/v1/ai/content/generate', ['brief' => 'b', 'platforms' => ['instagram'], 'async' => true])->json('data.job_id');
        AiGenerationJob::whereKey($id)->update(['status' => 'processing', 'updated_at' => now()->subHour()]);

        $this->artisan('iden:reconcile-ai-jobs')->assertSuccessful();
        $this->artisan('iden:reconcile-ai-jobs')->assertSuccessful();

        $this->assertSame('failed', AiGenerationJob::find($id)->status);
        $this->assertSame(100, (int) $user->fresh()->wallet->balance);
    }
}
