<table class="table">
    <thead>
        <tr><th>PO Number</th><th>Order date</th><th>Supplier</th><th>Status</th><th class="text-right">Lines</th><th class="text-right">Total</th><th>Created by</th><th>Received</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $order)
            <tr wire:key="r-{{ $order->purchase_order_id }}">
                <td><a href="{{ route('purchase-orders.show', $order) }}" class="font-mono font-semibold text-navy hover:text-accent">{{ $order->reference() }}</a></td>
                <td>{{ $order->order_date->format('d M Y') }}</td>
                <td>{{ $order->supplier->supplier_name }}</td>
                <td><x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge></td>
                <td class="text-right">{{ $order->items_count }}</td>
                <td class="text-right font-semibold tabular-nums">{{ Decimal::formatMoney($order->total_amount) }}</td>
                <td>{{ $order->creator->full_name }}</td>
                <td class="text-xs">{{ $order->received_at?->format('d M Y, H:i') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty-state icon="clipboard-list" title="No purchase orders for these filters" /></td></tr>
        @endforelse
    </tbody>
</table>
