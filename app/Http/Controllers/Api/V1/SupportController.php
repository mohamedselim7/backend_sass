<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Services\Admin\SupportService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User side of the support system (React app, JWT). Every mutation goes through
 * SupportService, the same service the Inertia Admin uses, and every read is
 * scoped by SupportThreadPolicy / scopeVisibleTo.
 */
class SupportController extends Controller
{
    private const IMAGE_RULES = ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'];

    public function __construct(
        private readonly SupportService $support,
        private readonly MediaService $media,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $threads = SupportThread::query()
            ->where('user_id', $request->user()->getKey())
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (SupportThread $thread) => $this->threadRow($thread));

        return response()->json(['data' => $threads]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => (int) SupportThread::where('user_id', $request->user()->getKey())->sum('unread_for_user'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'in:general,billing,technical,account,content'],
            'image' => self::IMAGE_RULES,
        ]);

        $attachments = $this->storeImage($request);
        $thread = $this->support->openThread(
            $request->user(),
            $data['subject'],
            (string) ($data['body'] ?? ''),
            $data['category'] ?? 'general',
            'normal',
            $attachments,
        );

        return response()->json(['data' => $this->threadRow($thread)], 201);
    }

    public function show(Request $request, SupportThread $thread): JsonResponse
    {
        $this->authorize('view', $thread);
        $this->support->markReadForUser($thread);

        return response()->json([
            'data' => $this->threadRow($thread->refresh()) + [
                'messages' => $thread->messages()->with('sender:id,name')->get()
                    ->map(fn (SupportMessage $message) => $this->messageRow($message)),
            ],
        ]);
    }

    public function reply(Request $request, SupportThread $thread): JsonResponse
    {
        $this->authorize('reply', $thread);

        $data = $request->validate([
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => self::IMAGE_RULES,
        ]);

        $message = $this->support->addMessage(
            $thread,
            $request->user(),
            (string) ($data['body'] ?? ''),
            'user',
            $this->storeImage($request),
        );

        return response()->json(['data' => $this->messageRow($message->load('sender:id,name'))], 201);
    }

    public function markRead(Request $request, SupportThread $thread): JsonResponse
    {
        $this->authorize('view', $thread);
        $this->support->markReadForUser($thread);

        return response()->json(['ok' => true]);
    }

    /** @return array<int, array<string, mixed>> */
    private function storeImage(Request $request): array
    {
        if (! $request->hasFile('image')) {
            return [];
        }
        $media = $this->media->store($request->user(), $request->file('image'), 'support');

        return [[
            'media_id' => $media->getKey(),
            'url' => $media->url,
            'mime' => $media->mime,
            'size' => (int) $media->size,
        ]];
    }

    /** @return array<string, mixed> */
    private function threadRow(SupportThread $thread): array
    {
        return [
            'id' => $thread->getKey(),
            'subject' => $thread->subject,
            'category' => $thread->category,
            'status' => $thread->status,
            'unread' => (int) $thread->unread_for_user,
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
            'created_at' => $thread->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function messageRow(SupportMessage $message): array
    {
        return [
            'id' => $message->getKey(),
            'thread_id' => $message->thread_id,
            'author_type' => $message->author_type,
            'body' => $message->body,
            'attachments' => $message->attachments ?? [],
            'sender' => ['id' => $message->sender_id, 'name' => $message->sender?->name],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
