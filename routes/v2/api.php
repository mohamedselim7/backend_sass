<?php

use App\Http\Controllers\Api\V2;
use Illuminate\Support\Facades\Route;

/*
 * v2 — same surface as v1 unless a controller overrides it. V2 controllers
 * extend their V1 counterparts, so only changed behaviour lives here.
 */
Route::prefix('v2')->group(function () {
    //
});
