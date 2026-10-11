<?php

namespace App\Modules\DashboardReports\Services;

use App\Enums\BatchStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Services\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;

/**
 * Keeps the notifications table in sync with the real stock situation.
 *
 *  - Creates an alert when a condition starts (at most one active alert per
 *    type + item + batch; also enforced by a unique index on active_key).
 *  - Resolves the alert automatically when the condition improves.
 *  - Never creates purchase orders; it only informs staff.
 */
class StockAlertService
{
    /**
     * @param  array<int, int>  $itemIds
     */
    public function refreshForItems(array $itemIds): void
    {
        InventoryItem::query()
            ->withStockLevels()
            ->whereIn('inventory_items.item_id', array_unique($itemIds))
            ->with('batches')
            ->get()
            ->each(fn (InventoryItem $item) => $this->refreshItem($item));
    }

    /**
     * Re-evaluates every item and batch. Run by the scheduler so that expiry
     * alerts follow the calendar even when no stock moves.
     *
     * @return array{created: int, resolved: int}
     */
    public function refreshAll(): array
    {
        $before = $this->counts();

        InventoryItem::query()
            ->withStockLevels()
            ->with('batches')
            ->chunkById(100, function (Collection $items): void {
                $items->each(fn (InventoryItem $item) => $this->refreshItem($item));
            }, 'inventory_items.item_id', 'item_id');

        $after = $this->counts();

        return [
            'created' => max(0, $after['total'] - $before['total']),
            'resolved' => max(0, $after['resolved'] - $before['resolved']),
        ];
    }

    public function refreshItem(InventoryItem $item): void
    {
        $this->refreshLowStock($item);

        foreach ($item->batches as $batch) {
            $batch->setRelation('item', $item);
            $this->refreshBatch($batch);
        }
    }

    public function refreshLowStock(InventoryItem $item): void
    {
        if ($item->is_active && $item->isLowStock()) {
            $usable = $item->usableQuantity();
            $message = Decimal::isPositive($usable)
                ? sprintf('%s is low on stock: %s %s usable (minimum %s %s).', $item->item_name, Decimal::formatQuantity($usable), $item->unit, Decimal::formatQuantity($item->minimum_stock), $item->unit)
                : sprintf('%s is out of usable stock (minimum %s %s).', $item->item_name, Decimal::formatQuantity($item->minimum_stock), $item->unit);

            $this->raise(NotificationType::LowStock, $item->item_id, null, $message);

            return;
        }

        $this->resolve(NotificationType::LowStock, $item->item_id, null);
    }

    public function refreshBatch(InventoryBatch $batch): void
    {
        $item = $batch->item;
        $status = $item->is_active ? $batch->status() : BatchStatus::Depleted;

        if ($status === BatchStatus::Expired) {
            $this->resolve(NotificationType::ExpiringSoon, $batch->item_id, $batch->batch_id);
            $this->raise(NotificationType::Expired, $batch->item_id, $batch->batch_id, sprintf(
                'Batch %s of %s (%s %s) expired on %s. Dispose of it; it is excluded from usable stock.',
                $batch->batch_number,
                $item->item_name,
                Decimal::formatQuantity($batch->remaining_quantity),
                $item->unit,
                $batch->expiration_date->format('d M Y'),
            ));

            return;
        }

        if ($status === BatchStatus::ExpiringSoon) {
            $days = $batch->daysUntilExpiry();
            $this->resolve(NotificationType::Expired, $batch->item_id, $batch->batch_id);
            $this->raise(NotificationType::ExpiringSoon, $batch->item_id, $batch->batch_id, sprintf(
                'Batch %s of %s (%s %s) expires on %s (%s).',
                $batch->batch_number,
                $item->item_name,
                Decimal::formatQuantity($batch->remaining_quantity),
                $item->unit,
                $batch->expiration_date->format('d M Y'),
                match (true) {
                    $days === 0 => 'today',
                    $days === 1 => 'tomorrow',
                    default => "in {$days} days",
                },
            ));

            return;
        }

        $this->resolve(NotificationType::ExpiringSoon, $batch->item_id, $batch->batch_id);
        $this->resolve(NotificationType::Expired, $batch->item_id, $batch->batch_id);
    }

    public function markRead(Notification $notification): void
    {
        if ($notification->status === NotificationStatus::Unread) {
            $notification->update(['status' => NotificationStatus::Read]);
        }
    }

    public function markAllRead(): int
    {
        return Notification::query()->unread()->update(['status' => NotificationStatus::Read]);
    }

    /**
     * Create the alert unless an active one already exists (then only its
     * message is refreshed, keeping its READ/UNREAD status).
     */
    private function raise(NotificationType $type, int $itemId, ?int $batchId, string $message): void
    {
        $existing = $this->activeQuery($type, $itemId, $batchId)->first();

        if ($existing !== null) {
            if ($existing->message !== $message) {
                $existing->update(['message' => $message]);
            }

            return;
        }

        try {
            Notification::query()->create([
                'item_id' => $itemId,
                'batch_id' => $batchId,
                'notification_type' => $type,
                'message' => $message,
                'status' => NotificationStatus::Unread,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another request created the same active alert at the same moment.
        }
    }

    private function resolve(NotificationType $type, int $itemId, ?int $batchId): void
    {
        $this->activeQuery($type, $itemId, $batchId)->update([
            'status' => NotificationStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    /**
     * @return Builder<Notification>
     */
    private function activeQuery(NotificationType $type, int $itemId, ?int $batchId)
    {
        return Notification::query()
            ->active()
            ->where('notification_type', $type)
            ->where('item_id', $itemId)
            ->when(
                $batchId === null,
                fn ($query) => $query->whereNull('batch_id'),
                fn ($query) => $query->where('batch_id', $batchId),
            );
    }

    /**
     * @return array{total: int, resolved: int}
     */
    private function counts(): array
    {
        return [
            'total' => Notification::query()->count(),
            'resolved' => Notification::query()->where('status', NotificationStatus::Resolved)->count(),
        ];
    }
}
