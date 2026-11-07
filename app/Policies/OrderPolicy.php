<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Every staff member can list the orders they are allowed to see.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * An order is visible to whoever can see its client.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->reachesClient($order->client);
    }

    /**
     * Only the office creates orders.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-orders');
    }

    /**
     * Only the office gives money back.
     */
    public function refund(User $user, Order $order): bool
    {
        return $user->can('manage-orders');
    }

    /**
     * Only the office moves an order along its life.
     */
    public function transition(User $user, Order $order): bool
    {
        return $user->can('manage-orders');
    }
}
