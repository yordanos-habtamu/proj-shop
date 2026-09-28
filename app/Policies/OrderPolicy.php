<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view the receipt for the given order.
     */
    public function view(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id;
    }

    /**
     * Determine whether the user can request a download for the given order.
     */
    public function viewDownload(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->isPaid();
    }
}
