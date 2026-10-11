<table class="table">
    <thead>
        <tr><th>Item</th><th>Category</th><th class="text-right">Usable</th><th class="text-right">Minimum</th><th class="text-right">Shortfall</th><th>Cheapest supplier</th><th class="no-print text-right">Action</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $item)
            @php($cheapest = $item->supplierItems->first())
            <tr wire:key="r-{{ $item->item_id }}">
                <td><a href="{{ route('inventory.show', $item) }}" class="font-medium text-navy hover:text-accent">{{ $item->item_name }}</a></td>
                <td>{{ $item->category?->category_name }}</td>
                <td class="text-right font-semibold tabular-nums text-danger">{{ Decimal::formatQuantity($item->usable_quantity) }} {{ $item->unit }}</td>
                <td class="text-right tabular-nums">{{ Decimal::formatQuantity($item->minimum_stock) }} {{ $item->unit }}</td>
                <td class="text-right tabular-nums">{{ Decimal::formatQuantity(Decimal::sub($item->minimum_stock, $item->usable_quantity)) }} {{ $item->unit }}</td>
                <td>{{ $cheapest ? $cheapest->supplier->supplier_name.' · '.Decimal::formatMoney($cheapest->unit_price) : '—' }}</td>
                <td class="no-print text-right"><a href="{{ route('purchase-orders.create', ['item_id' => $item->item_id]) }}" class="btn btn-accent btn-sm">Reorder</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty-state icon="circle-check" title="No low-stock items" description="Every active item is above its minimum stock level." /></td></tr>
        @endforelse
    </tbody>
</table>
