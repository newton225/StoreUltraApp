<?php

namespace App\Enums;

/**
 * The life of an order: draft, proforma issued, advance received, delivered, settled, closed (or cancelled).
 * "Ordered" and "received" belong to the past: purchases and stock are followed apart from the order (menus Achats
 * and Stock), no order reaches those two steps any more. The cases stay so that the history of older orders is
 * still readable.
 */
enum OrderStatus: string
{
    case Draft = 'draft';
    case ProformaIssued = 'proforma_issued';
    case AdvanceReceived = 'advance_received';
    /** @deprecated No order reaches it any more; kept for the history of older orders. */
    case Ordered = 'ordered';
    /** @deprecated No order reaches it any more; kept for the history of older orders. */
    case Received = 'received';
    case Delivered = 'delivered';
    case Settled = 'settled';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * The statuses an order can be in today, in the order it goes through (cancelled apart).
     *
     * @return list<self>
     */
    public static function workflow(): array
    {
        return [self::Draft, self::ProformaIssued, self::AdvanceReceived, self::Delivered, self::Settled, self::Closed, self::Cancelled];
    }

    /**
     * The values of the statuses an order can be in today.
     *
     * @return list<string>
     */
    public static function workflowValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::workflow());
    }

    /**
     * The statuses from which kits can be handed over: once the advance is received (the older steps stay
     * deliverable for the orders that were already there).
     *
     * @return list<self>
     */
    public static function deliverable(): array
    {
        return [self::AdvanceReceived, self::Ordered, self::Received];
    }

    /**
     * The values of the statuses from which kits can be handed over.
     *
     * @return list<string>
     */
    public static function deliverableValues(): array
    {
        return array_map(fn (self $status): string => $status->value, self::deliverable());
    }

    /**
     * The values of the statuses whose orders can be bought from the shops: once the advance is received, or as soon
     * as the proforma is issued when the shops are bought from on credit.
     *
     * @return list<string>
     */
    public static function purchasableValues(bool $onCredit): array
    {
        return $onCredit
            ? [self::ProformaIssued->value, self::AdvanceReceived->value]
            : [self::AdvanceReceived->value];
    }

    /**
     * Whether kits can be handed over to the client in this status.
     */
    public function canBeDelivered(): bool
    {
        return in_array($this, self::deliverable(), true);
    }

    /**
     * The next step of the normal life of an order.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Draft => self::ProformaIssued,
            self::ProformaIssued => self::AdvanceReceived,
            self::AdvanceReceived, self::Ordered, self::Received => self::Delivered,
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
