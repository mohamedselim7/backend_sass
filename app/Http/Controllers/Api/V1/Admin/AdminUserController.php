<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CreditReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\Admin\UserPlanChanger;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly UserPlanChanger $planChanger,
    ) {}

    public function changePlan(Request $request, User $user): UserResource
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $data = $request->validate(['plan_id' => ['required', 'uuid', 'exists:plans,id']]);
        $this->planChanger->change($request->user(), $user, Plan::findOrFail($data['plan_id']));

        return new UserResource($user->load(['wallet', 'activeSubscription.plan']));
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $users = User::with(['wallet', 'activeSubscription.plan'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($inner) => $inner->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['wallet', 'activeSubscription.plan']));
    }

    public function setStatus(Request $request, User $user): UserResource
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);

        $user->update($data);
        $this->logger->activity($request->user(), 'admin.user.status_changed', [
            'entity' => 'user',
            'details' => ['target' => $user->getKey(), 'status' => $data['status']],
        ]);

        return new UserResource($user);
    }

    public function syncRoles(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $user->syncRoles($data['roles']);
        $this->logger->activity($request->user(), 'admin.user.roles_synced', [
            'entity' => 'user',
            'details' => ['target' => $user->getKey(), 'roles' => $data['roles']],
        ]);

        return new UserResource($user->load('roles'));
    }

    public function adjustCredits(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $log = $data['amount'] > 0
            ? $this->credits->grant($user, $data['amount'], CreditReason::AdminAdjustment, null, null, ['note' => $data['note'] ?? null])
            : $this->credits->charge($user, 'admin_adjustment', abs($data['amount']), null, null, ['note' => $data['note'] ?? null]);

        $this->logger->activity($request->user(), 'admin.credits.adjusted', [
            'entity' => 'credit_wallet',
            'details' => ['target' => $user->getKey(), 'amount' => $data['amount']],
        ]);

        return response()->json(['balance' => (int) $log->balance_after]);
    }
}
