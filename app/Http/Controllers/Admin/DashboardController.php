<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\Admin\AdminMetricsService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminMetricsService $metrics)
    {
    }

    public function index(): Response
    {
        return Inertia::render('dashboard/Dashboard', [
            'metrics' => $this->metrics->overview(),
            'revenueSeries' => $this->metrics->revenueSeries(),
            'userGrowthSeries' => $this->metrics->userGrowthSeries(),
            'subscriptionStatus' => $this->metrics->subscriptionStatusBreakdown(),
            'recentPayments' => Payment::with('user:id,name,email')
                ->latest()->limit(6)->get()
                ->map(fn (Payment $p) => [
                    'id' => $p->getKey(),
                    'user' => $p->user?->only(['id', 'name', 'email']),
                    'amount' => (float) $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'gateway' => $p->gateway,
                    'created_at' => $p->created_at?->toIso8601String(),
                ]),
            'recentSubscriptions' => Subscription::with(['user:id,name,email', 'plan:id,name,price,currency'])
                ->latest()->limit(6)->get()
                ->map(fn (Subscription $s) => [
                    'id' => $s->getKey(),
                    'user' => $s->user?->only(['id', 'name', 'email']),
                    'plan' => $s->plan?->only(['id', 'name', 'price', 'currency']),
                    'status' => $s->status,
                    'ends_at' => $s->ends_at?->toIso8601String(),
                ]),
            'recentUsers' => User::latest()->limit(6)->get()
                ->map(fn (User $u) => [
                    'id' => $u->getKey(),
                    'name' => $u->name,
                    'email' => $u->email,
                    'status' => $u->status,
                    'created_at' => $u->created_at?->toIso8601String(),
                ]),
            'expiringSubscriptions' => Subscription::with(['user:id,name,email', 'plan:id,name'])
                ->whereIn('status', ['active', 'trialing'])
                ->whereNotNull('ends_at')
                ->whereBetween('ends_at', [Carbon::now(), Carbon::now()->addDays(14)])
                ->orderBy('ends_at')
                ->limit(6)->get()
                ->map(fn (Subscription $s) => [
                    'id' => $s->getKey(),
                    'user' => $s->user?->only(['id', 'name', 'email']),
                    'plan' => $s->plan?->only(['id', 'name']),
                    'ends_at' => $s->ends_at?->toIso8601String(),
                ]),
            'supportQueue' => SupportThread::with('user:id,name,email')
                ->open()->orderByDesc('last_message_at')->limit(6)->get()
                ->map(fn (SupportThread $t) => [
                    'id' => $t->getKey(),
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'priority' => $t->priority,
                    'unread' => $t->unread_for_admin,
                    'user' => $t->user?->only(['id', 'name', 'email']),
                    'last_message_at' => $t->last_message_at?->toIso8601String(),
                ]),
        ]);
    }
}
