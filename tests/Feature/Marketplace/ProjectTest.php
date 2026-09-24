<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Enums\ProjectCompleteness;
use App\Enums\ProjectStatus;
use App\Enums\ReviewVerdict;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_belongs_to_a_seller_and_has_reviews_and_orders(): void
    {
        $project = Project::factory()->create();

        $this->assertInstanceOf(User::class, $project->seller);
        $this->assertTrue($project->reviews()->get()->isEmpty());
        $this->assertTrue($project->orders()->get()->isEmpty());
    }

    public function test_project_enum_casts_and_default_factory_state(): void
    {
        $project = Project::factory()->draft()->create();

        $this->assertInstanceOf(ProjectCompleteness::class, $project->completeness);
        $this->assertInstanceOf(ProjectStatus::class, $project->status);
        $this->assertIsArray($project->tech_stack);
        $this->assertFalse($project->isPublished());
    }

    public function test_approved_events_are_auditable_via_reviews(): void
    {
        $project = Project::factory()->pendingReview()->create();
        $reviewer = User::factory()->reviewer()->create();

        $project->reviewed_at = now();
        $project->review_notes = 'Looks safe.';
        $project->save();

        $project->reviews()->create([
            'reviewer_id' => $reviewer->id,
            'verdict' => ReviewVerdict::Approved,
        ]);

        $this->assertSame('Looks safe.', $project->refresh()->review_notes);
        $this->assertSame(ReviewVerdict::Approved, $project->reviews->first()->verdict);
    }

    public function test_mark_approved_and_mark_rejected_transitions(): void
    {
        $project = Project::factory()->pendingReview()->create();

        $project->markApproved('Good to go');
        $this->assertTrue($project->isPublished());
        $this->assertNotNull($project->reviewed_at);

        $other = Project::factory()->pendingReview()->create();
        $other->markRejected('Contains secrets');
        $this->assertSame(ProjectStatus::Rejected, $other->status);
        $this->assertSame('Contains secrets', $other->review_notes);
        $this->assertNotNull($other->reviewed_at);
    }

    public function test_guest_can_view_published_project_but_not_draft(): void
    {
        $published = Project::factory()->approved()->create();
        $draft = Project::factory()->draft()->create();

        $this->assertTrue(Gate::forUser(null)->allows('view', $published));
        $this->assertFalse(Gate::forUser(null)->allows('view', $draft));
    }

    public function test_policies_gate_project_mutations(): void
    {
        $seller = User::factory()->seller()->create();
        $other = User::factory()->create();

        $draft = Project::factory()->draft()->create(['seller_id' => $seller->id]);
        $published = Project::factory()->approved()->create(['seller_id' => $seller->id]);

        $this->assertTrue($seller->can('update', $draft));
        $this->assertTrue($seller->can('delete', $draft));
        $this->assertFalse($other->can('update', $draft));
        $this->assertFalse($seller->can('update', $published));
        $this->assertFalse($seller->can('delete', $published));
        $this->assertTrue($seller->can('submitForReview', $draft));
    }

    public function test_only_sellers_can_create_projects(): void
    {
        $buyer = User::factory()->create();
        $reviewer = User::factory()->reviewer()->create();

        $this->assertFalse($buyer->can('create', Project::class));
        $this->assertTrue($reviewer->can('create', Project::class));
    }

    public function test_purchase_policy(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();
        $published = Project::factory()->approved()->create(['seller_id' => $seller->id]);
        $draft = Project::factory()->create(['seller_id' => $seller->id]);

        $this->assertTrue($buyer->can('purchase', $published));
        $this->assertFalse($buyer->can('purchase', $draft));
        $this->assertFalse($seller->can('purchase', $published));

        Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $published->id,
            'amount_cents' => $published->price_cents,
        ]);

        $this->assertFalse($buyer->can('purchase', $published));
    }

    public function test_download_policy_only_for_paying_buyer(): void
    {
        $buyer = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->approved()->create();

        $order = Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $project->id,
            'amount_cents' => $project->price_cents,
        ]);

        $this->assertTrue($buyer->can('viewDownload', $order));
        $this->assertFalse($other->can('viewDownload', $order));

        $order->markRefunded();
        $this->assertSame(OrderStatus::Refunded, $order->status);
        $this->assertFalse($buyer->can('viewDownload', $order));
    }

    public function test_review_policy_only_for_reviewers(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create();
        $project = Project::factory()->pendingReview()->create();

        $this->assertTrue($reviewer->can('approve', $project));
        $this->assertTrue($admin->can('reject', $project));
        $this->assertFalse($buyer->can('approve', $project));
        $this->assertFalse($buyer->can('manageReview', $project));
    }
}
