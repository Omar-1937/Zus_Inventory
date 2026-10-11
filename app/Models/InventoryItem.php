<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Services\Decimal;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stock quantities are never stored on the item. They are derived from the
 * remaining_quantity of the item's batches:
 *
 *  - physical quantity: everything still on the shelf (including expired stock
 *    that has not been disposed of yet);
 *  - usable quantity: stock in batches that are not expired.
 */
class InventoryItem extends Model
{
    /** @use HasFactory<InventoryItemFactory> */
    use HasFactory;

    protected $primaryKey = 'item_id';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'category_id',
        'item_name',
        'description',
        'unit',
        'minimum_stock',
        'expiry_tracking',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_stock' => 'decimal:3',
            'expiry_tracking' => 'boolean',
            'is_active' => 'boolean',
            'physical_quantity' => 'decimal:3',
            'usable_quantity' => 'decimal:3',
        ];
    }

    /**
     * Correlated sub-query: SUM of remaining quantity across all batches.
     *
     * @return Builder<InventoryBatch>
     */
    public static function physicalQuantitySubquery(): Builder
    {
        return InventoryBatch::query()
            ->selectRaw('COALESCE(SUM(inventory_batches.remaining_quantity), 0)')
            ->whereColumn('inventory_batches.item_id', 'inventory_items.item_id');
    }

    /**
     * Correlated sub-query: SUM of remaining quantity across non-expired batches.
     *
     * @return Builder<InventoryBatch>
     */
    public static function usableQuantitySubquery(): Builder
    {
        return static::physicalQuantitySubquery()->usable();
    }

    /**
     * Adds physical_quantity and usable_quantity columns to the query.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeWithStockLevels(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('inventory_items.*');
        }

        $query->addSelect([
            'physical_quantity' => static::physicalQuantitySubquery(),
            'usable_quantity' => static::usableQuantitySubquery(),
        ]);
    }

    /**
     * Items whose usable stock is at or below their minimum stock level.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeLowStock(Builder $query): void
    {
        $usable = static::usableQuantitySubquery();

        $query->where('inventory_items.minimum_stock', '>', 0)
            ->whereRaw('('.$usable->toSql().') <= inventory_items.minimum_stock', $usable->getBindings());
    }

    /**
     * Items with no usable stock left.
     *
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeOutOfStock(Builder $query): void
    {
        $usable = static::usableQuantitySubquery();

        $query->whereRaw('('.$usable->toSql().') <= 0', $usable->getBindings());
    }

    /**
     * @param  Builder<InventoryItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('inventory_items.is_active', true);
    }

    public function usableQuantity(): string
    {
        if (array_key_exists('usable_quantity', $this->attributes)) {
            return Decimal::quantity($this->attributes['usable_quantity']);
        }

        return Decimal::quantity($this->batches()->usable()->sum('remaining_quantity'));
    }

    public function physicalQuantity(): string
    {
        if (array_key_exists('physical_quantity', $this->attributes)) {
            return Decimal::quantity($this->attributes['physical_quantity']);
        }

        return Decimal::quantity($this->batches()->sum('remaining_quantity'));
    }

    public function stockStatus(): StockStatus
    {
        $usable = $this->usableQuantity();

        if (! Decimal::isPositive($usable)) {
            return StockStatus::OutOfStock;
        }

        if (Decimal::isPositive($this->minimum_stock) && Decimal::compare($usable, $this->minimum_stock) <= 0) {
            return StockStatus::LowStock;
        }

        return StockStatus::InStock;
    }

    public function isLowStock(): bool
    {
        return Decimal::isPositive($this->minimum_stock)
            && Decimal::compare($this->usableQuantity(), $this->minimum_stock) <= 0;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * @return HasMany<InventoryBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class, 'item_id');
    }

    /**
     * @return HasMany<StockTransaction, $this>
     */
    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'item_id');
    }

    /**
     * @return HasMany<SupplierItem, $this>
     */
    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class, 'item_id');
    }

    /**
     * @return BelongsToMany<Supplier, $this, SupplierItem>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'supplier_items', 'item_id', 'supplier_id')
            ->using(SupplierItem::class)
            ->withPivot(['supplier_item_id', 'unit_price', 'is_active', 'updated_at']);
    }

    /**
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'item_id');
    }
}
