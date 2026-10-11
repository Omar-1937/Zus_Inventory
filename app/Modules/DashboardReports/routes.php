<?php

use App\Modules\DashboardReports\Controllers\DashboardController;
use App\Modules\DashboardReports\Controllers\NotificationController;
use App\Modules\DashboardReports\Controllers\ReportController;
use App\Modules\DashboardReports\Services\ReportService;
use Illuminate\Support\Facades\Route;

/*
| Module 4 — Dashboard, Notifications and Reports
| Loaded by DashboardReportsServiceProvider behind the web, auth and active middleware.
*/

Route::get('/dashboard', DashboardController::class)->name('dashboard');

Route::prefix('notifications')->name('notifications.')->group(function (): void {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::get('/{notification}/open', [NotificationController::class, 'open'])->whereNumber('notification')->name('open');
});

Route::prefix('reports')->name('reports.')->group(function (): void {
    $reports = implode('|', array_keys(ReportService::REPORTS));

    Route::get('/{report}', [ReportController::class, 'show'])->where('report', $reports)->name('show');
    Route::get('/{report}/export', [ReportController::class, 'export'])->where('report', $reports)->name('export');
});
