<div class="card">
    <div class="card-header">
        <div class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="relative lg:col-span-2">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search PO number, supplier or item…" aria-label="Search purchase orders">
            </div>
            <select wire:model.live="status" class="form-input" aria-label="Filter by status">
                <option value="">All statuses</option>
                @foreach ($statuses as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
            <select wire:model.live="supplierId" class="form-input" aria-label="Filter by supplier">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $option)
                    <option value="{{ $option->supplier_id }}">{{ $option->supplier_name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <input type="date" wire:model.live="from" class="form-input" aria-label="Order date from">
                <input type="date" wire:model.live="to" class="form-input" aria-label="Order date to">
            </div>
        </div>
    </div>

    <div class="table-wrap relative">
        <div wire:loading.flex class="absolute inset-0 z-10 items-center justify-center bg-white/60"><x-spinner class="h-6 w-6 text-accent" /></div>
        <table class="table">
            <thead>
                <tr>
                    <x-sort-header field="purchase_order_id" :sort-field="$sortField" :sort-direction="$sortDirection">PO Number</x-sort-header>
                    <x-sort-header field="order_date" :sort-field="$sortField" :sort-direction="$sortDirection">Order date</x-sort-header>
                    <th>Supplier</th>
                    <th class="text-right">Lines</th>
                    <x-sort-header field="total_amount" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Total</x-sort-header>
                    <x-sort-header field="status" :sort-field="$sortField" :sort-direction="$sortDirection">Status</x-sort-header>
                    <th>Created by</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($orders as $order)
                    <tr wire:key="po-{{ $order->purchase_order_id }}">
                        <td><a href="{{ route('purchase-orders.show', $order) }}" class="font-mono font-semibold text-navy hover:text-accent">{{ $order->reference() }}</a></td>
                        <td class="whitespace-nowrap">{{ $order->order_date->format('d M Y') }}</td>
                        <td>{{ $order->supplier->supplier_name }}</td>
                        <td class="text-right">{{ $order->items_count }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ Decimal::formatMoney($order->total_amount) }}</td>
                        <td><x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge></td>
                        <td>{{ $order->creator->full_name }}</td>
                        <td>
                            <div class="flex justify-end gap-1">
                                @if ($order->status === App\Enums\PurchaseOrderStatus::Pending)
                                    @can('approve', $order)
                                        <a href="{{ route('purchase-orders.approve', $order) }}" class="btn btn-success btn-sm">Approve</a>
                                    @endcan
                                @elseif ($order->status === App\Enums\PurchaseOrderStatus::Ordered)
                                    @can('receive', $order)
                                        <a href="{{ route('purchase-orders.receive', $order) }}" class="btn btn-accent btn-sm">Receive</a>
                                    @endcan
                                @endif
                                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-ghost btn-sm" title="View"><x-lucide-eye class="h-4 w-4" /><span class="sr-only">View</span></a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8">
                        <x-empty-state icon="clipboard-list" title="No purchase orders found" description="Create a purchase order when stock runs low.">
                            @if ($search !== '' || $status !== '' || $supplierId !== '' || $from !== '' || $to !== '')
                                <button type="button" wire:click="clearFilters" class="btn btn-secondary">Clear filters</button>
                            @endif
                            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><x-lucide-file-plus class="h-4 w-4" /> Create Purchase Order</a>
                        </x-empty-state>
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="border-t border-slate-100 px-5 py-3">{{ $orders->links() }}</div>
</div>
