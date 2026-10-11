<div class="card">
    <div class="card-header">
        <h2 class="card-title">{{ $fixedSupplierId ? 'Products & prices' : 'All supplier products' }}</h2>
        @if ($canManage)
            <button type="button" wire:click="openAssign" class="btn btn-primary btn-sm"><x-lucide-plus class="h-4 w-4" /> Assign product</button>
        @endif
    </div>

    <div class="grid gap-3 border-b border-slate-100 px-5 py-3 sm:grid-cols-3">
        <div class="relative">
            <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search item or supplier…" aria-label="Search products">
        </div>
        @unless ($fixedSupplierId)
            <select wire:model.live="supplierFilter" class="form-input" aria-label="Filter by supplier">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $option)
                    <option value="{{ $option->supplier_id }}">{{ $option->supplier_name }}{{ $option->is_active ? '' : ' (inactive)' }}</option>
                @endforeach
            </select>
        @endunless
        <select wire:model.live="itemFilter" class="form-input" aria-label="Filter by item">
            <option value="">All items</option>
            @foreach ($items as $option)
                <option value="{{ $option->item_id }}">{{ $option->item_name }}</option>
            @endforeach
        </select>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Item</th>
                    @unless ($fixedSupplierId)<th>Supplier</th>@endunless
                    <th>Category</th>
                    <th class="text-right">Unit price</th>
                    <th>Last updated</th>
                    <th>Status</th>
                    @if ($canManage)<th class="text-right">Actions</th>@endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($supplierItems as $supplierItem)
                    <tr wire:key="si-{{ $supplierItem->supplier_item_id }}" @class(['opacity-60' => ! $supplierItem->is_active])>
                        <td><a href="{{ route('inventory.show', $supplierItem->item_id) }}" class="font-medium text-navy hover:text-accent">{{ $supplierItem->item->item_name }}</a></td>
                        @unless ($fixedSupplierId)
                            <td><a href="{{ route('suppliers.show', $supplierItem->supplier_id) }}" class="hover:text-accent">{{ $supplierItem->supplier->supplier_name }}</a></td>
                        @endunless
                        <td class="text-slate-500">{{ $supplierItem->item->category?->category_name }}</td>
                        <td class="text-right">
                            @if ($editingId === $supplierItem->supplier_item_id)
                                <form wire:submit="savePrice" class="flex items-center justify-end gap-2">
                                    <span class="text-xs text-slate-500">RM</span>
                                    <input type="number" step="0.01" min="0.01" wire:model="editPrice" class="form-input w-28 py-1 text-right" aria-label="New unit price" autofocus>
                                    <button type="submit" class="btn btn-success btn-sm" title="Save"><x-lucide-check class="h-4 w-4" /></button>
                                    <button type="button" wire:click="cancelEdit" class="btn btn-secondary btn-sm" title="Cancel"><x-lucide-x class="h-4 w-4" /></button>
                                </form>
                                @error('editPrice') <p class="form-error">{{ $message }}</p> @enderror
                            @else
                                <span class="font-semibold tabular-nums">{{ Decimal::formatMoney($supplierItem->unit_price) }}</span>
                                <span class="text-xs text-slate-400">/ {{ $supplierItem->item->unit }}</span>
                            @endif
                        </td>
                        <td class="text-xs text-slate-500">{{ $supplierItem->updated_at?->format('d M Y, H:i') }}</td>
                        <td><x-badge :color="$supplierItem->is_active ? 'success' : 'neutral'">{{ $supplierItem->is_active ? 'Available' : 'Inactive' }}</x-badge></td>
                        @if ($canManage)
                            <td>
                                <div class="flex justify-end gap-1">
                                    <button type="button" wire:click="startEdit({{ $supplierItem->supplier_item_id }})" class="btn btn-ghost btn-sm" title="Update price"><x-lucide-badge-dollar-sign class="h-4 w-4" /><span class="sr-only">Update price</span></button>
                                    <button type="button" wire:click="toggle({{ $supplierItem->supplier_item_id }})" class="btn btn-ghost btn-sm" title="{{ $supplierItem->is_active ? 'Deactivate' : 'Reactivate' }}"><x-lucide-power class="h-4 w-4" /><span class="sr-only">Toggle</span></button>
                                    <button type="button" wire:click="remove({{ $supplierItem->supplier_item_id }})" wire:confirm="Remove {{ $supplierItem->item->item_name }} from {{ $supplierItem->supplier->supplier_name }}? Items with purchase history are deactivated instead." class="btn btn-ghost btn-sm text-danger" title="Remove"><x-lucide-trash-2 class="h-4 w-4" /><span class="sr-only">Remove</span></button>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state icon="badge-dollar-sign" title="No products assigned" description="Assign the items this supplier provides together with their unit prices." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-5 py-3">{{ $supplierItems->links() }}</div>

    <x-modal wire:model="showAssign" title="Assign product to supplier" max-width="md">
        <form id="assign-form" wire:submit="assign" class="space-y-4" novalidate>
            @unless ($fixedSupplierId)
                <x-field label="Supplier" for="assign_supplier" error="supplier_id" required>
                    <select id="assign_supplier" wire:model.live="supplier_id" @class(['form-input', 'form-input-error' => $errors->has('supplier_id')])>
                        <option value="">Select a supplier…</option>
                        @foreach ($suppliers->where('is_active', true) as $option)
                            <option value="{{ $option->supplier_id }}">{{ $option->supplier_name }}</option>
                        @endforeach
                    </select>
                </x-field>
            @endunless
            <x-field label="Inventory item" for="assign_item" error="item_id" required>
                <select id="assign_item" wire:model="item_id" @class(['form-input', 'form-input-error' => $errors->has('item_id')])>
                    <option value="">Select an item…</option>
                    @foreach ($assignableItems as $option)
                        <option value="{{ $option->item_id }}">{{ $option->item_name }} ({{ $option->unit }})</option>
                    @endforeach
                </select>
            </x-field>
            <x-field label="Unit price (RM)" for="assign_price" error="unit_price" required>
                <input id="assign_price" type="number" step="0.01" min="0.01" wire:model="unit_price" @class(['form-input', 'form-input-error' => $errors->has('unit_price')])>
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="submit" form="assign-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="assign"><x-spinner wire:loading wire:target="assign" /> Assign</button>
        </x-slot:footer>
    </x-modal>
</div>
