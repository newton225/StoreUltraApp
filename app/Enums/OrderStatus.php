<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case ProformaIssued = 'proforma_issued';
    case AdvanceReceived = 'advance_received';
    case Ordered = 'ordered';
    case Received = 'received';
    case Delivered = 'delivered';
    case Settled = 'settled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * The next step of the normal life of an order.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Draft => self::ProformaIssued,
            self::ProformaIssued => self::AdvanceReceived,
            self::AdvanceReceived => self::Ordered,
            self::Ordered => self::Received,
            self::Received => self::Delivered,
            self::Delivered => self::Settled,
            self::Settled => self::Closed,
            self::Closed, self::Cancelled => null,
        };
    }

    /**
     * The steps taken by hand that the person confirms with their password (playbook SEC-15).
     */
    public function needsPassword(): bool
    {
        return in_array($this, [self::Delivered, self::Settled, self::Closed], true);
    }

    /**
     * Whether nothing can happen to the order any more.
     */
    public function isFinal(): bool
    {
        return $this === self::Closed || $this === self::Cancelled;
    }

    /**
     * An order can be cancelled until the kits are delivered.
     */
    public function isCancellable(): bool
    {
        return match ($this) {
            self::Draft, self::ProformaIssued, self::AdvanceReceived, self::Ordered, self::Received => true,
            default => false,
        };
    }

    /**
     * Whether the order may move to the given status: one step forward, or a cancellation.
     */
    public function canTransitionTo(self $to): bool
    {
        if ($to === self::Cancelled) {
            return $this->isCancellable();
        }

        return $this->next() === $to;
    }

    /**
     * @return array<int, string>
     */
    public function allowedNext(): array
    {
        $allowed = [];

        if ($this->next() !== null) {
            $allowed[] = $this->next()->value;
        }

        if ($this->isCancellable()) {
            $allowed[] = self::Cancelled->value;
        }

        return $allowed;
    }
}
