<form wire:submit="save" class="card" novalidate>
    <div class="card-header">
        <div>
            <h2 class="card-title">Stock Out</h2>
            <p class="text-xs text-slate-500">Deducts from the earliest-expiring usable batches first. Expired stock is never used.</p>
        </div>
    </div>
    <div class="card-body grid gap-5 sm:grid-cols-2">
        <x-field label="Inventory item" for="out_item_id" error="item_id" required class="sm:col-span-2">
            <select id="out_item_id" wire:model.live="item_id" @class(['form-input', 'form-input-error' => $errors->has('item_id')])>
                <option value="">Select an item…</option>
                @foreach ($items as $option)
                    <option value="{{ $option->item_id }}">{{ $option->item_name }} ({{ $option->unit }})</option>
                @endforeach
            </select>
        </x-field>

        @if ($this->selectedItem)
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg bg-accent-50 px-4 py-3 text-sm text-secondary sm:col-span-2">
                <span>Usable stock: <strong data-testid="out-usable">{{ Decimal::formatQuantity($this->selectedItem->usable_quantity) }} {{ $this->selectedItem->unit }}</strong></span>
                @if (Decimal::compare($this->selectedItem->physical_quantity, $this->selectedItem->usable_quantity) > 0)
                    <span class="text-danger">Expired (not usable): <strong>{{ Decimal::formatQuantity(Decimal::sub($this->selectedItem->physical_quantity, $this->selectedItem->usable_quantity)) }} {{ $this->selectedItem->unit }}</strong></span>
                @endif
            </div>
        @endif

        <x-field label="Quantity used{{ $this->selectedItem ? ' ('.$this->selectedItem->unit.')' : '' }}" for="out_quantity" error="quantity" required>
            <input id="out_quantity" type="number" step="0.001" min="0.001" inputmode="decimal" wire:model.live.debounce.400ms="quantity" @class(['form-input', 'form-input-error' => $errors->has('quantity')])>
        </x-field>

        <x-field label="Reason" for="out_reason" error="reason" required>
            <input id="out_reason" type="text" list="out-reasons" wire:model.blur="reason" @class(['form-input', 'form-input-error' => $errors->has('reason')]) maxlength="255">
            <datalist id="out-reasons">
                @foreach ($reasons as $option)
                    <option value="{{ $option }}">
                @endforeach
            </datalist>
        </x-field>

        <x-field label="Notes" for="out_notes" error="notes" class="sm:col-span-2">
            <textarea id="out_notes" wire:model="notes" rows="2" class="form-input" maxlength="1000" placeholder="Optional"></textarea>
        </x-field>

        @if ($preview !== [])
            <div class="sm:col-span-2">
                <p class="form-label">FEFO deduction preview</p>
                <ul class="divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                    @foreach ($preview as $line)
                        <li class="flex items-center justify-between px-4 py-2">
                            <span><span class="font-mono text-xs">{{ $line['batch']->batch_number }}</span>
                                <span class="text-slate-500">· expires {{ $line['batch']->expiration_date?->format('d M Y') ?? 'never' }}</span></span>
                            <span class="font-semibold text-danger tabular-nums">−{{ Decimal::formatQuantity($line['take']) }} {{ $this->selectedItem?->unit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            <x-spinner wire:loading wire:target="save" />
            <x-lucide-package-minus wire:loading.remove wire:target="save" class="h-4 w-4" />
            Confirm Stock Out
        </button>
    </div>
</form>
