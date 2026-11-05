<?php

namespace App\Enums;

enum PaymentKind: string
{
    case Advance = 'advance';
    case Balance = 'balance';
    case Refund = 'refund';

    /**
     * The order statuses in which this kind of payment can be taken.
     * A refund can be made at any time, up to what was paid.
     *
     * @return array<int, OrderStatus>|null
     */
    public function allowedStatuses(): ?array
    {
        return match ($this) {
            self::Advance => [
                OrderStatus::Draft,
                OrderStatus::ProformaIssued,
                OrderStatus::AdvanceReceived,
                OrderStatus::Ordered,
                OrderStatus::Received,
            ],
            self::Balance => [OrderStatus::Received, OrderStatus::Delivered],
            self::Refund => null,
        };
    }
}
