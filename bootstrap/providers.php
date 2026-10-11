<?php

use App\Modules\DashboardReports\DashboardReportsServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\PurchaseOrders\PurchaseOrdersServiceProvider;
use App\Modules\Suppliers\SuppliersServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,

    // The four feature modules (app/Modules/*).
    InventoryServiceProvider::class,
    SuppliersServiceProvider::class,
    PurchaseOrdersServiceProvider::class,
    DashboardReportsServiceProvider::class,
];
