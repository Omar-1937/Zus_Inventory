<div @class(['card' => ! $compact])>
    @unless ($compact)
        <div class="card-header">
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="relative lg:col-span-2">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search item, batch, employee or reason…" aria-label="Search transactions">
                </div>
                <select wire:model.live="type" class="form-input" aria-label="Filter by type">
                    <option value="">All types</option>
                    @foreach ($types as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
                <select wire:model.live="itemId" class="form-input" aria-label="Filter by item">
                    <option value="">All items</option>
                    @foreach ($items as $option)
                        <option value="{{ $option->item_id }}">{{ $option->item_name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <input type="date" wire:model.live="from" class="form-input" aria-label="From date">
                    <input type="date" wire:model.live="to" class="form-input" aria-label="To date">
                </div>
            </div>
        </div>
    @endunless

    @if ($compact)
        <ul class="divide-y divide-slate-100">
            @forelse ($transactions as $transaction)
                <li wire:key="recent-tx-{{ $transaction->transaction_id }}" class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-navy">{{ $transaction->item->item_name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ $transaction->created_at?->format('d M, H:i') }} · {{ $transaction->user->full_name }} · <span class="font-mono">{{ $transaction->batch->batch_number }}</span></p>
                    </div>
                    <div class="text-right">
                        <x-badge :color="$transaction->transaction_type->color()">{{ $transaction->transaction_type->label() }}</x-badge>
                        <p @class(['mt-1 text-xs font-semibold tabular-nums', 'text-success' => Decimal::isPositive($transaction->quantity_change), 'text-danger' => ! Decimal::isPositive($transaction->quantity_change)])>
                            {{ Decimal::isPositive($transaction->quantity_change) ? '+' : '' }}{{ Decimal::formatQuantity($transaction->quantity_change) }} {{ $transaction->item->unit }}
                        </p>
                    </div>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-slate-500">No stock movements yet.</li>
            @endforelse
        </ul>
    @else
        @include('inventory::history.partials.table', ['transactions' => $transactions])
        <div class="border-t border-slate-100 px-5 py-3">{{ $transactions->links() }}</div>
    @endif
</div>
