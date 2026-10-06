<?php

namespace Tests\Feature\Chat;

use App\Actions\Ai\ChatContextBuilder;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use Tests\TestCase;

class ChatContextBuilderTest extends TestCase
{
    public function test_context_keeps_the_newest_messages_not_the_oldest(): void
    {
        $user = $this->actingAsUser();
        $thread = ChatThread::create(['user_id' => $user->id, 'title' => 't', 'mode' => 'chat']);
        foreach (range(1, 60) as $i) {
            $m = ChatMessage::create(['thread_id' => $thread->id, 'role' => $i % 2 ? 'user' : 'assistant', 'content' => "msg-{$i}"]);
            $m->forceFill(['created_at' => now()->subMinutes(100 - $i)])->save();
        }

        $flat = json_encode(app(ChatContextBuilder::class)->build($user, $thread->fresh()), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('msg-60', $flat);
        $this->assertStringNotContainsString('"msg-1"', $flat);
    }
}
