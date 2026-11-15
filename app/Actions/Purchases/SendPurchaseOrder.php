<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOperation;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SendPurchaseOrder
{
    /**
     * Mark a draft as sent to the shop. Purchases are followed apart from the orders of the clients: sending
     * a purchase order does not move any order forward.
     *
     * @throws InvalidPurchaseOperation when the purchase order is not a draft
     */
    public function handle(PurchaseOrder $purchase, ?User $user = null): void
    {
        if ($purchase->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidPurchaseOperation('status', 'purchases.errors.not_draft');
        }

        DB::transaction(function () use ($purchase, $user): void {
            $purchase->forceFill(['status' => PurchaseOrderStatus::Sent, 'sent_at' => now()])->save();

            AuditLog::record('purchase_order.sent', $purchase, ['status' => 'draft'], ['status' => 'sent'], $user);
        });
    }
}
