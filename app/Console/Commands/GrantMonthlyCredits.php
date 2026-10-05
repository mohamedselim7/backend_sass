<?php

namespace App\Console\Commands;

use App\Enums\CreditReason;
use App\Models\Subscription;
use App\Services\CreditService;
use Illuminate\Console\Command;

class GrantMonthlyCredits extends Command
{
    protected $signature = 'iden:grant-monthly-credits';

    protected $description = 'Top up credits for active subscriptions once per billing month';

    public function handle(CreditService $credits): int
    {
        $granted = 0;

        Subscription::with(['user', 'plan'])
            ->whereIn('status', ['active', 'trialing'])
            ->each(function (Subscription $subscription) use ($credits, &$granted) {
                if (! $subscription->user || ! $subscription->plan || $subscription->plan->monthly_credits <= 0) {
                    return;
                }

                // The idempotency key makes a re-run within the same month a no-op.
                $credits->grant(
                    $subscription->user,
                    (int) $subscription->plan->monthly_credits,
                    CreditReason::SubscriptionGrant,
                    $subscription->getKey(),
                    'monthly:'.$subscription->getKey().':'.now()->format('Y-m'),
                    ['plan' => $subscription->plan->code],
                );

                $granted++;
            });

        $this->info("Topped up {$granted} subscription(s).");

        return self::SUCCESS;
    }
}
