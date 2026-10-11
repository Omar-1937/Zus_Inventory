<?php

namespace App\Models;

use Database\Factories\SupplierItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custom pivot between suppliers and inventory items carrying the supplier's
 * current unit price. It has its own auto-increment key because purchase
 * order lines reference it directly.
 */
class SupplierItem extends Pivot
{
    /** @use HasFactory<SupplierItemFactory> */
    use HasFactory;

    protected $table = 'supplier_items';

    protected $primaryKey = 'supplier_item_id';

    public $incrementing = true;

    public $timestamps = true;

    public const CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'item_id',
        'unit_price',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<SupplierItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('supplier_items.is_active', true);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'supplier_item_id');
    }
}
