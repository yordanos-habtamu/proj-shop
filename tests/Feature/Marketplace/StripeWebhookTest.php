<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Project;
use App\Notifications\OrderPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.enabled' => true,
            'services.stripe.webhook_secret' => 'whsec_test_secret',
        ]);
    }

    public function test_valid_signature_marks_the_order_paid(): void
    {
        Notification::fake();

        $project = Project::factory()->approved()->create(['price_cents' => 1999]);
        $order = Order::factory()->create([
            'project_id' => $project->id,
            'amount_cents' => 1999,
        ]);

        $this->postEvent('checkout.session.completed', [
            'id' => 'cs_test_123',
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_test_123',
        ]);

        $order->refresh();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('stripe', $order->provider);
        $this->assertSame('pi_test_123', $order->provider_tx_id);
        $this->assertSame('cs_test_123', $order->stripe_checkout_id);

        Notification::assertSentTo($order->buyer, OrderPaid::class);
    }

    public function test_replayed_completed_event_settles_only_once(): void
    {
        Notification::fake();

        $order = Order::factory()->create();

        $event = [
            'id' => 'cs_test_123',
            'client_reference_id' => (string) $order->id,
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_test_123',
        ];

        $this->postEvent('checkout.session.completed', $event);
        $this->postEvent('checkout.session.completed', $event);

        Notification::assertSentToTimes($order->buyer, OrderPaid::class, 1);
    }

    public function test_expired_checkout_session_expires_the_order(): void
    {
        $order = Order::factory()->create();

        $this->postEvent('checkout.session.expired', [
            'id' => 'cs_test_123',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(OrderStatus::Expired, $order->refresh()->status);
    }

    public function test_payment_failure_marks_the_order_failed(): void
    {
        $order = Order::factory()->create();

        $this->postEvent('payment_intent.payment_failed', [
            'id' => 'pi_test_123',
            'metadata' => ['order_id' => (string) $order->id],
        ]);

        $this->assertSame(OrderStatus::Failed, $order->refresh()->status);
    }

    public function test_a_payment_that_settles_after_expiry_still_pays_out(): void
    {
        $order = Order::factory()->expired()->create();

        $this->postEvent('checkout.session.completed', [
            'id' => 'cs_test_123',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_test_123',
        ]);

        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }

    public function test_refunded_charge_refunds_a_paid_order(): void
    {
        $order = Order::factory()->paid()->create([
            'provider_tx_id' => 'pi_test_123',
        ]);

        $this->postEvent('charge.refunded', [
            'id' => 'ch_test_123',
            'payment_intent' => 'pi_test_123',
        ]);

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
    }

    public function test_refund_is_ignored_for_an_unpaid_order(): void
    {
        $order = Order::factory()->create([
            'provider_tx_id' => 'pi_test_123',
        ]);

        $this->postEvent('charge.refunded', [
            'id' => 'ch_test_123',
            'payment_intent' => 'pi_test_123',
        ]);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_a_refunded_order_cannot_be_settled_again(): void
    {
        Notification::fake();

        $order = Order::factory()->refunded()->create([
            'provider_tx_id' => 'pi_test_123',
        ]);

        $this->postEvent('checkout.session.completed', [
            'id' => 'cs_test_123',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_test_123',
        ]);

        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);

        Notification::assertNothingSent();
    }

    public function test_missing_order_in_the_event_is_ignored(): void
    {
        $order = Order::factory()->create();

        $this->postEvent('checkout.session.completed', [
            'id' => 'cs_test_123',
            'metadata' => ['order_id' => '99999'],
            'payment_intent' => 'pi_test_123',
        ]);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_unrelated_event_types_are_acknowledged(): void
    {
        $order = Order::factory()->create();

        $this->postEvent('customer.created', ['id' => 'cus_test_123']);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $order = Order::factory()->create();

        $payload = $this->eventPayload('checkout.session.completed', [
            'id' => 'cs_test_123',
            'metadata' => ['order_id' => (string) $order->id],
            'payment_intent' => 'pi_test_123',
        ]);

        $this->postJson(
            route('webhooks.stripe'),
            json_decode($payload, true),
            ['Stripe-Signature' => 't=1,v1=not-the-right-signature'],
        )->assertStatus(400);

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_webhook_is_ignored_when_stripe_is_disabled(): void
    {
        config(['services.stripe.enabled' => false]);

        $this->postJson(route('webhooks.stripe'))
            ->assertOk()
            ->assertJson(['ignored' => true]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function postEvent(string $type, array $object): void
    {
        $payload = $this->eventPayload($type, $object);

        $this->postJson(
            route('webhooks.stripe'),
            json_decode($payload, true),
            ['Stripe-Signature' => $this->sign($payload)],
        )->assertOk()->assertJson(['received' => true]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function eventPayload(string $type, array $object): string
    {
        return json_encode([
            'id' => 'evt_test_123',
            'type' => $type,
            'data' => ['object' => $object],
        ], JSON_THROW_ON_ERROR);
    }

    private function sign(string $payload): string
    {
        $timestamp = time();
        $secret = config('services.stripe.webhook_secret');

        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
