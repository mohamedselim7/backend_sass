<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
 * v1 — media endpoints. Authenticated (jwt) + active account.
 */
Route::prefix('v1')->group(function () {
    // Public: serves the raw file bytes so CORS/browser fetch works
    // regardless of the dev server (php artisan serve skips the framework
    // entirely for files that already exist under public/storage/*).
    Route::get('media-file/{path}', [V1\MediaFileController::class, 'show'])->where('path', '.*');

    Route::middleware(['auth:api', 'active'])->group(function () {
        Route::get('media', [V1\MediaController::class, 'index']);

        Route::middleware('verified.api')->group(function () {
            Route::post('media', [V1\MediaController::class, 'store']);
            Route::delete('media/{media}', [V1\MediaController::class, 'destroy']);
        });
    });
});