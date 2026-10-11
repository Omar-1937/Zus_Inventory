<?php

namespace App\Modules\Suppliers\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function toggleActive(User $user, Supplier $supplier): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Assign products to a supplier and change their unit prices.
     */
    public function managePrices(User $user, ?Supplier $supplier = null): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}
