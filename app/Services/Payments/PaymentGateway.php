<?php

namespace App\Services\Payments;

use App\Models\Payment;

/**
 * Contract every payment provider implements. Adding a gateway means adding a
 * class here, never touching the controllers.
 */
interface PaymentGateway
{
    public function name(): string;

    /**
     * Create a hosted checkout for a pending payment.
     *
     * @return array{checkout_url:string, reference:string}
     */
    public function createCheckout(Payment $payment, array $context = []): array;

    /** Confirm the webhook really came from the provider. */
    public function verifyWebhook(array $payload, array $headers): bool;

    /**
     * Normalise a webhook body.
     *
     * @return array{reference:?string, transaction_id:?string, status:string, amount:?float}
     */
    public function parseWebhook(array $payload): array;
}
