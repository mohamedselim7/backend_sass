<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
 * All routes are versioned under /api/v1. Everything that touches user data
 * sits behind the jwt guard plus the active-account check; write endpoints also
 * require a verified email.
 */

Route::prefix('v1')->group(function () {
    // --- Public ---
    Route::post('auth/register', [V1\Auth\AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [V1\Auth\AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('auth/password/forgot', [V1\Auth\PasswordController::class, 'forgot'])->middleware('throttle:password-reset');
    Route::post('auth/password/reset', [V1\Auth\PasswordController::class, 'reset'])->middleware('throttle:6,1');
    Route::get('auth/verify/{id}/{hash}', [V1\Auth\EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Refresh accepts an expired access token that is still inside JWT_REFRESH_TTL,
    // so it cannot sit behind auth:api (which rejects expired tokens outright).
    Route::post('auth/refresh', [V1\Auth\AuthController::class, 'refresh'])->middleware('throttle:30,1');

    Route::get('plans', [V1\BillingController::class, 'plans']);
    Route::get('billing/gateways', [V1\BillingController::class, 'gateways']);
    Route::get('billing/credit-packages', [V1\BillingController::class, 'creditPackages']);

    // Gateway callback: authenticated by signature inside the controller.
    Route::post('webhooks/payment', [V1\WebhookController::class, 'payment']);
    Route::post('webhooks/easykash', [V1\WebhookController::class, 'easykash'])->middleware('throttle:120,1');

    // --- Authenticated ---
    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('auth/me', [V1\Auth\AuthController::class, 'me']);
        Route::post('auth/logout', [V1\Auth\AuthController::class, 'logout']);
        Route::post('auth/verify/send', [V1\Auth\EmailVerificationController::class, 'send'])->middleware('throttle:6,1');

        Route::patch('profile', [V1\ProfileController::class, 'update']);
        Route::post('profile/password', [V1\ProfileController::class, 'changePassword']);

        Route::get('notifications', [V1\NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [V1\NotificationController::class, 'unreadCount']);
        Route::post('notifications/{notification}/read', [V1\NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [V1\NotificationController::class, 'markAllRead']);
        Route::post('notifications/self', [V1\NotificationController::class, 'storeSelf'])->middleware('throttle:30,1');

        Route::get('billing/wallet', [V1\BillingController::class, 'wallet']);
        Route::get('billing/credits', [V1\BillingController::class, 'creditHistory']);
        Route::get('billing/payments/{payment}', [V1\BillingController::class, 'payment']);

        // Support conversations with the Ma3roof team (own threads only — enforced by policy).
        Route::get('support/threads', [V1\SupportController::class, 'index']);
        Route::get('support/unread-count', [V1\SupportController::class, 'unreadCount']);
        Route::post('support/threads', [V1\SupportController::class, 'store'])->middleware('throttle:10,1');
        Route::get('support/threads/{thread}', [V1\SupportController::class, 'show']);
        Route::post('support/threads/{thread}/messages', [V1\SupportController::class, 'reply'])->middleware('throttle:30,1');
        Route::post('support/threads/{thread}/read', [V1\SupportController::class, 'markRead']);

        Route::get('brands', [V1\BrandController::class, 'index']);
        Route::get('brands/{brand}', [V1\BrandController::class, 'show']);
        Route::get('content/generations', [V1\ContentController::class, 'generations']);
        Route::get('content/generations/{generation}', [V1\ContentController::class, 'showGeneration']);
        Route::get('content/posts', [V1\ContentController::class, 'posts']);
        Route::get('designs', [V1\DesignController::class, 'index']);
        Route::get('designs/{design}', [V1\DesignController::class, 'show']);
        Route::get('goals', [V1\GoalController::class, 'index']);
        Route::get('schedule', [V1\ScheduleController::class, 'index']);
        Route::get('social-accounts', [V1\SocialAccountController::class, 'index']);
        Route::get('records', [V1\WorkspaceRecordController::class, 'index']);
        Route::get('records/{workspaceRecord}', [V1\WorkspaceRecordController::class, 'show']);

        // --- Writes: verified email required ---
        Route::middleware('verified.api')->group(function () {
            Route::post('billing/checkout', [V1\BillingController::class, 'checkout']);
            Route::post('billing/credit-packages/checkout', [V1\BillingController::class, 'checkoutCreditPackage'])->middleware('throttle:10,1');

            Route::post('brands', [V1\BrandController::class, 'store']);
            Route::patch('brands/{brand}', [V1\BrandController::class, 'update']);
            Route::delete('brands/{brand}', [V1\BrandController::class, 'destroy']);

            Route::post('content/generations', [V1\ContentController::class, 'storeGeneration']);
            Route::patch('content/posts/{post}', [V1\ContentController::class, 'updatePost']);
            Route::post('content/posts/{post}/status', [V1\ContentController::class, 'transitionPost']);
            Route::delete('content/posts/{post}', [V1\ContentController::class, 'destroyPost']);

            Route::post('designs', [V1\DesignController::class, 'store']);
            Route::post('designs/{design}/versions', [V1\DesignController::class, 'addVersion']);
            Route::delete('designs/{design}', [V1\DesignController::class, 'destroy']);

            Route::post('goals', [V1\GoalController::class, 'store']);
            Route::patch('goals/{goal}', [V1\GoalController::class, 'update']);
            Route::delete('goals/{goal}', [V1\GoalController::class, 'destroy']);

            Route::post('schedule', [V1\ScheduleController::class, 'store']);
            Route::patch('schedule/{scheduledPost}', [V1\ScheduleController::class, 'update']);
            Route::post('schedule/{scheduledPost}/cancel', [V1\ScheduleController::class, 'cancel']);

            Route::delete('social-accounts/{socialAccount}', [V1\SocialAccountController::class, 'destroy']);

            Route::post('records', [V1\WorkspaceRecordController::class, 'store']);
            Route::delete('records/{workspaceRecord}', [V1\WorkspaceRecordController::class, 'destroy']);
        });

        // --- Admin ---
        Route::middleware('role:admin')->prefix('admin')->group(function () {
            Route::get('stats', [V1\Admin\AdminStatsController::class, 'overview']);
            Route::get('activity', [V1\Admin\AdminStatsController::class, 'activity']);
            Route::get('usage', [V1\Admin\AdminStatsController::class, 'usage']);

            Route::get('users', [V1\Admin\AdminUserController::class, 'index']);
            Route::get('users/{user}', [V1\Admin\AdminUserController::class, 'show']);
            Route::post('users/{user}/status', [V1\Admin\AdminUserController::class, 'setStatus']);
            Route::post('users/{user}/roles', [V1\Admin\AdminUserController::class, 'syncRoles']);
            Route::post('users/{user}/credits', [V1\Admin\AdminUserController::class, 'adjustCredits']);
            Route::post('users/{user}/plan', [V1\Admin\AdminUserController::class, 'changePlan']);

            Route::get('plans', [V1\Admin\AdminPlanController::class, 'index']);
            Route::post('plans', [V1\Admin\AdminPlanController::class, 'store']);
            Route::patch('plans/{plan}', [V1\Admin\AdminPlanController::class, 'update']);
            Route::delete('plans/{plan}', [V1\Admin\AdminPlanController::class, 'destroy']);

            Route::get('ai/models', [V1\Admin\AdminAiModelController::class, 'index']);

            Route::get('settings', [V1\Admin\AdminSettingsController::class, 'index']);
            Route::post('settings', [V1\Admin\AdminSettingsController::class, 'updateSetting']);
            Route::post('settings/providers', [V1\Admin\AdminSettingsController::class, 'upsertProviderKey']);
            Route::delete('settings/providers/{providerKey}', [V1\Admin\AdminSettingsController::class, 'deleteProviderKey']);

            Route::get('design-requests', [V1\Admin\AdminDesignRequestController::class, 'index']);
            Route::patch('design-requests/{designRequest}', [V1\Admin\AdminDesignRequestController::class, 'update']);

            Route::post('notifications', [V1\Admin\AdminNotificationController::class, 'store']);
        });
    });
});

/*
 * Feature route partials (one file per domain) and the v2 surface.
 */
foreach (['media', 'ai', 'social', 'workspace'] as $group) {
    require base_path("routes/v1/{$group}.php");
}

require base_path('routes/v2/api.php');
