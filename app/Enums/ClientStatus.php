<?php

namespace App\Enums;

enum ClientStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    /**
     * Whether a client in this status takes a place under the client cap.
     */
    public function countsTowardsCap(): bool
    {
        return $this !== self::Ended;
    }
}
