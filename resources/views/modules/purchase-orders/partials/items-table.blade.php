@php($showBatches = $showBatches ?? false)

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right">Quantity</th>
                <th class="text-right">Unit price</th>
                <th class="text-right">Subtotal</th>
                @if ($showBatches)<th>Received batch</th>@endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @foreach ($order->items as $line)
                <tr>
                    <td><a href="{{ route('inventory.show', $line->supplierItem->item_id) }}" class="font-medium text-navy hover:text-accent">{{ $line->supplierItem->item->item_name }}</a></td>
                    <td class="text-right tabular-nums">{{ Decimal::formatQuantity($line->quantity_ordered) }} {{ $line->supplierItem->item->unit }}</td>
                    <td class="text-right tabular-nums">{{ Decimal::formatMoney($line->unit_price) }}</td>
                    <td class="text-right font-semibold tabular-nums">{{ Decimal::formatMoney($line->subtotal) }}</td>
                    @if ($showBatches)
                        <td class="text-xs">
                            @forelse ($line->batches as $batch)
                                <span class="block"><span class="font-mono">{{ $batch->batch_number }}</span>
                                    @if ($batch->expiration_date) · exp {{ $batch->expiration_date->format('d M Y') }} @endif</span>
                            @empty
                                <span class="text-slate-400">—</span>
                            @endforelse
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="bg-slate-50">
                <td colspan="3" class="px-4 py-3 text-right font-semibold text-navy">Total amount</td>
                <td class="px-4 py-3 text-right text-lg font-bold text-navy tabular-nums">{{ Decimal::formatMoney($order->total_amount) }}</td>
                @if ($showBatches)<td></td>@endif
            </tr>
        </tfoot>
    </table>
</div>
