<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Pending = 'PENDING';
    case Approved = 'APPROVED';
    case Ordered = 'ORDERED';
    case Received = 'RECEIVED';
    case Cancelled = 'CANCELLED';

    /**
     * The statuses this status may move to.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Cancelled],
            self::Approved => [self::Ordered, self::Cancelled],
            self::Ordered => [self::Received, self::Cancelled],
            self::Received, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    public function label(): string
    {
        return ucfirst(strtolower($this->value));
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Ordered => 'primary',
            self::Received => 'success',
            self::Cancelled => 'danger',
        };
    }
}
