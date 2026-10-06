<?php

namespace Tests\Feature\Chat;

use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\User;
use Tests\TestCase;

class ChatMessagesPaginationTest extends TestCase
{
    public function test_messages_are_cursor_paginated_without_gaps_or_duplicates(): void
    {
        $user = $this->actingAsUser();
        $thread = ChatThread::create(['user_id' => $user->id, 'title' => 't', 'mode' => 'chat']);
        foreach (range(1, 7) as $i) {
            $m = ChatMessage::create(['thread_id' => $thread->id, 'role' => 'user', 'content' => "m{$i}"]);
            $m->forceFill(['created_at' => now()->subMinutes(10 - $i)])->save();
        }

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $res = $this->getJson("/api/v1/ai/chat/threads/{$thread->id}/messages?per_page=3".($cursor ? "&cursor={$cursor}" : ''))->assertOk();
            $seen = array_merge(array_column($res->json('data'), 'content'), $seen);
            $cursor = $res->json('meta.has_more') ? $res->json('meta.next_cursor') : null;
            $pages++;
        } while ($cursor && $pages < 5);

        $this->assertSame(['m1', 'm2', 'm3', 'm4', 'm5', 'm6', 'm7'], $seen);
        $this->assertSame(3, $pages);
    }

    public function test_other_users_cannot_read_thread_messages(): void
    {
        $owner = User::factory()->create();
        $thread = ChatThread::create(['user_id' => $owner->id, 'title' => 't', 'mode' => 'chat']);
        $this->actingAsUser();
        $this->getJson("/api/v1/ai/chat/threads/{$thread->id}/messages")->assertNotFound();
    }
}
