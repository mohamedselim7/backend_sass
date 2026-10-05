<?php

namespace App\Enums;

enum PostStatus: string
{
    case Generated = 'generated';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case DesignPending = 'design_pending';
    case DesignGenerated = 'design_generated';
    case DesignApproved = 'design_approved';
    case Ready = 'ready';
    case Published = 'published';

    /** Statuses a post may move to from the current one. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Generated => [self::Approved, self::Rejected],
            self::Approved => [self::DesignPending, self::Ready, self::Rejected],
            self::Rejected => [self::Generated],
            self::DesignPending => [self::DesignGenerated, self::Approved],
            self::DesignGenerated => [self::DesignApproved, self::DesignPending],
            self::DesignApproved => [self::Ready],
            self::Ready => [self::Published],
            self::Published => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
