<?php

namespace App\Modules\Inventory\Services;

use App\Models\InventoryItem;
use App\Models\User;
use App\Modules\Inventory\Events\StockLevelChanged;
use App\Services\AuditLogger;
use App\Services\Decimal;
use Illuminate\Support\Facades\DB;

class InventoryItemService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{category_id: int|string, item_name: string, description?: string|null, unit: string, minimum_stock: string|int|float, expiry_tracking?: bool}  $data
     */
    public function create(array $data, User $user): InventoryItem
    {
        return DB::transaction(function () use ($data, $user): InventoryItem {
            $item = InventoryItem::query()->create($this->attributes($data) + ['is_active' => true]);

            $this->audit->log('CREATE', $item, details: $item->only(['item_name', 'category_id', 'unit', 'minimum_stock', 'expiry_tracking']), user: $user);

            StockLevelChanged::dispatch([$item->item_id]);

            return $item;
        });
    }

    /**
     * @param  array{category_id: int|string, item_name: string, description?: string|null, unit: string, minimum_stock: string|int|float, expiry_tracking?: bool}  $data
     */
    public function update(InventoryItem $item, array $data, User $user): InventoryItem
    {
        return DB::transaction(function () use ($item, $data, $user): InventoryItem {
            $item->fill($this->attributes($data));
            $changes = $item->getDirty();
            $original = array_intersect_key($item->getOriginal(), $changes);
            $item->save();

            if ($changes !== []) {
                $this->audit->log('UPDATE', $item, details: ['before' => $original, 'after' => $changes], user: $user);
            }

            StockLevelChanged::dispatch([$item->item_id]);

            return $item;
        });
    }

    /**
     * Items are never hard-deleted because batches and transactions reference them.
     */
    public function setActive(InventoryItem $item, bool $active, User $user): InventoryItem
    {
        return DB::transaction(function () use ($item, $active, $user): InventoryItem {
            $item->update(['is_active' => $active]);

            $this->audit->log($active ? 'ACTIVATE' : 'DEACTIVATE', $item, details: ['item_name' => $item->item_name], user: $user);

            StockLevelChanged::dispatch([$item->item_id]);

            return $item;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'category_id' => (int) $data['category_id'],
            'item_name' => trim($data['item_name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'unit' => trim($data['unit']),
            'minimum_stock' => Decimal::quantity($data['minimum_stock']),
            'expiry_tracking' => (bool) ($data['expiry_tracking'] ?? false),
        ];
    }
}
