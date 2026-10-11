<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Every test uses Tests\TestCase, which refuses to run unless the database is
| the dedicated test database (zus_coffee_inventory_shadow). Feature test
| files choose their own database trait (RefreshDatabase, or
| DatabaseTruncation for the multi-connection concurrency tests).
*/

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function staff(array $attributes = []): User
{
    return User::factory()->staff()->create($attributes);
}

/**
 * An item with one usable batch of the given quantity.
 */
function itemWithStock(string|int $quantity, array $itemAttributes = [], array $batchAttributes = []): InventoryItem
{
    $item = InventoryItem::factory()->create($itemAttributes);

    InventoryBatch::factory()
        ->for($item, 'item')
        ->quantity($quantity)
        ->create($batchAttributes);

    return $item;
}

/**
 * A purchase order for one line, advanced to the requested status through the real service.
 */
function purchaseOrderInStatus(PurchaseOrderStatus $status, string $quantity = '10', string $price = '12.50', ?InventoryItem $item = null): PurchaseOrder
{
    $item ??= InventoryItem::factory()->tracksExpiry()->create();
    $supplier = Supplier::factory()->create();
    $supplierItem = SupplierItem::factory()->for($supplier)->for($item, 'item')->price($price)->create();

    $service = app(PurchaseOrderService::class);
    $creator = User::factory()->staff()->create();
    $approver = User::factory()->admin()->create();

    $order = $service->create($supplier, [['supplier_item_id' => $supplierItem->supplier_item_id, 'quantity' => $quantity]], null, $creator);

    match ($status) {
        PurchaseOrderStatus::Pending => null,
        PurchaseOrderStatus::Approved => $service->approve($order, $approver),
        PurchaseOrderStatus::Ordered => [$service->approve($order, $approver), $service->markOrdered($order, $approver)],
        PurchaseOrderStatus::Received => [
            $service->approve($order, $approver),
            $service->markOrdered($order, $approver),
            $service->receive($order, receiveLines($order->fresh('items')), $creator),
        ],
        PurchaseOrderStatus::Cancelled => $service->cancel($order, $approver),
    };

    return $order->fresh(['items.supplierItem.item', 'supplier']);
}

/**
 * Receive payload matching the order exactly (full-order receiving).
 *
 * @return array<int, array{batch_number: string, quantity: string, expiration_date: string}>
 */
function receiveLines(PurchaseOrder $order, string $batchPrefix = 'RCV'): array
{
    return $order->items->mapWithKeys(fn ($line): array => [
        $line->po_item_id => [
            'batch_number' => $batchPrefix.'-'.$line->po_item_id,
            'quantity' => (string) $line->quantity_ordered,
            'expiration_date' => today()->addDays(30)->toDateString(),
        ],
    ])->all();
}
