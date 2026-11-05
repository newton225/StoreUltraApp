<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /**
     * Whether the goods have been asked from the shop (the order is no longer a draft).
     */
    public function isPlaced(): bool
    {
        return match ($this) {
            self::Sent, self::PartiallyReceived, self::Received => true,
            default => false,
        };
    }

    /**
     * Whether goods can still be received on it.
     */
    public function canReceive(): bool
    {
        return $this === self::Sent || $this === self::PartiallyReceived;
    }
}
