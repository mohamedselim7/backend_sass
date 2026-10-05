<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\ContentPost;
use App\Models\Payment;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStatsController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'users' => [
                'total' => User::count(),
                'active' => User::where('status', 'active')->count(),
                'new_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
            ],
            'brands' => Brand::count(),
            'posts' => [
                'total' => ContentPost::count(),
                'published' => ContentPost::where('status', 'published')->count(),
            ],
            'revenue' => [
                'this_month' => (float) Payment::where('status', 'paid')
                    ->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'lifetime' => (float) Payment::where('status', 'paid')->sum('amount'),
            ],
            'ai_cost_this_month' => (float) UsageLog::where('created_at', '>=', now()->startOfMonth())->sum('cost'),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $logs = ActivityLog::latest()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->string('user_id')))
            ->paginate(min($request->integer('per_page', 50), 200));

        return response()->json($logs);
    }

    public function usage(Request $request): JsonResponse
    {
        $logs = UsageLog::latest()
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->string('provider')))
            ->paginate(min($request->integer('per_page', 50), 200));

        return response()->json($logs);
    }
}
