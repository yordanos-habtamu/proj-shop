<?php

namespace Tests\Feature\Marketplace;

use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_browse_lists_only_approved_projects(): void
    {
        Project::factory()->count(2)->approved()->create();
        Project::factory()->pendingReview()->create();
        Project::factory()->draft()->create();

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('market/browse')
                ->has('projects', 2)
                ->where('count', 2)
            );
    }

    public function test_browse_filters_by_completeness(): void
    {
        Project::factory()->approved()->create(['completeness' => 'mvp', 'title' => 'MVP thing']);
        Project::factory()->approved()->create(['completeness' => 'complete', 'title' => 'Full kit']);

        $this->get(route('projects.index', ['completeness' => 'mvp']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.completeness', 'mvp')
            );
    }

    public function test_browse_filters_by_tech_stack_tag(): void
    {
        Project::factory()->approved()->create(['tech_stack' => ['laravel', 'react'], 'title' => 'Laravel app']);
        Project::factory()->approved()->create(['tech_stack' => ['django'], 'title' => 'Django app']);

        $this->get(route('projects.index', ['stack' => 'laravel']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.title', 'Laravel app')
            );
    }

    public function test_browse_searches_title_tagline_and_description(): void
    {
        Project::factory()->approved()->create(['title' => 'Payment Insights', 'tagline' => 'Nothing here']);
        Project::factory()->approved()->create(['tagline' => 'Analytics dashboard', 'title' => 'Other']);
        Project::factory()->approved()->create(['description' => 'A deep dive into analytics', 'title' => 'Docs']);

        $this->get(route('projects.index', ['q' => 'analytics']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 2)
            );
    }

    public function test_browse_sorts_by_price(): void
    {
        Project::factory()->approved()->create(['price_cents' => 9900, 'title' => 'Expensive']);
        Project::factory()->approved()->create(['price_cents' => 999, 'title' => 'Cheap']);

        $this->get(route('projects.index', ['sort' => 'price_asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.title', 'Cheap')
                ->where('projects.1.title', 'Expensive')
            );
    }

    public function test_guest_can_view_an_approved_project_page(): void
    {
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->approved()->create(['seller_id' => $seller->id]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('market/show')
                ->where('project.title', $project->title)
                ->where('can_buy', false)
                ->where('is_owner', false)
            );
    }

    public function test_pending_project_is_not_visible_to_guests(): void
    {
        $project = Project::factory()->pendingReview()->create();

        $this->get(route('projects.show', $project->slug))
            ->assertForbidden();
    }

    public function test_owner_can_view_their_pending_project_via_slug(): void
    {
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->pendingReview()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('is_owner', true));
    }

    public function test_cover_image_is_served_to_guests_for_published_projects(): void
    {
        Storage::fake('local');

        $project = Project::factory()->approved()->create();
        $project->update(['cover_image_path' => 'projects/'.$project->id.'/cover.png']);
        Storage::disk('local')->put($project->cover_image_path, 'fake-image-bytes');

        $this->get(route('projects.cover', $project))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_cover_of_pending_project_is_forbidden_for_guests(): void
    {
        $project = Project::factory()->pendingReview()->create(['cover_image_path' => 'projects/1/cover.png']);

        $this->get(route('projects.cover', $project))
            ->assertForbidden();
    }

    public function test_cover_of_project_without_image_is_404(): void
    {
        $project = Project::factory()->approved()->create(['cover_image_path' => null]);

        $this->get(route('projects.cover', $project))
            ->assertNotFound();
    }

    public function test_home_page_lists_recent_approved_projects(): void
    {
        Project::factory()->count(3)->approved()->create();
        Project::factory()->pendingReview()->create();

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('projects', 3)
            );
    }

    public function test_show_reports_ownership_and_purchase_state(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $project = Project::factory()->approved()->create(['seller_id' => $seller->id]);

        $this->actingAs($buyer)
            ->get(route('projects.show', $project->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_buy', true)
                ->where('is_owner', false)
                ->where('is_bought', false)
            );

        $order = Order::factory()->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('projects.show', $project->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('can_buy', false)
                ->where('is_bought', true)
            );

        $this->actingAs($seller)
            ->get(route('projects.show', $project->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('is_owner', true)
                ->where('can_buy', false)
            );
    }
}
