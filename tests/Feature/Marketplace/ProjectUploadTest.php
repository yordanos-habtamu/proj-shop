<?php

namespace Tests\Feature\Marketplace;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class ProjectUploadTest extends TestCase
{
    use RefreshDatabase;

    private function makeZip(string $uncompressedContent = 'readme'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'project').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create fixture zip.');
        }

        $zip->addFromString('project/readme.txt', $uncompressedContent);
        $zip->close();

        return new UploadedFile($path, 'project.zip', 'application/zip', null, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(UploadedFile $zip): array
    {
        return [
            'title' => 'SaaS boilerplate',
            'tagline' => 'Modern stack',
            'description' => 'A ready-to-ship SaaS starter with auth, billing and a dashboard.',
            'price_cents' => 2499,
            'currency' => 'USD',
            'completeness' => 'mvp',
            'tech_stack' => ['laravel', 'react'],
            'zip' => $zip,
        ];
    }

    public function test_seller_can_upload_a_project_and_it_goes_pending_review(): void
    {
        Storage::fake('local');

        $seller = User::factory()->seller()->create();

        $response = $this->actingAs($seller)
            ->post(route('projects.store'), $this->validPayload($this->makeZip()));

        $response->assertRedirect(route('projects.mine'));

        $this->assertDatabaseHas('projects', [
            'seller_id' => $seller->id,
            'title' => 'SaaS boilerplate',
            'slug' => 'saas-boilerplate',
            'price_cents' => 2499,
            'status' => ProjectStatus::PendingReview->value,
        ]);

        $project = $seller->projects()->firstOrFail();

        $this->assertNotNull($project->zip_path);
        Storage::disk('local')->assertExists($project->zip_path);
    }

    public function test_seller_can_save_a_draft_without_submitting_for_review(): void
    {
        Storage::fake('local');

        $seller = User::factory()->seller()->create();

        $payload = $this->validPayload($this->makeZip());
        $payload['save'] = 'draft';

        $this->actingAs($seller)->post(route('projects.store'), $payload);

        $this->assertDatabaseHas('projects', [
            'seller_id' => $seller->id,
            'status' => ProjectStatus::Draft->value,
        ]);
    }

    public function test_upload_without_a_zip_fails(): void
    {
        $seller = User::factory()->seller()->create();

        $payload = $this->validPayload($this->makeZip());
        unset($payload['zip']);

        $this->actingAs($seller)
            ->from(route('projects.create'))
            ->post(route('projects.store'), $payload)
            ->assertSessionHasErrors('zip');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_non_zip_upload_is_rejected(): void
    {
        $seller = User::factory()->seller()->create();

        $payload = $this->validPayload(
            new UploadedFile(
                $this->writeTempBytes('this is not a real zip archive'),
                'project.zip',
                'application/zip',
                null,
                true
            )
        );

        $this->actingAs($seller)
            ->post(route('projects.store'), $payload)
            ->assertSessionHasErrors('zip');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_zip_bomb_with_extreme_compression_ratio_is_rejected(): void
    {
        $seller = User::factory()->seller()->create();

        $payload = $this->validPayload($this->makeZip(str_repeat('a', 16_384 * 1_024)));

        $this->actingAs($seller)
            ->post(route('projects.store'), $payload)
            ->assertSessionHasErrors('zip');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_buyer_cannot_upload_a_project(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)
            ->post(route('projects.store'), $this->validPayload($this->makeZip()))
            ->assertForbidden();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_seller_can_view_own_listings_and_create_form(): void
    {
        $seller = User::factory()->seller()->create();
        Project::factory()->count(2)->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)
            ->get(route('projects.mine'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/index')
                ->has('projects', 2)
            );

        $this->actingAs($seller)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/create')
                ->has('completenessOptions', 4)
            );
    }

    public function test_seller_can_update_metadata_and_replace_the_archive(): void
    {
        Storage::fake('local');

        $seller = User::factory()->seller()->create();
        $project = Project::factory()->draft()->create(['seller_id' => $seller->id]);

        $response = $this->actingAs($seller)
            ->from(route('projects.edit', $project))
            ->patch(route('projects.update', $project), [
                'title' => 'Renamed boilerplate',
                'tagline' => 'Even better',
                'description' => 'Rewritten description.',
                'price_cents' => 3999,
                'currency' => 'USD',
                'completeness' => 'complete',
                'tech_stack' => ['laravel'],
                'zip' => $this->makeZip('v2 archive'),
            ]);

        $response->assertRedirect(route('projects.edit', $project));

        $project->refresh();

        $this->assertSame('Renamed boilerplate', $project->title);
        $this->assertSame('3999', (string) $project->price_cents);
        $this->assertSame(ProjectStatus::Draft, $project->status);
        Storage::disk('local')->assertExists($project->zip_path);
    }

    public function test_seller_can_submit_a_draft_for_review(): void
    {
        $seller = User::factory()->seller()->create();
        $project = Project::factory()->draft()->create([
            'seller_id' => $seller->id,
            'review_notes' => 'Old note',
        ]);

        $this->actingAs($seller)
            ->post(route('projects.submit-for-review', $project))
            ->assertRedirect(route('projects.mine'));

        $project->refresh();

        $this->assertTrue($project->isPendingReview());
        $this->assertNull($project->review_notes);
    }

    public function test_seller_cannot_edit_or_delete_someone_elses_project(): void
    {
        $seller = User::factory()->seller()->create();
        $other = User::factory()->seller()->create();
        $project = Project::factory()->draft()->create(['seller_id' => $other->id]);

        $this->actingAs($seller)
            ->get(route('projects.edit', $project))
            ->assertForbidden();

        $this->actingAs($seller)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_owner_can_delete_a_draft_and_its_archive(): void
    {
        Storage::fake('local');

        $seller = User::factory()->seller()->create();
        $project = Project::factory()->draft()->create(['seller_id' => $seller->id]);

        Storage::disk('local')->put($project->zip_path, 'archive');

        $this->actingAs($seller)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect(route('projects.mine'));

        $this->assertDatabaseCount('projects', 0);
        Storage::disk('local')->assertMissing($project->zip_path);
    }

    private function writeTempBytes(string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'project').'.zip';
        file_put_contents($path, $bytes);

        return $path;
    }
}
