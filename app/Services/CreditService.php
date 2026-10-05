<?php

namespace App\Services;

use App\Enums\CreditReason;
use App\Exceptions\InsufficientCreditsException;
use App\Models\CreditLog;
use App\Events\CreditsChanged;
use App\Models\CreditWallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for credit balances. Every mutation happens inside a
 * transaction with a row lock so concurrent requests cannot overspend.
 */
class CreditService
{
    public function wallet(User $user): CreditWallet
    {
        return CreditWallet::firstOrCreate(['user_id' => $user->getKey()]);
    }

    public function balance(User $user): int
    {
        return (int) $this->wallet($user)->balance;
    }

    public function cost(string $feature): int
    {
        $prices = config('payments.credit_costs');

        return (int) ($prices[$feature] ?? $prices['default'] ?? 1);
    }

    /** Charge a feature. Throws when the balance is too low. */
    public function charge(
        User $user,
        string $feature,
        ?int $amount = null,
        ?string $refId = null,
        ?string $idempotencyKey = null,
        array $details = [],
        CreditReason $reason = CreditReason::Charge,
    ): CreditLog {
        $amount = $amount ?? $this->cost($feature);

        return DB::transaction(function () use ($user, $feature, $amount, $refId, $idempotencyKey, $details, $reason) {
            if ($idempotencyKey !== null) {
                $existing = CreditLog::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = CreditWallet::where('user_id', $user->getKey())->lockForUpdate()->first()
                ?? CreditWallet::create(['user_id' => $user->getKey()]);

            if ($wallet->balance < $amount) {
                throw new InsufficientCreditsException($amount, (int) $wallet->balance);
            }

            $wallet->balance -= $amount;
            $wallet->lifetime_spent += $amount;
            $wallet->save();

            CreditsChanged::dispatch($wallet);

            return CreditLog::create([
                'user_id' => $user->getKey(),
                'amount' => -$amount,
                'balance_after' => $wallet->balance,
                'reason' => $reason,
                'feature' => $feature,
                'ref_id' => $refId,
                'idempotency_key' => $idempotencyKey,
                'details' => $details,
            ]);
        });
    }

    /** Add credits (signup bonus, plan grant, admin adjustment, refund). */
    public function grant(
        User $user,
        int $amount,
        CreditReason $reason = CreditReason::AdminAdjustment,
        ?string $refId = null,
        ?string $idempotencyKey = null,
        array $details = [],
    ): CreditLog {
        return DB::transaction(function () use ($user, $amount, $reason, $refId, $idempotencyKey, $details) {
            if ($idempotencyKey !== null) {
                $existing = CreditLog::where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $wallet = CreditWallet::where('user_id', $user->getKey())->lockForUpdate()->first()
                ?? CreditWallet::create(['user_id' => $user->getKey()]);

            $wallet->balance += $amount;
            $wallet->lifetime_granted += $amount;
            $wallet->last_granted_at = now();
            $wallet->save();

            CreditsChanged::dispatch($wallet);

            return CreditLog::create([
                'user_id' => $user->getKey(),
                'amount' => $amount,
                'balance_after' => $wallet->balance,
                'reason' => $reason,
                'ref_id' => $refId,
                'idempotency_key' => $idempotencyKey,
                'details' => $details,
            ]);
        });
    }

    /** Give back credits for a failed operation, keyed to the original charge. */
    public function refund(User $user, CreditLog $charge, string $note = ''): CreditLog
    {
        return $this->grant(
            $user,
            abs((int) $charge->amount),
            CreditReason::Refund,
            $charge->getKey(),
            'refund:'.$charge->getKey(),
            ['note' => $note, 'feature' => $charge->feature],
        );
    }
}
