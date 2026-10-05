<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CreditReason;
use App\Http\Controllers\Controller;
use App\Models\CreditLog;
use App\Models\Plan;
use App\Models\User;
use App\Services\Admin\UserPlanChanger;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly UserPlanChanger $planChanger,
    ) {
    }

    public function changePlan(Request $request, User $user): RedirectResponse
    {
        // Only admins may move a user between plans (verified server-side).
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate(['plan_id' => ['required', 'uuid', 'exists:plans,id']]);
        $this->planChanger->change($request->user(), $user, Plan::findOrFail($data['plan_id']));

        return back()->with('success', __('The plan has been updated.'));
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'role' => $request->string('role')->toString(),
        ];

        $users = User::query()
            ->with(['wallet', 'activeSubscription.plan', 'roles:id,name'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['role'] !== '', fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $filters['role'])))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->row($user));

        return Inertia::render('users/Index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function show(User $user): Response
    {
        $user->load(['wallet', 'activeSubscription.plan', 'roles:id,name']);

        return Inertia::render('users/Show', [
            'user' => $this->row($user) + [
                'phone' => $user->phone,
                'locale' => $user->locale,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
            'canAdjustCredits' => (bool) request()->user()?->can('admin.users'),
            'canChangePlan' => (bool) request()->user()?->hasRole('admin'),
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'code']),
            'currentPlanId' => $user->activeSubscription?->plan_id,
            'wallet' => [
                'balance' => (int) ($user->wallet?->balance ?? 0),
                'lifetime_granted' => (int) ($user->wallet?->lifetime_granted ?? 0),
                'lifetime_spent' => (int) ($user->wallet?->lifetime_spent ?? 0),
            ],
            'currentSubscription' => ($sub = $user->activeSubscription) ? [
                'plan' => $sub->plan?->name,
                'plan_code' => $sub->plan?->code,
                'monthly_credits' => (int) ($sub->plan?->monthly_credits ?? 0),
                'status' => $sub->status,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'ends_at' => $sub->ends_at?->toIso8601String(),
                'days_left' => $sub->ends_at ? max(0, (int) now()->diffInDays($sub->ends_at, false)) : null,
            ] : null,
            'stats' => [
                'total_paid' => (float) $user->payments()->where('status', 'paid')->sum('amount'),
                'payments_count' => $user->payments()->count(),
                'support_threads' => \App\Models\SupportThread::where('user_id', $user->getKey())->count(),
                'credits_used_30d' => abs((int) CreditLog::where('user_id', $user->getKey())->where('amount', '<', 0)->where('created_at', '>=', now()->subDays(30))->sum('amount')),
            ],
            'roles' => Role::orderBy('name')->pluck('name'),
            'subscriptions' => $user->subscriptions()->with('plan:id,name,price,currency')->latest()->limit(10)->get()
                ->map(fn ($s) => [
                    'id' => $s->getKey(),
                    'plan' => $s->plan?->only(['id', 'name', 'price', 'currency']),
                    'status' => $s->status,
                    'starts_at' => $s->starts_at?->toIso8601String(),
                    'ends_at' => $s->ends_at?->toIso8601String(),
                ]),
            'payments' => $user->payments()->latest()->limit(10)->get()
                ->map(fn ($p) => [
                    'id' => $p->getKey(),
                    'amount' => (float) $p->amount,
                    'currency' => $p->currency,
                    'status' => $p->status,
                    'gateway' => $p->gateway,
                    'created_at' => $p->created_at?->toIso8601String(),
                ]),
            'creditLogs' => CreditLog::where('user_id', $user->getKey())->latest()->limit(30)->get()
                ->map(fn (CreditLog $log) => [
                    'id' => $log->getKey(),
                    'amount' => (int) $log->amount,
                    'reason' => $log->reason,
                    'balance_after' => (int) $log->balance_after,
                    'feature' => $log->feature,
                    'note' => $log->details['note'] ?? null,
                    'by_name' => $log->details['by_name'] ?? null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function setStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);

        $user->update($data);
        $this->logger->activity($request->user(), 'admin.user.status_changed', [
            'entity' => 'user',
            'details' => ['target' => $user->getKey(), 'status' => $data['status']],
        ]);

        return back()->with('success', __('The account status has been updated.'));
    }

    public function syncRoles(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($data['roles']);
        $this->logger->activity($request->user(), 'admin.user.roles_synced', [
            'entity' => 'user',
            'details' => ['target' => $user->getKey(), 'roles' => $data['roles']],
        ]);

        return back()->with('success', __('Roles have been updated.'));
    }

    public function adjustCredits(Request $request, User $user): RedirectResponse
    {
        // Support agents can open this screen but only admins (or holders of
        // admin.users) may move balances. Gate::before already passes admins.
        abort_unless($request->user()->can('admin.users'), 403);

        $data = $request->validate([
            'direction' => ['required', 'in:add,deduct'],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $details = ['note' => $data['note'], 'by' => $request->user()->getKey(), 'by_name' => $request->user()->name];

        try {
            $log = $data['direction'] === 'add'
                ? $this->credits->grant($user, $data['amount'], CreditReason::AdminAdjustment, null, null, $details)
                : $this->credits->charge($user, 'admin_adjustment', $data['amount'], null, null, $details, CreditReason::AdminAdjustment);
        } catch (\App\Exceptions\InsufficientCreditsException) {
            return back()->withErrors(['amount' => __('The user does not have enough credits for this deduction.')]);
        }

        $this->logger->activity($request->user(), 'admin.credits.adjusted', [
            'entity' => 'credit_wallet',
            'details' => [
                'target' => $user->getKey(),
                'amount' => $data['direction'] === 'add' ? $data['amount'] : -$data['amount'],
                'balance_after' => (int) $log->balance_after,
                'note' => $data['note'],
            ],
        ]);


        return back()->with('success', __('The credit balance has been adjusted.'));
    }

    /** @return array<string, mixed> */
    private function row(User $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'avatar_url' => $user->avatar_url,
            'roles' => $user->roles->pluck('name'),
            'credits' => (int) ($user->wallet?->balance ?? 0),
            'plan' => $user->activeSubscription?->plan?->name,
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
