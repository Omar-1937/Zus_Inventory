<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Modules\DashboardReports\Livewire\Dashboard;
use App\Modules\DashboardReports\Services\DashboardService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard statistics are calculated from the database', function () {
    itemWithStock('4', ['item_name' => 'Oat Milk', 'minimum_stock' => '8']);          // low stock
    itemWithStock('50', ['minimum_stock' => '5']);                                      // fine
    $milk = itemWithStock('20', ['minimum_stock' => '5']);                              // fine
    InventoryBatch::factory()->for($milk, 'item')->quantity('2')->expiresInDays(1)->create();   // expiring soon
    InventoryBatch::factory()->for($milk, 'item')->quantity('2')->expired()->create();          // expired
    InventoryBatch::factory()->for($milk, 'item')->quantity('2')->expired()->depleted()->create(); // expired but empty: not counted
    InventoryItem::factory()->inactive()->create();                                     // not counted

    purchaseOrderInStatus(PurchaseOrderStatus::Pending);
    purchaseOrderInStatus(PurchaseOrderStatus::Pending);
    purchaseOrderInStatus(PurchaseOrderStatus::Approved);
    Supplier::factory()->inactive()->create();

    // 3 active items above + 3 items created by the purchase order helper (no stock => low stock).
    expect(app(DashboardService::class)->summary())->toBe([
        'total_items' => 6,
        'low_stock_items' => 4,
        'expiring_soon_batches' => 1,
        'expired_batches' => 1,
        'pending_purchase_orders' => 2,
        'active_suppliers' => 3,
    ]);
});

test('dashboard works with a completely empty database', function () {
    $this->actingAs(staff())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Total Inventory Items')
        ->assertSee('All items are above minimum stock');

    expect(app(DashboardService::class)->summary())->toBe([
        'total_items' => 0,
        'low_stock_items' => 0,
        'expiring_soon_batches' => 0,
        'expired_batches' => 0,
        'pending_purchase_orders' => 0,
        'active_suppliers' => 0,
    ]);
});

test('dashboard charts use real data', function () {
    $item = itemWithStock('10');
    app(StockService::class)->stockOut($item, '1', 'Usage', null, staff());
    purchaseOrderInStatus(PurchaseOrderStatus::Received);

    $dashboard = app(DashboardService::class);
    $trend = collect($dashboard->movementTrend(7)['datasets'])->keyBy('type');
    $status = $dashboard->purchaseOrderStatusDistribution();

    expect(array_sum($trend['OUT']['data']))->toBe(1)
        ->and(array_sum($trend['IN']['data']))->toBe(1)
        ->and($status['data'][array_search('RECEIVED', $status['statuses'], true)])->toBe(1)
        ->and(array_sum(array_merge(...array_column($dashboard->stockByCategory()['datasets'], 'data'))))->toBe(2);
});

test('the movement trend filter dispatches new chart data', function () {
    Livewire::actingAs(staff())
        ->test(Dashboard::class)
        ->set('trendDays', 30)
        ->assertDispatched('dashboard-trend-updated');
});
