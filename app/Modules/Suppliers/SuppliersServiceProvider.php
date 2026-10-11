<?php

namespace App\Modules\Suppliers;

use App\Models\Supplier;
use App\Modules\ModuleServiceProvider;
use App\Modules\Suppliers\Livewire\SupplierForm;
use App\Modules\Suppliers\Livewire\SupplierProducts;
use App\Modules\Suppliers\Livewire\SupplierTable;
use App\Modules\Suppliers\Policies\SupplierPolicy;

class SuppliersServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Suppliers';
    }

    protected function viewNamespace(): string
    {
        return 'suppliers';
    }

    protected function livewireComponents(): array
    {
        return [
            'suppliers.supplier-table' => SupplierTable::class,
            'suppliers.supplier-form' => SupplierForm::class,
            'suppliers.supplier-products' => SupplierProducts::class,
        ];
    }

    protected function policies(): array
    {
        return [
            Supplier::class => SupplierPolicy::class,
        ];
    }
}
