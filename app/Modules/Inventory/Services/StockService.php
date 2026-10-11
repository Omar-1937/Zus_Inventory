<?php

namespace App\Modules\Inventory\Services;

use App\Enums\TransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\PurchaseOrderItem;
use App\Models\StockTransaction;
use App\Models\User;
use App\Modules\Inventory\Events\StockLevelChanged;
use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Services\AuditLogger;
use App\Services\Decimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * All stock movements go through this service. Every method runs inside a
 * database transaction and takes row locks (SELECT ... FOR UPDATE) so that
 * concurrent requests can never overspend or double-count stock.
 */
class StockService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Receive stock into a new batch and record an IN transaction.
     *
     * @param  array{batch_number: string, quantity: string|int|float, received_date?: string|null, expiration_date?: string|null, notes?: string|null, reason?: string|null}  $data
     */
    public function stockIn(InventoryItem $item, array $data, User $user, ?PurchaseOrderItem $purchaseOrderItem = null): InventoryBatch
    {
        return DB::transaction(function () use ($item, $data, $user, $purchaseOrderItem): InventoryBatch {
            /** @var InventoryItem $item */
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->item_id);

            if (! $item->is_active) {
                throw new BusinessRuleException("{$item->item_name} is inactive and cannot receive stock.");
            }

            $quantity = Decimal::quantity($data['quantity']);

            if (! Decimal::isPositive($quantity)) {
                throw new BusinessRuleException('Quantity received must be greater than zero.');
            }

            $batchNumber = trim($data['batch_number']);
            $receivedDate = Carbon::parse($data['received_date'] ?? today())->startOfDay();
            $expirationDate = filled($data['expiration_date'] ?? null)
                ? Carbon::parse($data['expiration_date'])->startOfDay()
                : null;

            if ($item->expiry_tracking && $expirationDate === null) {
                throw new BusinessRuleException("An expiration date is required for {$item->item_name}.");
            }

            if ($expirationDate !== null && $expirationDate->lt($receivedDate)) {
                throw new BusinessRuleException('The expiration date cannot be before the received date.');
            }

            if ($expirationDate !== null && $expirationDate->lt(today())) {
                throw new BusinessRuleException('Stock that has already expired cannot be received.');
            }

            if ($receivedDate->gt(today())) {
                throw new BusinessRuleException('The received date cannot be in the future.');
            }

            $duplicate = InventoryBatch::query()
                ->where('item_id', $item->item_id)
                ->where('batch_number', $batchNumber)
                ->exists();

            if ($duplicate) {
                throw new BusinessRuleException("Batch number {$batchNumber} already exists for {$item->item_name}.");
            }

            $batch = InventoryBatch::query()->create([
                'item_id' => $item->item_id,
                'po_item_id' => $purchaseOrderItem?->po_item_id,
                'batch_number' => $batchNumber,
                'received_date' => $receivedDate->toDateString(),
                'expiration_date' => $expirationDate?->toDateString(),
                'initial_quantity' => $quantity,
                'remaining_quantity' => $quantity,
            ]);

            StockTransaction::query()->create([
                'item_id' => $item->item_id,
                'batch_id' => $batch->batch_id,
                'user_id' => $user->user_id,
                'transaction_type' => TransactionType::In,
                'quantity_change' => $quantity,
                'reason' => $data['reason'] ?? ($purchaseOrderItem
                    ? 'Received from purchase order '.$purchaseOrderItem->purchaseOrder->reference()
                    : 'Stock received'),
                'notes' => $data['notes'] ?? null,
            ]);

            $this->audit->log('STOCK_IN', $batch, details: [
                'item_id' => $item->item_id,
                'item_name' => $item->item_name,
                'batch_number' => $batchNumber,
                'quantity' => $quantity,
                'expiration_date' => $expirationDate?->toDateString(),
                'po_item_id' => $purchaseOrderItem?->po_item_id,
            ], user: $user);

            StockLevelChanged::dispatch([$item->item_id]);

            return $batch;
        });
    }

    /**
     * Remove stock using FEFO (First Expired, First Out). Expired batches are
     * never consumed; non-expiring batches are used after dated ones.
     *
     * @return Collection<int, StockTransaction>
     *
     * @throws InsufficientStockException
     */
    public function stockOut(InventoryItem $item, string|int|float $quantity, string $reason, ?string $notes, User $user): Collection
    {
        return DB::transaction(function () use ($item, $quantity, $reason, $notes, $user): Collection {
            // Locking the item row serialises all stock-outs for this item.
            /** @var InventoryItem $item */
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($item->item_id);

            if (! $item->is_active) {
                throw new BusinessRuleException("{$item->item_name} is inactive. Reactivate it before recording usage.");
            }

            $requested = Decimal::quantity($quantity);

            if (! Decimal::isPositive($requested)) {
                throw new BusinessRuleException('Quantity used must be greater than zero.');
            }

            /** @var Collection<int, InventoryBatch> $batches */
            $batches = InventoryBatch::query()
                ->where('item_id', $item->item_id)
                ->usable()
                ->fefoOrder()
                ->lockForUpdate()
                ->get();

            $available = Decimal::sum($batches->pluck('remaining_quantity'), Decimal::QUANTITY_SCALE);

            if (Decimal::compare($available, $requested) < 0) {
                throw new InsufficientStockException(sprintf(
                    'Insufficient usable stock for %s. Requested %s %s but only %s %s is available (expired stock is excluded).',
                    $item->item_name,
                    Decimal::formatQuantity($requested),
                    $item->unit,
                    Decimal::formatQuantity($available),
                    $item->unit,
                ), ['requested' => $requested, 'available' => $available]);
            }

            $outstanding = $requested;
            $transactions = collect();
            $allocations = [];

            foreach ($batches as $batch) {
                if (! Decimal::isPositive($outstanding)) {
                    break;
                }

                $take = Decimal::quantity(Decimal::min($batch->remaining_quantity, $outstanding));

                $batch->remaining_quantity = Decimal::quantity(Decimal::sub($batch->remaining_quantity, $take));
                $batch->save();

                $transactions->push(StockTransaction::query()->create([
                    'item_id' => $item->item_id,
                    'batch_id' => $batch->batch_id,
                    'user_id' => $user->user_id,
                    'transaction_type' => TransactionType::Out,
                    'quantity_change' => Decimal::quantity('-'.$take),
                    'reason' => $reason,
                    'notes' => $notes,
                ]));

                $allocations[] = ['batch_id' => $batch->batch_id, 'batch_number' => $batch->batch_number, 'quantity' => $take];
                $outstanding = Decimal::quantity(Decimal::sub($outstanding, $take));
            }

            $this->audit->log('STOCK_OUT', $item, details: [
                'item_name' => $item->item_name,
                'quantity' => $requested,
                'reason' => $reason,
                'allocations' => $allocations,
            ], user: $user);

            StockLevelChanged::dispatch([$item->item_id]);

            return $transactions;
        });
    }

    /**
     * Write off stock from a batch (typically an expired batch).
     */
    public function dispose(InventoryBatch $batch, string|int|float|null $quantity, string $reason, ?string $notes, User $user): StockTransaction
    {
        return DB::transaction(function () use ($batch, $quantity, $reason, $notes, $user): StockTransaction {
            /** @var InventoryBatch $batch */
            $batch = InventoryBatch::query()->with('item')->lockForUpdate()->findOrFail($batch->batch_id);

            if (! Decimal::isPositive($batch->remaining_quantity)) {
                throw new BusinessRuleException("Batch {$batch->batch_number} has no remaining stock to dispose of.");
            }

            $toDispose = $quantity === null || $quantity === ''
                ? Decimal::quantity($batch->remaining_quantity)
                : Decimal::quantity($quantity);

            if (! Decimal::isPositive($toDispose)) {
                throw new BusinessRuleException('Disposal quantity must be greater than zero.');
            }

            if (Decimal::compare($toDispose, $batch->remaining_quantity) > 0) {
                throw new BusinessRuleException(sprintf(
                    'Cannot dispose of %s %s; batch %s only has %s %s remaining.',
                    Decimal::formatQuantity($toDispose),
                    $batch->item->unit,
                    $batch->batch_number,
                    Decimal::formatQuantity($batch->remaining_quantity),
                    $batch->item->unit,
                ));
            }

            $batch->remaining_quantity = Decimal::quantity(Decimal::sub($batch->remaining_quantity, $toDispose));
            $batch->save();

            $transaction = StockTransaction::query()->create([
                'item_id' => $batch->item_id,
                'batch_id' => $batch->batch_id,
                'user_id' => $user->user_id,
                'transaction_type' => TransactionType::Disposal,
                'quantity_change' => Decimal::quantity('-'.$toDispose),
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $this->audit->log('STOCK_DISPOSAL', $batch, details: [
                'item_name' => $batch->item->item_name,
                'batch_number' => $batch->batch_number,
                'quantity' => $toDispose,
                'was_expired' => $batch->isExpired(),
                'reason' => $reason,
            ], user: $user);

            StockLevelChanged::dispatch([$batch->item_id]);

            return $transaction;
        });
    }

    /**
     * Correct a batch's remaining quantity after a physical stock count.
     */
    public function adjust(InventoryBatch $batch, string|int|float $countedQuantity, string $reason, ?string $notes, User $user): StockTransaction
    {
        return DB::transaction(function () use ($batch, $countedQuantity, $reason, $notes, $user): StockTransaction {
            /** @var InventoryBatch $batch */
            $batch = InventoryBatch::query()->with('item')->lockForUpdate()->findOrFail($batch->batch_id);

            $counted = Decimal::quantity($countedQuantity);

            if (Decimal::compare($counted, '0') < 0) {
                throw new BusinessRuleException('The counted quantity cannot be negative.');
            }

            $difference = Decimal::quantity(Decimal::sub($counted, $batch->remaining_quantity));

            if (Decimal::isZero($difference)) {
                throw new BusinessRuleException('The counted quantity matches the recorded quantity; no adjustment is needed.');
            }

            $previous = Decimal::quantity($batch->remaining_quantity);
            $batch->remaining_quantity = $counted;
            $batch->save();

            $transaction = StockTransaction::query()->create([
                'item_id' => $batch->item_id,
                'batch_id' => $batch->batch_id,
                'user_id' => $user->user_id,
                'transaction_type' => TransactionType::Adjustment,
                'quantity_change' => $difference,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $this->audit->log('STOCK_ADJUSTMENT', $batch, details: [
                'item_name' => $batch->item->item_name,
                'batch_number' => $batch->batch_number,
                'previous_quantity' => $previous,
                'counted_quantity' => $counted,
                'difference' => $difference,
                'reason' => $reason,
            ], user: $user);

            StockLevelChanged::dispatch([$batch->item_id]);

            return $transaction;
        });
    }
}
