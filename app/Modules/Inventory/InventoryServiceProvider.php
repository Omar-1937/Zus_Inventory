<?php

namespace App\Modules\Inventory;

use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Modules\Inventory\Livewire\BatchTracking;
use App\Modules\Inventory\Livewire\CategoryManager;
use App\Modules\Inventory\Livewire\InventoryTable;
use App\Modules\Inventory\Livewire\ItemForm;
use App\Modules\Inventory\Livewire\StockHistoryTable;
use App\Modules\Inventory\Livewire\StockInForm;
use App\Modules\Inventory\Livewire\StockOutForm;
use App\Modules\Inventory\Policies\CategoryPolicy;
use App\Modules\Inventory\Policies\InventoryBatchPolicy;
use App\Modules\Inventory\Policies\InventoryItemPolicy;
use App\Modules\ModuleServiceProvider;

class InventoryServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'Inventory';
    }

    protected function viewNamespace(): string
    {
        return 'inventory';
    }

    protected function livewireComponents(): array
    {
        return [
            'inventory.inventory-table' => InventoryTable::class,
            'inventory.item-form' => ItemForm::class,
            'inventory.category-manager' => CategoryManager::class,
            'inventory.stock-in-form' => StockInForm::class,
            'inventory.stock-out-form' => StockOutForm::class,
            'inventory.batch-tracking' => BatchTracking::class,
            'inventory.stock-history-table' => StockHistoryTable::class,
        ];
    }

    protected function policies(): array
    {
        return [
            InventoryItem::class => InventoryItemPolicy::class,
            InventoryBatch::class => InventoryBatchPolicy::class,
            Category::class => CategoryPolicy::class,
        ];
    }
}
