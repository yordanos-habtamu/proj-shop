<?php

namespace App\Http\Controllers;

use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;

class ConnectController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    /**
     * Show the seller's Stripe Connect status.
     */
    public function show(): Response
    {
        $user = request()->user();

        return Inertia::render('connect', [
            'is_demo' => ! $this->stripe->isEnabled(),
            'connected' => $user->isStripeConnected(),
            'status' => $user->stripe_connect_status,
        ]);
    }

    /**
     * Start Stripe Connect onboarding.
     */
    public function start(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->stripe->isEnabled()) {
            $state = Str::random(40);
            $request->session()->put('stripe_connect_state', $state);

            return redirect()->away(
                $this->stripe->oauthAuthorizeUrl($state, config('services.stripe.redirect_uri')),
            );
        }

        $this->stripe->connectSeller($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stripe connected (demo).')]);

        return back();
    }

    /**
     * Handle the Connect OAuth redirect back from Stripe.
     */
    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $code = $request->query('code');

        if (
            ! $this->stripe->isEnabled()
            || $code === null
            || $state !== $request->session()->pull('stripe_connect_state')
        ) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe connection failed.')]);

            return to_route('connect.show');
        }

        try {
            $accountId = $this->stripe->oauthRetrieveConnectedAccountId($code);
        } catch (ApiErrorException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe connection failed.')]);

            return to_route('connect.show');
        }

        $this->stripe->connectSeller($request->user(), $accountId);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stripe account connected.')]);

        return to_route('connect.show');
    }
}
