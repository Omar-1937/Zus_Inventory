<table class="table">
    <thead>
        <tr><th>ID</th><th>Item</th><th>Category</th><th class="text-right">Physical</th><th class="text-right">Usable</th><th>Unit</th><th class="text-right">Minimum</th><th>Status</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $item)
            <tr wire:key="r-{{ $item->item_id }}">
                <td class="font-mono text-xs text-slate-500">#{{ $item->item_id }}</td>
                <td><a href="{{ route('inventory.show', $item) }}" class="font-medium text-navy hover:text-accent">{{ $item->item_name }}</a></td>
                <td>{{ $item->category?->category_name }}</td>
                <td class="text-right tabular-nums">{{ Decimal::formatQuantity($item->physical_quantity) }}</td>
                <td class="text-right font-semibold tabular-nums" data-testid="report-usable">{{ Decimal::formatQuantity($item->usable_quantity) }}</td>
                <td>{{ $item->unit }}</td>
                <td class="text-right tabular-nums">{{ Decimal::formatQuantity($item->minimum_stock) }}</td>
                <td><x-badge :color="$item->stockStatus()->color()">{{ $item->stockStatus()->label() }}</x-badge></td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty-state icon="file-text" title="No data for these filters" /></td></tr>
        @endforelse
    </tbody>
</table>
