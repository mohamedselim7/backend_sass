<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Ai\ChatAction;
use App\Actions\Ai\GenerateAnglesAction;
use App\Actions\Ai\GenerateContentAction;
use App\Actions\Ai\GenerateDesignAction;
use App\Actions\Ai\GenerateImageAction;
use App\Actions\Ai\GenerateReelsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AnglesRequest;
use App\Http\Requests\Ai\ChatRequest;
use App\Http\Requests\Ai\DesignAiRequest;
use App\Http\Requests\Ai\GenerateContentRequest;
use App\Http\Requests\Ai\GenerateImageRequest;
use App\Http\Requests\Ai\ReelsRequest;
use App\Http\Resources\Ai\ChatMessageResource;
use App\Http\Resources\Ai\MarketingAngleResource;
use App\Http\Resources\ContentGenerationResource;
use App\Http\Resources\DesignResource;
use App\Http\Resources\MediaResource;
use App\Jobs\GenerateContentJob;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\Ai\ProviderResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiController extends Controller
{
    public function __construct(
        private readonly GenerateContentAction $generateContent,
        private readonly GenerateAnglesAction $generateAngles,
        private readonly ChatAction $chat,
        private readonly GenerateDesignAction $generateDesign,
        private readonly GenerateImageAction $generateImage,
        private readonly GenerateReelsAction $generateReels,
        private readonly ProviderResolver $resolver,
    ) {}

    public function generateContentPost(GenerateContentRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($data['async'] ?? false) {
            GenerateContentJob::dispatch($user->getKey(), $data);

            return response()->json(['data' => ['status' => 'queued']], 202);
        }

        $generation = $this->generateContent->execute($user, $data, $idempotencyKey);

        return (new ContentGenerationResource($generation))->response()->setStatusCode(201);
    }

    public function angles(AnglesRequest $request): JsonResponse
    {
        $angles = $this->generateAngles->execute($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return response()->json(['data' => MarketingAngleResource::collection($angles)]);
    }

    public function chat(ChatRequest $request): JsonResponse
    {
        $result = $this->chat->execute($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return response()->json([
            'thread_id' => $result['thread']->getKey(),
            'messages' => ChatMessageResource::collection($result['messages']),
        ]);
    }

    public function chatThreads(Request $request): JsonResponse
    {
        $data = $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $threads = ChatThread::query()
            ->where('user_id', $request->user()->getKey())
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => collect($threads->items())->map(fn (ChatThread $thread) => $this->chatThreadRow($thread))->values(),
            'links' => [
                'first' => $threads->url(1),
                'last' => $threads->url($threads->lastPage()),
                'prev' => $threads->previousPageUrl(),
                'next' => $threads->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $threads->currentPage(),
                'from' => $threads->firstItem(),
                'last_page' => $threads->lastPage(),
                'per_page' => $threads->perPage(),
                'to' => $threads->lastItem(),
                'total' => $threads->total(),
            ],
        ]);
    }

    public function createChatThread(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $data = $request->validate([
            'brand_id' => ['nullable', 'uuid', Rule::exists('brands', 'id')->where('user_id', $userId)],
            'mode' => ['nullable', 'in:free,smart'],
            'title' => ['nullable', 'string', 'max:160'],
        ]);
        $thread = ChatThread::create([
            'user_id' => $userId,
            'brand_id' => $data['brand_id'] ?? null,
            'mode' => $data['mode'] ?? 'free',
            'title' => $data['title'] ?? 'محادثة جديدة',
        ]);
        $thread->setAttribute('messages_count', 0);

        return response()->json(['data' => $this->chatThreadRow($thread)], 201);
    }

    public function showChatThread(Request $request, ChatThread $thread): JsonResponse
    {
        $thread = $this->ownedChatThread($request, $thread)->load('messages');

        return response()->json([
            'thread' => $this->chatThreadRow($thread),
            'messages' => $thread->messages->map(fn (ChatMessage $message) => $this->chatMessageRow($message))->values(),
        ]);
    }

    public function updateChatThread(Request $request, ChatThread $thread): JsonResponse
    {
        $thread = $this->ownedChatThread($request, $thread);
        $data = $request->validate(['title' => ['required', 'string', 'max:160']]);
        $thread->update(['title' => $data['title']]);

        return response()->json(['data' => $this->chatThreadRow($thread->refresh())]);
    }

    public function deleteChatThread(Request $request, ChatThread $thread): JsonResponse
    {
        $this->ownedChatThread($request, $thread)->delete();

        return response()->json(['ok' => true]);
    }

    private function ownedChatThread(Request $request, ChatThread $thread): ChatThread
    {
        abort_unless($thread->user_id === $request->user()->getKey(), 404);

        return $thread;
    }

    /** @return array<string, mixed> */
    private function chatThreadRow(ChatThread $thread): array
    {
        return [
            'id' => $thread->getKey(),
            'title' => $thread->title ?: 'محادثة جديدة',
            'mode' => $thread->mode ?: 'free',
            'message_count' => (int) ($thread->messages_count ?? $thread->messages()->count()),
            'created_at' => $thread->created_at?->toIso8601String(),
            'updated_at' => ($thread->last_message_at ?? $thread->updated_at)?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function chatMessageRow(ChatMessage $message): array
    {
        return [
            'id' => $message->getKey(),
            'role' => $message->role,
            'content' => $message->content,
            'tokens' => $message->tokens,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    public function design(DesignAiRequest $request): JsonResponse
    {
        $design = $this->generateDesign->execute($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return (new DesignResource($design))->response()->setStatusCode(201);
    }

    public function image(GenerateImageRequest $request): JsonResponse
    {
        $media = $this->generateImage->execute($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return (new MediaResource($media))->response()->setStatusCode(201);
    }

    public function reels(ReelsRequest $request): JsonResponse
    {
        $result = $this->generateReels->execute($request->user(), $request->validated(), $request->header('Idempotency-Key'));

        return response()->json(['result' => $result]);
    }

    public function models(Request $request): JsonResponse
    {
        $models = $this->resolver->availableModels($request->user());

        return response()->json(['data' => $models]);
    }
}
