<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\OAuth;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.stripe.enabled');
    }

    /**
     * Create a Stripe Checkout session charged to the buyer that transfers the
     * sale to the seller's connected account and collects the platform fee.
     *
     * The order id is stamped on both the session and the payment intent so
     * that later payment-level events can be correlated back to the order.
     *
     * @throws ApiErrorException
     */
    public function createCheckoutSession(Order $order): StripeCheckoutSession
    {
        $project = $order->project;
        $seller = $project->seller;

        return StripeCheckoutSession::create([
            'mode' => 'payment',
            'client_reference_id' => (string) $order->id,
            'metadata' => [
                'order_id' => (string) $order->id,
                'project_id' => (string) $project->id,
            ],
            'payment_intent_data' => [
                'transfer_data' => [
                    'destination' => $seller->stripe_connect_id,
                ],
                'application_fee_amount' => $order->fee_amount_cents,
                'metadata' => [
                    'order_id' => (string) $order->id,
                ],
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => $order->currency,
                    'product_data' => [
                        'name' => $project->title,
                    ],
                    'unit_amount' => $order->amount_cents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => route('orders.show', ['order' => $order, 'session_id' => '{CHECKOUT_SESSION_ID}']),
            'cancel_url' => route('projects.show', $project->slug),
        ], [
            'idempotency_key' => 'prodhunt-order-'.$order->getKey(),
        ]);
    }

    /**
     * Build the Connect OAuth authorize URL redirecting the seller to Stripe.
     */
    public function oauthAuthorizeUrl(string $state, ?string $redirectUri = null): string
    {
        $redirectUri ??= config('services.stripe.redirect_uri') ?? route('connect.callback');

        return 'https://connect.stripe.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.stripe.client_id'),
            'scope' => 'read_write',
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Exchange the OAuth code for the seller's connected account id.
     *
     * @throws ApiErrorException
     */
    public function oauthRetrieveConnectedAccountId(string $code): string
    {
        $response = OAuth::token([
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        return $response['stripe_user_id'];
    }

    /**
     * Verify the webhook signature and return the raw event payload.
     *
     * @return array<string, mixed>
     */
    public function verifyWebhookPayload(string $payload, string $signature): array
    {
        $event = Webhook::constructEvent($payload, $signature, config('services.stripe.webhook_secret'));

        return $event->toArray();
    }

    /**
     * Connect a seller for real (no-op) or demo (immediate fake account).
     */
    public function connectSeller(User $user, ?string $connectedAccountId = null): void
    {
        if ($connectedAccountId === null) {
            $connectedAccountId = 'acct_demo_'.$user->id;
        }

        $user->update([
            'stripe_connect_id' => $connectedAccountId,
            'stripe_connect_status' => 'active',
        ]);
    }
}
