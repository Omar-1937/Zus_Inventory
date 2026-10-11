<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'item_name' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'unit' => fake()->randomElement(['L', 'kg', 'pcs', 'bottle']),
            'minimum_stock' => '5.000',
            'expiry_tracking' => false,
            'is_active' => true,
        ];
    }

    public function tracksExpiry(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiry_tracking' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function minimum(string|int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'minimum_stock' => (string) $quantity,
        ]);
    }
}
