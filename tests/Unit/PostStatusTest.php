<?php

namespace Tests\Unit;

use App\Enums\PostStatus;
use PHPUnit\Framework\TestCase;

class PostStatusTest extends TestCase
{
    public function test_generated_can_only_be_approved_or_rejected(): void
    {
        $this->assertTrue(PostStatus::Generated->canTransitionTo(PostStatus::Approved));
        $this->assertTrue(PostStatus::Generated->canTransitionTo(PostStatus::Rejected));
        $this->assertFalse(PostStatus::Generated->canTransitionTo(PostStatus::Published));
    }

    public function test_published_is_terminal(): void
    {
        $this->assertSame([], PostStatus::Published->allowedTransitions());
    }
}
