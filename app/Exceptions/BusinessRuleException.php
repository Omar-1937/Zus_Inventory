<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by services when an operation violates a business rule
 * (insufficient stock, invalid status transition, duplicate receipt ...).
 * The message is safe to show to end users.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(string $message, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}
