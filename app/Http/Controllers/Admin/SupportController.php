<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportThread;
use App\Models\User;
use App\Services\Admin\SupportService;
use App\Services\UsageLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(
        private readonly SupportService $support,
        private readonly UsageLogger $logger,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SupportThread::class);

        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'priority' => $request->string('priority')->toString(),
            'assigned' => $request->string('assigned')->toString(),
        ];

        $threads = SupportThread::query()
            ->with(['user:id,name,email,avatar_url', 'agent:id,name'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner->where('subject', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['priority'] !== '', fn ($q) => $q->where('priority', $filters['priority']))
            ->when($filters['assigned'] === 'me', fn ($q) => $q->where('assigned_to', $request->user()->getKey()))
            ->when($filters['assigned'] === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SupportThread $thread) => $this->threadRow($thread));

        return Inertia::render('support/Index', [
            'threads' => $threads,
            'filters' => $filters,
            'agents' => $this->agents(),
            'counts' => [
                'open' => SupportThread::open()->count(),
                'unassigned' => SupportThread::open()->whereNull('assigned_to')->count(),
                'mine' => SupportThread::open()->where('assigned_to', $request->user()->getKey())->count(),
                'unread' => (int) SupportThread::sum('unread_for_admin'),
            ],
        ]);
    }

    public function show(Request $request, SupportThread $thread): Response
    {
        $this->authorize('view', $thread);

        $thread->load(['user:id,name,email,avatar_url,status', 'agent:id,name']);
        $this->support->markReadForAgent($thread);

        return Inertia::render('support/Show', [
            'thread' => $this->threadRow($thread) + [
                'category' => $thread->category,
                'created_at' => $thread->created_at?->toIso8601String(),
            ],
            'messages' => $thread->messages()->with('sender:id,name,avatar_url')->get()
                ->map(fn (SupportMessage $message) => [
                    'id' => $message->getKey(),
                    'author_type' => $message->author_type,
                    'body' => $message->body,
                    'attachments' => $message->attachments ?? [],
                    'sender' => $message->sender?->only(['id', 'name', 'avatar_url']),
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
            'agents' => $this->agents(),
        ]);
    }

    public function reply(Request $request, SupportThread $thread): RedirectResponse
    {
        $this->authorize('reply', $thread);

        $data = $request->validate([
            'body' => ['required_without:image', 'nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp,gif', 'max:5120'],
            'status' => ['nullable', 'in:open,pending,resolved,closed'],
        ]);

        $attachments = [];
        if ($request->hasFile('image')) {
            $media = app(\App\Services\MediaService::class)->store($request->user(), $request->file('image'), 'support');
            $attachments[] = ['media_id' => $media->getKey(), 'url' => $media->url, 'mime' => $media->mime, 'size' => (int) $media->size];
        }

        $this->support->addMessage($thread, $request->user(), (string) ($data['body'] ?? ''), 'agent', $attachments);

        if (! empty($data['status'])) {
            $this->support->setStatus($thread, $data['status']);
        }

        $this->logger->activity($request->user(), 'admin.support.replied', [
            'entity' => 'support_thread',
            'details' => ['target' => $thread->getKey()],
        ]);

        return back()->with('success', __('Your reply has been sent.'));
    }

    public function setStatus(Request $request, SupportThread $thread): RedirectResponse
    {
        $this->authorize('manage', $thread);

        $data = $request->validate(['status' => ['required', 'in:open,pending,resolved,closed']]);
        $this->support->setStatus($thread, $data['status']);

        return back()->with('success', __('The conversation status has been updated.'));
    }

    public function assign(Request $request, SupportThread $thread): RedirectResponse
    {
        $this->authorize('manage', $thread);

        $data = $request->validate(['agent_id' => ['nullable', 'uuid', 'exists:users,id']]);
        $agent = $data['agent_id'] ? User::findOrFail($data['agent_id']) : null;

        if ($agent && ! $agent->hasAnyRole(['admin', 'support'])) {
            return back()->withErrors(['agent_id' => __('This account is not a support agent.')]);
        }

        $this->support->assign($thread, $agent);

        return back()->with('success', __('The conversation has been assigned.'));
    }

    /**
     * Admins and support agents. Queried through the relation instead of
     * User::role(), which throws when a role row is missing for the guard.
     */
    private function agents()
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'support']))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, mixed> */
    private function threadRow(SupportThread $thread): array
    {
        return [
            'id' => $thread->getKey(),
            'subject' => $thread->subject,
            'status' => $thread->status,
            'priority' => $thread->priority,
            'unread' => (int) $thread->unread_for_admin,
            'user' => $thread->user?->only(['id', 'name', 'email', 'avatar_url']),
            'agent' => $thread->agent?->only(['id', 'name']),
            'last_message_at' => $thread->last_message_at?->toIso8601String(),
        ];
    }
}
