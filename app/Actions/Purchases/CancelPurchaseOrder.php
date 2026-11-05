<?php

namespace App\Actions\Purchases;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\InvalidPurchaseOperation;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelPurchaseOrder
{
    /**
     * Cancel a draft: its lines go back to the pool of needs to buy. Once sent, it cannot be cancelled.
     *
     * @throws InvalidPurchaseOperation when the purchase order is not a draft
     */
    public function handle(PurchaseOrder $purchase, ?User $user = null): void
    {
        if ($purchase->status !== PurchaseOrderStatus::Draft) {
            throw new InvalidPurchaseOperation('status', 'purchases.errors.not_cancellable');
        }

        DB::transaction(function () use ($purchase, $user): void {
            $purchase->orderItems()->update(['purchase_order_id' => null]);
            $purchase->forceFill(['status' => PurchaseOrderStatus::Cancelled])->save();

            AuditLog::record('purchase_order.cancelled', $purchase, ['status' => 'draft'], ['status' => 'cancelled'], $user);
        });
    }
}
