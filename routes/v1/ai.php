<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
 * v1 — ai endpoints. Authenticated (jwt) + active account + verified email,
 * throttled on the `generate` limiter since these are the expensive calls.
 */
Route::prefix('v1/ai')
    ->middleware(['auth:api', 'active', 'verified.api', 'throttle:generate'])
    ->group(function () {
        Route::post('content/generate', [V1\AiController::class, 'generateContentPost']);
        Route::get('jobs/{id}', [V1\AiController::class, 'jobStatus'])->whereNumber('id');
        // Marketing angles library removed from the user-facing product (spec #23).
        // Route::post('angles', [V1\AiController::class, 'angles']);
        Route::get('chat/threads', [V1\AiController::class, 'chatThreads']);
        Route::post('chat/threads', [V1\AiController::class, 'createChatThread']);
        Route::get('chat/threads/{thread}', [V1\AiController::class, 'showChatThread']);
        Route::get('chat/threads/{thread}/messages', [V1\AiController::class, 'chatThreadMessages']);
        Route::patch('chat/threads/{thread}', [V1\AiController::class, 'updateChatThread']);
        Route::delete('chat/threads/{thread}', [V1\AiController::class, 'deleteChatThread']);
        Route::post('chat', [V1\AiController::class, 'chat']);
        Route::post('design', [V1\AiController::class, 'design']);
        Route::post('image', [V1\AiController::class, 'image']);
        Route::post('reels', [V1\AiController::class, 'reels']);
        Route::get('models', [V1\AiController::class, 'models']);
    });
