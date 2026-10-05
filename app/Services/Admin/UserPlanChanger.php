<?php

namespace App\Services\Admin;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\UsageLogger;
use Illuminate\Support\Facades\DB;

/** Moves a user to another plan: ends the current subscription and starts a new one. */
class UserPlanChanger
{
    public function __construct(private readonly UsageLogger $logger) {}

    public function change(User $admin, User $user, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($admin, $user, $plan) {
            $current = $user->activeSubscription()->first();

            if ($current && $current->plan_id === $plan->getKey()) {
                return $current;
            }

            $endsAt = $current?->ends_at && $current->ends_at->isFuture()
                ? $current->ends_at
                : ($plan->interval === 'year' ? now()->addYear() : now()->addMonth());

            $current?->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()])->save();

            $subscription = Subscription::create([
                'user_id' => $user->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            $this->logger->activity($admin, 'admin.user.plan_changed', [
                'entity' => 'subscription',
                'details' => [
                    'target' => $user->getKey(),
                    'from_plan' => $current?->plan_id,
                    'to_plan' => $plan->getKey(),
                ],
            ]);

            return $subscription;
        });
    }
}
