<?php

namespace App\Services\Payments;

use App\Exceptions\DomainException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * EasyKash Direct Pay (https://easykash.gitbook.io/easykash-apis-documentation).
 *
 *  - Pay API:       POST {base}/api/directpayv1/pay      (header `authorization: <API key>`)
 *  - Callback:      POST to our /api/v1/webhooks/easykash, signed with HMAC-SHA512
 *                   over ProductCode.Amount.ProductType.PaymentMethod.status.easykashRef.customerReference
 *  - Inquiry API:   POST {base}/api/cash-api/inquire      (customerReference)
 *
 * The payment id is used as customerReference so every callback maps back to
 * exactly one local Payment. Secrets are read from config only (never the client).
 */
class EasyKashGateway implements PaymentGateway
{
    private const SIGNED_FIELDS = ['ProductCode', 'Amount', 'ProductType', 'PaymentMethod', 'status', 'easykashRef', 'customerReference'];

    public function name(): string
    {
        return 'easykash';
    }

    public static function configured(): bool
    {
        return filled(config('payments.gateways.easykash.api_key')) && filled(config('payments.gateways.easykash.hmac'));
    }

    public function createCheckout(Payment $payment, array $context = []): array
    {
        if (! self::configured()) {
            throw new DomainException('بوابة الدفع غير مفعّلة حاليًا.', 503, 'gateway_not_configured');
        }
        if (blank($context['phone'] ?? null)) {
            throw new DomainException('أضف رقم الموبايل في حسابك قبل الدفع.', 422, 'phone_required');
        }

        $body = array_filter([
            'amount' => round((float) $payment->amount, 2),
            'currency' => strtoupper((string) $payment->currency),
            'paymentOptions' => config('payments.gateways.easykash.payment_options') ?: null,
            'cashExpiry' => (int) config('payments.gateways.easykash.cash_expiry_hours', 3),
            'name' => (string) ($context['first_name'] ?? 'Customer'),
            'email' => (string) ($context['email'] ?? ''),
            'mobile' => (string) $context['phone'],
            'redirectUrl' => (string) config('payments.gateways.easykash.redirect_url'),
            'customerReference' => (string) $payment->getKey(),
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout(20)
            ->withHeaders(['authorization' => (string) config('payments.gateways.easykash.api_key')])
            ->acceptJson()
            ->post($this->base().'/api/directpayv1/pay', $body);

        $url = $response->json('redirectUrl');
        if (! $response->successful() || ! is_string($url) || $url === '') {
            Log::warning('easykash.pay_failed', ['status' => $response->status(), 'payment' => $payment->getKey(), 'body' => $response->json()]);
            throw new DomainException('تعذّر بدء عملية الدفع. حاول مرة أخرى.', 502, 'gateway_error');
        }

        return ['checkout_url' => $url, 'reference' => (string) $payment->getKey()];
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        $secret = (string) config('payments.gateways.easykash.hmac');
        $given = (string) ($payload['signatureHash'] ?? '');
        if ($secret === '' || $given === '') {
            return false;
        }
        $data = implode('', array_map(fn ($field) => (string) ($payload[$field] ?? ''), self::SIGNED_FIELDS));

        return hash_equals(hash_hmac('sha512', $data, $secret), strtolower($given));
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'reference' => isset($payload['customerReference']) ? (string) $payload['customerReference'] : null,
            'transaction_id' => isset($payload['easykashRef']) ? (string) $payload['easykashRef'] : null,
            'status' => $this->mapStatus((string) ($payload['status'] ?? '')),
            'amount' => isset($payload['Amount']) ? (float) $payload['Amount'] : null,
            'method' => $payload['PaymentMethod'] ?? null,
        ];
    }

    /**
     * Server-to-server status check, used when the user returns from EasyKash
     * before (or instead of) the callback. The browser never decides the status.
     *
     * @return array{reference:?string, transaction_id:?string, status:string, amount:?float}|null
     */
    public function inquire(Payment $payment): ?array
    {
        if (! self::configured()) {
            return null;
        }
        $response = Http::timeout(15)
            ->withHeaders(['authorization' => (string) config('payments.gateways.easykash.api_key')])
            ->acceptJson()
            ->post($this->base().'/api/cash-api/inquire', ['customerReference' => (string) $payment->getKey()]);

        if (! $response->successful() || ! is_array($response->json())) {
            return null;
        }
        $data = $response->json();

        return [
            'reference' => (string) $payment->getKey(),
            'transaction_id' => isset($data['easykashRef']) ? (string) $data['easykashRef'] : null,
            'status' => $this->mapStatus((string) ($data['status'] ?? '')),
            'amount' => isset($data['Amount']) ? (float) $data['Amount'] : null,
        ];
    }

    private function mapStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PAID', 'SUCCESS', 'DELIVERED' => 'paid',
            'FAILED', 'DECLINED' => 'failed',
            'EXPIRED' => 'expired',
            'CANCELED', 'CANCELLED', 'REFUNDED' => 'cancelled',
            default => 'pending',
        };
    }

    private function base(): string
    {
        return rtrim((string) config('payments.gateways.easykash.base_url', 'https://back.easykash.net'), '/');
    }
}
