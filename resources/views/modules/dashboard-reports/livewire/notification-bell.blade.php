<div class="relative" x-data="{ open: false }" x-on:click.outside="open = false" wire:poll.60s>
    <button type="button" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" x-on:click="open = ! open" aria-label="Notifications ({{ $unreadCount }} unread)">
        <x-lucide-bell class="h-5 w-5" />
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold text-white" data-testid="unread-count">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
        @endif
    </button>

    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg sm:w-96">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-semibold text-navy">Notifications</p>
            <span class="text-xs text-slate-500">{{ $unreadCount }} unread</span>
        </div>
        <ul class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
            @forelse ($latest as $notification)
                <li>
                    <a href="{{ route('notifications.open', $notification) }}" class="flex gap-3 px-4 py-3 hover:bg-slate-50">
                        <span @class([
                            'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full',
                            'bg-red-50 text-danger' => $notification->notification_type === App\Enums\NotificationType::Expired,
                            'bg-amber-50 text-warning' => $notification->notification_type !== App\Enums\NotificationType::Expired,
                        ])>
                            @if ($notification->notification_type === App\Enums\NotificationType::LowStock)
                                <x-lucide-triangle-alert class="h-4 w-4" />
                            @else
                                <x-lucide-calendar-clock class="h-4 w-4" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-2 text-xs font-semibold text-slate-500">
                                {{ $notification->notification_type->label() }}
                                @if ($notification->status === App\Enums\NotificationStatus::Unread)
                                    <span class="h-2 w-2 rounded-full bg-accent" aria-label="Unread"></span>
                                @endif
                            </span>
                            <span class="block text-sm text-ink">{{ $notification->message }}</span>
                        </span>
                    </a>
                </li>
            @empty
                <li class="px-4 py-8 text-center text-sm text-slate-500">You're all caught up.</li>
            @endforelse
        </ul>
        <a href="{{ route('notifications.index') }}" class="block border-t border-slate-100 px-4 py-3 text-center text-sm font-semibold text-accent hover:bg-slate-50">Open Notification Center</a>
    </div>
</div>
