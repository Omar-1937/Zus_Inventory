<?php

namespace App\Modules\DashboardReports\Livewire;

use App\Modules\DashboardReports\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class Dashboard extends Component
{
    /** Movement trend window in days (7, 14, 30 or 90). */
    #[Url(as: 'days', except: 14)]
    public int $trendDays = 14;

    public function updatedTrendDays(): void
    {
        $this->trendDays = in_array($this->trendDays, [7, 14, 30, 90], true) ? $this->trendDays : 14;

        $this->dispatch('dashboard-trend-updated', data: app(DashboardService::class)->movementTrend($this->trendDays));
    }

    public function render(): View
    {
        $dashboard = app(DashboardService::class);
        $days = in_array($this->trendDays, [7, 14, 30, 90], true) ? $this->trendDays : 14;

        return view('dashboard-reports::livewire.dashboard', [
            'summary' => $dashboard->summary(),
            'stockByCategory' => $dashboard->stockByCategory(),
            'movementTrend' => $dashboard->movementTrend($days),
            'statusDistribution' => $dashboard->purchaseOrderStatusDistribution(),
            'recentTransactions' => $dashboard->recentTransactions(),
            'lowStockItems' => $dashboard->lowStockItems(),
            'expiringBatches' => $dashboard->expiringBatches(),
            'recentOrders' => $dashboard->recentPurchaseOrders(),
        ]);
    }
}
