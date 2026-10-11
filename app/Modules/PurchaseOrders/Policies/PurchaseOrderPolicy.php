<?php

namespace App\Modules\PurchaseOrders\Policies;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;

/**
 * Role permissions for purchase orders. Whether a status change is valid at
 * all (e.g. PENDING -> RECEIVED) is enforced separately by the service.
 */
class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function markOrdered(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function receive(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->is_active;
    }

    /**
     * Admins may cancel any open order; staff may cancel their own order
     * while it is still awaiting approval.
     */
    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $purchaseOrder->created_by === $user->user_id
            && $purchaseOrder->status === PurchaseOrderStatus::Pending;
    }
}
