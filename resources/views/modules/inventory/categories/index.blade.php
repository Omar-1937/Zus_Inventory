@extends('layouts.app')

@section('title', 'Categories')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Inventory Management' => route('inventory.index'), 'Categories' => null]" />
@endsection

@section('content')
    <livewire:inventory.category-manager />
@endsection
