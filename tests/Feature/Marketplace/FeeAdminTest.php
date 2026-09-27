<?php

namespace Tests\Feature\Marketplace;

use App\Models\Fee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeeAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_non_admin_cannot_view_the_fee_page(): void
    {
        $this->get(route('admin.fees.index'))
            ->assertRedirect(route('login'));

        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)
            ->get(route('admin.fees.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_the_fee_page(): void
    {
        Fee::setActive(7, $this->admin->id);

        $this->actingAs($this->admin)
            ->get(route('admin.fees.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/fees')
                ->where('currentPercent', 7)
                ->has('history', 1)
            );
    }

    public function test_admin_can_update_the_active_fee(): void
    {
        Fee::setActive(5, $this->admin->id);

        $this->actingAs($this->admin)
            ->post(route('admin.fees.update'), ['percent' => 12])
            ->assertRedirect(route('admin.fees.index'));

        $this->assertSame(12, Fee::activePercent());
        $this->assertDatabaseCount('platform_fees', 2);
        $this->assertSame(1, Fee::where('is_active', true)->count());
    }

    public function test_fee_percent_is_validated(): void
    {
        foreach (['-1', 150, 'abc'] as $invalid) {
            $this->actingAs($this->admin)
                ->post(route('admin.fees.update'), ['percent' => $invalid])
                ->assertSessionHasErrors('percent');
        }
    }

    public function test_fee_compute_splits_fee_and_payout(): void
    {
        Fee::setActive(10, $this->admin->id);

        $split = Fee::computeFor(1999);

        $this->assertSame([
            'percent' => 10,
            'fee_amount_cents' => 200,
            'payout_cents' => 1799,
        ], $split);
    }

    public function test_fee_defaults_to_zero_when_none_is_active(): void
    {
        $this->assertSame(0, Fee::activePercent());

        $split = Fee::computeFor(1999);

        $this->assertSame(0, $split['fee_amount_cents']);
        $this->assertSame(1999, $split['payout_cents']);
    }
}
