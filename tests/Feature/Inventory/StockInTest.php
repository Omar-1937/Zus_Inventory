<?php

use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Modules\Inventory\Livewire\StockInForm;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('stock in creates a batch, an IN transaction for the employee and an audit log', function () {
    $staff = staff();
    $item = InventoryItem::factory()->tracksExpiry()->create(['unit' => 'L']);

    Livewire::actingAs($staff)
        ->test(StockInForm::class)
        ->set('item_id', (string) $item->item_id)
        ->set('batch_number', 'OAT-0001')
        ->set('quantity', '10.5')
        ->set('received_date', today()->toDateString())
        ->set('expiration_date', today()->addDays(14)->toDateString())
        ->set('notes', 'Delivery note 7781')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('stock-updated');

    $batch = InventoryBatch::query()->where('batch_number', 'OAT-0001')->firstOrFail();
    $transaction = StockTransaction::query()->where('batch_id', $batch->batch_id)->firstOrFail();

    expect($batch->initial_quantity)->toBe('10.500')
        ->and($batch->remaining_quantity)->toBe('10.500')
        ->and($batch->expiration_date->toDateString())->toBe(today()->addDays(14)->toDateString())
        ->and($transaction->transaction_type)->toBe(TransactionType::In)
        ->and($transaction->quantity_change)->toBe('10.500')
        ->and($transaction->user_id)->toBe($staff->user_id)
        ->and($transaction->notes)->toBe('Delivery note 7781')
        ->and($item->fresh()->usableQuantity())->toBe('10.500')
        ->and(AuditLog::query()->where('action', 'STOCK_IN')->where('record_id', $batch->batch_id)->exists())->toBeTrue();
});

test('expiry-tracked items require an expiration date', function () {
    $item = InventoryItem::factory()->tracksExpiry()->create();

    Livewire::actingAs(staff())
        ->test(StockInForm::class)
        ->set('item_id', (string) $item->item_id)
        ->set('batch_number', 'B-1')
        ->set('quantity', '5')
        ->set('expiration_date', '')
        ->call('save')
        ->assertHasErrors('expiration_date');

    expect(InventoryBatch::query()->count())->toBe(0);
});

test('non-expiring items can be received without an expiration date', function () {
    $item = InventoryItem::factory()->create(['item_name' => 'Coffee Cups', 'unit' => 'pcs']);

    $batch = app(StockService::class)->stockIn($item, ['batch_number' => 'CUP-1', 'quantity' => '500'], staff());

    expect($batch->expiration_date)->toBeNull()
        ->and($item->usableQuantity())->toBe('500.000');
});

test('stock in rejects invalid deliveries', function (array $data, string $field) {
    $item = InventoryItem::factory()->create();
    InventoryBatch::factory()->for($item, 'item')->create(['batch_number' => 'DUP-1']);

    Livewire::actingAs(staff())
        ->test(StockInForm::class)
        ->set(array_merge([
            'item_id' => (string) $item->item_id,
            'batch_number' => 'NEW-1',
            'quantity' => '5',
            'received_date' => today()->toDateString(),
            'expiration_date' => today()->addDays(10)->toDateString(),
        ], $data))
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'zero quantity' => [['quantity' => '0'], 'quantity'],
    'negative quantity' => [['quantity' => '-3'], 'quantity'],
    'non numeric quantity' => [['quantity' => 'ten'], 'quantity'],
    'already expired' => [['expiration_date' => today()->subDay()->toDateString()], 'expiration_date'],
    'future received date' => [['received_date' => today()->addDay()->toDateString()], 'received_date'],
    'duplicate batch number' => [['batch_number' => 'DUP-1'], 'batch_number'],
    'missing batch number' => [['batch_number' => ''], 'batch_number'],
]);

test('stock cannot be received for an inactive item', function () {
    $item = InventoryItem::factory()->inactive()->create();

    app(StockService::class)->stockIn($item, ['batch_number' => 'X-1', 'quantity' => '1'], staff());
})->throws(BusinessRuleException::class);
