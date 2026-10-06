<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Events\GenerationCompleted;
use App\Events\PostStatusChanged;
use App\Exceptions\InvalidStatusTransition;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persists AI-generated content plans and owns the post lifecycle. The state
 * machine lives here so no controller can move a post into an illegal status.
 */
class ContentService
{
    public function __construct(private readonly UsageLogger $logger) {}

    /**
     * Store a generation together with its plans and posts in one transaction.
     *
     * @param  array{brand_id?:string,brand_name?:string,business_brief?:string,monthly_brief?:string,options?:array,provider?:string,model?:string,write_mode?:string}  $meta
     * @param  array<int, array{name?:string,strategy?:string,goal?:string,pillars?:array,funnel?:array,formats?:array,posts?:array}>  $plans
     */
    public function storeGeneration(User $user, array $meta, array $plans): ContentGeneration
    {
        return DB::transaction(function () use ($user, $meta, $plans) {
            $generation = ContentGeneration::create([
                'user_id' => $user->getKey(),
                'brand_id' => $meta['brand_id'] ?? null,
                'brand_name' => $meta['brand_name'] ?? null,
                'business_brief' => $meta['business_brief'] ?? null,
                'monthly_brief' => $meta['monthly_brief'] ?? null,
                'options' => $meta['options'] ?? [],
                'provider' => $meta['provider'] ?? null,
                'model' => $meta['model'] ?? null,
                'write_mode' => $meta['write_mode'] ?? null,
                'prompt_version' => $meta['prompt_version'] ?? null,
                'status' => 'completed',
            ]);

            foreach (array_values($plans) as $index => $planData) {
                $plan = ContentPlan::create([
                    'generation_id' => $generation->getKey(),
                    'brand_id' => $generation->brand_id,
                    'plan_index' => $index,
                    'name' => $planData['name'] ?? 'Plan '.($index + 1),
                    'strategy' => $planData['strategy'] ?? null,
                    'goal' => $planData['goal'] ?? null,
                    'target_audience' => $planData['target_audience'] ?? null,
                    'pillars' => $planData['pillars'] ?? [],
                    'funnel' => $planData['funnel'] ?? [],
                    'formats' => $planData['formats'] ?? [],
                    'language_id' => $planData['language_id'] ?? null,
                    'dialect_id' => $planData['dialect_id'] ?? null,
                    'tone_id' => $planData['tone_id'] ?? null,
                ]);

                $rows = [];
                $now = now();
                foreach (array_values($planData['posts'] ?? []) as $postIndex => $post) {
                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'plan_id' => $plan->getKey(),
                        'brand_id' => $generation->brand_id,
                        'user_id' => $user->getKey(),
                        'post_number' => $post['post_number'] ?? $postIndex + 1,
                        'content_type' => $post['content_type'] ?? null,
                        'funnel_stage' => $post['funnel_stage'] ?? null,
                        'headline' => $post['headline'] ?? null,
                        'content' => $post['content'] ?? null,
                        'cta' => $post['cta'] ?? null,
                        'design_idea' => $post['design_idea'] ?? null,
                        'platform' => $post['platform'] ?? null,
                        'hashtags' => json_encode($post['hashtags'] ?? [], JSON_UNESCAPED_UNICODE),
                        'media' => json_encode($post['media'] ?? [], JSON_UNESCAPED_UNICODE),
                        'suggested_platforms' => json_encode($post['suggested_platforms'] ?? [], JSON_UNESCAPED_UNICODE),
                        'source' => $post['source'] ?? 'ai',
                        'status' => PostStatus::Generated->value,
                        'language_id' => $post['language_id'] ?? $plan->language_id,
                        'dialect_id' => $post['dialect_id'] ?? $plan->dialect_id,
                        'tone_id' => $post['tone_id'] ?? $plan->tone_id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                // Bulk insert: one query per plan instead of N — safe here because
                // posts have no model events/observers this flow depends on and
                // IDs/timestamps are generated up front.
                if ($rows !== []) {
                    ContentPost::query()->insert($rows);
                }
            }

            $this->logger->activity($user, 'content.generation.created', [
                'brand_id' => $generation->brand_id,
                'brand_name' => $generation->brand_name,
                'entity' => 'content_generation',
                'feature' => 'content_plan',
                'details' => ['generation_id' => $generation->getKey(), 'plans' => count($plans)],
            ]);

            $generation->load('plans.posts');

            GenerationCompleted::dispatch($generation);

            return $generation;
        });
    }

    /** Apply a status change, rejecting anything the state machine disallows. */
    public function transition(ContentPost $post, PostStatus $target, ?string $reason = null): ContentPost
    {
        $current = $post->status instanceof PostStatus ? $post->status : PostStatus::from((string) $post->status);

        if ($current === $target) {
            return $post;
        }

        if (! $current->canTransitionTo($target)) {
            throw new InvalidStatusTransition($current->value, $target->value);
        }

        $post->status = $target;
        $post->reject_reason = $target === PostStatus::Rejected ? $reason : null;
        $post->approved_at = $target === PostStatus::Approved ? now() : $post->approved_at;
        $post->save();

        PostStatusChanged::dispatch($post);

        return $post;
    }
}
