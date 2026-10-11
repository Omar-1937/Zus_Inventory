<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Staff = 'STAFF';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }
}
