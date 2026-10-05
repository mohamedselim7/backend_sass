<?php

namespace App\Policies;

use App\Models\SupportThread;
use App\Models\User;

class SupportThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SupportThread $thread): bool
    {
        return $this->isAgent($user) || $thread->user_id === $user->getKey();
    }

    public function reply(User $user, SupportThread $thread): bool
    {
        if (in_array($thread->status, ['closed'], true) && ! $this->isAgent($user)) {
            return false;
        }

        return $this->view($user, $thread);
    }

    public function manage(User $user, SupportThread $thread): bool
    {
        return $this->isAgent($user);
    }

    private function isAgent(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'support']);
    }
}
