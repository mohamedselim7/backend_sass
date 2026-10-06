<?php

namespace App\Actions\Ai;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Ai\Contracts\TextProvider;
use Illuminate\Support\Collection;

/**
 * Keeps AI chat context bounded no matter how long a thread grows.
 *
 * Context = system instructions (built elsewhere, in the prompt services)
 *         + a rolling summary of everything older than the recent window
 *         + the last N raw messages (N = plan.recent_ai_context_messages,
 *           falling back to a safe default).
 *
 * No vector database, no unbounded history — just a summary pointer
 * (summary_until_message_id) so we never re-summarize the same messages.
 */
class ChatContextBuilder
{
    /** Used when the user's plan does not configure a limit. */
    public const DEFAULT_RECENT_MESSAGES = 10;

    /** Hard ceiling regardless of plan configuration. */
    public const MAX_RECENT_MESSAGES = 50;

    /** Once more than this many messages sit before the recent window, fold them into the summary. */
    private const SUMMARY_TRIGGER_BACKLOG = 20;

    /**
     * @param  ChatMessage|null  $excludeMessageId  the just-created user message, appended separately by the caller
     * @return array{history: array<int, array{role: string, content: string}>, recent_limit: int}
     */
    public function build(User $user, ChatThread $thread, ?ChatMessage $excludeMessageId = null): array
    {
        $limit = $this->recentMessageLimit($user);

        $recent = $thread->messages()->reorder()
            ->when($excludeMessageId, fn ($q) => $q->whereKeyNot($excludeMessageId->getKey()))
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $history = [];

        if ($thread->summary) {
            $history[] = [
                'role' => 'system',
                'content' => 'ملخّص المحادثة حتى الآن (للسياق فقط، لا تكرره حرفيًا): '.$thread->summary,
            ];
        }

        foreach ($recent as $message) {
            $content = trim((string) $message->content);
            if ($content === '') {
                continue;
            }
            $history[] = ['role' => $message->role, 'content' => $content];
        }

        return ['history' => $history, 'recent_limit' => $limit];
    }

    /** Plan-aware recent-message window, clamped to a safe range. */
    public function recentMessageLimit(User $user): int
    {
        $configured = optional($user->activeSubscription?->plan)->recent_ai_context_messages;
        $limit = is_numeric($configured) && (int) $configured > 0
            ? (int) $configured
            : self::DEFAULT_RECENT_MESSAGES;

        return max(1, min($limit, self::MAX_RECENT_MESSAGES));
    }

    /**
     * Roll older messages into thread.summary when the backlog before the
     * recent window grows too large. Best-effort: failures never block the
     * chat reply, they just mean the summary stays as-is until next turn.
     */
    public function rollSummaryIfNeeded(ChatThread $thread, TextProvider $provider): void
    {
        $limit = self::DEFAULT_RECENT_MESSAGES;

        $afterId = $thread->summary_until_message_id;
        $olderQuery = $thread->messages()->reorder()
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->when($afterId, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('created_at')->orderBy('id');

        $totalMessages = $thread->messages()->count();
        $backlogBeforeRecent = max(0, $totalMessages - $limit);

        if ($backlogBeforeRecent < self::SUMMARY_TRIGGER_BACKLOG) {
            return;
        }

        /** @var Collection<int, ChatMessage> $toSummarize */
        $toSummarize = $olderQuery->limit($backlogBeforeRecent)->get();
        if ($toSummarize->isEmpty()) {
            return;
        }

        $transcript = $toSummarize
            ->map(fn (ChatMessage $m) => sprintf('%s: %s', $m->role === 'user' ? 'المستخدم' : 'المساعد', mb_substr((string) $m->content, 0, 800)))
            ->implode("\n");

        try {
            $system = 'لخّص المحادثة التالية بإيجاز شديد (٤-٦ أسطر) مع الحفاظ على الحقائق والقرارات المهمة فقط، بنفس لغة المحادثة.';
            $prior = $thread->summary ? "الملخص السابق:\n{$thread->summary}\n\n" : '';
            $result = $provider->complete($system, [
                ['role' => 'user', 'content' => $prior.'المحادثة الجديدة لتلخيصها:'."\n".$transcript],
            ]);

            $thread->forceFill([
                'summary' => trim($result->text) ?: $thread->summary,
                'summary_until_message_id' => $toSummarize->last()?->getKey(),
                'summary_updated_at' => now(),
            ])->save();
        } catch (\Throwable) {
            // Summarization is an optimization, not a correctness requirement — swallow and retry next turn.
        }
    }
}
