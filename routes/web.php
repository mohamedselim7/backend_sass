<?php

use App\Http\Controllers\Admin;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
 * Web (session) surface. This file serves ONLY the Inertia Admin application.
 * The user-facing React + Vite app keeps talking to /api/v1 with JWT.
 */

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function () {
    // --- Session authentication for the Admin ---
    Route::middleware('guest')->group(function () {
        Route::get('login', [Admin\AuthController::class, 'create'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('login.store');
    });

    // Language switch is available to any signed-in agent.
    Route::post('locale', [Admin\LocaleController::class, 'update'])
        ->middleware('auth:web')
        ->name('locale.update');

    Route::post('logout', [Admin\AuthController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('logout');

    // --- Admin application ---
    Route::middleware(['auth:web', 'active', 'admin'])->group(function () {
        // Realtime for the support inbox, authorised by the web session.

        Broadcast::routes(['middleware' => ['web', 'auth:web']]);
        
        Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

        // Users
        Route::get('users', [Admin\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [Admin\UserController::class, 'show'])->name('users.show');
        Route::post('users/{user}/status', [Admin\UserController::class, 'setStatus'])->name('users.status');
        Route::post('users/{user}/roles', [Admin\UserController::class, 'syncRoles'])->name('users.roles');
        Route::post('users/{user}/credits', [Admin\UserController::class, 'adjustCredits'])->name('users.credits');
        Route::post('users/{user}/plan', [Admin\UserController::class, 'changePlan'])->name('users.plan');

        // Content
        Route::get('content', [Admin\ContentController::class, 'index'])->name('content.index');
        Route::post('content/posts/{post}/status', [Admin\ContentController::class, 'transition'])->name('content.transition');

        // Payments
        Route::get('payments', [Admin\PaymentController::class, 'index'])->name('payments.index');

        // Subscriptions & plans
        Route::get('subscriptions', [Admin\SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::post('subscriptions/{subscription}/cancel', [Admin\SubscriptionController::class, 'cancel'])->name('subscriptions.cancel');

        // Support
        Route::get('support', [Admin\SupportController::class, 'index'])->name('support.index');
        Route::get('support/{thread}', [Admin\SupportController::class, 'show'])->name('support.show');
        Route::post('support/{thread}/reply', [Admin\SupportController::class, 'reply'])->name('support.reply');
        Route::post('support/{thread}/status', [Admin\SupportController::class, 'setStatus'])->name('support.status');
        Route::post('support/{thread}/assign', [Admin\SupportController::class, 'assign'])->name('support.assign');

        // Credit-saving tips shown to users
        Route::get('credit-tips', [Admin\CreditTipController::class, 'index'])->name('credit-tips.index');
        Route::post('credit-tips', [Admin\CreditTipController::class, 'store'])->name('credit-tips.store');
        Route::patch('credit-tips/{creditTip}', [Admin\CreditTipController::class, 'update'])->name('credit-tips.update');
        Route::delete('credit-tips/{creditTip}', [Admin\CreditTipController::class, 'destroy'])->name('credit-tips.destroy');

        // Settings
        Route::get('settings', [Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/plans', [Admin\SettingsController::class, 'upsertPlan'])->name('settings.plans.upsert');
        Route::post('settings/credit-packages', [Admin\SettingsController::class, 'upsertCreditPackage'])->name('settings.credit-packages.upsert');
        Route::post('settings/providers', [Admin\SettingsController::class, 'upsertProviderKey'])->name('settings.providers.upsert');
        Route::delete('settings/providers/{providerKey}', [Admin\SettingsController::class, 'deleteProviderKey'])->name('settings.providers.destroy');
    });
});

// Anything else under the web surface is an Inertia 404 inside the Admin shell.
Route::fallback(fn () => Inertia\Inertia::render('errors/ErrorPage', ['status' => 404])->toResponse(request())->setStatusCode(404));
