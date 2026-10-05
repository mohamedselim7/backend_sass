<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditTip;
use Illuminate\Http\JsonResponse;

/** Active credit-saving tips for the signed-in user (read only). */
class CreditTipController extends Controller
{
    public function index(): JsonResponse
    {
        $tips = CreditTip::query()->active()->ordered()->get()
            ->map(fn (CreditTip $tip) => [
                'id' => $tip->getKey(),
                'title' => $tip->title,
                'body' => $tip->body,
                'updated_at' => $tip->updated_at?->toIso8601String(),
            ])->values();

        return response()->json(['data' => $tips]);
    }
}
