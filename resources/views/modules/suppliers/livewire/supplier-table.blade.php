<div class="card">
    <div class="card-header">
        <div class="grid w-full gap-3 sm:grid-cols-3">
            <div class="relative sm:col-span-2">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search name, contact, email or phone…" aria-label="Search suppliers">
            </div>
            <select wire:model.live="status" class="form-input" aria-label="Filter by status">
                <option value="active">Active suppliers</option>
                <option value="inactive">Inactive suppliers</option>
                <option value="all">All suppliers</option>
            </select>
        </div>
    </div>

    <div class="table-wrap relative">
        <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/60"><x-spinner class="h-6 w-6 text-accent" /></div>
        <table class="table">
            <thead>
                <tr>
                    <x-sort-header field="supplier_id" :sort-field="$sortField" :sort-direction="$sortDirection">ID</x-sort-header>
                    <x-sort-header field="supplier_name" :sort-field="$sortField" :sort-direction="$sortDirection">Supplier</x-sort-header>
                    <x-sort-header field="contact_person" :sort-field="$sortField" :sort-direction="$sortDirection">Contact</x-sort-header>
                    <th>Phone / Email</th>
                    <x-sort-header field="supplier_items_count" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Products</x-sort-header>
                    <x-sort-header field="purchase_orders_count" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Orders</x-sort-header>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($suppliers as $supplier)
                    <tr wire:key="supplier-{{ $supplier->supplier_id }}" @class(['opacity-60' => ! $supplier->is_active])>
                        <td class="font-mono text-xs text-slate-500">#{{ $supplier->supplier_id }}</td>
                        <td><a href="{{ route('suppliers.show', $supplier) }}" class="font-semibold text-navy hover:text-accent">{{ $supplier->supplier_name }}</a></td>
                        <td>{{ $supplier->contact_person ?: '—' }}</td>
                        <td class="text-xs">
                            <span class="block">{{ $supplier->phone ?: '—' }}</span>
                            <span class="block text-slate-500">{{ $supplier->email }}</span>
                        </td>
                        <td class="text-right tabular-nums">{{ $supplier->supplier_items_count }}</td>
                        <td class="text-right tabular-nums">{{ $supplier->purchase_orders_count }}</td>
                        <td><x-badge :color="$supplier->is_active ? 'success' : 'neutral'">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                        <td>
                            <div class="flex justify-end gap-1">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-ghost btn-sm" title="View"><x-lucide-eye class="h-4 w-4" /><span class="sr-only">View</span></a>
                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-ghost btn-sm" title="Edit"><x-lucide-pencil class="h-4 w-4" /><span class="sr-only">Edit</span></a>
                                @endcan
                                @can('toggleActive', $supplier)
                                    <button type="button" wire:click="toggleActive({{ $supplier->supplier_id }})"
                                        wire:confirm="{{ $supplier->is_active ? "Deactivate {$supplier->supplier_name}? Their purchase history is kept." : "Reactivate {$supplier->supplier_name}?" }}"
                                        class="btn btn-ghost btn-sm {{ $supplier->is_active ? 'text-danger' : 'text-success' }}" title="{{ $supplier->is_active ? 'Deactivate' : 'Reactivate' }}">
                                        <x-lucide-power class="h-4 w-4" /><span class="sr-only">{{ $supplier->is_active ? 'Deactivate' : 'Reactivate' }}</span>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">
                        <x-empty-state icon="truck" title="No suppliers found" description="Add suppliers and assign the products they deliver with their prices.">
                            @can('create', App\Models\Supplier::class)
                                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-lucide-plus class="h-4 w-4" /> Add Supplier</a>
                            @endcan
                        </x-empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-5 py-3">{{ $suppliers->links() }}</div>
</div>
