<?php

namespace App\Exceptions;

use RuntimeException;

class OrderPricesMissing extends RuntimeException
{
    public static function forKit(?int $kitVersionId): self
    {
        return new self(
            $kitVersionId === null
                ? 'The kit has no version in effect.'
                : "The kit version [{$kitVersionId}] has missing prices.",
        );
    }
}
