@extends('layouts.app')

@section('title', 'Suppliers')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Supplier Management' => route('suppliers.index'), 'Suppliers' => null]" />
@endsection

@section('content')
    <x-page-header title="Suppliers" subtitle="Vendors that deliver ingredients and packaging to this branch.">
        <x-slot:actions>
            <a href="{{ route('suppliers.products.index') }}" class="btn btn-secondary"><x-lucide-badge-dollar-sign class="h-4 w-4" /> Products & Prices</a>
            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-lucide-plus class="h-4 w-4" /> Add Supplier</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:suppliers.supplier-table />
@endsection
