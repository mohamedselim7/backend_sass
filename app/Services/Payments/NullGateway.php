<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Default gateway for environments with no provider configured: it records the
 * payment and hands back an internal confirmation URL instead of failing.
 */
class NullGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'none';
    }

    public function createCheckout(Payment $payment, array $context = []): array
    {
        return [
            'checkout_url' => url("/billing/manual/{$payment->getKey()}"),
            'reference' => 'manual-'.$payment->getKey(),
        ];
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        return false;
    }

    public function parseWebhook(array $payload): array
    {
        return ['reference' => null, 'transaction_id' => null, 'status' => 'failed', 'amount' => null];
    }
}
