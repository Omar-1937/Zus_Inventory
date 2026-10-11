<?php

namespace App\Models;

use App\Enums\BatchStatus;
use App\Services\Decimal;
use Carbon\CarbonInterface;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Expiry rules (inclusive of the expiration date itself):
 *  - Expired: expiration_date is before today.
 *  - Expiring Soon: expiration_date is between today and today + threshold days.
 *  - Depleted: no remaining quantity.
 */
class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    protected $primaryKey = 'batch_id';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'po_item_id',
        'batch_number',
        'received_date',
        'expiration_date',
        'initial_quantity',
        'remaining_quantity',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'expiration_date' => 'date',
            'initial_quantity' => 'decimal:3',
            'remaining_quantity' => 'decimal:3',
        ];
    }

    public static function expiryThresholdDays(): int
    {
        return (int) config('inventory.expiry_alert_days', 3);
    }

    /**
     * Batches that still have stock and are not expired (usable for FEFO).
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query->where('inventory_batches.remaining_quantity', '>', 0)
            ->where(function (Builder $query): void {
                $query->whereNull('inventory_batches.expiration_date')
                    ->orWhere('inventory_batches.expiration_date', '>=', today()->toDateString());
            });
    }

    /**
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeWithStock(Builder $query): void
    {
        $query->where('inventory_batches.remaining_quantity', '>', 0);
    }

    /**
     * Batches with stock remaining whose expiration date has passed.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('inventory_batches.remaining_quantity', '>', 0)
            ->whereNotNull('inventory_batches.expiration_date')
            ->where('inventory_batches.expiration_date', '<', today()->toDateString());
    }

    /**
     * Batches with stock remaining that expire within the alert threshold.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeExpiringSoon(Builder $query, ?int $days = null): void
    {
        $days ??= static::expiryThresholdDays();

        $query->where('inventory_batches.remaining_quantity', '>', 0)
            ->whereBetween('inventory_batches.expiration_date', [
                today()->toDateString(),
                today()->addDays($days)->toDateString(),
            ]);
    }

    /**
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeDepleted(Builder $query): void
    {
        $query->where('inventory_batches.remaining_quantity', '<=', 0);
    }

    /**
     * First Expired, First Out: earliest expiry first, non-expiring stock last,
     * then oldest received.
     *
     * @param  Builder<InventoryBatch>  $query
     */
    public function scopeFefoOrder(Builder $query): void
    {
        $query->orderByRaw('inventory_batches.expiration_date IS NULL')
            ->orderBy('inventory_batches.expiration_date')
            ->orderBy('inventory_batches.received_date')
            ->orderBy('inventory_batches.batch_id');
    }

    public function status(?CarbonInterface $today = null): BatchStatus
    {
        $today = ($today ?? today())->copy()->startOfDay();

        if (! Decimal::isPositive($this->remaining_quantity)) {
            return BatchStatus::Depleted;
        }

        if ($this->expiration_date === null) {
            return BatchStatus::Good;
        }

        if ($this->expiration_date->lt($today)) {
            return BatchStatus::Expired;
        }

        if ($this->expiration_date->lte($today->copy()->addDays(static::expiryThresholdDays()))) {
            return BatchStatus::ExpiringSoon;
        }

        return BatchStatus::Good;
    }

    public function isExpired(): bool
    {
        return $this->expiration_date !== null && $this->expiration_date->lt(today());
    }

    public function daysUntilExpiry(): ?int
    {
        if ($this->expiration_date === null) {
            return null;
        }

        return (int) today()->diffInDays($this->expiration_date, false);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'po_item_id');
    }

    /**
     * @return HasMany<StockTransaction, $this>
     */
    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'batch_id');
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
