<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidOrderDiscount extends RuntimeException
{
    public function __construct(string $message, public readonly string $messageKey, public readonly int $maximum = 0)
    {
        parent::__construct($message);
    }

    public static function notAllowedAnyMore(): self
    {
        return new self('A discount can only be granted before the delivery.', 'orders.discount.errors.status');
    }

    public static function tooHigh(int $maximum): self
    {
        return new self("The discount cannot go above [{$maximum}].", 'orders.discount.errors.too_high', $maximum);
    }
}
