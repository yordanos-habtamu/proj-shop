<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can download the project for the given order.
     */
    public function viewDownload(User $user, Order $order): bool
    {
        return $order->buyer_id === $user->id && $order->isPaid();
    }
}
