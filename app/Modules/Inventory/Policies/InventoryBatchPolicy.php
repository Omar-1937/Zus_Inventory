<?php

namespace App\Modules\Inventory\Policies;

use App\Models\InventoryBatch;
use App\Models\User;

class InventoryBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Staff may write off expired or damaged stock; the action is audit logged.
     */
    public function dispose(User $user, InventoryBatch $batch): bool
    {
        return $user->is_active;
    }

    /**
     * Stock-count corrections can increase stock, so they are Admin only.
     */
    public function adjust(User $user, InventoryBatch $batch): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
