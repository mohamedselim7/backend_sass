<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditLogResource;
use App\Http\Resources\PlanResource;
use App\Models\CreditLog;
use App\Models\CreditPackage;
use App\Models\Plan;
use App\Services\CreditService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BillingController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly CreditService $credits,
    ) {}

    public function plans(): AnonymousResourceCollection
    {
        return PlanResource::collection(
            Plan::where('is_active', true)->orderBy('sort_order')->get()
        );
    }

    /** The client sends only a plan code; the price always comes from the database. */
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_code' => ['required', 'string', 'exists:plans,code'],
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $this->payments->availableGateways() ?: ['none'])],
        ]);

        $plan = Plan::where('code', $request->string('plan_code'))->where('is_active', true)->firstOrFail();

        $result = $this->payments->startCheckout($request->user(), $plan, $request->input('gateway'));

        return response()->json([
            'payment_id' => $result['payment']->getKey(),
            'checkout_url' => $result['checkout_url'],
        ], 201);
    }

    /** Active one-time credit packages, exactly as configured in the Admin. */
    public function creditPackages(): JsonResponse
    {
        $packages = CreditPackage::active()->orderBy('sort_order')->orderBy('price')->get()
            ->map(fn (CreditPackage $package) => [
                'id' => $package->getKey(),
                'name' => $package->name,
                'description' => $package->description,
                'price' => (float) $package->price,
                'currency' => $package->currency,
                'credits' => (int) $package->credits,
            ]);

        return response()->json(['data' => $packages]);
    }

    /** The client sends only credit_package_id; price and credits are read from the database. */
    public function checkoutCreditPackage(Request $request): JsonResponse
    {
        $request->validate([
            'credit_package_id' => ['required', 'uuid'],
            'gateway' => ['nullable', 'string', 'in:'.implode(',', $this->payments->availableGateways() ?: ['none'])],
        ]);

        $package = CreditPackage::active()->whereKey($request->input('credit_package_id'))->firstOrFail();

        $result = $this->payments->startCreditPackageCheckout($request->user(), $package, $request->input('gateway'));

        return response()->json([
            'payment_id' => $result['payment']->getKey(),
            'checkout_url' => $result['checkout_url'],
        ], 201);
    }

    public function gateways(): JsonResponse
    {
        return response()->json(['data' => $this->payments->availableGateways()]);
    }

    /** Status for the checkout return page. Own payments only; re-verified with the provider. */
    public function payment(Request $request, \App\Models\Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->getKey(), 404);
        $payment = $this->payments->sync($payment);
        $plan = $payment->subscription?->plan;
        $package = $payment->creditPackage;

        return response()->json([
            'id' => $payment->getKey(),
            'status' => $payment->status,
            'gateway' => $payment->gateway,
            'amount' => (float) $payment->amount,
            'currency' => $payment->currency,
            'type' => $package ? 'credit_package' : 'subscription',
            'plan' => $plan ? ['code' => $plan->code, 'name' => $plan->name] : null,
            'credit_package' => $package ? ['id' => $package->getKey(), 'name' => $package->name, 'credits' => (int) $package->credits] : null,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $wallet = $this->credits->wallet($request->user());

        return response()->json([
            'balance' => (int) $wallet->balance,
            'lifetime_granted' => (int) $wallet->lifetime_granted,
            'lifetime_spent' => (int) $wallet->lifetime_spent,
            'costs' => config('payments.credit_costs'),
        ]);
    }

    public function creditHistory(Request $request): AnonymousResourceCollection
    {
        $logs = CreditLog::where('user_id', $request->user()->getKey())
            ->latest()
            ->paginate(min($request->integer('per_page', 50), 200));

        return CreditLogResource::collection($logs);
    }
}
