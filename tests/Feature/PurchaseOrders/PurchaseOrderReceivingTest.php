<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\PurchaseOrders\Livewire\ReceivePurchaseOrder;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('receiving an ordered purchase order adds batches and stock in transactions', function () {
    $staff = staff();
    $item = itemWithStock('4', ['unit' => 'L', 'minimum_stock' => '8', 'expiry_tracking' => true]);
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Ordered, '10', '12.50', $item);
    $line = $order->items->first();

    Livewire::actingAs($staff)
        ->test(ReceivePurchaseOrder::class, ['purchaseOrder' => $order])
        ->assertSet("lines.{$line->po_item_id}.quantity", '10')
        ->set("lines.{$line->po_item_id}.batch_number", 'OAT-PO-001')
        ->set("lines.{$line->po_item_id}.expiration_date", today()->addDays(21)->toDateString())
        ->call('receive')
        ->assertHasNoErrors()
        ->assertRedirect(route('purchase-orders.show', $order));

    $order->refresh();
    $batch = InventoryBatch::query()->where('batch_number', 'OAT-PO-001')->sole();
    $transaction = StockTransaction::query()->where('batch_id', $batch->batch_id)->sole();

    expect($order->status)->toBe(PurchaseOrderStatus::Received)
        ->and($order->received_by)->toBe($staff->user_id)
        ->and($batch->po_item_id)->toBe($line->po_item_id)
        ->and($batch->initial_quantity)->toBe('10.000')
        ->and($batch->expiration_date->toDateString())->toBe(today()->addDays(21)->toDateString())
        ->and($transaction->transaction_type)->toBe(TransactionType::In)
        ->and($transaction->user_id)->toBe($staff->user_id)
        ->and($item->usableQuantity())->toBe('14.000');
});

test('only ordered purchase orders can be received', function (PurchaseOrderStatus $status) {
    $order = purchaseOrderInStatus($status);

    expect(fn () => app(PurchaseOrderService::class)->receive($order, receiveLines($order), staff()))
        ->toThrow(BusinessRuleException::class);

    expect(InventoryBatch::query()->whereNotNull('po_item_id')->count())->toBe($status === PurchaseOrderStatus::Received ? 1 : 0);
})->with([PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Received]);

test('an order cannot be received twice', function () {
    $item = InventoryItem::factory()->tracksExpiry()->create();
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Ordered, '10', '12.50', $item);
    $service = app(PurchaseOrderService::class);

    $service->receive($order, receiveLines($order, 'FIRST'), staff());

    expect(fn () => $service->receive($order, receiveLines($order, 'SECOND'), staff()))
        ->toThrow(BusinessRuleException::class, 'already been received');

    expect($item->usableQuantity())->toBe('10.000')
        ->and(InventoryBatch::query()->count())->toBe(1);

    // The receive page also refuses.
    $this->actingAs(staff())->get(route('purchase-orders.receive', $order))
        ->assertRedirect(route('purchase-orders.show', $order));
});

test('a failure on any line rolls back the whole receipt', function () {
    $supplier = Supplier::factory()->create();
    $milk = InventoryItem::factory()->tracksExpiry()->create();
    $cups = InventoryItem::factory()->create();
    $milkOffer = SupplierItem::factory()->for($supplier)->for($milk, 'item')->create();
    $cupsOffer = SupplierItem::factory()->for($supplier)->for($cups, 'item')->create();
    // Batch number already used for cups -> the second line will fail.
    InventoryBatch::factory()->for($cups, 'item')->create(['batch_number' => 'TAKEN']);

    $service = app(PurchaseOrderService::class);
    $admin = admin();
    $order = $service->create($supplier, [
        ['supplier_item_id' => $milkOffer->supplier_item_id, 'quantity' => '5'],
        ['supplier_item_id' => $cupsOffer->supplier_item_id, 'quantity' => '100'],
    ], null, staff());
    $service->approve($order, $admin);
    $service->markOrdered($order, $admin);
    $order->load('items');

    $lines = receiveLines($order);
    $lines[$order->items->firstWhere('supplier_item_id', $cupsOffer->supplier_item_id)->po_item_id]['batch_number'] = 'TAKEN';

    expect(fn () => $service->receive($order, $lines, staff()))->toThrow(BusinessRuleException::class);

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and(InventoryBatch::query()->whereNotNull('po_item_id')->count())->toBe(0)
        ->and(StockTransaction::query()->count())->toBe(0)
        ->and($milk->usableQuantity())->toBe('0.000');
});

test('full-order receiving requires the exact ordered quantity and every line', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Ordered, '10');
    $line = $order->items->first();

    Livewire::actingAs(staff())
        ->test(ReceivePurchaseOrder::class, ['purchaseOrder' => $order])
        ->set("lines.{$line->po_item_id}.quantity", '9')
        ->set("lines.{$line->po_item_id}.expiration_date", today()->addDays(10)->toDateString())
        ->call('receive')
        ->assertHasErrors("lines.{$line->po_item_id}.quantity");

    expect(fn () => app(PurchaseOrderService::class)->receive($order, [], staff()))
        ->toThrow(BusinessRuleException::class, 'full-order receiving');

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Ordered);
});

test('expiry-tracked items need an expiration date when received', function () {
    $item = InventoryItem::factory()->tracksExpiry()->create();
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Ordered, '10', '12.50', $item);
    $line = $order->items->first();

    Livewire::actingAs(staff())
        ->test(ReceivePurchaseOrder::class, ['purchaseOrder' => $order])
        ->set("lines.{$line->po_item_id}.expiration_date", '')
        ->call('receive')
        ->assertHasErrors("lines.{$line->po_item_id}.expiration_date");

    expect(PurchaseOrder::query()->find($order->purchase_order_id)->status)->toBe(PurchaseOrderStatus::Ordered);
});
