@extends('layouts.app')

@section('title', $item->item_name)

@section('breadcrumbs')
    <x-breadcrumbs :items="['Inventory Management' => route('inventory.index'), $item->item_name => null]" />
@endsection

@section('content')
    @php($stockStatus = $item->stockStatus())

    <x-page-header :title="$item->item_name" :subtitle="'#'.$item->item_id.' · '.$item->category?->category_name.' · measured in '.$item->unit">
        <x-slot:actions>
            @if ($item->is_active)
                <a href="{{ route('inventory.stock.index', ['tab' => 'in', 'item_id' => $item->item_id]) }}" class="btn btn-secondary"><x-lucide-package-plus class="h-4 w-4" /> Stock In</a>
                <a href="{{ route('inventory.stock.index', ['tab' => 'out', 'item_id' => $item->item_id]) }}" class="btn btn-secondary"><x-lucide-package-minus class="h-4 w-4" /> Stock Out</a>
                <a href="{{ route('purchase-orders.create', ['item_id' => $item->item_id]) }}" class="btn btn-accent"><x-lucide-shopping-cart class="h-4 w-4" /> Create Purchase Order</a>
            @endif
            @can('update', $item)
                <a href="{{ route('inventory.edit', $item) }}" class="btn btn-primary"><x-lucide-pencil class="h-4 w-4" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @unless ($item->is_active)
        <div class="mb-6 flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
            <x-lucide-power class="h-5 w-5" /> This item is inactive. It is hidden from stock forms and purchase orders, but its history is kept.
        </div>
    @endunless

    @foreach ($alerts as $alert)
        <div @class([
            'mb-3 flex items-start gap-3 rounded-xl border p-4 text-sm',
            'border-amber-200 bg-amber-50 text-amber-800' => $alert->notification_type !== App\Enums\NotificationType::Expired,
            'border-red-200 bg-red-50 text-red-800' => $alert->notification_type === App\Enums\NotificationType::Expired,
        ])>
            <x-lucide-triangle-alert class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="flex-1"><span class="font-semibold">{{ $alert->notification_type->label() }}:</span> {{ $alert->message }}</div>
        </div>
    @endforeach

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <p class="text-sm text-slate-500">Usable quantity</p>
            <p class="mt-1 text-3xl font-bold text-navy" data-testid="usable-quantity">{{ Decimal::formatQuantity($item->usableQuantity()) }} <span class="text-base font-medium text-slate-500">{{ $item->unit }}</span></p>
            <x-badge :color="$stockStatus->color()" class="mt-2">{{ $stockStatus->label() }}</x-badge>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Physical quantity</p>
            <p class="mt-1 text-3xl font-bold text-navy">{{ Decimal::formatQuantity($item->physicalQuantity()) }} <span class="text-base font-medium text-slate-500">{{ $item->unit }}</span></p>
            <p class="mt-2 text-xs text-slate-500">Includes expired stock awaiting disposal</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Minimum stock</p>
            <p class="mt-1 text-3xl font-bold text-navy">{{ Decimal::formatQuantity($item->minimum_stock) }} <span class="text-base font-medium text-slate-500">{{ $item->unit }}</span></p>
            <p class="mt-2 text-xs text-slate-500">Reorder point</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-slate-500">Expiry tracking</p>
            <p class="mt-1 text-xl font-bold text-navy">{{ $item->expiry_tracking ? 'Required' : 'Optional' }}</p>
            <p class="mt-2 text-xs text-slate-500">{{ $batches->where('remaining_quantity', '>', 0)->count() }} batch(es) in stock</p>
        </div>
    </div>

    @if ($item->description)
        <div class="card card-body mt-6 text-sm text-slate-600">{{ $item->description }}</div>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Batches (FEFO order)</h2>
                <a href="{{ route('inventory.batches.index', ['item' => $item->item_id, 'status' => 'ALL']) }}" class="text-sm font-medium text-accent hover:underline">Open batch tracking</a>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr><th>Batch</th><th>Received</th><th>Expires</th><th class="text-right">Initial</th><th class="text-right">Remaining</th><th>Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($batches as $batch)
                            @php($batchStatus = $batch->status())
                            <tr>
                                <td class="font-mono text-xs">{{ $batch->batch_number }}</td>
                                <td>{{ $batch->received_date->format('d M Y') }}</td>
                                <td>{{ $batch->expiration_date?->format('d M Y') ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ Decimal::formatQuantity($batch->initial_quantity) }}</td>
                                <td class="text-right font-semibold tabular-nums">{{ Decimal::formatQuantity($batch->remaining_quantity) }}</td>
                                <td><x-badge :color="$batchStatus->color()">{{ $batchStatus->label() }}</x-badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty-state icon="boxes" title="No batches yet" description="Record a Stock In or receive a purchase order to add stock." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Suppliers & prices</h2>
                @can('managePrices', App\Models\Supplier::class)
                    <a href="{{ route('suppliers.products.index', ['item' => $item->item_id]) }}" class="text-sm font-medium text-accent hover:underline">Manage</a>
                @endcan
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($supplierItems as $supplierItem)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div>
                            <a href="{{ route('suppliers.show', $supplierItem->supplier) }}" class="font-medium text-navy hover:text-accent">{{ $supplierItem->supplier->supplier_name }}</a>
                            @unless ($supplierItem->is_active && $supplierItem->supplier->is_active)
                                <x-badge color="neutral" class="ml-1">Inactive</x-badge>
                            @endunless
                        </div>
                        <span class="font-semibold tabular-nums">{{ Decimal::formatMoney($supplierItem->unit_price) }} / {{ $item->unit }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-sm text-slate-500">No supplier provides this item yet.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card mt-6">
        <div class="card-header">
            <h2 class="card-title">Recent stock transactions</h2>
            <a href="{{ route('reports.show', ['report' => 'stock-movements', 'search' => $item->item_name]) }}" class="text-sm font-medium text-accent hover:underline">Full history</a>
        </div>
        @include('inventory::history.partials.table', ['transactions' => $transactions, 'showItem' => false])
    </div>
@endsection
