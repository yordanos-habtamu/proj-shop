<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Models\Download;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;

    private Project $project;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->buyer = User::factory()->create();

        $this->project = Project::factory()->approved()->create([
            'slug' => 'acme-dashboard',
        ]);

        $this->storeArchive($this->project);

        $this->order = Order::factory()->paid()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);
    }

    public function test_receipt_is_visible_to_the_buyer(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('orders.show', $this->order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/receipt')
                ->where('order.id', $this->order->id)
                ->where('order.status', 'paid')
                ->where('order.can_download', true)
                ->where('project.slug', 'acme-dashboard')
            );
    }

    public function test_receipt_is_forbidden_for_another_buyer(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get(route('orders.show', $this->order))
            ->assertForbidden();
    }

    public function test_guest_cannot_see_the_receipt_or_issue_a_link(): void
    {
        $this->get(route('orders.show', $this->order))->assertRedirect(route('login'));
        $this->post(route('downloads.issue', $this->order))->assertRedirect(route('login'));
    }

    public function test_issuing_a_link_records_a_download_and_hands_back_a_signed_url(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('downloads.issue', $this->order))
            ->assertRedirect(route('orders.show', $this->order));

        $download = Download::sole();

        $this->assertSame($this->order->id, $download->order_id);
        $this->assertSame($this->project->id, $download->project_id);
        $this->assertNull($download->downloaded_at);

        $flash = session()->get('inertia.flash_data');

        $this->assertIsArray($flash);
        $this->assertArrayHasKey('download', $flash);
        $this->assertSame('success', $flash['toast']['type']);

        parse_str((string) parse_url($flash['download']['url'], PHP_URL_QUERY), $query);

        $this->assertArrayHasKey('signature', $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertStringContainsString($download->token, $flash['download']['url']);
        $this->assertNotEmpty($flash['download']['expires_at']);
    }

    public function test_issuing_requires_a_paid_order(): void
    {
        $pending = Order::factory()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('downloads.issue', $pending))
            ->assertForbidden();
    }

    public function test_issuing_is_forbidden_for_a_refunded_order(): void
    {
        $refunded = Order::factory()->refunded()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);

        $this->actingAs($this->buyer)
            ->post(route('downloads.issue', $refunded))
            ->assertForbidden();
    }

    public function test_another_buyer_cannot_issue_a_link(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->post(route('downloads.issue', $this->order))
            ->assertForbidden();

        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_a_signed_link_streams_the_archive_and_records_the_download(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        $response = $this->get($this->signedUrl($download->token));

        $response->assertOk();
        $response->assertDownload('acme-dashboard.zip');

        $this->assertSame(
            Storage::disk('local')->get($this->project->zip_path),
            $response->streamedContent()
        );

        $this->assertNotNull($download->refresh()->downloaded_at);
    }

    public function test_a_link_cannot_be_replayed(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        $this->get($this->signedUrl($download->token))->assertOk();

        $this->get($this->signedUrl($download->token))->assertForbidden();
    }

    public function test_the_buyer_can_issue_a_fresh_link_after_the_first_is_spent(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $first = Download::sole();
        $firstUrl = $this->signedUrl($first->token);

        $this->get($firstUrl)->assertOk();

        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $second = Download::orderByDesc('id')->firstOrFail();

        $this->assertNotSame($first->token, $second->token);
        $this->get($this->signedUrl($second->token))->assertOk();
    }

    public function test_an_unsigned_request_is_rejected(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        $this->get(route('downloads.serve', [
            'order' => $this->order->id,
            'token' => $download->token,
        ]))->assertForbidden();
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        $url = $this->signedUrl($download->token);
        $url = str_replace('signature=', 'signature=00', $url);

        $this->get($url)->assertForbidden();

        $this->assertNull($download->refresh()->downloaded_at);
    }

    public function test_an_expired_link_is_rejected(): void
    {
        config(['marketplace.download_ttl_minutes' => 15]);

        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();
        $url = $this->signedUrl($download->token);

        $this->travel(16)->minutes();

        $this->get($url)->assertForbidden();

        $this->assertNull($download->refresh()->downloaded_at);
    }

    public function test_a_token_from_another_order_is_rejected(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        $otherOrder = Order::factory()->paid()->create([
            'buyer_id' => $this->buyer->id,
            'project_id' => $this->project->id,
        ]);

        $this->get($this->signedUrl($download->token, $otherOrder))->assertNotFound();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->get($this->signedUrl('not-a-real-token'))->assertNotFound();
    }

    public function test_a_refund_revokes_a_link_that_was_already_issued(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();
        $url = $this->signedUrl($download->token);

        $this->order->markRefunded();

        $this->assertSame(OrderStatus::Refunded, $this->order->refresh()->status);

        $this->get($url)->assertForbidden();

        $this->assertNull($download->refresh()->downloaded_at);
    }

    public function test_a_missing_archive_is_not_found(): void
    {
        $this->actingAs($this->buyer)->post(route('downloads.issue', $this->order));

        $download = Download::sole();

        Storage::disk('local')->delete($this->project->zip_path);

        $this->get($this->signedUrl($download->token))->assertNotFound();
    }

    private function signedUrl(string $token, ?Order $order = null): string
    {
        return URL::temporarySignedRoute('downloads.serve', now()->addMinutes(15), [
            'order' => ($order ?? $this->order)->id,
            'token' => $token,
        ]);
    }

    private function storeArchive(Project $project): void
    {
        $path = 'projects/'.$project->id.'/archive.zip';

        $fixture = tempnam(sys_get_temp_dir(), 'project').'.zip';

        $zip = new ZipArchive;

        if ($zip->open($fixture, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Could not create fixture zip.');
        }

        $zip->addFromString('project/readme.txt', 'project contents');
        $zip->close();

        Storage::disk('local')->put($path, file_get_contents($fixture));

        $project->update(['zip_path' => $path]);
    }
}
