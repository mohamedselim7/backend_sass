<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoalRequest;
use App\Http\Resources\GoalResource;
use App\Models\Goal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $goals = Goal::visibleTo($request->user())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->latest()
            ->paginate(min($request->integer('per_page', 25), 100));

        return GoalResource::collection($goals);
    }

    public function store(GoalRequest $request): JsonResponse
    {
        $goal = Goal::create($request->validated() + ['user_id' => $request->user()->getKey()]);

        return (new GoalResource($goal))->response()->setStatusCode(201);
    }

    public function update(GoalRequest $request, Goal $goal): GoalResource
    {
        $this->authorize('update', $goal);

        $goal->update($request->validated());

        return new GoalResource($goal);
    }

    public function destroy(Request $request, Goal $goal): JsonResponse
    {
        $this->authorize('delete', $goal);

        $goal->delete();

        return response()->json(['message' => 'تم حذف الهدف.']);
    }
}
