<?php

use App\Modules\PurchaseOrders\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

/*
| Module 3 — Purchase Order Management
| Loaded by PurchaseOrdersServiceProvider behind the web, auth and active middleware.
*/

Route::prefix('purchase-orders')->name('purchase-orders.')->controller(PurchaseOrderController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::get('/{purchaseOrder}', 'show')->whereNumber('purchaseOrder')->name('show');
    Route::get('/{purchaseOrder}/approve', 'approveForm')->whereNumber('purchaseOrder')->name('approve');
    Route::post('/{purchaseOrder}/approve', 'approve')->whereNumber('purchaseOrder')->name('approve.store');
    Route::post('/{purchaseOrder}/mark-ordered', 'markOrdered')->whereNumber('purchaseOrder')->name('mark-ordered');
    Route::post('/{purchaseOrder}/cancel', 'cancel')->whereNumber('purchaseOrder')->name('cancel');
    Route::get('/{purchaseOrder}/receive', 'receiveForm')->whereNumber('purchaseOrder')->name('receive');
});
