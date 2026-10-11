<table class="table">
    <thead>
        <tr><th>Batch</th><th>Item</th><th>Received</th><th>Expires</th><th class="text-right">Days left</th><th class="text-right">Remaining</th><th>Status</th></tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
        @forelse ($rows as $batch)
            @php($batchStatus = $batch->status())
            <tr wire:key="r-{{ $batch->batch_id }}">
                <td class="font-mono text-xs">{{ $batch->batch_number }}</td>
                <td><a href="{{ route('inventory.show', $batch->item_id) }}" class="font-medium text-navy hover:text-accent">{{ $batch->item->item_name }}</a></td>
                <td>{{ $batch->received_date->format('d M Y') }}</td>
                <td data-testid="report-expiry">{{ $batch->expiration_date->format('d M Y') }}</td>
                <td class="text-right tabular-nums">{{ $batch->daysUntilExpiry() }}</td>
                <td class="text-right font-semibold tabular-nums">{{ Decimal::formatQuantity($batch->remaining_quantity) }} {{ $batch->item->unit }}</td>
                <td><x-badge :color="$batchStatus->color()">{{ $batchStatus->label() }}</x-badge></td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty-state icon="calendar-clock" title="No batches for these filters" /></td></tr>
        @endforelse
    </tbody>
</table>
