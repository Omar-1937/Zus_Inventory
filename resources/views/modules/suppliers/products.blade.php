@extends('layouts.app')

@section('title', 'Supplier Products & Prices')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Supplier Management' => route('suppliers.index'), 'Products & Prices' => null]" />
@endsection

@section('content')
    <x-page-header title="Supplier Products & Prices" subtitle="Which supplier provides which item, and at what unit price. Purchase orders always use these prices." />

    <livewire:suppliers.supplier-products />
@endsection
