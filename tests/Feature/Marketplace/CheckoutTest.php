<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Notifications\OrderPaid;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private User $seller;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Fee::setActive(10);

        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->seller()->create();
        $this->project = Project::factory()->approved()->create([
            'seller_id' => $this->seller->id,
            'price_cents' => 1999,
        ]);
    }

    public function test_checkout_creates_pending_order_with_fee_snapshot_in_demo_mode(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project))
            ->assertRedirect(route('checkout.demo', Order::first()));

        $order = Order::first();

        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(1999, $order->amount_cents);
        $this->assertSame(10, $order->fee_percent);
        $this->assertSame(200, $order->fee_amount_cents);
        $this->assertSame(1799, $order->payout_cents);
    }

    public function test_demo_page_shows_fee_breakdown(): void
    {
        $order = Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
            'amount_cents' => 1999,
            'fee_percent' => 10,
            'fee_amount_cents' => 200,
            'payout_cents' => 1799,
        ]);

        $this->actingAs($this->buyer)
            ->get(route('checkout.demo', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/demo')
                ->where('order.fee_percent', 10)
                ->where('order.payout_cents', 1799)
            );
    }

    public function test_demo_pay_marks_the_order_paid_and_notifies_the_buyer(): void
    {
        Notification::fake();

        $order = Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
            'amount_cents' => 1999,
            'fee_percent' => 10,
            'fee_amount_cents' => 200,
            'payout_cents' => 1799,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('checkout.demo-pay', $order))
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertSame('demo', $order->provider);
        $this->assertNotNull($order->provider_tx_id);

        Notification::assertSentTo($this->buyer, OrderPaid::class);
    }

    public function test_checkout_is_forbidden_without_auth_or_for_guests(): void
    {
        $this->post(route('checkout.store', $this->project))
            ->assertRedirect(route('login'));
    }

    public function test_owner_cannot_purchase_their_own_project(): void
    {
        $this->actingAs($this->seller)
            ->post(route('checkout.store', $this->project))
            ->assertForbidden();
    }

    public function test_buyer_cannot_buy_a_project_they_already_own(): void
    {
        Order::factory()->paid()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project))
            ->assertForbidden();
    }

    public function test_demo_checkout_is_limited_to_the_pending_order_owner(): void
    {
        $order = Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);

        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('checkout.demo', $order))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('checkout.demo-pay', $order))
            ->assertForbidden();
    }

    public function test_free_project_is_acquired_immediately(): void
    {
        $free = Project::factory()->approved()->create([
            'seller_id' => $this->seller->id,
            'price_cents' => 0,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $free))
            ->assertRedirect(route('orders.show', Order::firstOrFail()));

        $order = Order::where('project_id', $free->id)->firstOrFail();

        $this->assertTrue($order->isPaid());
        $this->assertSame('free', $order->provider);
    }

    public function test_re_entering_checkout_reuses_the_pending_order(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project));

        $first = Order::firstOrFail();

        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project))
            ->assertRedirect(route('checkout.demo', $first));

        $this->assertSame(1, Order::count());
        $this->assertTrue($first->refresh()->isPending());
    }

    public function test_re_entering_checkout_refreshes_the_fee_snapshot(): void
    {
        Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
            'amount_cents' => 100,
            'fee_percent' => 0,
            'fee_amount_cents' => 0,
            'payout_cents' => 100,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project));

        $order = Order::sole();

        $this->assertSame(1999, $order->amount_cents);
        $this->assertSame(10, $order->fee_percent);
        $this->assertSame(200, $order->fee_amount_cents);
        $this->assertSame(1799, $order->payout_cents);
    }

    public function test_enabled_stripe_creates_a_checkout_session(): void
    {
        $this->seller->update([
            'stripe_connect_id' => 'acct_123',
            'stripe_connect_status' => 'active',
        ]);

        $session = StripeCheckoutSession::constructFrom([
            'id' => 'cs_test_123',
            'url' => 'https://checkout.stripe.com/pay/cs_test_123',
        ]);

        $stripe = $this->mock(StripeService::class);
        $stripe->shouldReceive('isEnabled')->once()->andReturn(true);
        $stripe->shouldReceive('createCheckoutSession')->once()->andReturn($session);

        $this->actingAs($this->buyer)
            ->post(route('checkout.store', $this->project))
            ->assertRedirect('https://checkout.stripe.com/pay/cs_test_123');

        $order = Order::firstOrFail();

        $this->assertSame('stripe', $order->provider);
        $this->assertSame('cs_test_123', $order->stripe_checkout_id);
    }

    public function test_checkout_requires_the_seller_to_be_connected(): void
    {
        $stripe = $this->mock(StripeService::class);
        $stripe->shouldReceive('isEnabled')->once()->andReturn(true);

        $this->actingAs($this->buyer)
            ->from(route('projects.show', $this->project->slug))
            ->post(route('checkout.store', $this->project))
            ->assertRedirect(route('projects.show', $this->project->slug));

        $this->assertDatabaseCount('orders', 0);
    }
}
