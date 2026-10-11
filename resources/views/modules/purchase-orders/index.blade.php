@extends('layouts.app')

@section('title', 'Purchase Orders')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Purchase Orders' => route('purchase-orders.index'), 'All Purchase Orders' => null]" />
@endsection

@section('content')
    <x-page-header title="Purchase Orders" subtitle="PENDING → APPROVED → ORDERED → RECEIVED. Stock only increases when an order is received.">
        <x-slot:actions>
            @can('create', App\Models\PurchaseOrder::class)
                <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><x-lucide-file-plus class="h-4 w-4" /> Create Purchase Order</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:purchase-orders.purchase-order-table />
@endsection
