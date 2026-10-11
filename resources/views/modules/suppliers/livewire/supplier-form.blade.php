<form wire:submit="save" class="card max-w-3xl" novalidate>
    <div class="card-body grid gap-5 sm:grid-cols-2">
        <x-field label="Supplier name" for="supplier_name" error="supplier_name" required class="sm:col-span-2">
            <input id="supplier_name" type="text" wire:model.blur="supplier_name" @class(['form-input', 'form-input-error' => $errors->has('supplier_name')]) maxlength="150" autofocus>
        </x-field>
        <x-field label="Contact person" for="contact_person" error="contact_person">
            <input id="contact_person" type="text" wire:model.blur="contact_person" class="form-input" maxlength="100">
        </x-field>
        <x-field label="Phone" for="phone" error="phone">
            <input id="phone" type="tel" wire:model.blur="phone" @class(['form-input', 'form-input-error' => $errors->has('phone')]) maxlength="30" placeholder="e.g. 03-1234 5678">
        </x-field>
        <x-field label="Email" for="email" error="email" class="sm:col-span-2">
            <input id="email" type="email" wire:model.blur="email" @class(['form-input', 'form-input-error' => $errors->has('email')]) maxlength="255">
        </x-field>
        <x-field label="Address" for="address" error="address" class="sm:col-span-2">
            <textarea id="address" wire:model.blur="address" rows="3" class="form-input" maxlength="1000"></textarea>
        </x-field>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
        <a href="{{ $supplierId ? route('suppliers.show', $supplierId) : route('suppliers.index') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            <x-spinner wire:loading wire:target="save" /> {{ $supplierId ? 'Save changes' : 'Add supplier' }}
        </button>
    </div>
</form>
