<?php

use App\Enums\BatchStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\TransactionType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\StockTransaction;
use App\Modules\DashboardReports\Services\StockAlertService;
use App\Modules\Inventory\Livewire\BatchTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a batch moves from good to expiring soon to expired as days pass', function () {
    $batch = InventoryBatch::factory()->quantity('5')->create(['expiration_date' => today()->addDays(5)]);

    expect($batch->status())->toBe(BatchStatus::Good);

    $this->travelTo(today()->addDays(2));
    expect($batch->fresh()->status())->toBe(BatchStatus::ExpiringSoon);

    $this->travelTo(today()->addDays(4));
    expect($batch->fresh()->status())->toBe(BatchStatus::Expired)
        ->and($batch->item->fresh()->usableQuantity())->toBe('0.000')
        ->and($batch->item->fresh()->physicalQuantity())->toBe('5.000');
});

test('staff can dispose of an expired batch', function () {
    $staff = staff();
    $item = InventoryItem::factory()->minimum('1')->create();
    $batch = InventoryBatch::factory()->for($item, 'item')->quantity('4')->expired()->create();
    app(StockAlertService::class)->refreshAll();

    expect(Notification::query()->active()->where('notification_type', NotificationType::Expired)->count())->toBe(1);

    Livewire::actingAs($staff)
        ->test(BatchTracking::class)
        ->call('openDispose', $batch->batch_id)
        ->assertSet('quantity', '4')
        ->assertSet('reason', 'Expired stock disposal')
        ->call('dispose')
        ->assertHasNoErrors();

    $transaction = StockTransaction::query()->sole();

    expect($batch->fresh()->remaining_quantity)->toBe('0.000')
        ->and($batch->fresh()->status())->toBe(BatchStatus::Depleted)
        ->and($transaction->transaction_type)->toBe(TransactionType::Disposal)
        ->and($transaction->quantity_change)->toBe('-4.000')
        ->and($transaction->user_id)->toBe($staff->user_id)
        ->and(Notification::query()->where('notification_type', NotificationType::Expired)->sole()->status)->toBe(NotificationStatus::Resolved);
});

test('cannot dispose of more than the batch holds', function () {
    $batch = InventoryBatch::factory()->quantity('2')->expired()->create();

    Livewire::actingAs(staff())
        ->test(BatchTracking::class)
        ->call('openDispose', $batch->batch_id)
        ->set('quantity', '3')
        ->call('dispose')
        ->assertHasErrors('quantity');

    expect($batch->fresh()->remaining_quantity)->toBe('2.000');
});

test('only admins can adjust batch quantities', function () {
    $batch = InventoryBatch::factory()->quantity('10')->create();

    Livewire::actingAs(staff())
        ->test(BatchTracking::class)
        ->call('openAdjust', $batch->batch_id)
        ->assertForbidden();

    Livewire::actingAs(admin())
        ->test(BatchTracking::class)
        ->call('openAdjust', $batch->batch_id)
        ->set('quantity', '9.5')
        ->set('reason', 'Physical stock count')
        ->call('adjust')
        ->assertHasNoErrors();

    expect($batch->fresh()->remaining_quantity)->toBe('9.500')
        ->and(StockTransaction::query()->sole()->quantity_change)->toBe('-0.500');
});

test('the batch tracking page filters by expiry status', function () {
    $item = InventoryItem::factory()->create(['item_name' => 'Fresh Milk']);
    InventoryBatch::factory()->for($item, 'item')->expired()->create(['batch_number' => 'OLD-1']);
    InventoryBatch::factory()->for($item, 'item')->expiresInDays(2)->create(['batch_number' => 'SOON-1']);
    InventoryBatch::factory()->for($item, 'item')->expiresInDays(40)->create(['batch_number' => 'GOOD-1']);

    Livewire::actingAs(staff())
        ->test(BatchTracking::class)
        ->set('status', 'EXPIRED')
        ->assertSee('OLD-1')->assertDontSee('SOON-1')->assertDontSee('GOOD-1')
        ->set('status', 'EXPIRING_SOON')
        ->assertSee('SOON-1')->assertDontSee('OLD-1')
        ->set('status', 'GOOD')
        ->assertSee('GOOD-1')->assertDontSee('SOON-1');
});

afterEach(function () {
    Carbon::setTestNow();
});
