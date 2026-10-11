<?php

namespace App\Enums;

enum NotificationStatus: string
{
    case Unread = 'UNREAD';
    case Read = 'READ';
    case Resolved = 'RESOLVED';

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    /**
     * @return array<int, self>
     */
    public static function active(): array
    {
        return [self::Unread, self::Read];
    }
}
