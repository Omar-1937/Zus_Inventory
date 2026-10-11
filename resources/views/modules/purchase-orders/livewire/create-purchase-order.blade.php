<form wire:submit="save" novalidate class="grid gap-6 xl:grid-cols-3">
    <div class="space-y-6 xl:col-span-2">
        @if ($this->requestedItem)
            <div class="card flex flex-wrap items-center gap-4 border-accent/30 bg-accent-50/60 p-4" data-testid="requested-item">
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-accent"><x-lucide-package class="h-5 w-5" /></span>
                <div class="flex-1 text-sm">
                    <p class="font-semibold text-navy">Reordering: {{ $this->requestedItem->item_name }}</p>
                    <p class="text-slate-600">
                        Usable stock {{ Decimal::formatQuantity($this->requestedItem->usable_quantity) }} {{ $this->requestedItem->unit }}
                        · minimum {{ Decimal::formatQuantity($this->requestedItem->minimum_stock) }} {{ $this->requestedItem->unit }}
                        @if ($suggestedQuantity !== null && Decimal::isPositive($suggestedQuantity))
                            · at least <strong>{{ Decimal::formatQuantity($suggestedQuantity) }} {{ $this->requestedItem->unit }}</strong> needed to reach the minimum
                        @endif
                    </p>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">1. Supplier</h2></div>
            <div class="card-body">
                <x-field label="Supplier" for="po_supplier" error="supplier_id" required>
                    <select id="po_supplier" wire:model.live="supplier_id" @class(['form-input', 'form-input-error' => $errors->has('supplier_id')])>
                        <option value="">Select a supplier…</option>
                        @foreach ($this->suppliers as $supplier)
                            @php($offer = $this->requestedItemId && $supplier->relationLoaded('supplierItems') ? $supplier->supplierItems->first() : null)
                            <option value="{{ $supplier->supplier_id }}">
                                {{ $supplier->supplier_name }}@if ($offer) — {{ $this->requestedItem?->item_name }} {{ Decimal::formatMoney($offer->unit_price) }}/{{ $this->requestedItem?->unit }}@elseif ($this->requestedItemId) (does not supply {{ $this->requestedItem?->item_name }})@endif
                            </option>
                        @endforeach
                    </select>
                </x-field>
                @if ($this->requestedItemId && $supplier_id !== '' && ! $requestedItemSupplied)
                    <p class="mt-2 text-sm text-amber-700">This supplier does not supply {{ $this->requestedItem?->item_name }}. You can still order other items from them.</p>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">2. Items</h2>
                @if ($supplier_id !== '' && $this->supplierItems->isNotEmpty())
                    <button type="button" wire:click="addLine" class="btn btn-secondary btn-sm"><x-lucide-plus class="h-4 w-4" /> Add line</button>
                @endif
            </div>

            @if ($supplier_id === '')
                <x-empty-state icon="truck" title="Select a supplier first" description="Only items supplied by the selected supplier can be ordered." />
            @elseif ($this->supplierItems->isEmpty())
                <x-empty-state icon="badge-dollar-sign" title="No products for this supplier" description="Assign products and prices to this supplier before ordering.">
                    <a href="{{ route('suppliers.show', (int) $supplier_id) }}" class="btn btn-secondary">Open supplier</a>
                </x-empty-state>
            @else
                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr><th class="w-2/5">Item</th><th class="w-32">Quantity</th><th class="text-right">Unit price</th><th class="text-right">Subtotal</th><th></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($lines as $index => $line)
                                @php($selected = $line['supplier_item_id'] !== '' ? $this->supplierItems->firstWhere('supplier_item_id', (int) $line['supplier_item_id']) : null)
                                @php($quoted = $selected ? $quotedLines->get($selected->supplier_item_id) : null)
                                <tr wire:key="line-{{ $index }}">
                                    <td>
                                        <select wire:model.live="lines.{{ $index }}.supplier_item_id" @class(['form-input', 'form-input-error' => $errors->has("lines.$index.supplier_item_id")]) aria-label="Item">
                                            <option value="">Select item…</option>
                                            @foreach ($this->supplierItems as $supplierItem)
                                                <option value="{{ $supplierItem->supplier_item_id }}">{{ $supplierItem->item->item_name }} ({{ $supplierItem->item->unit }})</option>
                                            @endforeach
                                        </select>
                                        @error("lines.$index.supplier_item_id") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-1">
                                            <input type="number" step="0.001" min="0.001" inputmode="decimal" wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" @class(['form-input', 'form-input-error' => $errors->has("lines.$index.quantity")]) aria-label="Quantity">
                                            <span class="text-xs text-slate-500">{{ $selected?->item->unit }}</span>
                                        </div>
                                        @error("lines.$index.quantity") <p class="form-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="text-right tabular-nums text-slate-600" data-testid="unit-price">{{ $quoted ? Decimal::formatMoney($quoted['unit_price']) : '—' }}</td>
                                    <td class="text-right font-semibold tabular-nums">{{ $quoted ? Decimal::formatMoney($quoted['subtotal']) : '—' }}</td>
                                    <td class="text-right">
                                        <button type="button" wire:click="removeLine({{ $index }})" class="btn btn-ghost btn-sm text-danger" title="Remove line"><x-lucide-trash-2 class="h-4 w-4" /><span class="sr-only">Remove</span></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('lines') <p class="form-error px-5 pb-4">{{ $message }}</p> @enderror
            @endif
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">3. Notes</h2></div>
            <div class="card-body">
                <textarea wire:model="notes" rows="3" class="form-input" maxlength="1000" placeholder="Optional instructions for the supplier or approver" aria-label="Notes"></textarea>
                @error('notes') <p class="form-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div>
        <div class="card sticky top-24">
            <div class="card-header"><h2 class="card-title">Order summary</h2></div>
            <div class="card-body space-y-3 text-sm">
                @forelse ($quote['lines'] as $quotedLine)
                    @php($supplierItem = $this->supplierItems->firstWhere('supplier_item_id', $quotedLine['supplier_item_id']))
                    <div class="flex justify-between gap-3">
                        <span class="text-slate-600">{{ $supplierItem?->item->item_name }} × {{ Decimal::formatQuantity($quotedLine['quantity']) }}</span>
                        <span class="font-medium tabular-nums">{{ Decimal::formatMoney($quotedLine['subtotal']) }}</span>
                    </div>
                @empty
                    <p class="text-slate-500">No items added yet.</p>
                @endforelse
                <div class="flex items-center justify-between border-t border-slate-200 pt-3">
                    <span class="font-semibold text-navy">Total amount</span>
                    <span class="text-2xl font-bold text-navy tabular-nums" data-testid="po-total" wire:loading.class="opacity-50">{{ Decimal::formatMoney($quote['total']) }}</span>
                </div>
                <p class="text-xs text-slate-500">Prices are read from MySQL. The final amount is recalculated on the server when the order is saved.</p>
            </div>
            <div class="border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                <button type="submit" class="btn btn-primary w-full" wire:loading.attr="disabled" wire:target="save" @disabled($supplier_id === '')>
                    <x-spinner wire:loading wire:target="save" /> Submit purchase order
                </button>
            </div>
        </div>
    </div>
</form>
