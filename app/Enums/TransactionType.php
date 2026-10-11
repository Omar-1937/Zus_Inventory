<?php

namespace App\Enums;

enum TransactionType: string
{
    case In = 'IN';
    case Out = 'OUT';
    case Adjustment = 'ADJUSTMENT';
    case Disposal = 'DISPOSAL';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Stock In',
            self::Out => 'Stock Out',
            self::Adjustment => 'Adjustment',
            self::Disposal => 'Disposal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::In => 'success',
            self::Out => 'info',
            self::Adjustment => 'warning',
            self::Disposal => 'danger',
        };
    }
}
