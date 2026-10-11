<div>
    <x-page-header title="Notification Center" subtitle="Low-stock and expiry alerts. Alerts resolve automatically when stock is replenished or batches are disposed of. Purchase orders are never created automatically.">
        <x-slot:actions>
            <button type="button" wire:click="refreshAlerts" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="refreshAlerts">
                <x-lucide-refresh-cw class="h-4 w-4" wire:loading.class="animate-spin" wire:target="refreshAlerts" /> Refresh alerts
            </button>
            <button type="button" wire:click="markAllRead" class="btn btn-primary" @disabled($counts['unread'] === 0)>
                <x-lucide-check class="h-4 w-4" /> Mark all as read
            </button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div class="flex flex-wrap gap-3">
                <select wire:model.live="status" class="form-input w-48" aria-label="Filter by status">
                    <option value="active">Active ({{ $counts['active'] }})</option>
                    <option value="UNREAD">Unread ({{ $counts['unread'] }})</option>
                    <option value="READ">Read</option>
                    <option value="RESOLVED">Resolved</option>
                    <option value="all">All</option>
                </select>
                <select wire:model.live="type" class="form-input w-48" aria-label="Filter by type">
                    <option value="">All types</option>
                    @foreach ($types as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse ($notifications as $notification)
                <li wire:key="notification-{{ $notification->notification_id }}" @class([
                    'flex flex-wrap items-start gap-4 px-5 py-4',
                    'bg-accent-50/40' => $notification->status === App\Enums\NotificationStatus::Unread,
                    'opacity-60' => $notification->status === App\Enums\NotificationStatus::Resolved,
                ])>
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
                        'bg-red-50 text-danger' => $notification->notification_type === App\Enums\NotificationType::Expired,
                        'bg-amber-50 text-warning' => $notification->notification_type !== App\Enums\NotificationType::Expired,
                    ])>
                        @if ($notification->notification_type === App\Enums\NotificationType::LowStock)
                            <x-lucide-triangle-alert class="h-5 w-5" />
                        @else
                            <x-lucide-calendar-clock class="h-5 w-5" />
                        @endif
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-badge :color="$notification->notification_type->color()">{{ $notification->notification_type->label() }}</x-badge>
                            <x-badge :color="match ($notification->status) { App\Enums\NotificationStatus::Unread => 'info', App\Enums\NotificationStatus::Read => 'neutral', default => 'success' }">{{ $notification->status->label() }}</x-badge>
                            <span class="text-xs text-slate-400">{{ $notification->created_at?->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-ink">{{ $notification->message }}</p>
                        @if ($notification->resolved_at)
                            <p class="mt-1 text-xs text-success">Resolved {{ $notification->resolved_at->diffForHumans() }}</p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($notification->item_id)
                            <a href="{{ route('inventory.show', $notification->item_id) }}" class="btn btn-secondary btn-sm"><x-lucide-package class="h-4 w-4" /> View item</a>
                        @endif
                        @if ($notification->batch_id)
                            <a href="{{ route('inventory.batches.index', ['item' => $notification->item_id, 'status' => 'ALL']) }}" class="btn btn-secondary btn-sm"><x-lucide-boxes class="h-4 w-4" /> View batch</a>
                        @endif
                        @if ($notification->notification_type === App\Enums\NotificationType::LowStock && $notification->isActive() && $notification->item_id)
                            <a href="{{ route('purchase-orders.create', ['item_id' => $notification->item_id]) }}" class="btn btn-accent btn-sm"><x-lucide-shopping-cart class="h-4 w-4" /> Create Purchase Order</a>
                        @endif
                        @if ($notification->status === App\Enums\NotificationStatus::Unread)
                            <button type="button" wire:click="markRead({{ $notification->notification_id }})" class="btn btn-ghost btn-sm" title="Mark as read"><x-lucide-check class="h-4 w-4" /> Mark read</button>
                        @endif
                    </div>
                </li>
            @empty
                <li><x-empty-state icon="bell" title="No notifications" description="Low-stock and expiry alerts will appear here." /></li>
            @endforelse
        </ul>
        <div class="border-t border-slate-100 px-5 py-3">{{ $notifications->links() }}</div>
    </div>
</div>
