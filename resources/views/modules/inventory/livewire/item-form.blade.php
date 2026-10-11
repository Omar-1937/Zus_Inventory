<form wire:submit="save" class="card max-w-3xl" novalidate>
    <div class="card-body grid gap-5 sm:grid-cols-2">
        <x-field label="Item name" for="item_name" error="item_name" required class="sm:col-span-2">
            <input id="item_name" type="text" wire:model.blur="item_name" @class(['form-input', 'form-input-error' => $errors->has('item_name')]) maxlength="150" placeholder="e.g. Oat Milk" autofocus>
        </x-field>

        <x-field label="Category" for="category_id" error="category_id" required>
            <select id="category_id" wire:model="category_id" @class(['form-input', 'form-input-error' => $errors->has('category_id')])>
                <option value="">Select a category…</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                @endforeach
            </select>
            @if ($categories->isEmpty())
                <p class="form-help">No categories yet. <a href="{{ route('inventory.categories.index') }}" class="text-accent underline">Create one first.</a></p>
            @endif
        </x-field>

        <x-field label="Unit of measure" for="unit" error="unit" required help="e.g. L, kg, pcs, bottle">
            <input id="unit" type="text" list="unit-suggestions" wire:model.blur="unit" @class(['form-input', 'form-input-error' => $errors->has('unit')]) maxlength="30">
            <datalist id="unit-suggestions">
                @foreach ($unitSuggestions as $suggestion)
                    <option value="{{ $suggestion }}">
                @endforeach
            </datalist>
        </x-field>

        <x-field label="Minimum stock level" for="minimum_stock" error="minimum_stock" required help="A Low Stock alert is raised when usable stock falls to or below this level.">
            <input id="minimum_stock" type="number" step="0.001" min="0" inputmode="decimal" wire:model.blur="minimum_stock" @class(['form-input', 'form-input-error' => $errors->has('minimum_stock')])>
        </x-field>

        <x-field label="Expiry tracking" error="expiry_tracking">
            <label class="mt-2 flex items-start gap-3 rounded-lg border border-slate-200 p-3">
                <input type="checkbox" wire:model="expiry_tracking" class="form-checkbox mt-0.5">
                <span class="text-sm text-slate-600">
                    <span class="font-medium text-ink">Requires expiration dates</span><br>
                    Every batch received must have an expiration date (e.g. milk, syrups).
                </span>
            </label>
        </x-field>

        <x-field label="Description" for="description" error="description" class="sm:col-span-2">
            <textarea id="description" wire:model.blur="description" rows="3" @class(['form-input', 'form-input-error' => $errors->has('description')]) maxlength="1000" placeholder="Optional notes, brand, storage instructions…"></textarea>
        </x-field>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
        <a href="{{ $itemId ? route('inventory.show', $itemId) : route('inventory.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            <x-spinner wire:loading wire:target="save" />
            {{ $itemId ? 'Save changes' : 'Add item' }}
        </button>
    </div>
</form>
