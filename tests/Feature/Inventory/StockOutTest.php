<?php

use App\Enums\TransactionType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Livewire\StockOutForm;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('stock out deducts FEFO: earliest expiry first, non-expiring last', function () {
    $item = InventoryItem::factory()->create();
    $noExpiry = InventoryBatch::factory()->for($item, 'item')->quantity('10')->nonExpiring()->create(['batch_number' => 'NOEXP']);
    $later = InventoryBatch::factory()->for($item, 'item')->quantity('5')->expiresInDays(20)->create(['batch_number' => 'LATER']);
    $soonest = InventoryBatch::factory()->for($item, 'item')->quantity('3')->expiresInDays(2)->create(['batch_number' => 'SOONEST']);

    $transactions = app(StockService::class)->stockOut($item, '9.5', 'Used in beverage preparation', null, staff());

    expect($soonest->fresh()->remaining_quantity)->toBe('0.000')
        ->and($later->fresh()->remaining_quantity)->toBe('0.000')
        ->and($noExpiry->fresh()->remaining_quantity)->toBe('8.500')
        ->and($transactions)->toHaveCount(3)
        ->and($transactions->pluck('batch_id')->all())->toBe([$soonest->batch_id, $later->batch_id, $noExpiry->batch_id])
        ->and($transactions->pluck('quantity_change')->all())->toBe(['-3.000', '-5.000', '-1.500'])
        ->and($transactions->every(fn (StockTransaction $transaction) => $transaction->transaction_type === TransactionType::Out))->toBeTrue()
        ->and($item->usableQuantity())->toBe('8.500');
});

test('expired stock is never consumed', function () {
    $item = InventoryItem::factory()->create();
    $expired = InventoryBatch::factory()->for($item, 'item')->quantity('20')->expired()->create();
    $good = InventoryBatch::factory()->for($item, 'item')->quantity('5')->expiresInDays(10)->create();

    app(StockService::class)->stockOut($item, '4', 'Usage', null, staff());

    expect($expired->fresh()->remaining_quantity)->toBe('20.000')
        ->and($good->fresh()->remaining_quantity)->toBe('1.000');
});

test('insufficient usable stock is rejected and nothing changes', function () {
    $item = InventoryItem::factory()->create();
    InventoryBatch::factory()->for($item, 'item')->quantity('100')->expired()->create();
    $good = InventoryBatch::factory()->for($item, 'item')->quantity('3')->create();

    expect(fn () => app(StockService::class)->stockOut($item, '3.001', 'Usage', null, staff()))
        ->toThrow(InsufficientStockException::class);

    expect($good->fresh()->remaining_quantity)->toBe('3.000')
        ->and(StockTransaction::query()->count())->toBe(0);
});

test('the stock out form shows the insufficient stock error', function () {
    $item = itemWithStock('2', ['unit' => 'L']);

    Livewire::actingAs(staff())
        ->test(StockOutForm::class)
        ->set('item_id', (string) $item->item_id)
        ->set('quantity', '5')
        ->set('reason', 'Used in beverage preparation')
        ->call('save')
        ->assertHasErrors('quantity')
        ->assertDispatched('toast', type: 'error');

    expect($item->usableQuantity())->toBe('2.000');
});

test('the stock out form records usage and validates input', function () {
    $staff = staff();
    $item = itemWithStock('10');

    Livewire::actingAs($staff)
        ->test(StockOutForm::class)
        ->set('item_id', (string) $item->item_id)
        ->set('quantity', '0')
        ->call('save')
        ->assertHasErrors('quantity')
        ->set('quantity', '2.25')
        ->set('reason', '')
        ->call('save')
        ->assertHasErrors('reason')
        ->set('reason', 'Spillage / wastage')
        ->set('notes', 'Dropped carton')
        ->call('save')
        ->assertHasNoErrors();

    $transaction = StockTransaction::query()->sole();

    expect($item->usableQuantity())->toBe('7.750')
        ->and($transaction->reason)->toBe('Spillage / wastage')
        ->and($transaction->user_id)->toBe($staff->user_id);
});

test('stock never goes negative even at the database level', function () {
    $batch = InventoryBatch::factory()->quantity('1')->create();

    expect(fn () => $batch->forceFill(['remaining_quantity' => '-1'])->save())
        ->toThrow(QueryException::class);
});
