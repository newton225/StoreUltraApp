<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidExpense extends RuntimeException
{
    /**
     * @param  string  $field  The form field the problem is about.
     * @param  string  $messageKey  The translation key of the message shown to the user.
     */
    public function __construct(public readonly string $field, public readonly string $messageKey)
    {
        parent::__construct("Invalid expense ({$field}): {$messageKey}.");
    }
}
