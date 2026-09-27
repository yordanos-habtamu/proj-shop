<?php

namespace App\Services;

use App\Models\Order;
use App\Notifications\OrderPaid;
use Illuminate\Support\Facades\DB;

class PaymentProcessor
{
    /**
     * Mark an order as paid and notify the buyer.
     *
     * Idempotent: the row is re-read under a lock, so a replayed webhook never
     * settles twice and never sends a second receipt.
     */
    public static function markAsPaid(Order $order, string $provider, string $providerTxId, ?string $checkoutId = null): bool
    {
        $settled = DB::transaction(function () use ($order, $provider, $providerTxId, $checkoutId): bool {
            $locked = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->first();

            return $locked instanceof Order
                && $locked->markPaid($provider, $providerTxId, $checkoutId);
        });

        if (! $settled) {
            return false;
        }

        $order->refresh();
        $order->buyer->notify(new OrderPaid($order));

        return true;
    }
}
