<?php

namespace App\Services\Queue;

use App\Models\Plan;
use App\Models\User;

/**
 * Resolves the queue lane an AI job should be dispatched on, based on the
 * user's active subscription plan. Nothing here hardcodes a plan name, price,
 * credit amount, or sort order — the mapping is entirely driven by two
 * DB-configurable columns on `plans` (`ai_queue`, `queue_priority`), set by
 * admins through AdminPlanController. Plans with neither column set fall
 * back to the app-wide default lane.
 */
class PlanQueueResolver
{
    /**
     * @return string one of the named lanes in config('queue.names')
     */
    public function resolveForUser(?User $user): string
    {
        $plan = $this->activePlanFor($user);

        return $this->resolveForPlan($plan);
    }

    public function resolveForPlan(?Plan $plan): string
    {
        if ($plan === null) {
            return $this->defaultLane();
        }

        // 1) Explicit queue name on the plan wins outright.
        if (! empty($plan->ai_queue)) {
            return $plan->ai_queue;
        }

        // 2) Otherwise map a numeric priority to a lane. Lower number = higher
        //    priority. Anything outside 1-3 falls through to the default lane.
        if ($plan->queue_priority !== null) {
            return match ((int) $plan->queue_priority) {
                1 => config('queue.names.ai_high', 'ai-high'),
                2 => config('queue.names.ai_default', 'ai-default'),
                3 => config('queue.names.ai_low', 'ai-low'),
                default => $this->defaultLane(),
            };
        }

        return $this->defaultLane();
    }

    public function defaultLane(): string
    {
        return config('queue.names.ai_default', config('queue.ai_queue', 'ai-default'));
    }

    private function activePlanFor(?User $user): ?Plan
    {
        if ($user === null) {
            return null;
        }

        $subscription = $user->relationLoaded('subscriptions')
            ? $user->subscriptions->first(fn ($s) => $s->isActive())
            : $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->latest('starts_at')
                ->with('plan')
                ->get()
                ->first(fn ($s) => $s->isActive());

        return $subscription?->plan;
    }
}
