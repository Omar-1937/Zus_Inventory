<?php

namespace Database\Factories;

use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryBatch>
 */
class InventoryBatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => InventoryItem::factory(),
            'po_item_id' => null,
            'batch_number' => 'B-'.fake()->unique()->numerify('######'),
            'received_date' => today()->subDays(2),
            'expiration_date' => today()->addDays(30),
            'initial_quantity' => '10.000',
            'remaining_quantity' => '10.000',
        ];
    }

    public function quantity(string|int $quantity): static
    {
        return $this->state(fn (array $attributes): array => [
            'initial_quantity' => (string) $quantity,
            'remaining_quantity' => (string) $quantity,
        ]);
    }

    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiration_date' => today()->addDays($days),
        ]);
    }

    public function expired(int $daysAgo = 1): static
    {
        return $this->state(fn (array $attributes): array => [
            'received_date' => today()->subDays($daysAgo + 10),
            'expiration_date' => today()->subDays($daysAgo),
        ]);
    }

    public function nonExpiring(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expiration_date' => null,
        ]);
    }

    public function depleted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'remaining_quantity' => '0.000',
        ]);
    }
}
