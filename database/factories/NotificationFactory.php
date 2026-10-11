<?php

namespace Database\Factories;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\InventoryItem;
use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => InventoryItem::factory(),
            'batch_id' => null,
            'notification_type' => NotificationType::LowStock,
            'message' => fake()->sentence(),
            'status' => NotificationStatus::Unread,
        ];
    }
}
