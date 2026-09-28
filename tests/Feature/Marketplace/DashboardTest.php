<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Notifications\OrderRefunded;
use App\Notifications\SellerOrderPaid;
use App\Services\PaymentProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_buyer_sees_purchased_projects_in_library(): void
    {
        $buyer = User::factory()->create(['name' => 'Alice Buyer']);
        $seller = User::factory()->seller()->create(['name' => 'Bob Seller']);

        $project = Project::factory()->approved()->create([
            'seller_id' => $seller->id,
            'title' => 'SaaS Starter Kit',
            'slug' => 'saas-starter-kit',
            'price_cents' => 4900,
        ]);

        Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $project->id,
            'amount_cents' => 4900,
            'fee_percent' => 10,
            'fee_amount_cents' => 490,
            'payout_cents' => 4410,
        ]);

        $this->actingAs($buyer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('is_seller', false)
                ->where('pending_reviews_count', 0)
                ->has('library', 1)
                ->where('library.0.project.title', 'SaaS Starter Kit')
                ->where('library.0.project.seller_name', 'Bob Seller')
                ->where('library.0.amount_cents', 4900)
                ->where('library.0.can_download', true)
            );
    }

    public function test_unpaid_or_other_buyers_orders_do_not_appear_in_library(): void
    {
        $buyer = User::factory()->create();
        $otherBuyer = User::factory()->create();
        $project = Project::factory()->approved()->create();

        // Pending order for this buyer
        Order::factory()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $project->id,
            'status' => OrderStatus::Pending,
        ]);

        // Paid order for another buyer
        Order::factory()->paid()->create([
            'buyer_id' => $otherBuyer->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('library', 0)
            );
    }

    public function test_seller_sees_sales_metrics_revenue_and_recent_sales(): void
    {
        $seller = User::factory()->seller()->create([
            'stripe_connect_id' => 'acct_test_123',
            'stripe_connect_status' => 'active',
        ]);
        $buyer = User::factory()->create(['name' => 'Charlie Buyer']);

        $projectA = Project::factory()->approved()->create([
            'seller_id' => $seller->id,
            'title' => 'CRM Boilerplate',
            'price_cents' => 3000,
        ]);

        $projectB = Project::factory()->approved()->create([
            'seller_id' => $seller->id,
            'title' => 'AI Chat Template',
            'price_cents' => 5000,
        ]);

        // 2 paid sales for Project A
        Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $projectA->id,
            'amount_cents' => 3000,
            'fee_percent' => 10,
            'fee_amount_cents' => 300,
            'payout_cents' => 2700,
        ]);

        Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $projectA->id,
            'amount_cents' => 3000,
            'fee_percent' => 10,
            'fee_amount_cents' => 300,
            'payout_cents' => 2700,
        ]);

        // 1 paid sale for Project B
        Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $projectB->id,
            'amount_cents' => 5000,
            'fee_percent' => 10,
            'fee_amount_cents' => 500,
            'payout_cents' => 4500,
        ]);

        $this->actingAs($seller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('is_seller', true)
                ->where('seller.stripe_connected', true)
                ->where('seller.metrics.total_sales_count', 3)
                ->where('seller.metrics.gross_revenue_cents', 11000)
                ->where('seller.metrics.net_payout_cents', 9900)
                ->where('seller.metrics.projects_count', 2)
                ->where('seller.metrics.published_count', 2)
                ->has('seller.recent_sales', 3)
                ->where('seller.recent_sales.0.buyer_name', 'Charlie Buyer')
            );
    }

    public function test_reviewer_sees_pending_reviews_count(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        Project::factory()->pendingReview()->count(3)->create();
        Project::factory()->approved()->count(2)->create();

        $this->actingAs($reviewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('pending_reviews_count', 3)
            );
    }

    public function test_payment_processor_notifies_seller_on_sale(): void
    {
        Notification::fake();

        $seller = User::factory()->seller()->create();
        $buyer = User::factory()->create();
        $project = Project::factory()->approved()->create(['seller_id' => $seller->id]);

        $order = Order::factory()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $project->id,
            'amount_cents' => 2000,
            'payout_cents' => 1800,
            'status' => OrderStatus::Pending,
        ]);

        $settled = PaymentProcessor::markAsPaid($order, 'stripe', 'pi_test_123');

        $this->assertTrue($settled);
        Notification::assertSentTo($seller, SellerOrderPaid::class);
    }

    public function test_payment_processor_notifies_buyer_on_refund(): void
    {
        Notification::fake();

        $buyer = User::factory()->create();
        $order = Order::factory()->paid()->create(['buyer_id' => $buyer->id]);

        $refunded = PaymentProcessor::markAsRefunded($order);

        $this->assertTrue($refunded);
        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);
        Notification::assertSentTo($buyer, OrderRefunded::class);
    }
}
