<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Employee accounts</h2>
            <p class="text-xs text-slate-500">Admins manage users, prices and approvals. Staff record stock, create and receive orders.</p>
        </div>
        <button type="button" wire:click="create" class="btn btn-primary btn-sm"><x-lucide-plus class="h-4 w-4" /> Add employee</button>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($users as $user)
                    <tr wire:key="user-{{ $user->user_id }}" @class(['opacity-60' => ! $user->is_active])>
                        <td class="font-medium text-navy">{{ $user->full_name }} @if ($user->user_id === auth()->id()) <span class="text-xs text-slate-400">(you)</span> @endif</td>
                        <td>{{ $user->email }}</td>
                        <td><x-badge :color="$user->isAdmin() ? 'primary' : 'info'">{{ $user->role->label() }}</x-badge></td>
                        <td><x-badge :color="$user->is_active ? 'success' : 'neutral'">{{ $user->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="edit({{ $user->user_id }})" class="btn btn-ghost btn-sm" title="Edit"><x-lucide-pencil class="h-4 w-4" /><span class="sr-only">Edit</span></button>
                                @can('toggleActive', $user)
                                    <button type="button" wire:click="toggleActive({{ $user->user_id }})" wire:confirm="{{ $user->is_active ? "Deactivate {$user->full_name}? They will be signed out and unable to log in." : "Reactivate {$user->full_name}?" }}" class="btn btn-ghost btn-sm {{ $user->is_active ? 'text-danger' : 'text-success' }}" title="{{ $user->is_active ? 'Deactivate' : 'Reactivate' }}"><x-lucide-power class="h-4 w-4" /><span class="sr-only">Toggle</span></button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit employee' : 'Add employee'" max-width="md">
        <form id="user-form" wire:submit="save" class="space-y-4" novalidate>
            <x-field label="Full name" for="user_full_name" error="full_name" required>
                <input id="user_full_name" type="text" wire:model="full_name" class="form-input" maxlength="100">
            </x-field>
            <x-field label="Email" for="user_email" error="email" required>
                <input id="user_email" type="email" wire:model="email" class="form-input">
            </x-field>
            <x-field label="Role" for="user_role" error="role" required>
                <select id="user_role" wire:model="role" class="form-input">
                    @foreach ($roles as $option)
                        <option value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field :label="$editingId ? 'New password (leave blank to keep)' : 'Password'" for="user_password" error="password" :required="! $editingId" help="At least 8 characters with letters and numbers.">
                <input id="user_password" type="password" wire:model="password" class="form-input" autocomplete="new-password">
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="submit" form="user-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Save</button>
        </x-slot:footer>
    </x-modal>
</div>
