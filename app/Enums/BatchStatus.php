<?php

namespace App\Enums;

enum BatchStatus: string
{
    case Good = 'GOOD';
    case ExpiringSoon = 'EXPIRING_SOON';
    case Expired = 'EXPIRED';
    case Depleted = 'DEPLETED';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::ExpiringSoon => 'Expiring Soon',
            self::Expired => 'Expired',
            self::Depleted => 'Depleted',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Good => 'success',
            self::ExpiringSoon => 'warning',
            self::Expired => 'danger',
            self::Depleted => 'neutral',
        };
    }
}
