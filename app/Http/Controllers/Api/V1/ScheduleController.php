<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Http\Resources\ScheduledPostResource;
use App\Models\ScheduledPost;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $scheduler) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $items = ScheduledPost::visibleTo($request->user())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('scheduled_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('scheduled_at', '<=', $request->date('to')))
            ->orderBy('scheduled_at')
            ->paginate(min($request->integer('per_page', 50), 200));

        return ScheduledPostResource::collection($items);
    }

    public function store(ScheduleRequest $request): JsonResponse
    {
        $scheduled = $this->scheduler->schedule($request->user(), $request->validated());

        return (new ScheduledPostResource($scheduled))->response()->setStatusCode(201);
    }

    public function update(ScheduleRequest $request, ScheduledPost $scheduledPost): ScheduledPostResource
    {
        $this->authorize('update', $scheduledPost);

        $scheduledPost->update($request->validated());

        return new ScheduledPostResource($scheduledPost);
    }

    public function cancel(Request $request, ScheduledPost $scheduledPost): ScheduledPostResource
    {
        $this->authorize('update', $scheduledPost);

        return new ScheduledPostResource($this->scheduler->cancel($scheduledPost));
    }
}
