<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ai\ProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** GET /api/v1/admin/ai/models[?refresh=1] — live text models for OpenAI and Gemini (admin only). */
class AdminAiModelController extends Controller
{
    public function index(Request $request, ProviderResolver $resolver): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        return response()->json($resolver->discoveredModels($request->boolean('refresh')));
    }
}
