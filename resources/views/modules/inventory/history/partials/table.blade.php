@php($showItem = $showItem ?? true)

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Date & time</th>
                <th>Type</th>
                @if ($showItem)
                    <th>Item</th>
                @endif
                <th>Batch</th>
                <th class="text-right">Qty change</th>
                <th>Reason</th>
                <th>Employee</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($transactions as $transaction)
                <tr wire:key="tx-{{ $transaction->transaction_id }}">
                    <td class="whitespace-nowrap text-xs text-slate-500">{{ $transaction->created_at?->format('d M Y, H:i') }}</td>
                    <td><x-badge :color="$transaction->transaction_type->color()">{{ $transaction->transaction_type->label() }}</x-badge></td>
                    @if ($showItem)
                        <td><a href="{{ route('inventory.show', $transaction->item_id) }}" class="font-medium text-navy hover:text-accent">{{ $transaction->item->item_name }}</a></td>
                    @endif
                    <td class="font-mono text-xs">{{ $transaction->batch->batch_number }}</td>
                    <td @class(['text-right font-semibold tabular-nums whitespace-nowrap', 'text-success' => Decimal::isPositive($transaction->quantity_change), 'text-danger' => ! Decimal::isPositive($transaction->quantity_change)])>
                        {{ Decimal::isPositive($transaction->quantity_change) ? '+' : '' }}{{ Decimal::formatQuantity($transaction->quantity_change) }} {{ $transaction->item->unit }}
                    </td>
                    <td class="max-w-xs">
                        <span class="block truncate" title="{{ $transaction->reason }}">{{ $transaction->reason }}</span>
                        @if ($transaction->notes)
                            <span class="block truncate text-xs text-slate-400" title="{{ $transaction->notes }}">{{ $transaction->notes }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $transaction->user->full_name }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $showItem ? 7 : 6 }}"><x-empty-state icon="history" title="No stock transactions" description="Stock movements will appear here once stock is received or used." /></td></tr>
            @endforelse
        </tbody>
    </table>
</div>
