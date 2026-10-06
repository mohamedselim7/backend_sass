<?php

namespace App\Console\Commands;

use App\Models\AiGenerationJob;
use App\Services\Ai\AiJobService;
use Illuminate\Console\Command;

/**
 * Recovery path for "charged but never dispatched" and "stuck processing" jobs.
 */
class ReconcileAiJobs extends Command
{
    protected $signature = 'iden:reconcile-ai-jobs {--limit=100}';

    protected $description = 'Re-dispatch undispatched AI jobs and fail (with refund) stuck ones';

    public function handle(AiJobService $jobs): int
    {
        $limit = (int) $this->option('limit');

        // 1. Charged, committed, but dispatch failed.
        AiGenerationJob::where('status', AiGenerationJob::QUEUED)
            ->whereNull('dispatched_at')
            ->where('created_at', '<', now()->subMinute())
            ->limit($limit)->get()
            ->each(function (AiGenerationJob $job) use ($jobs) {
                if ($job->dispatch_attempts >= 5) {
                    $jobs->failPermanently($job->id, 'dispatch_failed', 'Could not queue the job');
                } else {
                    $jobs->dispatch($job);
                }
            });

        // 2. Workers that died mid-processing or jobs lost from the queue.
        AiGenerationJob::whereIn('status', [AiGenerationJob::QUEUED, AiGenerationJob::PROCESSING])
            ->whereNotNull('dispatched_at')
            ->where('updated_at', '<', now()->subMinutes(30))
            ->limit($limit)->get()
            ->each(fn (AiGenerationJob $job) => $jobs->failPermanently($job->id, 'timeout', 'Job did not finish in time'));

        return self::SUCCESS;
    }
}
