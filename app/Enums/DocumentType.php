<?php

namespace App\Enums;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Model;

enum DocumentType: string
{
    case Proforma = 'proforma';
    case Receipt = 'receipt';
    case PurchaseOrder = 'purchase_order';
    case DeliveryNote = 'delivery_note';
    case Invoice = 'invoice';
    case Statement = 'statement';

    /**
     * Whether the document gets a number of its own. A receipt keeps the number of its payment and a
     * purchase order its reference.
     */
    public function hasOwnNumber(): bool
    {
        return match ($this) {
            self::Receipt, self::PurchaseOrder => false,
            default => true,
        };
    }

    /**
     * Whether a field agent may issue it for the orders of their zones.
     */
    public function isOpenToAgents(): bool
    {
        return $this === self::Receipt || $this === self::DeliveryNote;
    }

    /**
     * The kind of record the document is made from (an invoice is made from the delivered order).
     *
     * @return class-string<Model>|null
     */
    public function subjectModel(): ?string
    {
        return match ($this) {
            self::Proforma, self::Invoice => Order::class,
            self::Receipt => Payment::class,
            self::PurchaseOrder => PurchaseOrder::class,
            self::DeliveryNote => Delivery::class,
            self::Statement => null,
        };
    }

    /**
     * Whether the document is about the business as a whole rather than one record.
     */
    public function hasNoSubject(): bool
    {
        return $this === self::Statement;
    }
}
