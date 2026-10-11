<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Modules\DashboardReports\Livewire\NotificationBell;
use App\Modules\DashboardReports\Livewire\NotificationCenter;
use App\Modules\DashboardReports\Services\StockAlertService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('a stock out that drops below minimum raises a low stock alert', function () {
    $item = itemWithStock('10', ['item_name' => 'Oat Milk', 'unit' => 'L', 'minimum_stock' => '8']);

    app(StockService::class)->stockOut($item, '6', 'Usage', null, staff());

    $alert = Notification::query()->sole();

    expect($alert->notification_type)->toBe(NotificationType::LowStock)
        ->and($alert->status)->toBe(NotificationStatus::Unread)
        ->and($alert->item_id)->toBe($item->item_id)
        ->and($alert->message)->toContain('Oat Milk')->toContain('4 L');
});

test('alerts are not duplicated', function () {
    $item = itemWithStock('10', ['minimum_stock' => '8']);
    $service = app(StockService::class);

    $service->stockOut($item, '3', 'Usage', null, staff());
    $service->stockOut($item, '1', 'Usage', null, staff());
    app(StockAlertService::class)->refreshAll();
    app(StockAlertService::class)->refreshAll();

    expect(Notification::query()->where('notification_type', NotificationType::LowStock)->count())->toBe(1)
        ->and(Notification::query()->sole()->message)->toContain('6');

    // The database itself refuses a second active alert for the same item.
    expect(fn () => Notification::query()->create([
        'item_id' => $item->item_id,
        'notification_type' => NotificationType::LowStock,
        'message' => 'duplicate',
        'status' => NotificationStatus::Read,
    ]))->toThrow(UniqueConstraintViolationException::class);
});

test('low stock alerts resolve automatically when stock is replenished', function () {
    $item = itemWithStock('4', ['minimum_stock' => '8']);
    app(StockAlertService::class)->refreshAll();
    expect(Notification::query()->active()->count())->toBe(1);

    app(StockService::class)->stockIn($item, ['batch_number' => 'REPL-1', 'quantity' => '10', 'expiration_date' => today()->addDays(10)->toDateString()], staff());

    $alert = Notification::query()->sole();

    expect($alert->status)->toBe(NotificationStatus::Resolved)
        ->and($alert->resolved_at)->not->toBeNull();

    // A new shortage later creates a fresh alert (resolved ones do not block it).
    app(StockService::class)->stockOut($item, '10', 'Usage', null, staff());
    expect(Notification::query()->active()->count())->toBe(1)
        ->and(Notification::query()->count())->toBe(2);
});

test('expiry alerts follow the calendar via the scheduled command', function () {
    $item = InventoryItem::factory()->minimum('0')->create(['item_name' => 'Fresh Milk']);
    $batch = InventoryBatch::factory()->for($item, 'item')->quantity('6')->create(['expiration_date' => today()->addDays(5), 'batch_number' => 'MILK-9']);

    Artisan::call('inventory:refresh-alerts');
    expect(Notification::query()->count())->toBe(0);

    $this->travel(2)->days();
    Artisan::call('inventory:refresh-alerts');
    $soon = Notification::query()->sole();
    expect($soon->notification_type)->toBe(NotificationType::ExpiringSoon)
        ->and($soon->batch_id)->toBe($batch->batch_id)
        ->and($soon->message)->toContain('MILK-9');

    $this->travel(4)->days();
    Artisan::call('inventory:refresh-alerts');

    expect($soon->fresh()->status)->toBe(NotificationStatus::Resolved)
        ->and(Notification::query()->active()->sole()->notification_type)->toBe(NotificationType::Expired);
});

test('the scheduler runs the alert refresh', function () {
    $events = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command);

    expect($events->filter(fn ($command) => str_contains((string) $command, 'inventory:refresh-alerts')))->not->toBeEmpty();
});

test('employees can mark alerts as read and open the affected item', function () {
    $item = itemWithStock('1', ['minimum_stock' => '8']);
    app(StockAlertService::class)->refreshAll();
    $alert = Notification::query()->sole();
    $staff = staff();

    Livewire::actingAs($staff)->test(NotificationBell::class)->assertSee('1 unread');

    Livewire::actingAs($staff)
        ->test(NotificationCenter::class)
        ->assertSee($item->item_name)
        ->assertSee('Create Purchase Order')
        ->call('markRead', $alert->notification_id)
        ->assertDispatched('notifications-updated');

    expect($alert->fresh()->status)->toBe(NotificationStatus::Read);

    $this->actingAs($staff)->get(route('notifications.open', $alert))->assertRedirect(route('inventory.show', $item->item_id));
    $this->actingAs($staff)->get(route('notifications.index'))->assertOk();
});

test('mark all as read', function () {
    itemWithStock('1', ['minimum_stock' => '8']);
    itemWithStock('1', ['minimum_stock' => '8']);
    app(StockAlertService::class)->refreshAll();

    Livewire::actingAs(staff())->test(NotificationCenter::class)->call('markAllRead');

    expect(Notification::query()->unread()->count())->toBe(0)
        ->and(Notification::query()->where('status', NotificationStatus::Read)->count())->toBe(2);
});

test('alerts never create purchase orders automatically', function () {
    itemWithStock('0.5', ['minimum_stock' => '8']);
    app(StockAlertService::class)->refreshAll();

    expect(PurchaseOrder::query()->count())->toBe(0);
});
