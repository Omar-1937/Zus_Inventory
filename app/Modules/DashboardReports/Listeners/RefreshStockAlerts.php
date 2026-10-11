<?php

namespace App\Modules\DashboardReports\Listeners;

use App\Modules\DashboardReports\Services\StockAlertService;
use App\Modules\Inventory\Events\StockLevelChanged;

class RefreshStockAlerts
{
    public function __construct(private readonly StockAlertService $alerts) {}

    public function handle(StockLevelChanged $event): void
    {
        $this->alerts->refreshForItems($event->itemIds);
    }
}
