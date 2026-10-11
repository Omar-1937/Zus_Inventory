@extends('layouts.app')

@section('title', 'Stock In / Stock Out')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Inventory Management' => route('inventory.index'), 'Stock In / Stock Out' => null]" />
@endsection

@section('content')
    <x-page-header title="Stock In / Stock Out" subtitle="Record deliveries received at the counter and ingredients used. Stock Out follows FEFO — First Expired, First Out." />

    <div x-data="{ tab: @js($tab) }">
        <div class="mb-5 inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm" role="tablist">
            <button type="button" role="tab" x-on:click="tab = 'in'" x-bind:aria-selected="tab === 'in'"
                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                x-bind:class="tab === 'in' ? 'bg-navy text-white shadow' : 'text-slate-600 hover:text-navy'">
                <x-lucide-package-plus class="h-4 w-4" /> Stock In
            </button>
            <button type="button" role="tab" x-on:click="tab = 'out'" x-bind:aria-selected="tab === 'out'"
                class="flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition"
                x-bind:class="tab === 'out' ? 'bg-navy text-white shadow' : 'text-slate-600 hover:text-navy'">
                <x-lucide-package-minus class="h-4 w-4" /> Stock Out
            </button>
        </div>

        <div class="grid gap-6 xl:grid-cols-5">
            <div class="xl:col-span-3">
                <div x-show="tab === 'in'" @if ($tab !== 'in') x-cloak @endif>
                    <livewire:inventory.stock-in-form :item-id="$itemId" />
                </div>
                <div x-show="tab === 'out'" @if ($tab !== 'out') x-cloak @endif>
                    <livewire:inventory.stock-out-form :item-id="$itemId" />
                </div>
            </div>
            <div class="xl:col-span-2">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Latest movements</h2>
                        <a href="{{ route('reports.show', 'stock-movements') }}" class="text-sm font-medium text-accent hover:underline">View all</a>
                    </div>
                    <livewire:inventory.stock-history-table :compact="true" />
                </div>
            </div>
        </div>
    </div>
@endsection
