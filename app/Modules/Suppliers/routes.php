<?php

use App\Modules\Suppliers\Controllers\SupplierController;
use App\Modules\Suppliers\Controllers\SupplierProductController;
use Illuminate\Support\Facades\Route;

/*
| Module 2 — Supplier Management
| Loaded by SuppliersServiceProvider behind the web, auth and active middleware.
*/

Route::prefix('suppliers')->name('suppliers.')->group(function (): void {
    Route::get('/', [SupplierController::class, 'index'])->name('index');
    Route::get('/create', [SupplierController::class, 'create'])->name('create');
    Route::get('/products', [SupplierProductController::class, 'index'])->name('products.index');
    Route::get('/{supplier}', [SupplierController::class, 'show'])->whereNumber('supplier')->name('show');
    Route::get('/{supplier}/edit', [SupplierController::class, 'edit'])->whereNumber('supplier')->name('edit');
});
