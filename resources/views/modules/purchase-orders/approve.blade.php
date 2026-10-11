@extends('layouts.app')

@section('title', 'Approve '.$order->reference())

@section('breadcrumbs')
    <x-breadcrumbs :items="['Purchase Orders' => route('purchase-orders.index'), $order->reference() => route('purchase-orders.show', $order), 'Approve' => null]" />
@endsection

@section('content')
    <x-page-header :title="'Approve '.$order->reference()" subtitle="Review the order before approving. Only administrators can approve purchase orders." />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">{{ $order->supplier->supplier_name }}</h2>
                <x-badge :color="$order->status->color()">{{ $order->status->value }}</x-badge>
            </div>
            @include('purchase-orders::partials.items-table', ['order' => $order])
            @if ($order->notes)
                <div class="border-t border-slate-100 px-5 py-4 text-sm"><span class="font-semibold text-navy">Notes:</span> {{ $order->notes }}</div>
            @endif
        </div>

        <div class="card h-fit">
            <div class="card-body space-y-3 text-sm">
                <p><span class="text-slate-500">Requested by</span><br><span class="font-semibold">{{ $order->creator->full_name }}</span> on {{ $order->created_at?->format('d M Y, H:i') }}</p>
                <p><span class="text-slate-500">Order total</span><br><span class="text-2xl font-bold text-navy">{{ Decimal::formatMoney($order->total_amount) }}</span></p>
            </div>
            <div class="flex flex-col gap-2 border-t border-slate-100 bg-slate-50/60 px-5 py-4">
                <form method="POST" action="{{ route('purchase-orders.approve.store', $order) }}">
                    @csrf
                    <button type="submit" class="btn btn-success w-full"><x-lucide-check class="h-4 w-4" /> Approve purchase order</button>
                </form>
                <a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-secondary w-full">Back</a>
            </div>
        </div>
    </div>
@endsection
