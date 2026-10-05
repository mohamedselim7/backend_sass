<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\PromptRequest;
use App\Http\Resources\PromptResource;
use App\Models\Prompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PromptController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $prompts = Prompt::visibleTo($request->user())
            ->search($request->string('q', $request->string('search'))->value() ?: null)
            ->when($request->filled('section'), fn ($q) => $q->where('section', $request->string('section')))
            ->orderByDesc('is_favorite')
            ->latest()
            ->paginate(min($request->integer('per_page', 50), 200));

        return PromptResource::collection($prompts);
    }

    public function store(PromptRequest $request): JsonResponse
    {
        $this->authorize('create', Prompt::class);

        $data = $request->validated();
        $data['body'] = $data['prompt'] ?? $data['body'] ?? '';

        $prompt = Prompt::create($data + ['user_id' => $request->user()->getKey()]);

        return (new PromptResource($prompt))->response()->setStatusCode(201);
    }

    public function update(PromptRequest $request, Prompt $prompt): PromptResource
    {
        $this->authorize('update', $prompt);

        $data = $request->validated();
        if (array_key_exists('prompt', $data)) {
            $data['body'] = $data['prompt'];
        }
        // Only the owner may re-flag sharing.
        if (! $request->user()->isAdmin() && $prompt->user_id !== $request->user()->getKey()) {
            unset($data['is_shared']);
        }

        $prompt->update($data);

        return new PromptResource($prompt);
    }

    public function favorite(Request $request, Prompt $prompt): JsonResponse
    {
        $this->authorize('update', $prompt);

        $data = $request->validate(['is_favorite' => ['required', 'boolean']]);
        $prompt->update($data);

        return response()->json(['is_favorite' => (bool) $prompt->is_favorite]);
    }

    public function destroy(Request $request, Prompt $prompt): JsonResponse
    {
        $this->authorize('delete', $prompt);

        $prompt->delete();

        return response()->json(['message' => 'تم حذف البرومبت.']);
    }
}
