<?php

namespace App\Actions\Ai;

use App\Models\ContentGeneration;
use App\Models\User;
use App\Services\Ai\ContentGenerator;
use App\Services\Ai\Contracts\ContentGeneratorInterface;
use App\Services\ContentService;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Throwable;

class GenerateContentAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly ContentService $content,
        private readonly UsageLogger $logger,
        private readonly ContentGeneratorInterface $generator,
    ) {}

    public function execute(User $user, array $data, ?string $idempotencyKey = null): ContentGeneration
    {
        $planCount = max(1, (int) ($data['plan_count'] ?? 1));
        $cost = $this->credits->cost('content_plan') * $planCount;
        $key = $idempotencyKey ?? 'ai-content-generate:'.$user->getKey().':'.sha1(json_encode($data));

        $charge = $this->credits->charge($user, 'content_plan', $cost, idempotencyKey: $key);

        try {
            $plans = $this->generator->generatePlan(array_merge($data, ['_user' => $user]));

            // Keep the chosen writing settings on every stored plan.
            $plans = array_map(fn ($plan) => is_array($plan) ? $plan + array_filter([
                'language_id' => $data['language_id'] ?? null,
                'dialect_id' => $data['dialect_id'] ?? null,
                'tone_id' => $data['tone_id'] ?? null,
            ]) : $plan, $plans);

            $generation = $this->content->storeGeneration($user, [
                'brand_id' => $data['brand_id'] ?? null,
                'brand_name' => $data['brand_name'] ?? null,
                'business_brief' => $data['brief'] ?? null,
                'options' => ['platforms' => $data['platforms'] ?? [], 'posts_per_plan' => $data['posts_per_plan'] ?? null],
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'prompt_version' => ContentGenerator::VERSION,
            ], $plans);

            $this->logger->usage($user, 'content_generate', [
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'ref_id' => $generation->getKey(),
                'status' => 'success',
            ]);

            return $generation;
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'content generation failed');
            $this->logger->usage($user, 'content_generate', [
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
