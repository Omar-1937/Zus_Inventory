<?php

namespace App\Modules\DashboardReports\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\StockStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the dashboard is computed live from MySQL.
 */
class DashboardService
{
    /**
     * @return array{total_items: int, low_stock_items: int, expiring_soon_batches: int, expired_batches: int, pending_purchase_orders: int, active_suppliers: int}
     */
    public function summary(): array
    {
        return [
            'total_items' => InventoryItem::query()->active()->count(),
            'low_stock_items' => InventoryItem::query()->active()->lowStock()->count(),
            'expiring_soon_batches' => $this->batchesForActiveItems()->expiringSoon()->count(),
            'expired_batches' => $this->batchesForActiveItems()->expired()->count(),
            'pending_purchase_orders' => PurchaseOrder::query()->where('status', PurchaseOrderStatus::Pending)->count(),
            'active_suppliers' => Supplier::query()->active()->count(),
        ];
    }

    /**
     * Number of active items per category, split by stock status. (Quantities
     * are not summed across categories because units differ: L, kg, pcs ...)
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, data: array<int, int>, status: string}>}
     */
    public function stockByCategory(): array
    {
        $categories = Category::query()->orderBy('category_name')->get();
        $items = InventoryItem::query()->active()->withStockLevels()->get()->groupBy('category_id');

        $series = collect(StockStatus::cases())->mapWithKeys(fn (StockStatus $status): array => [$status->value => []]);

        foreach ($categories as $category) {
            /** @var Collection<int, InventoryItem> $categoryItems */
            $categoryItems = $items->get($category->category_id, new Collection);
            $counts = $categoryItems->countBy(fn (InventoryItem $item): string => $item->stockStatus()->value);

            foreach (StockStatus::cases() as $status) {
                $series[$status->value] = [...$series[$status->value], (int) ($counts[$status->value] ?? 0)];
            }
        }

        return [
            'labels' => $categories->pluck('category_name')->all(),
            'datasets' => collect(StockStatus::cases())->map(fn (StockStatus $status): array => [
                'label' => $status->label(),
                'status' => $status->value,
                'data' => $series[$status->value],
            ])->all(),
        ];
    }

    /**
     * Daily count of stock movements by type over the last N days.
     *
     * @return array{labels: array<int, string>, datasets: array<int, array{label: string, type: string, data: array<int, int>}>}
     */
    public function movementTrend(int $days = 14): array
    {
        $days = max(1, min($days, 90));
        $start = today()->subDays($days - 1);

        $rows = StockTransaction::query()
            ->where('created_at', '>=', $start)
            ->select([
                DB::raw('DATE(created_at) as day'),
                'transaction_type',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('day', 'transaction_type')
            ->get();

        $byDay = [];

        foreach ($rows as $row) {
            $type = $row->transaction_type instanceof TransactionType ? $row->transaction_type->value : $row->transaction_type;
            $byDay[Carbon::parse($row->day)->toDateString()][$type] = (int) $row->total;
        }

        $labels = [];
        $datasets = collect(TransactionType::cases())->mapWithKeys(fn (TransactionType $type): array => [$type->value => []])->all();

        for ($date = $start->copy(); $date->lte(today()); $date->addDay()) {
            $labels[] = $date->format('d M');

            foreach (TransactionType::cases() as $type) {
                $datasets[$type->value][] = $byDay[$date->toDateString()][$type->value] ?? 0;
            }
        }

        return [
            'labels' => $labels,
            'datasets' => collect(TransactionType::cases())->map(fn (TransactionType $type): array => [
                'label' => $type->label(),
                'type' => $type->value,
                'data' => $datasets[$type->value],
            ])->all(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, statuses: array<int, string>, data: array<int, int>}
     */
    public function purchaseOrderStatusDistribution(): array
    {
        $counts = PurchaseOrder::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'labels' => collect(PurchaseOrderStatus::cases())->map->label()->all(),
            'statuses' => collect(PurchaseOrderStatus::cases())->map->value->all(),
            'data' => collect(PurchaseOrderStatus::cases())->map(fn (PurchaseOrderStatus $status): int => (int) ($counts[$status->value] ?? 0))->all(),
        ];
    }

    /**
     * @return Collection<int, StockTransaction>
     */
    public function recentTransactions(int $limit = 8): Collection
    {
        return StockTransaction::query()
            ->with(['item', 'batch', 'user'])
            ->latest('created_at')
            ->latest('transaction_id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, InventoryItem>
     */
    public function lowStockItems(int $limit = 6): Collection
    {
        return InventoryItem::query()
            ->active()
            ->withStockLevels()
            ->lowStock()
            ->with('category')
            ->orderBy('usable_quantity')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, InventoryBatch>
     */
    public function expiringBatches(int $limit = 6): Collection
    {
        return $this->batchesForActiveItems()
            ->withStock()
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', today()->addDays(InventoryBatch::expiryThresholdDays()))
            ->with('item')
            ->orderBy('expiration_date')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function recentPurchaseOrders(int $limit = 6): Collection
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'creator'])
            ->latest('created_at')
            ->latest('purchase_order_id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Builder<InventoryBatch>
     */
    private function batchesForActiveItems(): Builder
    {
        return InventoryBatch::query()->whereHas('item', fn (Builder $query) => $query->where('is_active', true));
    }
}
