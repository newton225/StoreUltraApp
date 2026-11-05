<?php

namespace App\Policies;

use App\Enums\DocumentType;
use App\Models\Delivery;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DocumentPolicy
{
    /**
     * Whoever can see the order behind a document can open it; the office documents (purchase
     * orders and statements) are for the office only.
     */
    public function view(User $user, Document $document): bool
    {
        if ($document->type === DocumentType::Statement || $document->type === DocumentType::PurchaseOrder) {
            return $user->can('manage-orders');
        }

        $order = self::orderOf($document->subject);

        return $order !== null && $user->can('view', $order);
    }

    /**
     * Only the office cancels or replaces a document.
     */
    public function cancel(User $user, Document $document): bool
    {
        return $user->can('manage-orders');
    }

    /**
     * Whether the user may issue a document of this kind for this record: a field agent only issues
     * receipts and delivery notes for the orders of their zones.
     */
    public function issue(User $user, DocumentType $type, ?Model $subject): bool
    {
        if ($user->can('manage-orders')) {
            return true;
        }

        $order = self::orderOf($subject);

        return $type->isOpenToAgents() && $order !== null && $user->can('view', $order);
    }

    /**
     * The order a record belongs to.
     */
    public static function orderOf(?Model $subject): ?Order
    {
        return match (true) {
            $subject instanceof Order => $subject,
            $subject instanceof Payment, $subject instanceof Delivery, $subject instanceof Invoice => $subject->order,
            default => null,
        };
    }
}
