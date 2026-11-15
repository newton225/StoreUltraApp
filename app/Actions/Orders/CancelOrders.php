<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancels, in one go, the orders a person ticked in the list, exactly as the button of the order page does one by
 * one (same rules, same trace, same reason for all). An order that cannot be cancelled any more (already delivered,
 * already cancelled...) or that the person may not see is left alone and counted as skipped: it never stops the others.
 */
class CancelOrders
{
    public function __construct(private readonly TransitionOrder $transition) {}

    /**
     * @param  list<int>  $orderIds
     * @return array{cancelled: int, skipped: int}
     */
    public function handle(array $orderIds, string $reason, User $user): array
    {
        $orders = Order::query()->visibleTo($user)->whereKey($orderIds)->orderBy('id')->get();
        $total = $orders->count();
        $cancelled = 0;

        foreach ($orders as $order) {
            if (! $order->status->isCancellable() || $user->cannot('transition', $order)) {
                continue;
            }

            try {
                DB::transaction(function () use ($order, $reason, $user, $total): void {
                    $this->transition->handle($order, OrderStatus::Cancelled, $reason, $user);

                    AuditLog::record('order.batch_step', $order, [], ['step' => 'cancelled', 'group_of' => $total], $user);
                });

                $cancelled++;
            } catch (InvalidOrderTransition) {
                continue;
            }
        }

        return ['cancelled' => $cancelled, 'skipped' => count(array_unique($orderIds)) - $cancelled];
    }
}
