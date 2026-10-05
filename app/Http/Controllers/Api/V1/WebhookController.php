<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoint: the gateway signature is the only authentication, so it is
 * verified before anything is read from the body.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function payment(Request $request): JsonResponse
    {
        $handled = $this->payments->handleWebhook(
            $request->all(),
            $request->headers->all(),
        );

        return $handled
            ? response()->json(['received' => true])
            : response()->json(['message' => 'Invalid signature'], 401);
    }

    /** EasyKash callback: HMAC-SHA512 `signatureHash` is verified before any field is used. */
    public function easykash(Request $request): JsonResponse
    {
        $handled = $this->payments->handleWebhook($request->all(), $request->headers->all(), 'easykash');

        return $handled
            ? response()->json(['received' => true])
            : response()->json(['message' => 'Invalid signature'], 401);
    }
}
