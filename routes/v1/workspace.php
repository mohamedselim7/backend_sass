<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
 * v1 — workspace endpoints. Authenticated (jwt) + active account.
 */
Route::prefix('v1')->middleware(['auth:api', 'active'])->group(function () {
    // --- Prompts ---
    Route::get('prompts', [V1\PromptController::class, 'index']);

    // --- Drafts (per-user autosave, keyed) ---
    Route::get('drafts/{key}', [V1\DraftController::class, 'show']);

    // --- Activity log ---
    Route::get('activity', [V1\ActivityController::class, 'index']);

    // --- Usage log ---
    Route::get('usage', [V1\UsageController::class, 'index']);

    // --- Marketing angles library ---
    Route::get('angles', [V1\MarketingAngleController::class, 'index']);
    Route::get('angles/{angle}/usages', [V1\MarketingAngleController::class, 'usages']);

    // --- Settings ---
    Route::get('settings', [V1\SettingsController::class, 'show']);

    Route::middleware('verified.api')->group(function () {
        Route::post('prompts', [V1\PromptController::class, 'store']);
        Route::patch('prompts/{prompt}', [V1\PromptController::class, 'update']);
        Route::post('prompts/{prompt}/favorite', [V1\PromptController::class, 'favorite']);
        Route::delete('prompts/{prompt}', [V1\PromptController::class, 'destroy']);

        Route::put('drafts/{key}', [V1\DraftController::class, 'update']);
        Route::delete('drafts/{key}', [V1\DraftController::class, 'destroy']);

        Route::post('activity', [V1\ActivityController::class, 'store']);

        Route::post('angles', [V1\MarketingAngleController::class, 'store']);
        Route::post('angles/generate', [V1\MarketingAngleController::class, 'generate'])->middleware('throttle:generate');
        Route::post('angles/{angle}/status', [V1\MarketingAngleController::class, 'status']);
        Route::post('angles/{angle}/usage', [V1\MarketingAngleController::class, 'recordUsage']);

        Route::patch('settings', [V1\SettingsController::class, 'update']);

        Route::post('onboarding/setup', [V1\OnboardingController::class, 'setup']);
    });
});
