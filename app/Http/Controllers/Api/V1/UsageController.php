<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsageLogResource;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UsageController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = UsageLog::query()
            ->where('user_id', $request->user()->getKey())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->when($request->filled('feature'), fn ($q) => $q->where('feature', $request->string('feature')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(min($request->integer('limit', $request->integer('per_page', 50)), 200));

        return UsageLogResource::collection($logs);
    }
}
