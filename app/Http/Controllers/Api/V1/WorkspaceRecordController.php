<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkspaceRecordRequest;
use App\Http\Resources\WorkspaceRecordResource;
use App\Models\WorkspaceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Generic store for feature outputs (ads, captions, analyses, ...). */
class WorkspaceRecordController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $records = WorkspaceRecord::visibleTo($request->user())
            ->when($request->filled('feature'), fn ($q) => $q->where('feature', $request->string('feature')))
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return WorkspaceRecordResource::collection($records);
    }

    public function store(WorkspaceRecordRequest $request): JsonResponse
    {
        $record = WorkspaceRecord::create($request->validated() + ['user_id' => $request->user()->getKey()]);

        return (new WorkspaceRecordResource($record))->response()->setStatusCode(201);
    }

    public function show(Request $request, WorkspaceRecord $workspaceRecord): WorkspaceRecordResource
    {
        $this->authorize('view', $workspaceRecord);

        return new WorkspaceRecordResource($workspaceRecord);
    }

    public function destroy(Request $request, WorkspaceRecord $workspaceRecord): JsonResponse
    {
        $this->authorize('delete', $workspaceRecord);

        $workspaceRecord->delete();

        return response()->json(['message' => 'تم حذف السجل.']);
    }
}
