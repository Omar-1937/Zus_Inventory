<div class="card">
    <div class="card-header">
        <div class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="relative lg:col-span-2">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search by item name, description or ID…" aria-label="Search inventory">
            </div>
            <select wire:model.live="categoryId" class="form-input" aria-label="Filter by category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                @endforeach
            </select>
            <select wire:model.live="status" class="form-input" aria-label="Filter by stock status">
                <option value="">All active items</option>
                <option value="IN_STOCK">In Stock</option>
                <option value="LOW_STOCK">Low Stock</option>
                <option value="OUT_OF_STOCK">Out of Stock</option>
                <option value="INACTIVE">Inactive items</option>
                <option value="ALL">All items (incl. inactive)</option>
            </select>
        </div>
    </div>

    <div class="table-wrap relative">
        <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/60">
            <x-spinner class="h-6 w-6 text-accent" />
        </div>

        <table class="table">
            <thead>
                <tr>
                    <x-sort-header field="item_id" :sort-field="$sortField" :sort-direction="$sortDirection">ID</x-sort-header>
                    <x-sort-header field="item_name" :sort-field="$sortField" :sort-direction="$sortDirection">Item Name</x-sort-header>
                    <x-sort-header field="category_name" :sort-field="$sortField" :sort-direction="$sortDirection">Category</x-sort-header>
                    <x-sort-header field="physical_quantity" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Physical Qty</x-sort-header>
                    <x-sort-header field="usable_quantity" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Usable Qty</x-sort-header>
                    <th>Unit</th>
                    <x-sort-header field="minimum_stock" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Min. Stock</x-sort-header>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $item)
                    @php($stockStatus = $item->stockStatus())
                    <tr wire:key="item-{{ $item->item_id }}" @class(['opacity-60' => ! $item->is_active])>
                        <td class="font-mono text-xs text-slate-500">#{{ $item->item_id }}</td>
                        <td>
                            <a href="{{ route('inventory.show', $item) }}" class="font-semibold text-navy hover:text-accent">{{ $item->item_name }}</a>
                            @if ($item->expiry_tracking)
                                <span class="ml-1 inline-flex align-middle text-slate-400" title="Tracks expiry dates"><x-lucide-calendar-clock class="h-3.5 w-3.5" /></span>
                            @endif
                        </td>
                        <td>{{ $item->category?->category_name }}</td>
                        <td class="text-right tabular-nums">{{ Decimal::formatQuantity($item->physical_quantity) }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ Decimal::formatQuantity($item->usable_quantity) }}</td>
                        <td>{{ $item->unit }}</td>
                        <td class="text-right tabular-nums">{{ Decimal::formatQuantity($item->minimum_stock) }}</td>
                        <td>
                            @if (! $item->is_active)
                                <x-badge color="neutral">Inactive</x-badge>
                            @else
                                <x-badge :color="$stockStatus->color()">{{ $stockStatus->label() }}</x-badge>
                            @endif
                        </td>
                        <td>
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('inventory.show', $item) }}" class="btn btn-ghost btn-sm" title="View details"><x-lucide-eye class="h-4 w-4" /><span class="sr-only">View</span></a>
                                @can('update', $item)
                                    <a href="{{ route('inventory.edit', $item) }}" class="btn btn-ghost btn-sm" title="Edit"><x-lucide-pencil class="h-4 w-4" /><span class="sr-only">Edit</span></a>
                                @endcan
                                @if ($item->is_active && $stockStatus !== App\Enums\StockStatus::InStock)
                                    <a href="{{ route('purchase-orders.create', ['item_id' => $item->item_id]) }}" class="btn btn-ghost btn-sm" title="Create purchase order"><x-lucide-shopping-cart class="h-4 w-4" /><span class="sr-only">Reorder</span></a>
                                @endif
                                @can('toggleActive', $item)
                                    <button type="button"
                                        wire:click="toggleActive({{ $item->item_id }})"
                                        wire:confirm="{{ $item->is_active ? "Deactivate {$item->item_name}? It will be hidden from stock forms but its history is kept." : "Reactivate {$item->item_name}?" }}"
                                        class="btn btn-ghost btn-sm {{ $item->is_active ? 'text-danger' : 'text-success' }}"
                                        title="{{ $item->is_active ? 'Deactivate' : 'Reactivate' }}">
                                        <x-lucide-power class="h-4 w-4" /><span class="sr-only">{{ $item->is_active ? 'Deactivate' : 'Reactivate' }}</span>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <x-empty-state icon="package" title="No inventory items found" description="Try changing the search or filters, or add your first inventory item.">
                                @if ($search !== '' || $categoryId !== '' || $status !== '')
                                    <button type="button" wire:click="clearFilters" class="btn btn-secondary">Clear filters</button>
                                @endif
                                @can('create', App\Models\InventoryItem::class)
                                    <a href="{{ route('inventory.create') }}" class="btn btn-primary"><x-lucide-plus class="h-4 w-4" /> Add Item</a>
                                @endcan
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-3">
        <label class="flex items-center gap-2 text-sm text-slate-500">
            Rows
            <select wire:model.live="perPage" class="form-input w-20 py-1">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </label>
        <div class="flex-1">{{ $items->links() }}</div>
    </div>
</div>
