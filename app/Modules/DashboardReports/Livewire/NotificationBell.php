<?php

namespace App\Modules\DashboardReports\Livewire;

use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    #[On('notifications-updated')]
    #[On('stock-updated')]
    public function refreshBell(): void
    {
        // Re-render with fresh counts.
    }

    public function render(): View
    {
        return view('dashboard-reports::livewire.notification-bell', [
            'unreadCount' => Notification::query()->unread()->count(),
            'latest' => Notification::query()->active()->with('item')->latest('created_at')->latest('notification_id')->limit(5)->get(),
        ]);
    }
}
