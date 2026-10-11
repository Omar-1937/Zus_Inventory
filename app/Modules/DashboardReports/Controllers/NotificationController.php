<?php

namespace App\Modules\DashboardReports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Modules\DashboardReports\Services\StockAlertService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('dashboard-reports::notifications.index');
    }

    /**
     * Mark the alert as read and jump to the affected item (or batch list).
     */
    public function open(Notification $notification, StockAlertService $alerts): RedirectResponse
    {
        $alerts->markRead($notification);

        if ($notification->batch_id !== null) {
            return redirect()->route('inventory.batches.index', ['item' => $notification->item_id, 'status' => 'ALL']);
        }

        if ($notification->item_id !== null) {
            return redirect()->route('inventory.show', $notification->item_id);
        }

        return redirect()->route('notifications.index');
    }
}
