<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Admin\AdminMetricsService;
use App\Services\UsageLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly AdminMetricsService $metrics,
        private readonly UsageLogger $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'plan' => $request->string('plan')->toString(),
        ];

        $subscriptions = Subscription::query()
            ->with(['user:id,name,email', 'plan:id,name,price,currency,interval'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['plan'] !== '', fn ($q) => $q->where('plan_id', $filters['plan']))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Subscription $s) => [
                'id' => $s->getKey(),
                'user' => $s->user?->only(['id', 'name', 'email']),
                'plan' => $s->plan?->only(['id', 'name', 'price', 'currency', 'interval']),
                'status' => $s->status,
                'is_active' => $s->isActive(),
                'starts_at' => $s->starts_at?->toIso8601String(),
                'ends_at' => $s->ends_at?->toIso8601String(),
                'trial_ends_at' => $s->trial_ends_at?->toIso8601String(),
                'cancelled_at' => $s->cancelled_at?->toIso8601String(),
            ]);

        return Inertia::render('subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'filters' => $filters,
            'plans' => Plan::orderBy('sort_order')->get(['id', 'name', 'price', 'currency', 'interval', 'is_active']),
            'summary' => [
                'active_subscribers' => $this->metrics->activeSubscriberCount(),
                'expiring_soon' => $this->metrics->expiringSoonCount(),
                'status_breakdown' => $this->metrics->subscriptionStatusBreakdown(),
            ],
        ]);
    }

    public function cancel(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'immediately' => ['sometimes', 'boolean'],
        ]);

        $immediately = (bool) ($data['immediately'] ?? false);

        $subscription->forceFill([
            'cancelled_at' => now(),
            'status' => $immediately ? 'cancelled' : $subscription->status,
            'ends_at' => $immediately ? now() : $subscription->ends_at,
        ])->save();

        $this->logger->activity($request->user(), 'admin.subscription.cancelled', [
            'entity' => 'subscription',
            'details' => ['target' => $subscription->getKey(), 'immediately' => $immediately],
        ]);

        return back()->with('success', $immediately
            ? __('The subscription has been cancelled immediately.')
            : __('The subscription will end at the close of the current period.'));
    }
}
