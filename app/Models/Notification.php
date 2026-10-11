<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock alert (low stock / expiring soon / expired). Unrelated to Laravel's
 * built-in database notifications, which this application does not use.
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $primaryKey = 'notification_id';

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'item_id',
        'batch_id',
        'notification_type',
        'message',
        'status',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notification_type' => NotificationType::class,
            'status' => NotificationStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Alerts that are UNREAD or READ (i.e. not resolved).
     *
     * @param  Builder<Notification>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('notifications.status', NotificationStatus::active());
    }

    /**
     * @param  Builder<Notification>  $query
     */
    public function scopeUnread(Builder $query): void
    {
        $query->where('notifications.status', NotificationStatus::Unread);
    }

    public function isActive(): bool
    {
        return in_array($this->status, NotificationStatus::active(), true);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    /**
     * @return BelongsTo<InventoryBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }
}
