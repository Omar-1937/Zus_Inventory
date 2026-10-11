<?php

namespace App\Modules\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Modules\ModuleServiceProvider;
use App\Modules\PurchaseOrders\Livewire\CreatePurchaseOrder;
use App\Modules\PurchaseOrders\Livewire\PurchaseOrderTable;
use App\Modules\PurchaseOrders\Livewire\ReceivePurchaseOrder;
use App\Modules\PurchaseOrders\Policies\PurchaseOrderPolicy;

class PurchaseOrdersServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'PurchaseOrders';
    }

    protected function viewNamespace(): string
    {
        return 'purchase-orders';
    }

    protected function livewireComponents(): array
    {
        return [
            'purchase-orders.purchase-order-table' => PurchaseOrderTable::class,
            'purchase-orders.create-purchase-order' => CreatePurchaseOrder::class,
            'purchase-orders.receive-purchase-order' => ReceivePurchaseOrder::class,
        ];
    }

    protected function policies(): array
    {
        return [
            PurchaseOrder::class => PurchaseOrderPolicy::class,
        ];
    }
}
