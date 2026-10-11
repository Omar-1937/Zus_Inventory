@extends('layouts.app')

@section('title', 'Search')

@section('content')
    <x-page-header title="Search results" :subtitle="$term !== '' ? 'Results for “'.$term.'”' : 'Type at least two characters in the search box.'" />

    <form method="GET" action="{{ route('search') }}" class="mb-6 max-w-xl md:hidden">
        <input name="q" type="search" value="{{ $term }}" class="form-input" placeholder="Search items, suppliers, POs…" aria-label="Search">
    </form>

    @php($total = $results['items']->count() + $results['suppliers']->count() + $results['orders']->count())

    @if ($term !== '' && $total === 0)
        <div class="card"><x-empty-state icon="search" title="No matches found" description="Try a different item name, supplier or PO number." /></div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        @if ($results['items']->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Inventory items</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($results['items'] as $item)
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <a href="{{ route('inventory.show', $item) }}" class="font-medium text-navy hover:text-accent">{{ $item->item_name }}</a>
                            <span class="text-slate-500">{{ Decimal::formatQuantity($item->usable_quantity) }} {{ $item->unit }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($results['suppliers']->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Suppliers</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($results['suppliers'] as $supplier)
                        <li class="px-5 py-3 text-sm"><a href="{{ route('suppliers.show', $supplier) }}" class="font-medium text-navy hover:text-accent">{{ $supplier->supplier_name }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($results['orders']->isNotEmpty())
            <div class="card">
                <div class="card-header"><h2 class="card-title">Purchase orders</h2></div>
                <ul class="divide-y divide-slate-100">
                    @foreach ($results['orders'] as $order)
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="font-mono font-medium text-navy hover:text-accent">{{ $order->reference() }}</a>
                            <x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endsection
