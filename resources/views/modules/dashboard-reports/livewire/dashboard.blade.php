@php
    // Categorical hues (validated: dataviz palette, fixed order) for movement types & PO statuses.
    $categorical = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4'];
    // Stock status is a state, so it uses the reserved status colours (always shown with a label).
    $statusColors = ['IN_STOCK' => '#16A34A', 'LOW_STOCK' => '#F59E0B', 'OUT_OF_STOCK' => '#DC2626'];

    $categoryConfig = [
        'type' => 'bar',
        'data' => [
            'labels' => $stockByCategory['labels'],
            'datasets' => collect($stockByCategory['datasets'])->map(fn ($dataset) => [
                'label' => $dataset['label'],
                'data' => $dataset['data'],
                'backgroundColor' => $statusColors[$dataset['status']],
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ])->all(),
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => '#eef2f7'], 'title' => ['display' => true, 'text' => 'Items']],
            ],
        ],
    ];

    $trendConfig = [
        'type' => 'line',
        'data' => [
            'labels' => $movementTrend['labels'],
            'datasets' => collect($movementTrend['datasets'])->values()->map(fn ($dataset, $index) => [
                'label' => $dataset['label'],
                'data' => $dataset['data'],
                'borderColor' => $categorical[$index],
                'backgroundColor' => $categorical[$index],
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                'cubicInterpolationMode' => 'monotone',
            ])->all(),
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['maxTicksLimit' => 10]],
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0], 'grid' => ['color' => '#eef2f7'], 'title' => ['display' => true, 'text' => 'Transactions']],
            ],
        ],
    ];

    $statusConfig = [
        'type' => 'doughnut',
        'data' => [
            'labels' => $statusDistribution['labels'],
            'datasets' => [[
                'label' => 'Purchase orders',
                'data' => $statusDistribution['data'],
                'backgroundColor' => $categorical,
                'borderColor' => '#ffffff',
                'borderWidth' => 2,
            ]],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '62%',
            'plugins' => ['legend' => ['position' => 'bottom']],
        ],
    ];
@endphp

