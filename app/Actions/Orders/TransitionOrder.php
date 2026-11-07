<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessSettings;
use Illuminate\Support\Facades\DB;

class TransitionOrder
{
    public function __construct(private BusinessSettings $settings) {}

    /**
     * Move an order one step along its life, or cancel it, and keep the trace of it. The state is read
     * again under a lock of the row, so two requests that both saw the same state cannot both move it.
     *
     * @throws InvalidOrderTransition when the order cannot go to this status
     */
    public function handle(Order $order, OrderStatus $to, ?string $reason = null, ?User $user = null): void
    {
        DB::transaction(function () use ($order, $to, $reason, $user): void {
            $current = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $current->status;

            if (! $from->canTransitionTo($to)) {
                throw InvalidOrderTransition::between($from, $to);
            }

            if ($to === OrderStatus::AdvanceReceived
                && $this->settings->get('advance_required')
                && $current->netPaid() < $current->advance_due) {
                throw InvalidOrderTransition::advanceMissing();
            }

            if ($to === OrderStatus::Delivered && $current->remainingKits() > 0) {
                throw InvalidOrderTransition::deliveryMissing();
            }

            if ($to === OrderStatus::Settled && $current->netPaid() < $current->total_sale) {
                throw InvalidOrderTransition::balanceDue();
            }

            $current->forceFill(['status' => $to])->save();

            $current->statusChanges()->create([
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
                'user_id' => $user?->id,
            ]);

            AuditLog::record(
                'order.status_changed',
                $current,
                ['status' => $from->value],
                ['status' => $to->value],
                $user,
            );

            $order->forceFill(['status' => $to])->syncOriginalAttribute('status');
        });
    }
}
