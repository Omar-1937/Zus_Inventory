<?php

namespace App\Modules\DashboardReports\Livewire;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithToasts;
use App\Models\Notification;
use App\Modules\DashboardReports\Services\StockAlertService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class NotificationCenter extends Component
{
    use WithAppPagination;
    use WithToasts;

    /** active (UNREAD + READ) | UNREAD | READ | RESOLVED | all */
    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $type = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function markRead(int $notificationId, StockAlertService $alerts): void
    {
        $alerts->markRead(Notification::query()->findOrFail($notificationId));
        $this->dispatch('notifications-updated');
    }

    public function markAllRead(StockAlertService $alerts): void
    {
        $count = $alerts->markAllRead();
        $this->dispatch('notifications-updated');
        $this->toast($count > 0 ? "{$count} alert(s) marked as read." : 'No unread alerts.', 'info');
    }

    /**
     * Re-evaluate all alerts now (the scheduler also does this every hour).
     */
    public function refreshAlerts(StockAlertService $alerts): void
    {
        $result = $alerts->refreshAll();
        $this->dispatch('notifications-updated');
        $this->toast("Alerts refreshed: {$result['created']} new, {$result['resolved']} resolved.", 'info');
    }

    public function render(): View
    {
        $notifications = Notification::query()
            ->with(['item', 'batch'])
            ->when($this->status === 'active', fn (Builder $query) => $query->active())
            ->when(in_array($this->status, ['UNREAD', 'READ', 'RESOLVED'], true), fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->type !== '' && NotificationType::tryFrom($this->type), fn (Builder $query) => $query->where('notification_type', $this->type))
            ->orderByRaw("FIELD(status, 'UNREAD', 'READ', 'RESOLVED')")
            ->latest('created_at')
            ->latest('notification_id')
            ->paginate(15);

        return view('dashboard-reports::livewire.notification-center', [
            'notifications' => $notifications,
            'types' => NotificationType::cases(),
            'counts' => [
                'unread' => Notification::query()->where('status', NotificationStatus::Unread)->count(),
                'active' => Notification::query()->active()->count(),
            ],
        ]);
    }
}