<div>
    <x-page-header title="Dashboard" :subtitle="'Live overview for '.config('inventory.branch_name').' · '.now()->format('l, d M Y')">
        <x-slot:actions>
            <a href="{{ route('inventory.stock.index') }}" class="btn btn-secondary"><x-lucide-arrow-left-right class="h-4 w-4" /> Stock In / Out</a>
            <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><x-lucide-file-plus class="h-4 w-4" /> New Purchase Order</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <x-stat-card label="Total Inventory Items" :value="$summary['total_items']" icon="package" :href="route('inventory.index')" />
        <x-stat-card label="Low Stock Items" :value="$summary['low_stock_items']" icon="triangle-alert" color="warning" :href="route('inventory.index', ['status' => 'LOW_STOCK'])" />
        <x-stat-card label="Expiring Soon Batches" :value="$summary['expiring_soon_batches']" icon="calendar-clock" color="warning" :href="route('inventory.batches.index', ['status' => 'EXPIRING_SOON'])" />
        <x-stat-card label="Expired Batches" :value="$summary['expired_batches']" icon="calendar-x" color="danger" :href="route('inventory.batches.index', ['status' => 'EXPIRED'])" />
        <x-stat-card label="Pending Purchase Orders" :value="$summary['pending_purchase_orders']" icon="clipboard-list" color="accent" :href="route('purchase-orders.index', ['status' => 'PENDING'])" />
        <x-stat-card label="Active Suppliers" :value="$summary['active_suppliers']" icon="truck" color="success" :href="route('suppliers.index')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Stock Movement Trend</h2>
                    <p class="text-xs text-slate-500">Number of stock transactions per day</p>
                </div>
                <select wire:model.live="trendDays" class="form-input w-36" aria-label="Trend period">
                    <option value="7">Last 7 days</option>
                    <option value="14">Last 14 days</option>
                    <option value="30">Last 30 days</option>
                    <option value="90">Last 90 days</option>
                </select>
            </div>
            <div class="card-body">
                <div wire:ignore x-data="chart(@js($trendConfig), 'dashboard-trend-updated')" class="relative h-72">
                    <canvas x-ref="canvas" role="img" aria-label="Line chart of daily stock movements by type"></canvas>
                </div>
                @include('dashboard-reports::partials.chart-table', ['labels' => $movementTrend['labels'], 'datasets' => $movementTrend['datasets'], 'labelHeader' => 'Day'])
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Purchase Order Status</h2></div>
            <div class="card-body">
                <div wire:ignore x-data="chart(@js($statusConfig))" class="relative h-72">
                    <canvas x-ref="canvas" role="img" aria-label="Doughnut chart of purchase orders by status"></canvas>
                </div>
                @include('dashboard-reports::partials.chart-table', ['labels' => $statusDistribution['labels'], 'datasets' => [['label' => 'Orders', 'data' => $statusDistribution['data']]], 'labelHeader' => 'Status'])
            </div>
        </div>

        <div class="card xl:col-span-3">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Stock by Category</h2>
                    <p class="text-xs text-slate-500">Active items per category, by stock status (units differ, so quantities are not summed)</p>
                </div>
            </div>
            <div class="card-body">
                @if ($stockByCategory['labels'] === [])
                    <x-empty-state icon="tags" title="No categories yet" description="Create categories and items to see stock by category." />
                @else
                    <div wire:ignore x-data="chart(@js($categoryConfig))" class="relative h-72">
                        <canvas x-ref="canvas" role="img" aria-label="Stacked bar chart of items per category by stock status"></canvas>
                    </div>
                    @include('dashboard-reports::partials.chart-table', ['labels' => $stockByCategory['labels'], 'datasets' => $stockByCategory['datasets'], 'labelHeader' => 'Category'])
                @endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Low-stock alerts</h2>
                <a href="{{ route('reports.show', 'low-stock') }}" class="text-sm font-medium text-accent hover:underline">Low stock report</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($lowStockItems as $item)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm" data-testid="low-stock-item">
                        <div class="min-w-0">
                            <a href="{{ route('inventory.show', $item) }}" class="font-semibold text-navy hover:text-accent">{{ $item->item_name }}</a>
                            <p class="text-xs text-slate-500">{{ Decimal::formatQuantity($item->usable_quantity) }} {{ $item->unit }} usable · minimum {{ Decimal::formatQuantity($item->minimum_stock) }} {{ $item->unit }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-badge :color="$item->stockStatus()->color()">{{ $item->stockStatus()->label() }}</x-badge>
                            <a href="{{ route('purchase-orders.create', ['item_id' => $item->item_id]) }}" class="btn btn-secondary btn-sm">Reorder</a>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="circle-check" title="All items are above minimum stock" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Expiring & expired batches</h2>
                <a href="{{ route('inventory.batches.index') }}" class="text-sm font-medium text-accent hover:underline">Batch tracking</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($expiringBatches as $batch)
                    @php($batchStatus = $batch->status())
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-navy">{{ $batch->item->item_name }} <span class="font-mono text-xs font-normal text-slate-500">{{ $batch->batch_number }}</span></p>
                            <p class="text-xs text-slate-500">{{ Decimal::formatQuantity($batch->remaining_quantity) }} {{ $batch->item->unit }} · expires {{ $batch->expiration_date->format('d M Y') }}</p>
                        </div>
                        <x-badge :color="$batchStatus->color()">{{ $batchStatus->label() }}</x-badge>
                    </li>
                @empty
                    <li><x-empty-state icon="calendar-clock" title="No batches expiring soon" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent stock transactions</h2>
                <a href="{{ route('reports.show', 'stock-movements') }}" class="text-sm font-medium text-accent hover:underline">View all</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentTransactions as $transaction)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-navy">{{ $transaction->item->item_name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $transaction->created_at?->format('d M, H:i') }} · {{ $transaction->user->full_name }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-badge :color="$transaction->transaction_type->color()">{{ $transaction->transaction_type->label() }}</x-badge>
                            <span class="w-24 text-right text-xs font-semibold tabular-nums {{ Decimal::isPositive($transaction->quantity_change) ? 'text-success' : 'text-danger' }}">
                                {{ Decimal::isPositive($transaction->quantity_change) ? '+' : '' }}{{ Decimal::formatQuantity($transaction->quantity_change) }} {{ $transaction->item->unit }}
                            </span>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="history" title="No stock movements yet" /></li>
                @endforelse
            </ul>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recent purchase orders</h2>
                <a href="{{ route('purchase-orders.index') }}" class="text-sm font-medium text-accent hover:underline">View all</a>
            </div>
            <ul class="divide-y divide-slate-100">
                @forelse ($recentOrders as $order)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('purchase-orders.show', $order) }}" class="font-mono font-semibold text-navy hover:text-accent">{{ $order->reference() }}</a>
                            <p class="truncate text-xs text-slate-500">{{ $order->supplier->supplier_name }} · {{ $order->order_date->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-semibold tabular-nums">{{ Decimal::formatMoney($order->total_amount) }}</span>
                            <x-badge :color="$order->status->color()">{{ $order->status->label() }}</x-badge>
                        </div>
                    </li>
                @empty
                    <li><x-empty-state icon="clipboard-list" title="No purchase orders yet" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
