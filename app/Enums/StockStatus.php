<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'IN_STOCK';
    case LowStock = 'LOW_STOCK';
    case OutOfStock = 'OUT_OF_STOCK';

    public function label(): string
    {
        return match ($this) {
            self::InStock => 'In Stock',
            self::LowStock => 'Low Stock',
            self::OutOfStock => 'Out of Stock',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::InStock => 'success',
            self::LowStock => 'warning',
            self::OutOfStock => 'danger',
        };
    }
}
