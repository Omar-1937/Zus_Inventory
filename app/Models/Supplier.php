<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected $primaryKey = 'supplier_id';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_name',
        'contact_person',
        'phone',
        'email',
        'address',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Supplier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('suppliers.is_active', true);
    }

    /**
     * @return HasMany<SupplierItem, $this>
     */
    public function supplierItems(): HasMany
    {
        return $this->hasMany(SupplierItem::class, 'supplier_id');
    }

    /**
     * @return BelongsToMany<InventoryItem, $this, SupplierItem>
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(InventoryItem::class, 'supplier_items', 'supplier_id', 'item_id')
            ->using(SupplierItem::class)
            ->withPivot(['supplier_item_id', 'unit_price', 'is_active', 'updated_at']);
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }
}
