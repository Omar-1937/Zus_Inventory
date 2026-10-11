<?php

namespace App\Enums;

enum NotificationType: string
{
    case LowStock = 'LOW_STOCK';
    case ExpiringSoon = 'EXPIRING_SOON';
    case Expired = 'EXPIRED';

    public function label(): string
    {
        return match ($this) {
            self::LowStock => 'Low Stock',
            self::ExpiringSoon => 'Expiring Soon',
            self::Expired => 'Expired',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::LowStock => 'warning',
            self::ExpiringSoon => 'warning',
            self::Expired => 'danger',
        };
    }
}
