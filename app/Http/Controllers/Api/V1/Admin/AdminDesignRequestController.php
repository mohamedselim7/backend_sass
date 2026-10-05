<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\DesignRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDesignRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = DesignRequest::latest()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->paginate(min($request->integer('per_page', 25), 100));

        return response()->json($items);
    }

    public function update(Request $request, DesignRequest $designRequest): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,in_progress,completed,rejected'],
            'design_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $designRequest->update($data + (
            ($data['status'] ?? null) === 'completed' ? ['completed_at' => now()] : []
        ));

        return response()->json($designRequest);
    }
}
