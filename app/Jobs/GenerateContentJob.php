<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use App\Services\ContentService;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs an AI content generation in the background for `async=true` requests.
 * Credits are charged before dispatch (see AiController); on failure here we
 * refund them so the user is never left charged for a plan that never landed.
 */
class GenerateContentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /**
     * @param  array{brand_id?:string,brand_name?:string,brief?:string,business_brief?:string,monthly_brief?:string,platforms?:array,options?:array,provider?:string,model?:string,plan_count?:int,posts_per_plan?:int}  $payload
     */
    public function __construct(
        public string $userId,
        public array $payload,
    ) {}

    public function handle(ContentGeneratorInterface $generator, ContentService $contentService, CreditService $credits): void
    {
        $user = User::findOrFail($this->userId);

        $planCount = max(1, (int) ($this->payload['plan_count'] ?? 1));
        $cost = $credits->cost('content_plan') * $planCount;
        $key = 'ai-content-generate-async:'.$this->userId.':'.sha1(json_encode($this->payload));

        $charge = $credits->charge($user, 'content_plan', $cost, idempotencyKey: $key);

        try {
            $plans = $generator->generatePlan(array_merge($this->payload, ['_user' => $user]));

            $generation = $contentService->storeGeneration($user, [
                'brand_id' => $this->payload['brand_id'] ?? null,
                'brand_name' => $this->payload['brand_name'] ?? null,
                'business_brief' => $this->payload['brief'] ?? $this->payload['business_brief'] ?? null,
                'options' => ['platforms' => $this->payload['platforms'] ?? [], 'posts_per_plan' => $this->payload['posts_per_plan'] ?? null],
                'provider' => $this->payload['provider'] ?? null,
                'model' => $this->payload['model'] ?? null,
            ], $plans);

            \App\Notifications\GenerationReadyNotification::send($user, $generation);
            \App\Events\GenerationCompleted::dispatch($generation);
        } catch (Throwable $e) {
            $credits->refund($user, $charge, 'async content generation failed');

            Log::error('GenerateContentJob failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('GenerateContentJob permanently failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
