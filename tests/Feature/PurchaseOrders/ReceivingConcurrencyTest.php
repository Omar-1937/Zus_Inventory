<?php

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBatch;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;

uses(DatabaseTruncation::class);

beforeEach(function () {
    config(['database.connections.mysql_other' => config('database.connections.mysql')]);
});

afterEach(function () {
    DB::connection('mysql_other')->rollBack();
    DB::purge('mysql_other');

    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    foreach (['notifications', 'audit_logs', 'stock_transactions', 'inventory_batches', 'purchase_order_items', 'purchase_orders', 'supplier_items', 'suppliers', 'inventory_items', 'categories', 'users'] as $table) {
        DB::table($table)->truncate();
    }
    DB::statement('SET FOREIGN_KEY_CHECKS=1');
});

test('a concurrent second receive is blocked by the order row lock and then rejected', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Ordered);

    // Request 1 is receiving the order: it holds the purchase order row lock.
    $other = DB::connection('mysql_other');
    $other->beginTransaction();
    $other->select('SELECT * FROM purchase_orders WHERE purchase_order_id = ? FOR UPDATE', [$order->purchase_order_id]);

    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');

    // Request 2 cannot proceed while request 1 holds the lock.
    expect(fn () => app(PurchaseOrderService::class)->receive($order, receiveLines($order, 'R2'), staff()))
        ->toThrow(fn (QueryException $exception) => expect($exception->errorInfo[1])->toBe(1205));

    // Request 1 completes and marks the order RECEIVED.
    $other->update("UPDATE purchase_orders SET status = 'RECEIVED' WHERE purchase_order_id = ?", [$order->purchase_order_id]);
    $other->commit();

    // Request 2 retries, sees RECEIVED under the lock, and adds nothing.
    expect(fn () => app(PurchaseOrderService::class)->receive($order, receiveLines($order, 'R2'), staff()))
        ->toThrow(BusinessRuleException::class, 'already been received');

    expect(InventoryBatch::query()->count())->toBe(0);
});
