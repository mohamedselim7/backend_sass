<?php

namespace App\Services;

use App\Enums\CreditReason;
use App\Models\CreditPackage;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\EasyKashGateway;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;

/**
 * Owns subscription state. Prices come from the plans table and the paid status
 * only ever changes from a verified gateway callback — never from the client.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
    ) {}

    /** Gateways a user may pick at checkout (names only — never secrets). */
    public function availableGateways(): array
    {
        $names = [];
        if (EasyKashGateway::configured()) {
            $names[] = 'easykash';
        }
        if (! in_array($this->gateway->name(), [...$names, 'none'], true)) {
            $names[] = $this->gateway->name();
        }

        return $names;
    }

    public function gateway(?string $name = null): PaymentGateway
    {
        if ($name === null || $name === $this->gateway->name()) {
            return $this->gateway;
        }

        return match ($name) {
            'easykash' => new EasyKashGateway,
            default => $this->gateway,
        };
    }

    /** @return array{payment:Payment, checkout_url:string} */
    public function startCheckout(User $user, Plan $plan, ?string $gatewayName = null): array
    {
        $gateway = $this->gateway($gatewayName);

        return DB::transaction(function () use ($user, $plan, $gateway) {
            $subscription = Subscription::create([
                'user_id' => $user->getKey(),
                'plan_id' => $plan->getKey(),
                'status' => 'pending',
            ]);

            $payment = Payment::create([
                'user_id' => $user->getKey(),
                'subscription_id' => $subscription->getKey(),
                'gateway' => $gateway->name(),
                'amount' => $plan->price,
                'currency' => $plan->currency,
                'status' => 'pending',
            ]);

            $checkout = $gateway->createCheckout($payment, [
                'email' => $user->email,
                'first_name' => $user->name,
                'phone' => $user->phone,
            ]);

            $payment->update(['gateway_reference' => $checkout['reference']]);

            return ['payment' => $payment, 'checkout_url' => $checkout['checkout_url']];
        });
    }

    /**
     * One-time credit purchase. Price and credits come only from the
     * credit_packages row; the client sends nothing but the package id.
     *
     * @return array{payment:Payment, checkout_url:string}
     */
    public function startCreditPackageCheckout(User $user, CreditPackage $package, ?string $gatewayName = null): array
    {
        $gateway = $this->gateway($gatewayName);

        return DB::transaction(function () use ($user, $package, $gateway) {
            $payment = Payment::create([
                'user_id' => $user->getKey(),
                'credit_package_id' => $package->getKey(),
                'gateway' => $gateway->name(),
                'amount' => $package->price,
                'currency' => $package->currency,
                'status' => 'pending',
            ]);

            $checkout = $gateway->createCheckout($payment, [
                'email' => $user->email,
                'first_name' => $user->name,
                'phone' => $user->phone,
            ]);

            $payment->update(['gateway_reference' => $checkout['reference']]);

            return ['payment' => $payment, 'checkout_url' => $checkout['checkout_url']];
        });
    }

    /** Idempotent: replayed webhooks for an already-paid payment are no-ops. */
    public function handleWebhook(array $payload, array $headers, ?string $gatewayName = null): bool
    {
        $gateway = $this->gateway($gatewayName);

        if (! $gateway->verifyWebhook($payload, $headers)) {
            return false;
        }

        $event = $gateway->parseWebhook($payload);

        if (! $event['reference']) {
            return false;
        }

        $payment = Payment::where('gateway_reference', $event['reference'])->first()
            ?? Payment::whereKey($event['reference'])->first();

        if (! $payment) {
            return true;
        }

        $this->applyEvent($payment, $event);

        return true;
    }

    /**
     * Server-to-server re-check used by the return page. The browser never
     * decides the status; only the provider's answer does.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $gateway = $this->gateway($payment->gateway);
        if ($gateway instanceof EasyKashGateway) {
            $event = $gateway->inquire($payment);
            if ($event !== null && $event['status'] !== 'pending') {
                $this->applyEvent($payment, $event);
            }
        }

        return $payment->refresh();
    }

    /** Single transition point, row-locked so concurrent webhook + inquiry never double-credit. */
    protected function applyEvent(Payment $payment, array $event): void
    {
        DB::transaction(function () use ($payment, $event) {
            $locked = Payment::whereKey($payment->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status === 'paid') {
                return;
            }

            // A "paid" callback for less than the server-side price is never honoured.
            if ($event['status'] === 'paid' && isset($event['amount']) && $event['amount'] !== null
                && round((float) $event['amount'], 2) < round((float) $locked->amount, 2)) {
                $event['status'] = 'failed';
                $event['reason'] = 'amount_mismatch';
            }

            $locked->update([
                'status' => $event['status'],
                'gateway_transaction_id' => $event['transaction_id'] ?? $locked->gateway_transaction_id,
                'paid_at' => $event['status'] === 'paid' ? now() : null,
                'payload' => $event,
            ]);

            if ($event['status'] !== 'paid') {
                return;
            }

            $locked->credit_package_id ? $this->fulfilCreditPackage($locked) : $this->activate($locked);
        });
    }

    protected function fulfilCreditPackage(Payment $payment): void
    {
        $package = $payment->creditPackage;
        if (! $package) {
            return;
        }

        // Idempotency key per payment: a replay can never add credits twice.
        $this->credits->grant(
            $payment->user,
            (int) $package->credits,
            CreditReason::CreditPurchase,
            $payment->getKey(),
            'credit-package:'.$payment->getKey(),
            ['package_id' => $package->getKey(), 'package' => $package->name],
        );

        $this->logger->activity($payment->user, 'billing.credit_package.purchased', [
            'entity' => 'payment',
            'feature' => 'billing',
            'status' => 'paid',
            'cost' => (float) $payment->amount,
            'details' => ['package_id' => $package->getKey(), 'credits' => (int) $package->credits, 'payment_id' => $payment->getKey()],
        ]);
    }

    protected function activate(Payment $payment): void
    {
        $subscription = $payment->subscription;

        if (! $subscription) {
            return;
        }

        $plan = $subscription->plan;
        $months = $plan->interval === 'year' ? 12 : 1;

        // Any other active subscription is superseded by this one.
        Subscription::where('user_id', $subscription->user_id)
            ->where('id', '!=', $subscription->getKey())
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths($months),
        ]);

        if ($plan->monthly_credits > 0) {
            $this->credits->grant(
                $payment->user,
                (int) $plan->monthly_credits,
                CreditReason::SubscriptionGrant,
                $subscription->getKey(),
                'plan-grant:'.$payment->getKey(),
                ['plan' => $plan->code],
            );
        }

        $this->logger->activity($payment->user, 'billing.subscription.activated', [
            'entity' => 'subscription',
            'feature' => 'billing',
            'status' => 'active',
            'cost' => (float) $payment->amount,
            'details' => ['plan' => $plan->code, 'payment_id' => $payment->getKey()],
        ]);
    }
}
