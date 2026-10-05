<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for records that belong to one user. Ownership is read from the
 * model, never from the request, so a forged id cannot grant access.
 */
abstract class OwnedByUserPolicy
{
    use HandlesAuthorization;

    protected string $ownerColumn = 'user_id';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model);
    }

    protected function owns(User $user, Model $model): bool
    {
        return $model->getAttribute($this->ownerColumn) === $user->getKey();
    }
}
