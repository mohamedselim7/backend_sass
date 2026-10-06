<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPlanController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $plan = Plan::create($this->validated($request, true));

        return (new PlanResource($plan))->response()->setStatusCode(201);
    }

    public function update(Request $request, Plan $plan): PlanResource
    {
        $plan->update($this->validated($request, false));

        return new PlanResource($plan);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        // Plans with history are deactivated rather than removed.
        if ($plan->subscriptions()->exists()) {
            $plan->update(['is_active' => false]);

            return response()->json(['message' => 'تم إيقاف الخطة (لها اشتراكات سابقة).']);
        }

        $plan->delete();

        return response()->json(['message' => 'تم حذف الخطة.']);
    }

    private function validated(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'code' => [$required, 'string', 'max:64', 'unique:plans,code'.($creating ? '' : ','.$request->route('plan')->id)],
            'name' => [$required, 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => [$required, 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'interval' => ['nullable', 'in:month,year'],
            'monthly_credits' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'ai_model' => [$required, 'string', 'in:'.implode(',', Plan::aiModels())],
            // Queue routing: an explicit queue name (e.g. "ai-high") always
            // wins over queue_priority when both are present (see
            // PlanQueueResolver). Neither field hardcodes a plan name/price.
            'ai_queue' => ['nullable', 'string', 'max:64', 'in:'.implode(',', array_values(config('queue.names')))],
            'queue_priority' => ['nullable', 'integer', 'min:1', 'max:3'],
        ]);
    }
}
