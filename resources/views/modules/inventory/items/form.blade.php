@extends('layouts.app')

@section('title', $item ? 'Edit Item' : 'Add Item')

@section('breadcrumbs')
    <x-breadcrumbs :items="array_filter([
        'Inventory Management' => route('inventory.index'),
        ($item?->item_name ?? '') => $item ? route('inventory.show', $item) : null,
        ($item ? 'Edit' : 'Add Item') => null,
    ], fn ($url, $label) => $label !== '', ARRAY_FILTER_USE_BOTH)" />
@endsection

@section('content')
    <x-page-header :title="$item ? 'Edit '.$item->item_name : 'Add Inventory Item'" subtitle="Item details only. Stock quantities are added through Stock In or purchase order receiving." />

    <livewire:inventory.item-form :item="$item" />
@endsection
