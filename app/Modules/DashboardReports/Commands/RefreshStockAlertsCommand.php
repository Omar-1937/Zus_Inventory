<?php

namespace App\Modules\DashboardReports\Commands;

use App\Modules\DashboardReports\Services\StockAlertService;
use Illuminate\Console\Command;

class RefreshStockAlertsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'inventory:refresh-alerts';

    /**
     * @var string
     */
    protected $description = 'Re-evaluate low-stock, expiring-soon and expired alerts for every item and batch';

    public function handle(StockAlertService $alerts): int
    {
        $result = $alerts->refreshAll();

        $this->components->info("Stock alerts refreshed: {$result['created']} created, {$result['resolved']} resolved.");

        return self::SUCCESS;
    }
}
