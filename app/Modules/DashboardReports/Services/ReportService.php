<?php

namespace App\Modules\DashboardReports\Services;

use App\Enums\BatchStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockStatus;
use App\Enums\TransactionType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Services\Decimal;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Builds the Eloquent queries behind the five reports. The same query is used
 * for the on-screen table and for the CSV export, so both always match.
 */
class ReportService
{
    public const REPORTS = [
        'current-stock' => 'Current Stock Report',
        'low-stock' => 'Low Stock Report',
        'expiry' => 'Expiration Report',
        'stock-movements' => 'Stock Movement Report',
        'purchase-orders' => 'Purchase Order Report',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function query(string $report, array $filters): Builder
    {
        return match ($report) {
            'current-stock' => $this->currentStock($filters),
            'low-stock' => $this->lowStock($filters),
            'expiry' => $this->expiry($filters),
            'stock-movements' => $this->stockMovements($filters),
            'purchase-orders' => $this->purchaseOrders($filters),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryItem>
     */
    public function currentStock(array $filters): Builder
    {
        $query = InventoryItem::query()
            ->withStockLevels()
            ->with('category')
            ->when(! ($filters['include_inactive'] ?? false), fn (Builder $query) => $query->active())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('inventory_items.item_name', 'like', "%{$search}%"))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('inventory_items.category_id', $categoryId));

        match ($filters['status'] ?? null) {
            StockStatus::LowStock->value => $query->lowStock(),
            StockStatus::OutOfStock->value => $query->outOfStock(),
            StockStatus::InStock->value => $query->whereNotIn('inventory_items.item_id', InventoryItem::query()->lowStock()->select('item_id'))
                ->whereNotIn('inventory_items.item_id', InventoryItem::query()->outOfStock()->select('item_id')),
            default => null,
        };

        return $query->orderBy('inventory_items.item_name');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryItem>
     */
    public function lowStock(array $filters): Builder
    {
        return InventoryItem::query()
            ->active()
            ->withStockLevels()
            ->lowStock()
            ->with(['category', 'supplierItems' => fn ($query) => $query->active()->with('supplier')->orderBy('unit_price')])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('inventory_items.item_name', 'like', "%{$search}%"))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('inventory_items.category_id', $categoryId))
            ->orderBy('usable_quantity')
            ->orderBy('inventory_items.item_name');
    }

