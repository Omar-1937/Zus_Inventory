<?php

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Modules\Inventory\Livewire\InventoryTable;
use App\Modules\Inventory\Livewire\ItemForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can add an inventory item', function () {
    $admin = admin();
    $category = Category::factory()->create(['category_name' => 'Dairy']);

    Livewire::actingAs($admin)
        ->test(ItemForm::class)
        ->set('item_name', 'Oat Milk')
        ->set('category_id', (string) $category->category_id)
        ->set('unit', 'L')
        ->set('minimum_stock', '8')
        ->set('expiry_tracking', true)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $item = InventoryItem::query()->where('item_name', 'Oat Milk')->firstOrFail();

    expect($item->minimum_stock)->toBe('8.000')
        ->and($item->expiry_tracking)->toBeTrue()
        ->and($item->is_active)->toBeTrue()
        ->and($item->usableQuantity())->toBe('0.000')
        ->and(AuditLog::query()->where('entity_name', 'InventoryItem')->where('action', 'CREATE')->where('record_id', $item->item_id)->exists())->toBeTrue();
});

test('item validation rejects bad input', function (array $input, string $errorField) {
    $admin = admin();
    InventoryItem::factory()->create(['item_name' => 'Existing Item']);
    $category = Category::factory()->create();

    Livewire::actingAs($admin)
        ->test(ItemForm::class)
        ->set(array_merge([
            'item_name' => 'New Item',
            'category_id' => (string) $category->category_id,
            'unit' => 'kg',
            'minimum_stock' => '1',
        ], $input))
        ->call('save')
        ->assertHasErrors($errorField);
})->with([
    'missing name' => [['item_name' => ''], 'item_name'],
    'duplicate name' => [['item_name' => 'Existing Item'], 'item_name'],
    'negative minimum' => [['minimum_stock' => '-1'], 'minimum_stock'],
    'too many decimals' => [['minimum_stock' => '1.2345'], 'minimum_stock'],
    'unknown category' => [['category_id' => '999999'], 'category_id'],
    'missing unit' => [['unit' => ''], 'unit'],
]);

test('admin can edit an item', function () {
    $item = InventoryItem::factory()->create(['item_name' => 'Fresh Milk', 'minimum_stock' => '5']);

    Livewire::actingAs(admin())
        ->test(ItemForm::class, ['item' => $item])
        ->assertSet('item_name', 'Fresh Milk')
        ->set('minimum_stock', '12.5')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->minimum_stock)->toBe('12.500')
        ->and(AuditLog::query()->where('action', 'UPDATE')->where('record_id', $item->item_id)->exists())->toBeTrue();
});

test('staff cannot create or edit items', function () {
    $staff = staff();
    $item = InventoryItem::factory()->create();

    $this->actingAs($staff)->get(route('inventory.create'))->assertForbidden();
    $this->actingAs($staff)->get(route('inventory.edit', $item))->assertForbidden();

    Livewire::actingAs($staff)->test(ItemForm::class)->assertForbidden();
});

test('deactivating an item keeps its batches and history', function () {
    $item = itemWithStock('5');

    Livewire::actingAs(admin())
        ->test(InventoryTable::class)
        ->call('toggleActive', $item->item_id);

    expect($item->fresh()->is_active)->toBeFalse()
        ->and(InventoryBatch::query()->where('item_id', $item->item_id)->count())->toBe(1);
});

test('inventory list searches, filters and shows calculated quantities', function () {
    $oat = itemWithStock('4', ['item_name' => 'Oat Milk', 'minimum_stock' => '8']);
    itemWithStock('50', ['item_name' => 'Coffee Beans', 'minimum_stock' => '5']);
    // Expired stock is physical but not usable.
    InventoryBatch::factory()->for($oat, 'item')->quantity('3')->expired()->create();

    Livewire::actingAs(staff())
        ->test(InventoryTable::class)
        ->assertSee('Oat Milk')
        ->assertSee('Coffee Beans')
        ->set('search', 'oat')
        ->assertSee('Oat Milk')
        ->assertDontSee('Coffee Beans')
        ->set('search', '')
        ->set('status', 'LOW_STOCK')
        ->assertSee('Oat Milk')
        ->assertDontSee('Coffee Beans');

    $loaded = InventoryItem::query()->withStockLevels()->find($oat->item_id);

    expect($loaded->physical_quantity)->toBe('7.000')
        ->and($loaded->usable_quantity)->toBe('4.000');
});

test('inventory pages render for staff', function () {
    $item = itemWithStock('5');
    $staff = staff();

    foreach ([
        route('inventory.index'),
        route('inventory.show', $item),
        route('inventory.categories.index'),
        route('inventory.stock.index'),
        route('inventory.stock.index', ['tab' => 'out', 'item_id' => $item->item_id]),
        route('inventory.batches.index'),
    ] as $url) {
        $this->actingAs($staff)->get($url)->assertOk();
    }
});
