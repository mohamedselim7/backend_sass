<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PostStatus;
use App\Events\PostStatusChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\StoreGenerationRequest;
use App\Http\Requests\Content\TransitionPostRequest;
use App\Http\Requests\Content\UpdatePostRequest;
use App\Http\Resources\ContentGenerationResource;
use App\Http\Resources\ContentPostResource;
use App\Models\ContentGeneration;
use App\Models\ContentPost;
use App\Services\ContentService;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContentController extends Controller
{
    public function __construct(
        private readonly ContentService $content,
        private readonly CreditService $credits,
    ) {}

    public function generations(Request $request): AnonymousResourceCollection
    {
        $generations = ContentGeneration::visibleTo($request->user())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->latest()
            ->paginate(min($request->integer('per_page', 20), 100));

        return ContentGenerationResource::collection($generations);
    }

    public function showGeneration(Request $request, ContentGeneration $generation): ContentGenerationResource
    {
        $this->authorize('view', $generation);

        return new ContentGenerationResource($generation->load('plans.posts'));
    }

    /** Persists a finished generation and charges credits in the same request. */
    public function storeGeneration(StoreGenerationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $charge = $this->credits->charge(
            $user,
            'content_plan',
            refId: null,
            idempotencyKey: $request->header('Idempotency-Key'),
            details: ['plans' => count($data['plans'])],
        );

        try {
            $generation = $this->content->storeGeneration($user, $data, $data['plans']);
        } catch (\Throwable $e) {
            $this->credits->refund($user, $charge, 'content generation failed');
            throw $e;
        }

        return (new ContentGenerationResource($generation))->response()->setStatusCode(201);
    }

    public function posts(Request $request): AnonymousResourceCollection
    {
        $posts = ContentPost::visibleTo($request->user())
            ->when($request->filled('brand_id'), fn ($q) => $q->where('brand_id', $request->string('brand_id')))
            ->when($request->filled('plan_id'), fn ($q) => $q->where('plan_id', $request->string('plan_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(min($request->integer('per_page', 50), 200));

        return ContentPostResource::collection($posts);
    }

    public function updatePost(UpdatePostRequest $request, ContentPost $post): ContentPostResource
    {
        $this->authorize('update', $post);

        $post->update($request->validated());

        return new ContentPostResource($post);
    }

    public function transitionPost(TransitionPostRequest $request, ContentPost $post): ContentPostResource
    {
        $this->authorize('update', $post);

        $post = $this->content->transition(
            $post,
            PostStatus::from($request->string('status')->value()),
            $request->input('reason'),
        );

        PostStatusChanged::dispatch($post);

        return new ContentPostResource($post);
    }

    public function destroyPost(Request $request, ContentPost $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $post->delete();

        return response()->json(['message' => 'تم حذف المنشور.']);
    }
}