    /**
     * Batches that still hold stock and carry an expiration date.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<InventoryBatch>
     */
    public function expiry(array $filters): Builder
    {
        $query = InventoryBatch::query()
            ->with('item.category')
            ->withStock()
            ->whereNotNull('expiration_date')
            ->whereHas('item', function (Builder $query) use ($filters): void {
                $query->where('is_active', true)
                    ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('item_name', 'like', "%{$search}%"))
                    ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('category_id', $categoryId));
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('expiration_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('expiration_date', '<=', $to));

        match ($filters['status'] ?? null) {
            BatchStatus::Expired->value => $query->expired(),
            BatchStatus::ExpiringSoon->value => $query->expiringSoon(),
            BatchStatus::Good->value => $query->whereDate('expiration_date', '>', today()->addDays(InventoryBatch::expiryThresholdDays())),
            default => null,
        };

        return $query->orderBy('expiration_date')->orderBy('batch_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<StockTransaction>
     */
    public function stockMovements(array $filters): Builder
    {
        return StockTransaction::query()
            ->with(['item', 'batch', 'user'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('transaction_type', $type))
            ->when($filters['item_id'] ?? null, fn (Builder $query, $itemId) => $query->where('item_id', $itemId))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->whereHas('item', fn (Builder $query) => $query->where('item_name', 'like', "%{$search}%"))
                    ->orWhereHas('batch', fn (Builder $query) => $query->where('batch_number', 'like', "%{$search}%"))
                    ->orWhere('reason', 'like', "%{$search}%");
            }))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest('created_at')
            ->latest('transaction_id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PurchaseOrder>
     */
    public function purchaseOrders(array $filters): Builder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['supplier_id'] ?? null, fn (Builder $query, $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $id = (int) preg_replace('/\D/', '', $search);
                $query->where(function (Builder $query) use ($search, $id): void {
                    $query->whereHas('supplier', fn (Builder $query) => $query->where('supplier_name', 'like', "%{$search}%"))
                        ->when($id > 0, fn (Builder $query) => $query->orWhere('purchase_order_id', $id));
                });
            })
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('order_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('order_date', '<=', $to))
            ->latest('order_date')
            ->latest('purchase_order_id');
    }

    /**
     * Column definitions for CSV export: header => value resolver.
     *
     * @return array<string, Closure(mixed): (string|int|null)>
     */
    public function csvColumns(string $report): array
    {
        return match ($report) {
            'current-stock' => [
                'Item ID' => fn (InventoryItem $item) => $item->item_id,
                'Item Name' => fn (InventoryItem $item) => $item->item_name,
                'Category' => fn (InventoryItem $item) => $item->category?->category_name,
                'Physical Quantity' => fn (InventoryItem $item) => $item->physicalQuantity(),
                'Usable Quantity' => fn (InventoryItem $item) => $item->usableQuantity(),
                'Unit' => fn (InventoryItem $item) => $item->unit,
                'Minimum Stock' => fn (InventoryItem $item) => Decimal::quantity($item->minimum_stock),
                'Stock Status' => fn (InventoryItem $item) => $item->stockStatus()->label(),
                'Active' => fn (InventoryItem $item) => $item->is_active ? 'Yes' : 'No',
            ],
            'low-stock' => [
                'Item ID' => fn (InventoryItem $item) => $item->item_id,
                'Item Name' => fn (InventoryItem $item) => $item->item_name,
                'Category' => fn (InventoryItem $item) => $item->category?->category_name,
                'Usable Quantity' => fn (InventoryItem $item) => $item->usableQuantity(),
                'Minimum Stock' => fn (InventoryItem $item) => Decimal::quantity($item->minimum_stock),
                'Shortfall' => fn (InventoryItem $item) => Decimal::quantity(Decimal::sub($item->minimum_stock, $item->usableQuantity())),
                'Unit' => fn (InventoryItem $item) => $item->unit,
                'Cheapest Supplier' => fn (InventoryItem $item) => $item->supplierItems->first()?->supplier?->supplier_name,
                'Unit Price (RM)' => fn (InventoryItem $item) => $item->supplierItems->first()?->unit_price,
            ],
            'expiry' => [
                'Batch ID' => fn (InventoryBatch $batch) => $batch->batch_id,
                'Batch Number' => fn (InventoryBatch $batch) => $batch->batch_number,
                'Item' => fn (InventoryBatch $batch) => $batch->item->item_name,
                'Category' => fn (InventoryBatch $batch) => $batch->item->category?->category_name,
                'Received Date' => fn (InventoryBatch $batch) => $batch->received_date->toDateString(),
                'Expiration Date' => fn (InventoryBatch $batch) => $batch->expiration_date?->toDateString(),
                'Days Until Expiry' => fn (InventoryBatch $batch) => $batch->daysUntilExpiry(),
                'Remaining Quantity' => fn (InventoryBatch $batch) => Decimal::quantity($batch->remaining_quantity),
                'Unit' => fn (InventoryBatch $batch) => $batch->item->unit,
                'Status' => fn (InventoryBatch $batch) => $batch->status()->label(),
            ],
            'stock-movements' => [
                'Transaction ID' => fn (StockTransaction $transaction) => $transaction->transaction_id,
                'Date/Time' => fn (StockTransaction $transaction) => $transaction->created_at?->format('Y-m-d H:i:s'),
                'Type' => fn (StockTransaction $transaction) => $transaction->transaction_type->value,
                'Item' => fn (StockTransaction $transaction) => $transaction->item->item_name,
                'Batch Number' => fn (StockTransaction $transaction) => $transaction->batch->batch_number,
                'Quantity Change' => fn (StockTransaction $transaction) => Decimal::quantity($transaction->quantity_change),
                'Unit' => fn (StockTransaction $transaction) => $transaction->item->unit,
                'Reason' => fn (StockTransaction $transaction) => $transaction->reason,
                'Notes' => fn (StockTransaction $transaction) => $transaction->notes,
                'Employee' => fn (StockTransaction $transaction) => $transaction->user->full_name,
            ],
            'purchase-orders' => [
                'PO Number' => fn (PurchaseOrder $order) => $order->reference(),
                'Order Date' => fn (PurchaseOrder $order) => $order->order_date->toDateString(),
                'Supplier' => fn (PurchaseOrder $order) => $order->supplier->supplier_name,
                'Status' => fn (PurchaseOrder $order) => $order->status->value,
                'Line Items' => fn (PurchaseOrder $order) => $order->items_count,
                'Total Amount (RM)' => fn (PurchaseOrder $order) => Decimal::money($order->total_amount),
                'Created By' => fn (PurchaseOrder $order) => $order->creator->full_name,
                'Received At' => fn (PurchaseOrder $order) => $order->received_at?->format('Y-m-d H:i'),
            ],
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCsv(string $report, array $filters): StreamedResponse
    {
        $columns = $this->csvColumns($report);
        $query = $this->query($report, $filters);
        $filename = $report.'-report-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($columns, $query): void {
            $handle = fopen('php://output', 'w');
            // UTF-8 BOM so Excel opens the file with the correct encoding.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_keys($columns));

            foreach ($query->lazy(200) as $row) {
                fputcsv($handle, array_map(fn (Closure $resolve) => $this->csvSafe($resolve($row)), $columns));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(string $report): array
    {
        return match ($report) {
            'current-stock' => collect(StockStatus::cases())->mapWithKeys(fn (StockStatus $status): array => [$status->value => $status->label()])->all(),
            'expiry' => collect([BatchStatus::Expired, BatchStatus::ExpiringSoon, BatchStatus::Good])->mapWithKeys(fn (BatchStatus $status): array => [$status->value => $status->label()])->all(),
            'purchase-orders' => collect(PurchaseOrderStatus::cases())->mapWithKeys(fn (PurchaseOrderStatus $status): array => [$status->value => $status->label()])->all(),
            'stock-movements' => collect(TransactionType::cases())->mapWithKeys(fn (TransactionType $type): array => [$type->value => $type->label()])->all(),
            default => [],
        };
    }

    /**
     * Neutralise spreadsheet formula injection (=, +, -, @ at the start of text).
     */
    private function csvSafe(string|int|float|null $value): string|int|float|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '@'], true)) {
            return "'".$value;
        }

        if (is_string($value) && str_starts_with($value, '-') && ! is_numeric($value)) {
            return "'".$value;
        }

        return $value;
    }
}
