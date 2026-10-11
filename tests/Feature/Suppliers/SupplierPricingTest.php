<?php

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\Suppliers\Livewire\SupplierProducts;
use App\Modules\Suppliers\Services\SupplierPricingService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can assign an item to a supplier with a unit price', function () {
    $supplier = Supplier::factory()->create();
    $item = InventoryItem::factory()->create(['item_name' => 'Oat Milk']);

    Livewire::actingAs(admin())
        ->test(SupplierProducts::class, ['supplier' => $supplier])
        ->call('openAssign')
        ->set('item_id', (string) $item->item_id)
        ->set('unit_price', '12.50')
        ->call('assign')
        ->assertHasNoErrors();

    $supplierItem = SupplierItem::query()->sole();

    expect($supplierItem->supplier_id)->toBe($supplier->supplier_id)
        ->and($supplierItem->item_id)->toBe($item->item_id)
        ->and($supplierItem->unit_price)->toBe('12.50');
});

test('one item can have several suppliers and one supplier several items', function () {
    $item = InventoryItem::factory()->create();
    [$a, $b] = Supplier::factory()->count(2)->create();
    $other = InventoryItem::factory()->create();
    $service = app(SupplierPricingService::class);
    $admin = admin();

    $service->assignItem($a, $item, '12.50', $admin);
    $service->assignItem($b, $item, '13.90', $admin);
    $service->assignItem($a, $other, '7.20', $admin);

    expect($item->suppliers()->count())->toBe(2)
        ->and($a->items()->count())->toBe(2)
        ->and($item->suppliers()->where('suppliers.supplier_id', $b->supplier_id)->first()->pivot->unit_price)->toBe('13.90');
});

test('duplicate supplier-item pairs are rejected', function () {
    $supplierItem = SupplierItem::factory()->create();

    Livewire::actingAs(admin())
        ->test(SupplierProducts::class, ['supplier' => $supplierItem->supplier])
        ->call('openAssign')
        ->set('item_id', (string) $supplierItem->item_id)
        ->set('unit_price', '5')
        ->call('assign')
        ->assertHasErrors(['item_id' => 'unique']);

    expect(fn () => app(SupplierPricingService::class)->assignItem($supplierItem->supplier, $supplierItem->item, '5', admin()))
        ->toThrow(BusinessRuleException::class);

    // The database unique index is the final guard.
    expect(fn () => SupplierItem::query()->create([
        'supplier_id' => $supplierItem->supplier_id,
        'item_id' => $supplierItem->item_id,
        'unit_price' => '1.00',
    ]))->toThrow(UniqueConstraintViolationException::class);

    expect(SupplierItem::query()->count())->toBe(1);
});

test('admin can update a unit price', function () {
    $supplierItem = SupplierItem::factory()->price('12.50')->create();

    Livewire::actingAs(admin())
        ->test(SupplierProducts::class)
        ->call('startEdit', $supplierItem->supplier_item_id)
        ->assertSet('editPrice', '12.50')
        ->set('editPrice', '13.75')
        ->call('savePrice')
        ->assertHasNoErrors();

    expect($supplierItem->fresh()->unit_price)->toBe('13.75');
});

test('unit prices must be positive with at most two decimals', function (string $price) {
    $supplierItem = SupplierItem::factory()->price('12.50')->create();

    Livewire::actingAs(admin())
        ->test(SupplierProducts::class)
        ->call('startEdit', $supplierItem->supplier_item_id)
        ->set('editPrice', $price)
        ->call('savePrice')
        ->assertHasErrors('editPrice');

    expect($supplierItem->fresh()->unit_price)->toBe('12.50');
})->with(['0', '-1', '1.234', 'abc']);

test('staff cannot manage prices', function () {
    $supplierItem = SupplierItem::factory()->create();

    Livewire::actingAs(staff())
        ->test(SupplierProducts::class)
        ->assertSee($supplierItem->item->item_name)
        ->call('startEdit', $supplierItem->supplier_item_id)
        ->assertForbidden();
});

test('removing a product that was ordered deactivates it instead of deleting history', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Pending);
    $supplierItem = $order->items->first()->supplierItem;

    $deleted = app(SupplierPricingService::class)->remove($supplierItem, admin());

    expect($deleted)->toBeFalse()
        ->and($supplierItem->fresh()->is_active)->toBeFalse();

    $unused = SupplierItem::factory()->create();
    expect(app(SupplierPricingService::class)->remove($unused, admin()))->toBeTrue()
        ->and(SupplierItem::query()->find($unused->supplier_item_id))->toBeNull();
});
