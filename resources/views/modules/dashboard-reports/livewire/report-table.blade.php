@php
    $titles = App\Modules\DashboardReports\Services\ReportService::REPORTS;
    $subtitles = [
        'current-stock' => 'Physical and usable quantity of every item, calculated from inventory batches.',
        'low-stock' => 'Active items whose usable stock is at or below the minimum stock level.',
        'expiry' => 'Batches with remaining stock and an expiration date.',
        'stock-movements' => 'All stock transactions (IN, OUT, ADJUSTMENT, DISPOSAL).',
        'purchase-orders' => 'Purchase orders with status and totals.',
    ];
    $statusModel = $report === 'stock-movements' ? 'type' : 'status';
@endphp

<div>
    <div class="mb-4 hidden print:block">
        <p class="text-lg font-bold text-navy">ZUS Coffee — {{ config('inventory.branch_name') }}</p>
        <p class="text-xs text-slate-500">Printed {{ now()->format('d M Y, H:i') }} by {{ auth()->user()->full_name }}</p>
    </div>

    <x-page-header :title="$titles[$report]" :subtitle="$subtitles[$report]">
        <x-slot:actions>
            <a href="{{ $exportUrl }}" class="btn btn-secondary" data-testid="export-csv"><x-lucide-download class="h-4 w-4" /> Export CSV</a>
            <button type="button" onclick="window.print()" class="btn btn-primary"><x-lucide-printer class="h-4 w-4" /> Print</button>
        </x-slot:actions>
    </x-page-header>

    <nav class="no-print mb-4 flex flex-wrap gap-2" aria-label="Reports">
        @foreach ($titles as $key => $label)
            <a href="{{ route('reports.show', $key) }}" @class(['rounded-full px-3 py-1.5 text-xs font-semibold transition', 'bg-navy text-white' => $key === $report, 'bg-white text-slate-600 ring-1 ring-slate-200 hover:text-navy' => $key !== $report])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card">
        <div class="card-header no-print">
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="relative lg:col-span-2">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search…" aria-label="Search report">
                </div>

                @if ($statusOptions !== [])
                    <select wire:model.live="{{ $statusModel }}" class="form-input" aria-label="Filter by status">
                        <option value="">{{ $report === 'stock-movements' ? 'All types' : 'All statuses' }}</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($categories->isNotEmpty())
                    <select wire:model.live="categoryId" class="form-input" aria-label="Filter by category">
                        <option value="">All categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->category_id }}">{{ $category->category_name }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($suppliers->isNotEmpty())
                    <select wire:model.live="supplierId" class="form-input" aria-label="Filter by supplier">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->supplier_id }}">{{ $supplier->supplier_name }}</option>
                        @endforeach
                    </select>
                @endif

                @if ($hasDateRange)
                    <div class="flex gap-2 lg:col-span-2">
                        <input type="date" wire:model.live="from" class="form-input" aria-label="From date" title="From">
                        <input type="date" wire:model.live="to" class="form-input" aria-label="To date" title="To">
                    </div>
                @endif
            </div>
            @if ($errors->any())
                <p class="form-error w-full">{{ $errors->first() }}</p>
            @endif
        </div>

        @if ($summary)
            <div class="grid grid-cols-2 gap-4 border-b border-slate-100 px-5 py-4 text-sm">
                <div><span class="text-slate-500">Orders</span><p class="text-xl font-bold text-navy">{{ $summary['orders'] }}</p></div>
                <div><span class="text-slate-500">Total value</span><p class="text-xl font-bold text-navy">{{ Decimal::formatMoney($summary['value']) }}</p></div>
            </div>
        @endif

        <div class="table-wrap relative">
            <div wire:loading.flex class="no-print absolute inset-0 z-10 items-center justify-center bg-white/60"><x-spinner class="h-6 w-6 text-accent" /></div>
            @include('dashboard-reports::reports.partials.'.$report, ['rows' => $rows])
        </div>
        <div class="no-print border-t border-slate-100 px-5 py-3">{{ $rows->links() }}</div>
    </div>
</div>
