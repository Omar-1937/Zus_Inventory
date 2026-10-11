<?php

namespace App\Modules\Suppliers\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Decimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Manages which items a supplier provides and at what unit price.
 * Price changes only affect future purchase orders: existing order lines keep
 * the price that was copied onto them when they were created.
 */
class SupplierPricingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function assignItem(Supplier $supplier, InventoryItem $item, string|int|float $unitPrice, User $user): SupplierItem
    {
        $price = $this->validPrice($unitPrice);

        try {
            return DB::transaction(function () use ($supplier, $item, $price, $user): SupplierItem {
                $exists = SupplierItem::query()
                    ->where('supplier_id', $supplier->supplier_id)
                    ->where('item_id', $item->item_id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw new BusinessRuleException("{$supplier->supplier_name} already supplies {$item->item_name}. Update the existing price instead.");
                }

                $supplierItem = SupplierItem::query()->create([
                    'supplier_id' => $supplier->supplier_id,
                    'item_id' => $item->item_id,
                    'unit_price' => $price,
                    'is_active' => true,
                ]);

                $this->audit->log('ASSIGN_ITEM', $supplierItem, details: [
                    'supplier' => $supplier->supplier_name,
                    'item' => $item->item_name,
                    'unit_price' => $price,
                ], user: $user);

                return $supplierItem;
            });
        } catch (UniqueConstraintViolationException) {
            // Two admins assigning the same pair at the same moment: the unique index wins.
            throw new BusinessRuleException("{$supplier->supplier_name} already supplies {$item->item_name}. Update the existing price instead.");
        }
    }

    public function updatePrice(SupplierItem $supplierItem, string|int|float $unitPrice, User $user): SupplierItem
    {
        $price = $this->validPrice($unitPrice);

        return DB::transaction(function () use ($supplierItem, $price, $user): SupplierItem {
            /** @var SupplierItem $supplierItem */
            $supplierItem = SupplierItem::query()->with(['supplier', 'item'])->lockForUpdate()->findOrFail($supplierItem->supplier_item_id);
            $previous = $supplierItem->unit_price;

            if (Decimal::compare($previous, $price) === 0) {
                return $supplierItem;
            }

            $supplierItem->unit_price = $price;
            $supplierItem->save();

            $this->audit->log('UPDATE_PRICE', $supplierItem, details: [
                'supplier' => $supplierItem->supplier->supplier_name,
                'item' => $supplierItem->item->item_name,
                'previous_price' => $previous,
                'new_price' => $price,
            ], user: $user);

            return $supplierItem;
        });
    }

    public function setActive(SupplierItem $supplierItem, bool $active, User $user): SupplierItem
    {
        return DB::transaction(function () use ($supplierItem, $active, $user): SupplierItem {
            $supplierItem->update(['is_active' => $active]);

            $this->audit->log($active ? 'ACTIVATE' : 'DEACTIVATE', $supplierItem, details: [
                'supplier_id' => $supplierItem->supplier_id,
                'item_id' => $supplierItem->item_id,
            ], user: $user);

            return $supplierItem;
        });
    }

    /**
     * Removes an assignment that has never been ordered. Assignments with
     * purchase history are deactivated instead so historical orders stay intact.
     */
    public function remove(SupplierItem $supplierItem, User $user): bool
    {
        return DB::transaction(function () use ($supplierItem, $user): bool {
            if ($supplierItem->purchaseOrderItems()->exists()) {
                $this->setActive($supplierItem, false, $user);

                return false;
            }

            $this->audit->log('REMOVE_ITEM', $supplierItem, details: [
                'supplier_id' => $supplierItem->supplier_id,
                'item_id' => $supplierItem->item_id,
            ], user: $user);

            $supplierItem->delete();

            return true;
        });
    }

    private function validPrice(string|int|float $unitPrice): string
    {
        $price = Decimal::money($unitPrice);

        if (! Decimal::isPositive($price)) {
            throw new BusinessRuleException('Unit price must be greater than RM 0.00.');
        }

        return $price;
    }
}
