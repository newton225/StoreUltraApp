<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidPayment extends RuntimeException
{
    /**
     * @param  string  $field  The form field the problem is about.
     * @param  string  $messageKey  The translation key of the message shown to the user.
     * @param  array<string, string>  $params  The values filled into the message.
     */
    public function __construct(public readonly string $field, public readonly string $messageKey, public readonly array $params = [])
    {
        parent::__construct("Invalid payment ({$field}): {$messageKey}.");
    }
}
