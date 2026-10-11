<?php

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Services\StockService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

/*
| These tests need real committed rows visible to a SECOND database connection,
| so they use DatabaseTruncation instead of RefreshDatabase's wrapping transaction.
*/
uses(DatabaseTruncation::class);

beforeEach(function () {
    config(['database.connections.mysql_other' => config('database.connections.mysql')]);
});

afterEach(function () {
    DB::connection('mysql_other')->rollBack();
    DB::purge('mysql_other');

    // Leave the shared test database empty for the RefreshDatabase tests.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach (['notifications', 'audit_logs', 'stock_transactions', 'inventory_batches', 'purchase_order_items', 'purchase_orders', 'supplier_items', 'suppliers', 'inventory_items', 'categories', 'users'] as $table) {
        DB::table($table)->truncate();
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
});

test('a stock out waits for row locks held by another transaction (no overspending)', function () {
    $user = staff();
    $item = itemWithStock('10');

    // Another request is in the middle of a stock out for this item and holds the lock.
    $other = DB::connection('mysql_other');
    $other->beginTransaction();
    $other->select('SELECT * FROM inventory_items WHERE item_id = ? FOR UPDATE', [$item->item_id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    expect(fn () => app(StockService::class)->stockOut($item, '6', 'Usage', null, $user))
        ->toThrow(fn (QueryException $exception) => expect($exception->errorInfo[1])->toBe(1205)); // lock wait timeout

    // Nothing was deducted while the other transaction held the lock.
    expect(InventoryBatch::query()->where('item_id', $item->item_id)->value('remaining_quantity'))->toBe('10.000')
        ->and(StockTransaction::query()->count())->toBe(0);

    // The other request finishes by consuming 6 of the 10.
    $other->update('UPDATE inventory_batches SET remaining_quantity = remaining_quantity - 6 WHERE item_id = ?', [$item->item_id]);
    $other->commit();

    // Our request now re-reads the locked rows and refuses to overspend.
    expect(fn () => app(StockService::class)->stockOut($item, '6', 'Usage', null, $user))
        ->toThrow(InsufficientStockException::class);

    app(StockService::class)->stockOut($item, '4', 'Usage', null, $user);

    expect(InventoryBatch::query()->where('item_id', $item->item_id)->value('remaining_quantity'))->toBe('0.000');
});

test('two back-to-back stock outs cannot exceed available stock', function () {
    $user = staff();
    $item = InventoryItem::factory()->create();
    InventoryBatch::factory()->for($item, 'item')->quantity('5')->create();

    $service = app(StockService::class);
    $service->stockOut($item, '3', 'Usage', null, $user);

    expect(fn () => $service->stockOut($item, '3', 'Usage', null, $user))->toThrow(InsufficientStockException::class)
        ->and($item->usableQuantity())->toBe('2.000');
});
