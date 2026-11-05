<?php

namespace App\Exceptions;

use App\Enums\OrderStatus;
use RuntimeException;

class InvalidOrderTransition extends RuntimeException
{
    public function __construct(string $message, public readonly string $messageKey = 'orders.errors.invalid_transition')
    {
        parent::__construct($message);
    }

    public static function between(OrderStatus $from, OrderStatus $to): self
    {
        return new self("An order cannot go from [{$from->value}] to [{$to->value}].");
    }

    public static function advanceMissing(): self
    {
        return new self('The advance is not covered yet.', 'orders.errors.advance_missing');
    }
}
