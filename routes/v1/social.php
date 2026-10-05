<?php

use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
 * v1 — social endpoints. Authenticated (jwt) + active account.
 */
Route::prefix('v1')->middleware(['auth:api', 'active'])->group(function () {
    //
});
