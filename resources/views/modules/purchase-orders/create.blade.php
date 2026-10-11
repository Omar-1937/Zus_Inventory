@extends('layouts.app')

@section('title', 'Create Purchase Order')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Purchase Orders' => route('purchase-orders.index'), 'Create Purchase Order' => null]" />
@endsection

@section('content')
    <x-page-header title="Create Purchase Order" subtitle="Unit prices come from the supplier's price list and cannot be edited here. Creating an order does not change stock." />

    <livewire:purchase-orders.create-purchase-order :item-id="$itemId" :supplier-id="$supplierId" />
@endsection
