@extends('layouts.app')

@section('title', 'Receive '.$order->reference())

@section('breadcrumbs')
    <x-breadcrumbs :items="['Purchase Orders' => route('purchase-orders.index'), $order->reference() => route('purchase-orders.show', $order), 'Receive' => null]" />
@endsection

@section('content')
    <x-page-header :title="'Receive '.$order->reference()" subtitle="Enter the batch details printed on the delivered goods. Every item must be received in full; stock is added in a single transaction." />

    <livewire:purchase-orders.receive-purchase-order :purchase-order="$order" />
@endsection
