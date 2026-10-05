<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DraftRequest;
use App\Models\WorkspaceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Arbitrary per-user JSON autosave, keyed by a string, stored as workspace_records(feature=draft). */
class DraftController extends Controller
{
    public function show(Request $request, string $key): JsonResponse
    {
        $record = WorkspaceRecord::query()
            ->where('user_id', $request->user()->getKey())
            ->where('feature', 'draft')
            ->where('key', $key)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Draft not found.'], 404);
        }

        return response()->json(['data' => ['key' => $record->key, 'data' => $record->result ?? []]]);
    }

    public function update(DraftRequest $request, string $key): JsonResponse
    {
        $record = WorkspaceRecord::updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'key' => $key],
            ['feature' => 'draft', 'title' => $key, 'result' => $request->validated()['data']],
        );

        return response()->json(['data' => ['key' => $record->key, 'data' => $record->result ?? []]]);
    }

    public function destroy(Request $request, string $key): JsonResponse
    {
        WorkspaceRecord::query()
            ->where('user_id', $request->user()->getKey())
            ->where('feature', 'draft')
            ->where('key', $key)
            ->delete();

        return response()->json(['message' => 'تم حذف المسودة.']);
    }
}
