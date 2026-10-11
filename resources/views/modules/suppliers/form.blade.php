@extends('layouts.app')

@section('title', $supplier ? 'Edit Supplier' : 'Add Supplier')

@section('breadcrumbs')
    <x-breadcrumbs :items="$supplier
        ? ['Supplier Management' => route('suppliers.index'), $supplier->supplier_name => route('suppliers.show', $supplier), 'Edit' => null]
        : ['Supplier Management' => route('suppliers.index'), 'Add Supplier' => null]" />
@endsection

@section('content')
    <x-page-header :title="$supplier ? 'Edit '.$supplier->supplier_name : 'Add Supplier'" subtitle="Contact details. Products and prices are managed on the supplier's page." />

    <livewire:suppliers.supplier-form :supplier="$supplier" />
@endsection
