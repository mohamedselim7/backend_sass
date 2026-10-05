<?php

namespace App\Services\Payments;

use App\Exceptions\DomainException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;

/** Paymob (Accept) integration: order -> payment key -> hosted iframe. */
class PaymobGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paymob';
    }

    public function createCheckout(Payment $payment, array $context = []): array
    {
        $config = config('payments.gateways.paymob');

        foreach (['api_key', 'integration_id', 'iframe_id'] as $required) {
            if (empty($config[$required])) {
                throw new DomainException('لم يتم إكمال إعداد بوابة الدفع.', 503, 'gateway_not_configured');
            }
        }

        $token = Http::timeout(30)
            ->post('https://accept.paymob.com/api/auth/tokens', ['api_key' => $config['api_key']])
            ->throw()->json('token');

        $order = Http::timeout(30)->post('https://accept.paymob.com/api/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => false,
            // Paymob works in the minor currency unit.
            'amount_cents' => (int) round((float) $payment->amount * 100),
            'currency' => $payment->currency,
            'merchant_order_id' => $payment->getKey(),
        ])->throw()->json();

        $paymentKey = Http::timeout(30)->post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => (int) round((float) $payment->amount * 100),
            'expiration' => 3600,
            'order_id' => $order['id'],
            'currency' => $payment->currency,
            'integration_id' => $config['integration_id'],
            'billing_data' => [
                'first_name' => $context['first_name'] ?? 'iden',
                'last_name' => $context['last_name'] ?? 'User',
                'email' => $context['email'] ?? 'user@example.com',
                'phone_number' => $context['phone'] ?? '+20000000000',
                'apartment' => 'NA', 'floor' => 'NA', 'street' => 'NA', 'building' => 'NA',
                'shipping_method' => 'NA', 'postal_code' => 'NA', 'city' => 'NA',
                'country' => 'EG', 'state' => 'NA',
            ],
        ])->throw()->json('token');

        return [
            'checkout_url' => "https://accept.paymob.com/api/acceptance/iframes/{$config['iframe_id']}?payment_token={$paymentKey}",
            'reference' => (string) $order['id'],
        ];
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        $secret = config('payments.gateways.paymob.hmac');
        $received = $payload['hmac'] ?? ($headers['hmac'][0] ?? null);
        $obj = $payload['obj'] ?? [];

        if (! $secret || ! $received || ! $obj) {
            return false;
        }

        // Paymob signs a fixed, alphabetically ordered concatenation of fields.
        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
            'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
            'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
        ];

        $concatenated = collect($fields)
            ->map(fn ($path) => $this->stringify(data_get($obj, $path)))
            ->implode('');

        return hash_equals(hash_hmac('sha512', $concatenated, $secret), (string) $received);
    }

    public function parseWebhook(array $payload): array
    {
        $obj = $payload['obj'] ?? [];
        $success = (bool) ($obj['success'] ?? false);

        return [
            'reference' => isset($obj['order']['id']) ? (string) $obj['order']['id'] : null,
            'transaction_id' => isset($obj['id']) ? (string) $obj['id'] : null,
            'status' => $success ? 'paid' : 'failed',
            'amount' => isset($obj['amount_cents']) ? ((int) $obj['amount_cents']) / 100 : null,
        ];
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }
}
