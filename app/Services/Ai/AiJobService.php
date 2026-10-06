<?php

namespace App\Services\Ai;

use App\Jobs\GenerateContentJob;
use App\Models\AiGenerationJob;
use App\Models\CreditLog;
use App\Models\User;
use App\Services\CreditService;
use App\Services\Queue\PlanQueueResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates logical AI operations: charge once, persist the job, commit,
 * then dispatch. Refunds happen exactly once, only on permanent failure.
 */
class AiJobService
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly PlanQueueResolver $queueResolver,
    ) {}

    /**
     * Same user + same Idempotency-Key returns the existing operation.
     * A missing key always creates a new operation (and a new charge).
     *
     * @throws \App\Exceptions\InsufficientCreditsException before anything is queued
     */
    public function startContentGeneration(User $user, array $payload, ?string $idempotencyKey): AiGenerationJob
    {
        $idempotencyKey = $idempotencyKey !== null ? Str::limit(trim($idempotencyKey), 191, '') : null;

        if ($idempotencyKey) {
            $existing = AiGenerationJob::where('user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $planCount = max(1, (int) ($payload['plan_count'] ?? 1));
        $cost = $this->credits->cost('content_plan') * $planCount;
        unset($payload['async']);

        $queueName = $this->queueResolver->resolveForUser($user);

        try {
            $job = DB::transaction(function () use ($user, $payload, $idempotencyKey, $cost, $queueName) {
                $job = AiGenerationJob::create([
                    'user_id' => $user->getKey(),
                    'brand_id' => $payload['brand_id'] ?? null,
                    'feature' => 'content_plan',
                    'status' => AiGenerationJob::QUEUED,
                    'provider' => $payload['provider'] ?? null,
                    'model' => $payload['model'] ?? null,
                    'queue_name' => $queueName,
                    'idempotency_key' => $idempotencyKey,
                    'request_hash' => sha1(json_encode($payload)),
                    'payload' => $payload,
                    'queued_at' => now(),
                ]);

                // Charge key is the operation id, never the payload hash.
                $charge = $this->credits->charge(
                    $user, 'content_plan', $cost,
                    refId: (string) $job->id,
                    idempotencyKey: 'ai-job:'.$job->id.':charge',
                );

                $job->update(['credit_log_id' => $charge->getKey(), 'credits_reserved' => $cost]);

                return $job;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Concurrent request with the same key won the race.
            return AiGenerationJob::where('user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)->firstOrFail();
        }

        $this->dispatch($job);

        return $job->fresh();
    }

    /** Dispatch after commit. On failure the job stays queued for reconciliation. */
    public function dispatch(AiGenerationJob $job): bool
    {
        $job->increment('dispatch_attempts');

        try {
            GenerateContentJob::dispatch($job->id)->onQueue($job->queue_name ?: 'default');
            $job->update(['dispatched_at' => now()]);

            return true;
        } catch (Throwable $e) {
            Log::warning('AI job dispatch failed; will be reconciled', ['ai_job_id' => $job->id, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /** Atomically claim a job for processing. Returns null if it is already finished. */
    public function claim(int $jobId): ?AiGenerationJob
    {
        return DB::transaction(function () use ($jobId) {
            $job = AiGenerationJob::whereKey($jobId)->lockForUpdate()->first();
            if (! $job || $job->isFinished()) {
                return null;
            }
            $job->status = AiGenerationJob::PROCESSING;
            $job->started_at ??= now();
            $job->attempts++;
            $job->save();

            return $job;
        });
    }

    /** Mark completed exactly once. Returns true only for the call that made the transition. */
    public function complete(int $jobId, ?string $generationId): bool
    {
        return DB::transaction(function () use ($jobId, $generationId) {
            $job = AiGenerationJob::whereKey($jobId)->lockForUpdate()->first();
            if (! $job || $job->status === AiGenerationJob::COMPLETED) {
                return false;
            }
            $job->update([
                'status' => AiGenerationJob::COMPLETED,
                'content_generation_id' => $generationId,
                'completed_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            return true;
        });
    }

    /** Claim the right to notify. Prevents duplicate notifications on retries. */
    public function claimNotification(int $jobId): bool
    {
        return AiGenerationJob::whereKey($jobId)->whereNull('notified_at')
            ->update(['notified_at' => now()]) === 1;
    }

    /** Permanent failure: mark failed and refund exactly once. */
    public function failPermanently(int $jobId, string $errorCode, string $message): void
    {
        DB::transaction(function () use ($jobId, $errorCode, $message) {
            $job = AiGenerationJob::whereKey($jobId)->lockForUpdate()->first();
            if (! $job || $job->status === AiGenerationJob::COMPLETED) {
                return;
            }

            $job->status = AiGenerationJob::FAILED;
            $job->failed_at ??= now();
            $job->error_code = $errorCode;
            $job->error_message = Str::limit($message, 1000);

            if ($job->refunded_at === null && $job->credit_log_id) {
                $charge = CreditLog::find($job->credit_log_id);
                if ($charge) {
                    // refund() is itself idempotent via 'refund:{charge_id}'.
                    $this->credits->refund($job->user, $charge, 'ai job '.$job->id.' failed');
                }
                $job->refunded_at = now();
            }

            $job->save();
        });
    }
}
