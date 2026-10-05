<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPost;
use App\Models\ScheduledPost;
use App\Services\UsageLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ContentController extends Controller
{
    public function __construct(private readonly UsageLogger $logger)
    {
    }

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->toString(),
            'status' => $request->string('status')->toString(),
            'platform' => $request->string('platform')->toString(),
        ];

        $posts = ContentPost::query()
            ->with(['user:id,name,email', 'brand:id,name'])
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $term)->orWhere('body', 'like', $term));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['platform'] !== '', fn ($q) => $q->where('platform', $filters['platform']))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ContentPost $post) => [
                'id' => $post->getKey(),
                'title' => $post->title,
                'excerpt' => mb_strimwidth((string) $post->body, 0, 160, '…'),
                'status' => $post->status,
                'platform' => $post->platform,
                'user' => $post->user?->only(['id', 'name', 'email']),
                'brand' => $post->brand?->only(['id', 'name']),
                'created_at' => $post->created_at?->toIso8601String(),
            ]);

        return Inertia::render('content/Index', [
            'posts' => $posts,
            'filters' => $filters,
            'statuses' => ContentPost::query()->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')->pluck('total', 'status'),
            'platforms' => ContentPost::query()->whereNotNull('platform')
                ->distinct()->orderBy('platform')->pluck('platform'),
            'scheduled' => ScheduledPost::with(['user:id,name'])
                ->whereIn('status', ['pending', 'queued', 'scheduled'])
                ->orderBy('scheduled_at')
                ->limit(10)->get()
                ->map(fn (ScheduledPost $post) => [
                    'id' => $post->getKey(),
                    'status' => $post->status,
                    'platform' => $post->platform,
                    'user' => $post->user?->only(['id', 'name']),
                    'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                ]),
        ]);
    }

    public function transition(Request $request, ContentPost $post): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:draft,approved,published,archived'],
        ]);

        $post->update($data);
        $this->logger->activity($request->user(), 'admin.content.status_changed', [
            'entity' => 'content_post',
            'details' => ['target' => $post->getKey(), 'status' => $data['status']],
        ]);

        return back()->with('success', __('The content status has been updated.'));
    }
}
