<?php

use App\Modules\Inventory\Controllers\BatchController;
use App\Modules\Inventory\Controllers\CategoryController;
use App\Modules\Inventory\Controllers\InventoryItemController;
use App\Modules\Inventory\Controllers\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
| Module 1 — Inventory Management
| Loaded by InventoryServiceProvider behind the web, auth and active middleware.
*/

Route::prefix('inventory')->name('inventory.')->group(function (): void {
    Route::get('/', [InventoryItemController::class, 'index'])->name('index');
    Route::get('/create', [InventoryItemController::class, 'create'])->name('create');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/stock', [StockMovementController::class, 'index'])->name('stock.index');
    Route::get('/batches', [BatchController::class, 'index'])->name('batches.index');
    Route::get('/{item}', [InventoryItemController::class, 'show'])->whereNumber('item')->name('show');
    Route::get('/{item}/edit', [InventoryItemController::class, 'edit'])->whereNumber('item')->name('edit');
});
