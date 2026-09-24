<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ProjectStatus;
use App\Enums\ReviewVerdict;
use App\Jobs\ScanProjectArchive;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectReviewOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_dispatches_a_scan_job(): void
    {
        Queue::fake();

        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->post(
            route('projects.store'),
            $this->validPayload(),
        );

        Queue::assertPushed(ScanProjectArchive::class);
    }

    public function test_upload_runs_the_scan_and_stores_a_report(): void
    {
        Storage::fake('local');

        $seller = User::factory()->seller()->create();

        $this->actingAs($seller)->post(
            route('projects.store'),
            $this->validPayload(),
        );

        $project = $seller->projects()->firstOrFail();

        $this->assertIsArray($project->scan_report);
        $this->assertSame('clean', $project->scan_report['verdict']);
    }

    public function test_reviewer_can_view_the_queue_with_scan_reports(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->pendingReview()->create(['seller_id' => $seller->id]);

        $this->actingAs($reviewer)
            ->get(route('projects.review'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/review')
                ->has('projects', 1)
                ->where('projects.0.seller_id', $seller->id)
            );
    }

    public function test_non_reviewers_cannot_access_the_queue(): void
    {
        $buyer = User::factory()->create();
        $seller = User::factory()->seller()->create();

        $this->actingAs($buyer)->get(route('projects.review'))->assertForbidden();
        $this->actingAs($seller)->get(route('projects.review'))->assertForbidden();
    }

    public function test_reviewer_can_approve_a_pending_project(): void
    {
        Notification::fake();

        $reviewer = User::factory()->reviewer()->create();
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->pendingReview()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($reviewer)->post(
            route('projects.approve', $project),
            ['notes' => 'Looks great!'],
        );

        $response->assertRedirect(route('projects.review'));

        $project->refresh();

        $this->assertTrue($project->isPublished());

        $this->assertDatabaseHas('project_reviews', [
            'project_id' => $project->id,
            'reviewer_id' => $reviewer->id,
            'verdict' => ReviewVerdict::Approved->value,
            'notes' => 'Looks great!',
        ]);

        Notification::assertSentTo(
            $seller,
            ProjectReviewOutcome::class,
            fn ($notification, $channels): bool => $notification->approved === true,
        );
    }

    public function test_reviewer_can_reject_a_pending_project_with_notes(): void
    {
        Notification::fake();

        $reviewer = User::factory()->reviewer()->create();
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->pendingReview()->create(['seller_id' => $seller->id]);

        $this->actingAs($reviewer)
            ->post(route('projects.reject', $project), ['notes' => 'Add a license file.'])
            ->assertRedirect(route('projects.review'));

        $project->refresh();

        $this->assertSame(ProjectStatus::Rejected, $project->status);
        $this->assertSame('Add a license file.', $project->review_notes);

        $this->assertDatabaseHas('project_reviews', [
            'project_id' => $project->id,
            'reviewer_id' => $reviewer->id,
            'verdict' => ReviewVerdict::Rejected->value,
            'notes' => 'Add a license file.',
        ]);

        Notification::assertSentTo(
            $seller,
            ProjectReviewOutcome::class,
            fn ($notification, $channels): bool => $notification->approved === false,
        );
    }

    public function test_reject_requires_notes(): void
    {
        $reviewer = User::factory()->reviewer()->create();
        $project = Project::factory()->pendingReview()->create();

        $this->actingAs($reviewer)
            ->post(route('projects.reject', $project), [])
            ->assertSessionHasErrors('notes');

        $project->refresh();

        $this->assertTrue($project->isPendingReview());
    }

    public function test_non_reviewers_cannot_approve_or_reject(): void
    {
        $buyer = User::factory()->create();
        $project = Project::factory()->pendingReview()->create();

        $this->actingAs($buyer)
            ->post(route('projects.approve', $project), [])
            ->assertForbidden();

        $this->actingAs($buyer)
            ->post(route('projects.reject', $project), ['notes' => 'no'])
            ->assertForbidden();

        $project->refresh();

        $this->assertTrue($project->isPendingReview());
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $path = tempnam(sys_get_temp_dir(), 'review').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create fixture zip.');
        }

        $zip->addFromString('src/App.php', '<?php echo "hello";');
        $zip->close();

        return [
            'title' => 'Review me',
            'tagline' => 'Tiny demo',
            'description' => 'A small demo project used in tests.',
            'price_cents' => 999,
            'currency' => 'USD',
            'completeness' => 'mvp',
            'tech_stack' => ['php'],
            'zip' => new UploadedFile($path, 'project.zip', 'application/zip', null, true),
        ];
    }
}
