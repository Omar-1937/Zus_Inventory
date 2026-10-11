@extends('layouts.app')

@section('title', 'Batch & Expiry Tracking')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Inventory Management' => route('inventory.index'), 'Batch & Expiry Tracking' => null]" />
@endsection

@section('content')
    <livewire:inventory.batch-tracking />
@endsection
