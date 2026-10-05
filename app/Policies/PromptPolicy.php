<?php

namespace App\Policies;

use App\Models\Prompt;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PromptPolicy extends OwnedByUserPolicy
{
    /** Shared library prompts are readable by everyone, editable by their owner. */
    public function view(User $user, Model $model): bool
    {
        /** @var Prompt $model */
        return $model->is_shared || $this->owns($user, $model);
    }
}
