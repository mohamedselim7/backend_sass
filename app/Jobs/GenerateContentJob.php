<?php

namespace App\Jobs;

use App\Models\AiGenerationJob;
use App\Services\Ai\AiJobService;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use App\Services\ContentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Processes one logical AI operation (ai_generation_jobs row).
 * Credits were charged once when the operation was created.
 * Intermediate failures only retry; refund happens in failed() exactly once.
 * The queue payload is just the job id.
 */
class GenerateContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 300;

    public function __construct(public int $aiJobId) {}

    public function handle(ContentGeneratorInterface $generator, ContentService $contentService, AiJobService $jobs): void
    {
        $job = $jobs->claim($this->aiJobId);
        if (! $job) {
            return; // already finished: duplicate delivery, do nothing
        }

        $user = $job->user;
        $payload = $job->payload ?? [];

        $plans = $generator->generatePlan(array_merge($payload, ['_user' => $user]));

        $generation = DB::transaction(function () use ($contentService, $user, $payload, $plans, $jobs, $job) {
            $fresh = AiGenerationJob::whereKey($job->id)->lockForUpdate()->first();
            if ($fresh->status === AiGenerationJob::COMPLETED) {
                return null; // another worker already stored the result
            }

            $generation = $contentService->storeGeneration($user, [
                'brand_id' => $payload['brand_id'] ?? null,
                'brand_name' => $payload['brand_name'] ?? null,
                'business_brief' => $payload['brief'] ?? $payload['business_brief'] ?? null,
                'options' => ['platforms' => $payload['platforms'] ?? [], 'posts_per_plan' => $payload['posts_per_plan'] ?? null],
                'provider' => $payload['provider'] ?? null,
                'model' => $payload['model'] ?? null,
            ], $plans);

            $jobs->complete($job->id, (string) $generation->getKey());

            return $generation;
        });

        // Side effects run after commit, at most once per operation.
        if ($generation && $jobs->claimNotification($job->id)) {
            \App\Notifications\GenerationReadyNotification::send($user, $generation);
            \App\Events\GenerationCompleted::dispatch($generation);
        }
    }

    /** Called once by Laravel after the final attempt. Refund is idempotent anyway. */
    public function failed(Throwable $exception): void
    {
        Log::error('GenerateContentJob permanently failed', [
            'ai_job_id' => $this->aiJobId,
            'error' => $exception->getMessage(),
        ]);

        app(AiJobService::class)->failPermanently($this->aiJobId, 'generation_failed', $exception->getMessage());
    }
}
