<?php

namespace App\Actions\Orders;

use App\Exceptions\InvalidOrderDiscount;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderDiscount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ApplyOrderDiscount
{
    /**
     * Lower the price of an order by an amount, with the reason, and keep the trace of it. The order is read again
     * under a lock, so two discounts granted together cannot both rely on the same total. The price can never go
     * below what the client has already paid (a refund is the way to give money back). The advance still to be
     * received follows the price; one already received stays as it is.
     *
     * @throws InvalidOrderDiscount when the order is past the delivery or the amount is above what is left to pay
     */
    public function handle(Order $order, int $amount, string $reason, ?User $user = null): OrderDiscount
    {
        return DB::transaction(function () use ($order, $amount, $reason, $user): OrderDiscount {
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (! $current->status->isCancellable()) {
                throw InvalidOrderDiscount::notAllowedAnyMore();
            }

            $paid = $current->netPaid();
            $maximum = max(0, $current->total_sale - $paid);

            if ($amount < 1 || $amount > $maximum) {
                throw InvalidOrderDiscount::tooHigh($maximum);
            }

            $before = $current->total_sale;
            $after = $before - $amount;
            $advanceBefore = $current->advance_due;
            $advance = $advanceBefore;

            if ($paid < $advanceBefore && $before > 0) {
                $advance = min($advanceBefore, max($paid, intdiv($advanceBefore * $after, $before)));
            }

            $current->forceFill(['total_sale' => $after, 'advance_due' => $advance])->save();

            $discount = $current->discounts()->create([
                'amount' => $amount,
                'reason' => $reason,
                'total_before' => $before,
                'total_after' => $after,
                'user_id' => $user?->id,
            ]);

            AuditLog::record(
                'order.discount_granted',
                $current,
                ['total_sale' => $before, 'advance_due' => $advanceBefore],
                ['total_sale' => $after, 'advance_due' => $advance, 'discount' => $amount, 'reason' => $reason],
                $user,
            );

            $order->forceFill(['total_sale' => $after, 'advance_due' => $advance])->syncOriginal();

            return $discount;
        });
    }
}
