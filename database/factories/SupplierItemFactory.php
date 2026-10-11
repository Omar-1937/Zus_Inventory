<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use App\Models\Supplier;
use App\Models\SupplierItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierItem>
 */
class SupplierItemFactory extends Factory
{
    protected $model = SupplierItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'item_id' => InventoryItem::factory(),
            'unit_price' => '12.50',
            'is_active' => true,
        ];
    }

    public function price(string $price): static
    {
        return $this->state(fn (array $attributes): array => [
            'unit_price' => $price,
        ]);
    }
}
