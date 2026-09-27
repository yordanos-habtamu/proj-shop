<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_connect_page_shows_demo_status(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->get(route('connect.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('connect')
                ->where('is_demo', true)
                ->where('connected', false)
            );
    }

    public function test_guest_is_redirected_away_from_connect(): void
    {
        $this->get(route('connect.show'))
            ->assertRedirect(route('login'));
    }

    public function test_demo_connect_fakes_a_connected_account(): void
    {
        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->from(route('connect.show'))
            ->post(route('connect.start'))
            ->assertRedirect(route('connect.show'));

        $seller->refresh();

        $this->assertSame('acct_demo_'.$seller->id, $seller->stripe_connect_id);
        $this->assertSame('active', $seller->stripe_connect_status);
        $this->assertTrue($seller->isStripeConnected());
    }

    public function test_start_redirects_to_stripe_oauth_when_enabled(): void
    {
        config(['services.stripe.enabled' => true]);

        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->post(route('connect.start'))
            ->assertRedirectContains('https://connect.stripe.com/oauth/authorize');
    }

    public function test_callback_stores_the_connected_account(): void
    {
        config(['services.stripe.enabled' => true]);

        $stripe = $this->partialMock(StripeService::class);
        $stripe->shouldReceive('oauthRetrieveConnectedAccountId')->once()->with('code_123')->andReturn('acct_real_123');

        $seller = User::factory()->seller()->create();

        $this->withSession(['stripe_connect_state' => 'state_abc'])
            ->actingAs($seller)
            ->get(route('connect.callback', ['code' => 'code_123', 'state' => 'state_abc']))
            ->assertRedirect(route('connect.show'));

        $seller->refresh();

        $this->assertSame('acct_real_123', $seller->stripe_connect_id);
        $this->assertSame('active', $seller->stripe_connect_status);
    }

    public function test_callback_rejects_mismatched_state(): void
    {
        config(['services.stripe.enabled' => true]);

        $stripe = $this->partialMock(StripeService::class);

        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->get(route('connect.callback', ['code' => 'code_123', 'state' => 'wrong']))
            ->assertRedirect(route('connect.show'));

        $seller->refresh();

        $this->assertNull($seller->stripe_connect_id);
    }
}
