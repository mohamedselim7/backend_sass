<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreditReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\OnboardingSetupRequest;
use App\Http\Resources\UserResource;
use App\Models\Brand;
use App\Models\Goal;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** First-login bootstrap: welcome credits + the user's first brand/goals, in one idempotent call. */
class OnboardingController extends Controller
{
    private const WELCOME_CREDITS = 50;

    public function __construct(private readonly CreditService $credits) {}

    public function setup(OnboardingSetupRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            if (! empty($data['full_name']) && ! $user->name) {
                $user->name = $data['full_name'];
            }

            // Welcome credits are granted once per account: skip if signup already granted them.
            $alreadyGranted = $user->welcome_granted_at
                || \App\Models\CreditLog::where('user_id', $user->getKey())
                    ->where('reason', CreditReason::Signup)->exists();

            if ($alreadyGranted && ! $user->welcome_granted_at) {
                $user->welcome_granted_at = now();
            } elseif (! $alreadyGranted) {
                $this->credits->grant($user, self::WELCOME_CREDITS, CreditReason::Signup, null, 'onboarding:'.$user->getKey());
                $user->welcome_granted_at = now();
            }

            if (! $user->onboarded_at) {
                if (! empty($data['brand']) && ! Brand::where('created_by', $user->getKey())->exists()) {
                    Brand::create($data['brand'] + ['created_by' => $user->getKey()]);
                }

                foreach ($data['goals'] ?? [] as $goal) {
                    Goal::create($goal + ['user_id' => $user->getKey(), 'status' => 'active']);
                }

                $user->onboarded_at = now();
            }

            $user->save();
        });

        return response()->json([
            'data' => [
                'user' => new UserResource($user->fresh(['wallet'])),
                'wallet' => collect($this->credits->wallet($user)->toArray())->only(['balance', 'lifetime_granted', 'lifetime_spent']),
            ],
        ]);
    }
}
