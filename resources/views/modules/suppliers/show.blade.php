@extends('layouts.app')

@section('title', $supplier->supplier_name)

@section('breadcrumbs')
    <x-breadcrumbs :items="['Supplier Management' => route('suppliers.index'), $supplier->supplier_name => null]" />
@endsection

@section('content')
    <x-page-header :title="$supplier->supplier_name" :subtitle="'Supplier #'.$supplier->supplier_id">
        <x-slot:actions>
            @if ($supplier->is_active)
                <a href="{{ route('purchase-orders.create', ['supplier_id' => $supplier->supplier_id]) }}" class="btn btn-accent"><x-lucide-shopping-cart class="h-4 w-4" /> New Purchase Order</a>
            @endif
            @can('update', $supplier)
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary"><x-lucide-pencil class="h-4 w-4" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Contact details</h2>
                <x-badge :color="$supplier->is_active ? 'success' : 'neutral'">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</x-badge>
            </div>
            <dl class="card-body space-y-4 text-sm">
                <div class="flex gap-3"><x-lucide-user class="h-4 w-4 shrink-0 text-slate-400" /><div><dt class="text-xs text-slate-500">Contact person</dt><dd class="font-medium">{{ $supplier->contact_person ?: '—' }}</dd></div></div>
                <div class="flex gap-3"><x-lucide-phone class="h-4 w-4 shrink-0 text-slate-400" /><div><dt class="text-xs text-slate-500">Phone</dt><dd class="font-medium">{{ $supplier->phone ?: '—' }}</dd></div></div>
                <div class="flex gap-3"><x-lucide-mail class="h-4 w-4 shrink-0 text-slate-400" /><div><dt class="text-xs text-slate-500">Email</dt><dd class="font-medium break-all">{{ $supplier->email ?: '—' }}</dd></div></div>
                <div class="flex gap-3"><x-lucide-map-pin class="h-4 w-4 shrink-0 text-slate-400" /><div><dt class="text-xs text-slate-500">Address</dt><dd class="font-medium whitespace-pre-line">{{ $supplier->address ?: '—' }}</dd></div></div>
            </dl>
        </div>

        <div class="grid grid-cols-2 gap-4 lg:col-span-2">
            <div class="card p-5"><p class="text-sm text-slate-500">Products supplied</p><p class="mt-1 text-3xl font-bold text-navy">{{ $stats['products'] }}</p></div>
            <div class="card p-5"><p class="text-sm text-slate-500">Purchase orders</p><p class="mt-1 text-3xl font-bold text-navy">{{ $stats['orders'] }}</p></div>
            <div class="card p-5"><p class="text-sm text-slate-500">Open orders</p><p class="mt-1 text-3xl font-bold text-navy">{{ $stats['open_orders'] }}</p></div>
            <div class="card p-5"><p class="text-sm text-slate-500">Total received value</p><p class="mt-1 text-2xl font-bold text-navy">{{ Decimal::formatMoney($stats['received_value']) }}</p></div>
        </div>
    </div>

    <div class="mt-6">
        <livewire:suppliers.supplier-products :supplier="$supplier" />
    </div>

    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Purchase history</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>PO</th><th>Order date</th><th>Status</th><th class="text-right">Lines</th><th class="text-right">Total</th><th>Created by</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchaseOrders as $order)
                        <tr>
                            <td><a href="{{ route('purchase-orders.show', $order) }}" class="font-mono font-semibold text-navy hover:text-accent">{{ $order->reference() }}</a></td>
                            <td>{{ $order->order_date->format('d M Y') }}</td>
                            <td><x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge></td>
                            <td class="text-right">{{ $order->items_count }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ Decimal::formatMoney($order->total_amount) }}</td>
                            <td>{{ $order->creator->full_name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="clipboard-list" title="No purchase orders yet" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $purchaseOrders->links() }}</div>
    </div>
@endsection
