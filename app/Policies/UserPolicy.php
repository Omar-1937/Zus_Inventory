<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Admins cannot deactivate or demote themselves, which prevents locking
     * every administrator out of the system.
     */
    public function toggleActive(User $user, User $model): bool
    {
        return $user->is_active && $user->isAdmin() && $user->user_id !== $model->user_id;
    }
}
