<form wire:submit="receive" novalidate class="space-y-6">
    @error('receive')
        <div class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
            <x-lucide-circle-alert class="mt-0.5 h-5 w-5 shrink-0" /> {{ $message }}
        </div>
    @enderror

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ $order->supplier->supplier_name }}</h2>
                <p class="text-xs text-slate-500">Ordered {{ $order->order_date->format('d M Y') }} · {{ Decimal::formatMoney($order->total_amount) }}</p>
            </div>
            <div class="w-48">
                <label for="received_date" class="form-label">Received date <span class="text-danger">*</span></label>
                <input id="received_date" type="date" max="{{ today()->toDateString() }}" wire:model="received_date" @class(['form-input', 'form-input-error' => $errors->has('received_date')])>
                @error('received_date') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Item</th><th class="text-right">Ordered</th><th>Batch number *</th><th>Quantity received *</th><th>Expiration date</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($order->items as $line)
                        @php($item = $line->supplierItem->item)
                        <tr wire:key="receive-{{ $line->po_item_id }}">
                            <td>
                                <p class="font-medium text-navy">{{ $item->item_name }}</p>
                                @if ($item->expiry_tracking)
                                    <x-badge color="warning" class="mt-1">Expiry date required</x-badge>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap tabular-nums">{{ Decimal::formatQuantity($line->quantity_ordered) }} {{ $item->unit }}</td>
                            <td>
                                <input type="text" wire:model="lines.{{ $line->po_item_id }}.batch_number" @class(['form-input font-mono', 'form-input-error' => $errors->has("lines.{$line->po_item_id}.batch_number")]) maxlength="50" aria-label="Batch number for {{ $item->item_name }}">
                                @error("lines.{$line->po_item_id}.batch_number") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <div class="flex items-center gap-1">
                                    <input type="number" step="0.001" min="0.001" wire:model="lines.{{ $line->po_item_id }}.quantity" @class(['form-input w-28', 'form-input-error' => $errors->has("lines.{$line->po_item_id}.quantity")]) aria-label="Quantity received for {{ $item->item_name }}">
                                    <span class="text-xs text-slate-500">{{ $item->unit }}</span>
                                </div>
                                @error("lines.{$line->po_item_id}.quantity") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                            <td>
                                <input type="date" min="{{ today()->toDateString() }}" wire:model="lines.{{ $line->po_item_id }}.expiration_date" @class(['form-input', 'form-input-error' => $errors->has("lines.{$line->po_item_id}.expiration_date")]) aria-label="Expiration date for {{ $item->item_name }}">
                                @error("lines.{$line->po_item_id}.expiration_date") <p class="form-error">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
            <p class="text-xs text-slate-500">Confirming creates the inventory batches and stock-in transactions and marks the order RECEIVED. It can only happen once.</p>
            <div class="flex gap-2">
                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-success" wire:loading.attr="disabled" wire:target="receive">
                    <x-spinner wire:loading wire:target="receive" /> <x-lucide-inbox wire:loading.remove wire:target="receive" class="h-4 w-4" /> Confirm delivery
                </button>
            </div>
        </div>
    </div>
</form>
