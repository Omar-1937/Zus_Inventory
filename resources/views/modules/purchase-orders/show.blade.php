@extends('layouts.app')

@section('title', $order->reference())

@section('breadcrumbs')
    <x-breadcrumbs :items="['Purchase Orders' => route('purchase-orders.index'), $order->reference() => null]" />
@endsection

@php
    use App\Enums\PurchaseOrderStatus;
    $steps = [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Received];
    $currentIndex = array_search($order->status, $steps, true);
@endphp

@section('content')
    <x-page-header :title="$order->reference()" :subtitle="'Supplier: '.$order->supplier->supplier_name.' · ordered '.$order->order_date->format('d M Y')">
        <x-slot:actions>
            <button type="button" onclick="window.print()" class="btn btn-secondary"><x-lucide-printer class="h-4 w-4" /> Print</button>

            @if ($order->status === PurchaseOrderStatus::Pending)
                @can('approve', $order)
                    <a href="{{ route('purchase-orders.approve', $order) }}" class="btn btn-success"><x-lucide-check class="h-4 w-4" /> Approve</a>
                @endcan
            @endif

            @if ($order->status === PurchaseOrderStatus::Approved)
                @can('markOrdered', $order)
                    <form method="POST" action="{{ route('purchase-orders.mark-ordered', $order) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary"><x-lucide-send class="h-4 w-4" /> Mark as Ordered</button>
                    </form>
                @endcan
            @endif

            @if ($order->status === PurchaseOrderStatus::Ordered)
                @can('receive', $order)
                    <a href="{{ route('purchase-orders.receive', $order) }}" class="btn btn-accent"><x-lucide-inbox class="h-4 w-4" /> Receive Order</a>
                @endcan
            @endif

            @if (! $order->status->isTerminal())
                @can('cancel', $order)
                    <div x-data="{ open: false }">
                        <button type="button" class="btn btn-secondary text-danger" x-on:click="open = true"><x-lucide-ban class="h-4 w-4" /> Cancel</button>
                        <template x-teleport="body">
                            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                                <div class="fixed inset-0 bg-navy-900/60" x-on:click="open = false"></div>
                                <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                                    @csrf
                                    <h2 class="text-lg font-semibold text-navy">Cancel {{ $order->reference() }}?</h2>
                                    <p class="mt-1 text-sm text-slate-500">Cancelled orders cannot be reopened.</p>
                                    <label for="cancel_reason" class="form-label mt-4">Reason (optional)</label>
                                    <textarea id="cancel_reason" name="reason" rows="3" class="form-input" maxlength="500"></textarea>
                                    <div class="mt-5 flex justify-end gap-2">
                                        <button type="button" class="btn btn-secondary" x-on:click="open = false">Keep order</button>
                                        <button type="submit" class="btn btn-danger">Cancel order</button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Status tracker --}}
    <div class="card mb-6 p-5">
        @if ($order->status === PurchaseOrderStatus::Cancelled)
            <div class="flex items-center gap-3 text-danger">
                <x-lucide-ban class="h-6 w-6" />
                <div>
                    <p class="font-semibold">This purchase order was cancelled</p>
                    <p class="text-sm text-slate-500">{{ $order->cancelled_at?->format('d M Y, H:i') }}</p>
                </div>
            </div>
        @else
            <ol class="grid grid-cols-2 gap-4 sm:grid-cols-4" aria-label="Order progress">
                @foreach ($steps as $index => $step)
                    @php($done = $currentIndex !== false && $index <= $currentIndex)
                    <li class="flex items-center gap-3">
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                            'bg-navy text-white' => $done,
                            'bg-slate-100 text-slate-400' => ! $done,
                        ])>
                            @if ($done) <x-lucide-check class="h-4 w-4" /> @else {{ $index + 1 }} @endif
                        </span>
                        <span>
                            <span @class(['block text-sm font-semibold', 'text-navy' => $done, 'text-slate-400' => ! $done])>{{ $step->label() }}</span>
                            <span class="block text-xs text-slate-500">
                                @switch($step)
                                    @case(PurchaseOrderStatus::Pending) {{ $order->created_at?->format('d M, H:i') }} @break
                                    @case(PurchaseOrderStatus::Approved) {{ $order->approved_at?->format('d M, H:i') }} @break
                                    @case(PurchaseOrderStatus::Ordered) {{ $order->ordered_at?->format('d M, H:i') }} @break
                                    @case(PurchaseOrderStatus::Received) {{ $order->received_at?->format('d M, H:i') }} @break
                                @endswitch
                            </span>
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Order items</h2>
                <x-badge :color="$order->status->color()" data-testid="po-status">{{ $order->status->value }}</x-badge>
            </div>
            @include('purchase-orders::partials.items-table', ['order' => $order, 'showBatches' => $order->status === PurchaseOrderStatus::Received])
            @if ($order->notes)
                <div class="border-t border-slate-100 px-5 py-4 text-sm"><span class="font-semibold text-navy">Notes:</span> {{ $order->notes }}</div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Details</h2></div>
                <dl class="card-body grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-xs text-slate-500">Supplier</dt><dd class="font-medium"><a href="{{ route('suppliers.show', $order->supplier) }}" class="hover:text-accent">{{ $order->supplier->supplier_name }}</a></dd></div>
                    <div><dt class="text-xs text-slate-500">Created by</dt><dd class="font-medium">{{ $order->creator->full_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Approved by</dt><dd class="font-medium">{{ $order->approver?->full_name ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Received by</dt><dd class="font-medium">{{ $order->receiver?->full_name ?? '—' }}</dd></div>
                </dl>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Activity</h2></div>
                <ol class="card-body space-y-4">
                    @foreach ($activity as $log)
                        <li class="flex gap-3 text-sm">
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-accent"></span>
                            <div>
                                <p class="font-medium text-ink">
                                    @switch($log->action)
                                        @case('CREATE') Order created @break
                                        @case('STATUS_CHANGE') Status changed {{ $log->details['from'] ?? '' }} → {{ $log->details['to'] ?? '' }} @break
                                        @case('CANCEL') Order cancelled{{ isset($log->details['reason']) ? ': '.$log->details['reason'] : '' }} @break
                                        @case('RECEIVE') Goods received ({{ count($log->details['batches'] ?? []) }} batch(es)) @break
                                        @default {{ $log->action }}
                                    @endswitch
                                </p>
                                <p class="text-xs text-slate-500">{{ $log->user?->full_name ?? 'System' }} · {{ $log->created_at?->format('d M Y, H:i') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </div>
@endsection
