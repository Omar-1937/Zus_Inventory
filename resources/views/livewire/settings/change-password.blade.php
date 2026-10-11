<form wire:submit="save" class="card" novalidate>
    <div class="card-header"><h2 class="card-title">Change password</h2></div>
    <div class="card-body space-y-4">
        <x-field label="Current password" for="current_password" error="current_password" required>
            <input id="current_password" type="password" wire:model="current_password" class="form-input" autocomplete="current-password">
        </x-field>
        <x-field label="New password" for="new_password" error="password" required help="At least 8 characters with letters and numbers.">
            <input id="new_password" type="password" wire:model="password" class="form-input" autocomplete="new-password">
        </x-field>
        <x-field label="Confirm new password" for="password_confirmation" required>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" class="form-input" autocomplete="new-password">
        </x-field>
    </div>
    <div class="flex justify-end border-t border-slate-100 bg-slate-50/60 px-5 py-4">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled"><x-spinner wire:loading wire:target="save" /> Update password</button>
    </div>
</form>
