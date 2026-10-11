<?php

namespace App\Modules\Inventory\Policies;

use App\Models\InventoryItem;
use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, InventoryItem $item): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, InventoryItem $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Deactivate / reactivate (items are never hard-deleted).
     */
    public function toggleActive(User $user, InventoryItem $item): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Stock In / Stock Out are part of daily work for Admin and Staff.
     */
    public function recordMovement(User $user): bool
    {
        return $user->is_active;
    }
}
