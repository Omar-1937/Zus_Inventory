<?php

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\PurchaseOrders\Livewire\CreatePurchaseOrder;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('creating an order uses supplier prices from the database and calculates totals', function () {
    $staff = staff();
    $supplier = Supplier::factory()->create();
    $oat = SupplierItem::factory()->for($supplier)->price('12.50')->create();
    $beans = SupplierItem::factory()->for($supplier)->price('68.00')->create();

    Livewire::actingAs($staff)
        ->test(CreatePurchaseOrder::class)
        ->set('supplier_id', (string) $supplier->supplier_id)
        ->set('lines', [
            ['supplier_item_id' => (string) $oat->supplier_item_id, 'quantity' => '10'],
            ['supplier_item_id' => (string) $beans->supplier_item_id, 'quantity' => '2.5'],
        ])
        ->assertSee('RM 295.00') // live preview: 125.00 + 170.00
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $order = PurchaseOrder::query()->with('items')->sole();
    $lines = $order->items->keyBy('supplier_item_id');

    expect($order->status)->toBe(PurchaseOrderStatus::Pending)
        ->and($order->created_by)->toBe($staff->user_id)
        ->and($order->total_amount)->toBe('295.00')
        ->and($lines[$oat->supplier_item_id]->unit_price)->toBe('12.50')
        ->and($lines[$oat->supplier_item_id]->subtotal)->toBe('125.00')
        ->and($lines[$beans->supplier_item_id]->quantity_ordered)->toBe('2.500')
        ->and($lines[$beans->supplier_item_id]->subtotal)->toBe('170.00');
});

test('subtotals are rounded to cents correctly', function () {
    $supplier = Supplier::factory()->create();
    $cups = SupplierItem::factory()->for($supplier)->price('0.28')->create();
    $milk = SupplierItem::factory()->for($supplier)->price('7.33')->create();

    $order = app(PurchaseOrderService::class)->create($supplier, [
        ['supplier_item_id' => $cups->supplier_item_id, 'quantity' => '1333'],   // 373.24
        ['supplier_item_id' => $milk->supplier_item_id, 'quantity' => '3.333'],  // 24.43089 -> 24.43
    ], null, staff());

    expect($order->items->firstWhere('supplier_item_id', $milk->supplier_item_id)->subtotal)->toBe('24.43')
        ->and($order->total_amount)->toBe('397.67');
});

test('prices sent by the client are ignored', function () {
    $supplier = Supplier::factory()->create();
    $supplierItem = SupplierItem::factory()->for($supplier)->price('12.50')->create();

    $order = app(PurchaseOrderService::class)->create($supplier, [
        ['supplier_item_id' => $supplierItem->supplier_item_id, 'quantity' => '10', 'unit_price' => '0.01', 'subtotal' => '0.10'],
    ], null, staff());

    expect($order->items->first()->unit_price)->toBe('12.50')
        ->and($order->total_amount)->toBe('125.00');
});

test('historical prices are preserved when the supplier price changes', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Pending, '10', '12.50');
    $supplierItem = $order->items->first()->supplierItem;

    $supplierItem->update(['unit_price' => '15.00']);

    $order->refresh()->load('items');

    expect($order->items->first()->unit_price)->toBe('12.50')
        ->and($order->total_amount)->toBe('125.00');
});

test('only items supplied by the selected supplier can be ordered', function () {
    $supplier = Supplier::factory()->create();
    $otherSuppliersItem = SupplierItem::factory()->create();

    Livewire::actingAs(staff())
        ->test(CreatePurchaseOrder::class)
        ->set('supplier_id', (string) $supplier->supplier_id)
        ->set('lines', [['supplier_item_id' => (string) $otherSuppliersItem->supplier_item_id, 'quantity' => '1']])
        ->call('save')
        ->assertHasErrors('lines.0.supplier_item_id');

    expect(fn () => app(PurchaseOrderService::class)->create($supplier, [
        ['supplier_item_id' => $otherSuppliersItem->supplier_item_id, 'quantity' => '1'],
    ], null, staff()))->toThrow(BusinessRuleException::class);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('order validation', function (array $lines, string $field) {
    $supplier = Supplier::factory()->create();
    $supplierItem = SupplierItem::factory()->for($supplier)->create();
    $lines = array_map(fn (array $line) => ['supplier_item_id' => $line[0] === 'ID' ? (string) $supplierItem->supplier_item_id : $line[0], 'quantity' => $line[1]], $lines);

    Livewire::actingAs(staff())
        ->test(CreatePurchaseOrder::class)
        ->set('supplier_id', (string) $supplier->supplier_id)
        ->set('lines', $lines)
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'no lines' => [[], 'lines'],
    'zero quantity' => [[['ID', '0']], 'lines.0.quantity'],
    'negative quantity' => [[['ID', '-5']], 'lines.0.quantity'],
    'duplicate item' => [[['ID', '1'], ['ID', '2']], 'lines.0.supplier_item_id'],
]);

test('inactive suppliers cannot receive new orders', function () {
    $supplier = Supplier::factory()->inactive()->create();
    $supplierItem = SupplierItem::factory()->for($supplier)->create();

    app(PurchaseOrderService::class)->create($supplier, [['supplier_item_id' => $supplierItem->supplier_item_id, 'quantity' => '1']], null, staff());
})->throws(BusinessRuleException::class);

test('creating an order does not change stock', function () {
    $item = itemWithStock('4');
    purchaseOrderInStatus(PurchaseOrderStatus::Approved, '10', '12.50', $item);

    expect($item->usableQuantity())->toBe('4.000')
        ->and(InventoryBatch::query()->where('item_id', $item->item_id)->count())->toBe(1);
});

test('the create form preselects the requested low stock item for a supplier that sells it', function () {
    $item = itemWithStock('4', ['item_name' => 'Oat Milk', 'unit' => 'L', 'minimum_stock' => '8']);
    $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A']);
    $offer = SupplierItem::factory()->for($supplierA)->for($item, 'item')->price('12.50')->create();

    Livewire::actingAs(staff())
        ->test(CreatePurchaseOrder::class, ['itemId' => $item->item_id])
        ->assertSee('Reordering: Oat Milk')
        ->assertSee('at least')
        ->set('supplier_id', (string) $supplierA->supplier_id)
        ->assertSet('lines', [['supplier_item_id' => (string) $offer->supplier_item_id, 'quantity' => '']])
        ->set('lines.0.quantity', '10')
        ->assertSee('RM 12.50')
        ->assertSee('RM 125.00');
});

test('purchase order pages render', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Received);
    $staff = staff();

    $this->actingAs($staff)->get(route('purchase-orders.index'))->assertOk()->assertSee($order->reference());
    $this->actingAs($staff)->get(route('purchase-orders.create'))->assertOk();
    $this->actingAs($staff)->get(route('purchase-orders.show', $order))->assertOk()->assertSee('RECEIVED');
    $this->actingAs($staff)->get(route('purchase-orders.create', ['item_id' => InventoryItem::query()->value('item_id')]))->assertOk();
});
