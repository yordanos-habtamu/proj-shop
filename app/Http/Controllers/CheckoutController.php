<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Project;
use App\Services\PaymentProcessor;
use App\Services\StripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;

class CheckoutController extends Controller
{
    public function __construct(private readonly StripeService $stripe) {}

    /**
     * Create a pending order for a project and start checkout.
     *
     * Re-entering checkout reuses the buyer's existing pending order for the
     * project so a double click cannot open two payment attempts.
     */
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('purchase', $project);

        $user = $request->user();

        $order = $this->pendingOrderFor($user->id, $project);

        $split = Fee::computeFor($project->price_cents);

        $order->fill([
            'amount_cents' => $project->price_cents,
            'fee_percent' => $split['percent'],
            'fee_amount_cents' => $split['fee_amount_cents'],
            'payout_cents' => $split['payout_cents'],
            'currency' => $project->currency,
        ])->save();

        if ($order->amount_cents === 0) {
            PaymentProcessor::markAsPaid($order, 'free', 'free_'.$order->getKey());

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Project acquired.')]);

            return to_route('orders.show', $order);
        }

        if (! $this->stripe->isEnabled()) {
            return to_route('checkout.demo', $order);
        }

        if (! $project->seller->isStripeConnected()) {
            $order->delete();

            Inertia::flash('toast', ['type' => 'error', 'message' => __('The seller has not connected Stripe yet.')]);

            return back();
        }

        try {
            $session = $this->stripe->createCheckoutSession($order);
        } catch (ApiErrorException) {
            $order->delete();

            Inertia::flash('toast', ['type' => 'error', 'message' => __('Stripe checkout could not be started.')]);

            return back();
        }

        $order->update([
            'provider' => 'stripe',
            'stripe_checkout_id' => $session->id,
        ]);

        return redirect()->away($session->url);
    }

    /**
     * Demo payment page (used when STRIPE_ENABLED is false).
     */
    public function demo(Request $request, Order $order): Response
    {
        $this->authorizeOrder($request, $order);

        $order->load('project:id,title,slug');

        return Inertia::render('checkout/demo', [
            'order' => [
                'id' => $order->id,
                'amount_cents' => $order->amount_cents,
                'fee_percent' => $order->fee_percent,
                'fee_amount_cents' => $order->fee_amount_cents,
                'payout_cents' => $order->payout_cents,
                'currency' => $order->currency,
            ],
            'project' => [
                'id' => $order->project->id,
                'title' => $order->project->title,
                'slug' => $order->project->slug,
            ],
        ]);
    }

    /**
     * Demo pay: mark the order paid as if a webhook fired.
     */
    public function demoPay(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOrder($request, $order);

        PaymentProcessor::markAsPaid($order, 'demo', 'demo_'.Str::random(16));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Payment confirmed.')]);

        return to_route('orders.show', $order);
    }

    /**
     * Find (or prepare) the buyer's pending order for a project.
     *
     * Returns an unsaved order when none exists; the caller fills the money
     * columns and saves it once.
     */
    private function pendingOrderFor(int $buyerId, Project $project): Order
    {
        $existing = Order::query()
            ->where('buyer_id', $buyerId)
            ->where('project_id', $project->getKey())
            ->where('status', OrderStatus::Pending)
            ->latest('id')
            ->first();

        return $existing ?? new Order([
            'buyer_id' => $buyerId,
            'project_id' => $project->getKey(),
            'status' => OrderStatus::Pending,
        ]);
    }

    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless($order->buyer_id === $request->user()?->id, 403);
        abort_unless($order->status === OrderStatus::Pending, 403);
    }
}
