<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\CreditReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->string('name'),
                'email' => $request->string('email')->lower(),
                'password' => $request->string('password'),
                'phone' => $request->input('phone'),
                'locale' => $request->input('locale', 'ar'),
            ]);

            $user->assignRole('user');

            $signupCredits = (int) config('payments.signup_credits');
            if ($signupCredits > 0) {
                $this->credits->grant($user, $signupCredits, CreditReason::Signup, null, 'signup:'.$user->getKey());
                // Mark the welcome grant so onboarding never grants it a second time.
                $user->forceFill(['welcome_granted_at' => now()])->save();
            }

            // Auto-activate the free plan so new users never need manual activation from the dashboard.
            $freePlan = Plan::where('code', 'free')->where('is_active', true)->first();
            if ($freePlan) {
                Subscription::create([
                    'user_id' => $user->getKey(),
                    'plan_id' => $freePlan->getKey(),
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => null, // free plan never expires
                ]);
            }

            return $user;
        });

        $user->sendEmailVerificationNotification();
        $this->logger->activity($user, 'auth.registered', ['entity' => 'user']);

        return $this->respondWithToken(Auth::login($user), $user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // Throttle per email+IP (5 fails / 5 min) and per email across all IPs
        // (10 fails / 15 min) so neither single nor distributed brute force works.
        $email = $request->string('email')->lower()->trim()->value();
        $key = 'login:'.sha1($email.'|'.$request->ip());
        $emailKey = 'login-email:'.sha1($email);

        foreach ([[$key, 5], [$emailKey, 10]] as [$k, $max]) {
            if (RateLimiter::tooManyAttempts($k, $max)) {
                $seconds = RateLimiter::availableIn($k);

                return response()->json([
                    'message' => 'محاولات دخول كثيرة. حاول مرة أخرى بعد '.max(1, (int) ceil($seconds / 60)).' دقيقة.',
                    'error' => 'too_many_attempts',
                    'retry_after' => $seconds,
                ], 429)->header('Retry-After', (string) $seconds);
            }
        }

        $token = Auth::attempt([
            'email' => $email,
            'password' => $request->string('password')->value(),
        ]);

        if (! $token) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($emailKey, 900);

            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
                'error' => 'invalid_credentials',
            ], 401);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($emailKey);

        /** @var User $user */
        $user = Auth::user();

        if ($user->status !== 'active') {
            Auth::logout();

            return response()->json([
                'message' => 'تم إيقاف هذا الحساب.',
                'error' => 'account_suspended',
            ], 403);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->logger->activity($user, 'auth.logged_in', ['entity' => 'user']);

        return $this->respondWithToken($token, $user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource(
            $request->user()->load(['wallet', 'activeSubscription.plan'])
        );
    }

    public function refresh(): JsonResponse
    {
        try {
            // Works for an expired token still inside JWT_REFRESH_TTL; the old token is blacklisted.
            $token = Auth::refresh();
        } catch (\Throwable) {
            return response()->json(['message' => 'انتهت الجلسة، سجّل الدخول مرة أخرى.', 'error' => 'token_expired'], 401);
        }

        /** @var User|null $user */
        $user = Auth::setToken($token)->user();
        if (! $user || $user->status !== 'active') {
            Auth::logout();

            return response()->json(['message' => 'تم إيقاف هذا الحساب.', 'error' => 'account_suspended'], 403);
        }

        return $this->respondWithToken($token, $user);
    }

    public function logout(): JsonResponse
    {
        Auth::logout();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    protected function respondWithToken(string $token, User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60,
            'user' => new UserResource($user->load(['wallet', 'activeSubscription.plan'])),
        ], $status);
    }
}
