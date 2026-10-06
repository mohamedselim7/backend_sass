<?php

namespace App\Actions\Ai;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use App\Services\Ai\ProviderResolver;
use App\Services\CreditService;
use App\Services\UsageLogger;
use Throwable;

class ChatAction
{
    public function __construct(
        private readonly CreditService $credits,
        private readonly UsageLogger $logger,
        private readonly ProviderResolver $resolver,
        private readonly ChatContextBuilder $contextBuilder,
    ) {}

    /** @return array{thread: ChatThread, messages: \Illuminate\Support\Collection<int, ChatMessage>} */
    public function execute(User $user, array $data, ?string $idempotencyKey = null): array
    {
        $cost = $this->credits->cost('chat_message');
        $key = $idempotencyKey ?? 'ai-chat:'.$user->getKey().':'.sha1(json_encode($data).microtime());

        $charge = $this->credits->charge($user, 'chat_message', $cost, idempotencyKey: $key);

        $thread = null;
        if (! empty($data['thread_id'])) {
            $thread = ChatThread::visibleTo($user)->findOrFail($data['thread_id']);
        }

        if (! $thread) {
            $thread = ChatThread::create([
                'user_id' => $user->getKey(),
                'brand_id' => $data['brand_id'] ?? null,
                'title' => mb_substr($data['message'], 0, 60),
                'mode' => $data['mode'] ?? 'free',
                'provider' => $data['provider'] ?? null,
                'model' => $data['model'] ?? null,
            ]);
        }

        try {
            $userMessage = ChatMessage::create([
                'thread_id' => $thread->getKey(),
                'role' => 'user',
                'content' => $data['message'],
            ]);

            $options = array_filter(['provider' => $data['provider'] ?? null, 'model' => $data['model'] ?? null]);
            $textProvider = $this->resolver->text($user, $options);

            // Bounded context: rolling summary + last N messages (plan-aware), never the full thread.
            $context = $this->contextBuilder->build($user, $thread, $userMessage);
            $history = $context['history'];
            $history[] = ['role' => 'user', 'content' => (string) $data['message']];

            $system = 'أنت مساعد ذكاء اصطناعي داخل منصة iden، أجب عن سؤال المستخدم الأخير مباشرةً وبإيجاز ووضوح وبنفس لغته، واستخدم المحادثة السابقة كسياق فقط.';

            $result = $textProvider->complete($system, $history, $options);

            $assistantMessage = ChatMessage::create([
                'thread_id' => $thread->getKey(),
                'role' => 'assistant',
                'content' => $result->text,
                'tokens' => $result->completionTokens,
            ]);

            $thread->update(['last_message_at' => now()]);
            $this->contextBuilder->rollSummaryIfNeeded($thread, $textProvider);

            $this->logger->usage($user, 'chat_message', ['status' => 'success', 'ref_id' => $thread->getKey()]);

            return ['thread' => $thread, 'messages' => collect([$userMessage, $assistantMessage])];
        } catch (Throwable $e) {
            $this->credits->refund($user, $charge, 'chat reply failed');
            $this->logger->usage($user, 'chat_message', ['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
