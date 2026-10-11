<?php

namespace App\Modules\DashboardReports;

use App\Modules\DashboardReports\Commands\RefreshStockAlertsCommand;
use App\Modules\DashboardReports\Listeners\RefreshStockAlerts;
use App\Modules\DashboardReports\Livewire\Dashboard;
use App\Modules\DashboardReports\Livewire\NotificationBell;
use App\Modules\DashboardReports\Livewire\NotificationCenter;
use App\Modules\DashboardReports\Livewire\ReportTable;
use App\Modules\Inventory\Events\StockLevelChanged;
use App\Modules\ModuleServiceProvider;

class DashboardReportsServiceProvider extends ModuleServiceProvider
{
    protected function moduleName(): string
    {
        return 'DashboardReports';
    }

    protected function viewNamespace(): string
    {
        return 'dashboard-reports';
    }

    protected function livewireComponents(): array
    {
        return [
            'dashboard-reports.dashboard' => Dashboard::class,
            'dashboard-reports.notification-bell' => NotificationBell::class,
            'dashboard-reports.notification-center' => NotificationCenter::class,
            'dashboard-reports.report-table' => ReportTable::class,
        ];
    }

    protected function listeners(): array
    {
        return [
            StockLevelChanged::class => [RefreshStockAlerts::class],
        ];
    }

    protected function consoleCommands(): array
    {
        return [
            RefreshStockAlertsCommand::class,
        ];
    }
}
