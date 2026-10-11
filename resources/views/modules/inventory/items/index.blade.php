@extends('layouts.app')

@section('title', 'All Inventory')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Inventory Management' => route('inventory.index'), 'All Inventory' => null]" />
@endsection

@section('content')
    <x-page-header title="Inventory" subtitle="Café ingredients and packaging. Quantities are calculated live from inventory batches.">
        <x-slot:actions>
            <a href="{{ route('inventory.stock.index', ['tab' => 'in']) }}" class="btn btn-secondary">
                <x-lucide-package-plus class="h-4 w-4" /> Stock In
            </a>
            <a href="{{ route('inventory.stock.index', ['tab' => 'out']) }}" class="btn btn-secondary">
                <x-lucide-package-minus class="h-4 w-4" /> Stock Out
            </a>
            @can('create', App\Models\InventoryItem::class)
                <a href="{{ route('inventory.create') }}" class="btn btn-primary">
                    <x-lucide-plus class="h-4 w-4" /> Add Item
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:inventory.inventory-table />
@endsection
