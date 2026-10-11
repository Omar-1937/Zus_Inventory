<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\InventoryBatch;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockTransaction>
 */
class StockTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => InventoryBatch::factory(),
            'item_id' => fn (array $attributes): int => InventoryBatch::query()->findOrFail($attributes['batch_id'])->item_id,
            'user_id' => User::factory(),
            'transaction_type' => TransactionType::In,
            'quantity_change' => '10.000',
            'reason' => 'Stock received',
            'notes' => null,
        ];
    }
}
