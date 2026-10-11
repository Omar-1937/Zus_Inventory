<?php

namespace App\Modules\PurchaseOrders\Services;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Models\User;
use App\Modules\Inventory\Services\StockService;
use App\Services\AuditLogger;
use App\Services\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Purchase order lifecycle: PENDING -> APPROVED -> ORDERED -> RECEIVED,
 * with CANCELLED as an alternative terminal state.
 *
 * Prices are ALWAYS read from supplier_items on the server. Client input only
 * ever supplies which supplier items and what quantities are wanted.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StockService $stock,
    ) {}

    /**
     * Price preview used by the create form. Uses the same calculation as create().
     *
     * @param  array<int, array{supplier_item_id: int|string, quantity: string|int|float|null}>  $lines
     * @return array{lines: array<int, array{supplier_item_id: int, unit_price: string, quantity: string, subtotal: string}>, total: string}
     */
    public function quote(Supplier $supplier, array $lines): array
    {
        $supplierItems = SupplierItem::query()
            ->where('supplier_id', $supplier->supplier_id)
            ->whereIn('supplier_item_id', collect($lines)->pluck('supplier_item_id')->filter()->map(fn ($id): int => (int) $id))
            ->get()
            ->keyBy('supplier_item_id');

        $quoted = [];

        foreach ($lines as $line) {
            $supplierItem = $supplierItems->get((int) ($line['supplier_item_id'] ?? 0));

            if ($supplierItem === null) {
                continue;
            }

            $quantity = is_numeric($line['quantity'] ?? null) && Decimal::isPositive($line['quantity'])
                ? Decimal::quantity($line['quantity'])
                : '0.000';

            $quoted[] = [
                'supplier_item_id' => $supplierItem->supplier_item_id,
                'unit_price' => Decimal::money($supplierItem->unit_price),
                'quantity' => $quantity,
                'subtotal' => $this->subtotal($quantity, $supplierItem->unit_price),
            ];
        }

        return [
            'lines' => $quoted,
            'total' => Decimal::sum(array_column($quoted, 'subtotal'), Decimal::MONEY_SCALE),
        ];
    }

    /**
     * @param  array<int, array{supplier_item_id: int|string, quantity: string|int|float}>  $lines
     */
    public function create(Supplier $supplier, array $lines, ?string $notes, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($supplier, $lines, $notes, $user): PurchaseOrder {
            /** @var Supplier $supplier */
            $supplier = Supplier::query()->sharedLock()->findOrFail($supplier->supplier_id);

            if (! $supplier->is_active) {
                throw new BusinessRuleException("{$supplier->supplier_name} is inactive. Choose an active supplier.");
            }

            if ($lines === []) {
                throw new BusinessRuleException('Add at least one item to the purchase order.');
            }

            $requestedIds = collect($lines)->map(fn (array $line): int => (int) $line['supplier_item_id']);

            if ($requestedIds->duplicates()->isNotEmpty()) {
                throw new BusinessRuleException('Each item can only appear once on a purchase order.');
            }

            /** @var Collection<int, SupplierItem> $supplierItems */
            $supplierItems = SupplierItem::query()
                ->with('item')
                ->whereIn('supplier_item_id', $requestedIds)
                ->sharedLock()
                ->get()
                ->keyBy('supplier_item_id');

            $purchaseOrder = PurchaseOrder::query()->create([
                'supplier_id' => $supplier->supplier_id,
                'created_by' => $user->user_id,
                'order_date' => today()->toDateString(),
                'status' => PurchaseOrderStatus::Pending,
                'total_amount' => '0.00',
                'notes' => filled($notes) ? trim($notes) : null,
            ]);

            $total = '0';

            foreach ($lines as $line) {
                $supplierItem = $supplierItems->get((int) $line['supplier_item_id']);

                if ($supplierItem === null || $supplierItem->supplier_id !== $supplier->supplier_id) {
                    throw new BusinessRuleException("One of the selected items is not supplied by {$supplier->supplier_name}.");
                }

                if (! $supplierItem->is_active || ! $supplierItem->item->is_active) {
                    throw new BusinessRuleException("{$supplierItem->item->item_name} is no longer available from {$supplier->supplier_name}.");
                }

                $quantity = Decimal::quantity($line['quantity']);

                if (! Decimal::isPositive($quantity)) {
                    throw new BusinessRuleException("Enter a quantity greater than zero for {$supplierItem->item->item_name}.");
                }

                $unitPrice = Decimal::money($supplierItem->unit_price);
                $subtotal = $this->subtotal($quantity, $unitPrice);

                PurchaseOrderItem::query()->create([
                    'purchase_order_id' => $purchaseOrder->purchase_order_id,
                    'supplier_item_id' => $supplierItem->supplier_item_id,
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);

                $total = Decimal::add($total, $subtotal);
            }

            $purchaseOrder->update(['total_amount' => Decimal::money($total)]);

            $this->audit->log('CREATE', $purchaseOrder, details: [
                'reference' => $purchaseOrder->reference(),
                'supplier' => $supplier->supplier_name,
                'total_amount' => $purchaseOrder->total_amount,
                'lines' => count($lines),
            ], user: $user);

            return $purchaseOrder->fresh(['items.supplierItem.item', 'supplier']);
        });
    }

    public function approve(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Approved, $user);
    }

    public function markOrdered(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Ordered, $user);
    }

    public function cancel(PurchaseOrder $purchaseOrder, User $user, ?string $reason = null): PurchaseOrder
    {
        return $this->transition($purchaseOrder, PurchaseOrderStatus::Cancelled, $user, $reason);
    }

    /**
     * Receive the full order in one atomic transaction: validate status,
     * lock the order, create batches + IN transactions, mark RECEIVED.
     * If any line fails, nothing is saved.
     *
     * @param  array<int|string, array{batch_number: string, quantity: string|int|float, expiration_date?: string|null}>  $lines  keyed by po_item_id
     */
    public function receive(PurchaseOrder $purchaseOrder, array $lines, User $user, ?string $receivedDate = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $lines, $user, $receivedDate): PurchaseOrder {
            // The row lock makes a second, concurrent receive wait here; once it
            // continues it sees RECEIVED and is rejected below.
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->purchase_order_id);

            if ($purchaseOrder->status === PurchaseOrderStatus::Received) {
                throw new BusinessRuleException("{$purchaseOrder->reference()} has already been received. Stock was not added again.");
            }

            if ($purchaseOrder->status !== PurchaseOrderStatus::Ordered) {
                throw new BusinessRuleException("Only ORDERED purchase orders can be received. {$purchaseOrder->reference()} is {$purchaseOrder->status->value}.");
            }

            $purchaseOrder->load('items.supplierItem.item');

            $expectedIds = $purchaseOrder->items->pluck('po_item_id')->map(fn ($id): int => (int) $id)->sort()->values();
            $givenIds = collect(array_keys($lines))->map(fn ($id): int => (int) $id)->sort()->values();

            if ($expectedIds->all() !== $givenIds->all()) {
                throw new BusinessRuleException('Every ordered item must be received together (full-order receiving).');
            }

            $receivedSummary = [];

            foreach ($purchaseOrder->items as $poItem) {
                $line = $lines[$poItem->po_item_id];
                $item = $poItem->supplierItem->item;
                $quantity = Decimal::quantity($line['quantity']);

                if (Decimal::compare($quantity, $poItem->quantity_ordered) !== 0) {
                    throw new BusinessRuleException(sprintf(
                        'Received quantity for %s (%s %s) must equal the ordered quantity (%s %s).',
                        $item->item_name,
                        Decimal::formatQuantity($quantity),
                        $item->unit,
                        Decimal::formatQuantity($poItem->quantity_ordered),
                        $item->unit,
                    ));
                }

                $batch = $this->stock->stockIn($item, [
                    'batch_number' => $line['batch_number'],
                    'quantity' => $quantity,
                    'received_date' => $receivedDate ?? today()->toDateString(),
                    'expiration_date' => $line['expiration_date'] ?? null,
                    'reason' => 'Received from purchase order '.$purchaseOrder->reference(),
                ], $user, $poItem);

                $receivedSummary[] = [
                    'item' => $item->item_name,
                    'batch_id' => $batch->batch_id,
                    'batch_number' => $batch->batch_number,
                    'quantity' => $quantity,
                ];
            }

            $purchaseOrder->update([
                'status' => PurchaseOrderStatus::Received,
                'received_by' => $user->user_id,
                'received_at' => now(),
            ]);

            $this->audit->log('RECEIVE', $purchaseOrder, details: [
                'reference' => $purchaseOrder->reference(),
                'batches' => $receivedSummary,
            ], user: $user);

            return $purchaseOrder->fresh(['items.supplierItem.item', 'items.batches', 'supplier']);
        });
    }

    private function transition(PurchaseOrder $purchaseOrder, PurchaseOrderStatus $target, User $user, ?string $reason = null): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $target, $user, $reason): PurchaseOrder {
            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->purchase_order_id);
            $from = $purchaseOrder->status;

            if (! $from->canTransitionTo($target)) {
                throw new BusinessRuleException("Invalid status change: {$purchaseOrder->reference()} cannot move from {$from->value} to {$target->value}.");
            }

            $attributes = ['status' => $target];

            match ($target) {
                PurchaseOrderStatus::Approved => $attributes += ['approved_by' => $user->user_id, 'approved_at' => now()],
                PurchaseOrderStatus::Ordered => $attributes += ['ordered_at' => now()],
                PurchaseOrderStatus::Cancelled => $attributes += ['cancelled_at' => now()],
                default => null,
            };

            $purchaseOrder->update($attributes);

            $this->audit->log($target === PurchaseOrderStatus::Cancelled ? 'CANCEL' : 'STATUS_CHANGE', $purchaseOrder, details: array_filter([
                'reference' => $purchaseOrder->reference(),
                'from' => $from->value,
                'to' => $target->value,
                'reason' => $reason,
            ]), user: $user);

            return $purchaseOrder;
        });
    }

    private function subtotal(string|int|float $quantity, string|int|float $unitPrice): string
    {
        return Decimal::round(Decimal::mul($quantity, $unitPrice), Decimal::MONEY_SCALE);
    }
}
