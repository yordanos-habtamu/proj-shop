<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentProcessor;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    /**
     * Handle Stripe webhooks (CSRF-exempt).
     */
    public function handle(Request $request): JsonResponse
    {
        if (! $this->stripe->isEnabled()) {
            return response()->json(['ignored' => true]);
        }

        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');

        try {
            $event = $this->stripe->verifyWebhookPayload($payload, $signature);
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['error' => 'invalid signature'], 400);
        }

        $type = is_string($event['type'] ?? null) ? $event['type'] : '';
        $object = $event['data']['object'] ?? [];

        if (is_array($object)) {
            $this->handleEvent($type, $object);
        }

        return response()->json(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function handleEvent(string $type, array $object): void
    {
        switch ($type) {
            case 'checkout.session.completed':
                $this->settleOrder($object);
                break;
            case 'checkout.session.expired':
                $this->resolveOrder($object)?->markExpired();
                break;
            case 'payment_intent.payment_failed':
                $this->resolveOrder($object)?->markFailed();
                break;
            case 'charge.refunded':
                $this->refundOrder($object);
                break;
        }
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function settleOrder(array $session): void
    {
        $order = $this->resolveOrder($session);

        if (! $order instanceof Order) {
            return;
        }

        PaymentProcessor::markAsPaid(
            $order,
            'stripe',
            (string) ($session['payment_intent'] ?? $session['id']),
            is_string($session['id'] ?? null) ? $session['id'] : null,
        );
    }

    /**
     * Resolve the order referenced by a Stripe object, either through the
     * metadata we stamp on sessions/payment intents or the stored transaction id.
     *
     * @param  array<string, mixed>  $object
     */
    private function resolveOrder(array $object): ?Order
    {
        $orderId = $object['metadata']['order_id'] ?? $object['client_reference_id'] ?? null;

        if (is_numeric($orderId)) {
            $order = Order::find((int) $orderId);

            if ($order instanceof Order) {
                return $order;
            }
        }

        $paymentIntent = $object['payment_intent'] ?? $object['id'] ?? null;

        if (! is_string($paymentIntent)) {
            return null;
        }

        return Order::query()
            ->where('provider_tx_id', $paymentIntent)
            ->first();
    }

    /**
     * A refunded charge settles the order only if it was paid; the transition
     * guard in the model keeps replays of this event harmless.
     *
     * @param  array<string, mixed>  $charge
     */
    private function refundOrder(array $charge): void
    {
        $order = $this->resolveOrder($charge);

        if (! $order instanceof Order) {
            return;
        }

        PaymentProcessor::markAsRefunded($order);
    }
}
