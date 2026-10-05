<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityLogRequest;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Services\UsageLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function __construct(private readonly UsageLogger $logger) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = ActivityLog::query()
            ->where('user_id', $request->user()->getKey())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->when($request->filled('feature'), fn ($q) => $q->where('feature', $request->string('feature')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(min($request->integer('limit', $request->integer('per_page', 50)), 200));

        return ActivityLogResource::collection($logs);
    }

    public function store(ActivityLogRequest $request): JsonResponse
    {
        $log = $this->logger->activity($request->user(), $request->validated()['action'], $request->validated());

        return (new ActivityLogResource($log))->response()->setStatusCode(201);
    }
}
