<form wire:submit="save" class="card" novalidate>
    <div class="card-header">
        <div>
            <h2 class="card-title">Stock In</h2>
            <p class="text-xs text-slate-500">Creates a new batch and an IN transaction recorded under your name.</p>
        </div>
    </div>
    <div class="card-body grid gap-5 sm:grid-cols-2">
        <x-field label="Inventory item" for="in_item_id" error="item_id" required class="sm:col-span-2">
            <select id="in_item_id" wire:model.live="item_id" @class(['form-input', 'form-input-error' => $errors->has('item_id')])>
                <option value="">Select an item…</option>
                @foreach ($items as $option)
                    <option value="{{ $option->item_id }}">{{ $option->item_name }} ({{ $option->unit }})</option>
                @endforeach
            </select>
        </x-field>

        @if ($this->selectedItem)
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-lg bg-accent-50 px-4 py-3 text-sm text-secondary sm:col-span-2">
                <span>Usable now: <strong>{{ Decimal::formatQuantity($this->selectedItem->usable_quantity) }} {{ $this->selectedItem->unit }}</strong></span>
                <span>Minimum: <strong>{{ Decimal::formatQuantity($this->selectedItem->minimum_stock) }} {{ $this->selectedItem->unit }}</strong></span>
                @if ($this->selectedItem->expiry_tracking)
                    <x-badge color="warning">Expiration date required</x-badge>
                @endif
            </div>
        @endif

        <x-field label="Batch number" for="in_batch_number" error="batch_number" required help="Must be unique for this item.">
            <input id="in_batch_number" type="text" wire:model.blur="batch_number" @class(['form-input font-mono', 'form-input-error' => $errors->has('batch_number')]) maxlength="50">
        </x-field>

        <x-field label="Quantity received{{ $this->selectedItem ? ' ('.$this->selectedItem->unit.')' : '' }}" for="in_quantity" error="quantity" required>
            <input id="in_quantity" type="number" step="0.001" min="0.001" inputmode="decimal" wire:model.blur="quantity" @class(['form-input', 'form-input-error' => $errors->has('quantity')])>
        </x-field>

        <x-field label="Received date" for="in_received_date" error="received_date" required>
            <input id="in_received_date" type="date" max="{{ today()->toDateString() }}" wire:model.blur="received_date" @class(['form-input', 'form-input-error' => $errors->has('received_date')])>
        </x-field>

        <x-field label="Expiration date" for="in_expiration_date" error="expiration_date" :required="(bool) $this->selectedItem?->expiry_tracking" help="Leave empty for non-perishable items such as cups and lids.">
            <input id="in_expiration_date" type="date" min="{{ today()->toDateString() }}" wire:model.blur="expiration_date" @class(['form-input', 'form-input-error' => $errors->has('expiration_date')])>
        </x-field>

        <x-field label="Notes" for="in_notes" error="notes" class="sm:col-span-2">
            <textarea id="in_notes" wire:model="notes" rows="2" class="form-input" maxlength="1000" placeholder="Optional, e.g. delivery reference"></textarea>
        </x-field>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
        <button type="submit" class="btn btn-success" wire:loading.attr="disabled" wire:target="save">
            <x-spinner wire:loading wire:target="save" />
            <x-lucide-package-plus wire:loading.remove wire:target="save" class="h-4 w-4" />
            Confirm Stock In
        </button>
    </div>
</form>
