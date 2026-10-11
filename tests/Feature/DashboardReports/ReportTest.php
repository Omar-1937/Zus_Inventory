<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\InventoryBatch;
use App\Modules\DashboardReports\Livewire\ReportTable;
use App\Modules\DashboardReports\Services\ReportService;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('every report page renders', function (string $report) {
    itemWithStock('4', ['minimum_stock' => '8']);
    purchaseOrderInStatus(PurchaseOrderStatus::Received);

    $this->actingAs(staff())->get(route('reports.show', $report))->assertOk()->assertSee(ReportService::REPORTS[$report]);
})->with(array_keys(ReportService::REPORTS));

test('unknown reports return 404', function () {
    $this->actingAs(staff())->get('/reports/salaries')->assertNotFound();
});

test('current stock report shows calculated quantities and filters', function () {
    $oat = itemWithStock('4', ['item_name' => 'Oat Milk', 'minimum_stock' => '8']);
    InventoryBatch::factory()->for($oat, 'item')->quantity('2')->expired()->create();
    itemWithStock('50', ['item_name' => 'Coffee Beans', 'minimum_stock' => '5']);

    $rows = app(ReportService::class)->currentStock([])->get()->keyBy('item_name');

    expect($rows['Oat Milk']->physical_quantity)->toBe('6.000')
        ->and($rows['Oat Milk']->usable_quantity)->toBe('4.000')
        ->and($rows['Coffee Beans']->usable_quantity)->toBe('50.000');

    Livewire::actingAs(staff())
        ->test(ReportTable::class, ['report' => 'current-stock'])
        ->set('status', 'LOW_STOCK')
        ->assertSee('Oat Milk')
        ->assertDontSee('Coffee Beans');
});

test('low stock report lists only items at or below minimum', function () {
    itemWithStock('8', ['item_name' => 'At Minimum', 'minimum_stock' => '8']);
    itemWithStock('8.001', ['item_name' => 'Just Above', 'minimum_stock' => '8']);

    $names = app(ReportService::class)->lowStock([])->pluck('item_name')->all();

    expect($names)->toBe(['At Minimum']);
});

test('expiration report filters by status and date range', function () {
    InventoryBatch::factory()->expired()->create(['batch_number' => 'EXPIRED-1']);
    InventoryBatch::factory()->expiresInDays(2)->create(['batch_number' => 'SOON-1']);
    InventoryBatch::factory()->expiresInDays(60)->create(['batch_number' => 'LATER-1']);
    InventoryBatch::factory()->nonExpiring()->create(['batch_number' => 'NOEXP-1']);

    $service = app(ReportService::class);

    expect($service->expiry([])->pluck('batch_number')->all())->toBe(['EXPIRED-1', 'SOON-1', 'LATER-1'])
        ->and($service->expiry(['status' => 'EXPIRED'])->pluck('batch_number')->all())->toBe(['EXPIRED-1'])
        ->and($service->expiry(['status' => 'EXPIRING_SOON'])->pluck('batch_number')->all())->toBe(['SOON-1'])
        ->and($service->expiry(['from' => today()->toDateString(), 'to' => today()->addDays(30)->toDateString()])->pluck('batch_number')->all())->toBe(['SOON-1']);
});

test('stock movement report filters by type and date', function () {
    $item = itemWithStock('10');
    app(StockService::class)->stockOut($item, '2', 'Usage', null, staff());

    $service = app(ReportService::class);

    expect($service->stockMovements(['type' => 'OUT'])->count())->toBe(1)
        ->and($service->stockMovements(['type' => 'IN'])->count())->toBe(0)
        ->and($service->stockMovements(['from' => today()->addDay()->toDateString()])->count())->toBe(0)
        ->and($service->stockMovements(['from' => today()->toDateString(), 'to' => today()->toDateString()])->count())->toBe(1);
});

test('purchase order report totals and filters', function () {
    purchaseOrderInStatus(PurchaseOrderStatus::Received, '10', '12.50');
    purchaseOrderInStatus(PurchaseOrderStatus::Pending, '2', '5.00');

    $service = app(ReportService::class);

    expect($service->purchaseOrders([])->count())->toBe(2)
        ->and($service->purchaseOrders(['status' => 'RECEIVED'])->sum('total_amount'))->toEqual(125)
        ->and($service->purchaseOrders(['from' => today()->addDay()->toDateString()])->count())->toBe(0);

    Livewire::actingAs(staff())
        ->test(ReportTable::class, ['report' => 'purchase-orders'])
        ->assertSee('RM 135.00')
        ->set('status', 'PENDING')
        ->assertSee('RM 10.00');
});

test('reports export CSV with the same filters', function () {
    itemWithStock('4', ['item_name' => 'Oat Milk', 'unit' => 'L', 'minimum_stock' => '8']);
    itemWithStock('50', ['item_name' => 'Coffee Beans', 'minimum_stock' => '5']);

    $response = $this->actingAs(staff())->get(route('reports.export', ['report' => 'current-stock', 'status' => 'LOW_STOCK']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    $rows = array_map('str_getcsv', array_filter(explode("\n", trim(ltrim($csv, "\xEF\xBB\xBF")))));

    expect($rows[0])->toBe(['Item ID', 'Item Name', 'Category', 'Physical Quantity', 'Usable Quantity', 'Unit', 'Minimum Stock', 'Stock Status', 'Active'])
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][1])->toBe('Oat Milk')
        ->and($rows[1][4])->toBe('4.000')
        ->and($rows[1][7])->toBe('Low Stock');
});

test('every report can be exported', function (string $report) {
    purchaseOrderInStatus(PurchaseOrderStatus::Received);

    $response = $this->actingAs(staff())->get(route('reports.export', $report));

    $response->assertOk();
    expect($response->streamedContent())->not->toBeEmpty();
})->with(array_keys(ReportService::REPORTS));

test('csv export neutralises spreadsheet formulas', function () {
    itemWithStock('1', ['item_name' => '=HYPERLINK("http://evil")', 'minimum_stock' => '0']);

    $csv = $this->actingAs(staff())->get(route('reports.export', 'current-stock'))->streamedContent();

    expect($csv)->toContain("'=HYPERLINK");
});

test('invalid report date ranges are rejected', function () {
    $this->actingAs(staff())
        ->get(route('reports.export', ['report' => 'stock-movements', 'from' => '2026-10-10', 'to' => '2026-10-01']))
        ->assertSessionHasErrors('to');
});
