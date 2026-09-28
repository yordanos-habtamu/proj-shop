<?php

namespace Tests\Feature\Marketplace;

use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Models\Download;
use App\Models\Fee;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Notifications\OrderPaid;
use App\Notifications\ProjectReviewOutcome;
use App\Notifications\SellerOrderPaid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;
use ZipArchive;

class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Fee::setActive(10);
    }

    private function createValidZip(string $readme = 'Awesome project code'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'e2e_zip').'.zip';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE | ZipArchive::CREATE) !== true) {
            throw new \RuntimeException('Failed to create fixture zip');
        }

        $zip->addFromString('project/README.md', $readme);
        $zip->addFromString('project/src/index.php', '<?php echo "Hello World";');
        $zip->close();

        return new UploadedFile($path, 'project.zip', 'application/zip', null, true);
    }

    public function test_full_marketplace_lifecycle_upload_scan_approve_purchase_and_download(): void
    {
        Notification::fake();

        // 1. Seller uploads project
        $seller = User::factory()->seller()->create();

        $zip = $this->createValidZip();

        $uploadResponse = $this->actingAs($seller)->post(route('projects.store'), [
            'title' => 'Analytics Engine',
            'tagline' => 'High performance metrics system',
            'description' => 'A full stack real-time analytics system built with PHP and React.',
            'price_cents' => 4900,
            'currency' => 'USD',
            'completeness' => 'complete',
            'tech_stack' => ['laravel', 'react', 'tailwind'],
            'zip' => $zip,
        ]);

        $uploadResponse->assertRedirect(route('projects.mine'));

        $project = Project::firstOrFail();
        $this->assertSame(ProjectStatus::PendingReview, $project->status);
        $this->assertSame('analytics-engine', $project->slug);
        $this->assertNotNull($project->zip_path);
        $this->assertTrue(Storage::disk('local')->exists($project->zip_path));

        // Scan report was generated cleanly
        $this->assertIsArray($project->scan_report);
        $this->assertSame('clean', $project->scan_report['verdict']);

        // 2. Unapproved project is not visible in public marketplace
        $storefrontResponse = $this->get(route('projects.index'));
        $storefrontResponse->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('market/browse')
                ->where('projects', [])
            );

        // 3. Reviewer inspects queue and approves
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->get(route('projects.review'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('projects/review')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
            );

        $approveResponse = $this->actingAs($reviewer)->post(route('projects.approve', $project), [
            'notes' => 'Code quality is exceptional, approved.',
        ]);

        $approveResponse->assertRedirect(route('projects.review'));

        $project->refresh();
        $this->assertSame(ProjectStatus::Approved, $project->status);

        Notification::assertSentTo(
            $seller,
            ProjectReviewOutcome::class,
            fn (ProjectReviewOutcome $notification): bool => $notification->approved === true
        );

        // 4. Now visible in public marketplace & project page
        $this->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('market/browse')
                ->has('projects', 1)
                ->where('projects.0.slug', 'analytics-engine')
            );

        $this->get(route('projects.show', $project))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('market/show')
                ->where('project.slug', 'analytics-engine')
                ->where('project.price_cents', 4900)
            );

        // 5. Buyer purchases project
        $buyer = User::factory()->create();

        $checkoutResponse = $this->actingAs($buyer)->post(route('checkout.store', $project));

        $order = Order::firstOrFail();
        $checkoutResponse->assertRedirect(route('checkout.demo', $order));

        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(4900, $order->amount_cents);
        $this->assertSame(10, $order->fee_percent);
        $this->assertSame(490, $order->fee_amount_cents);
        $this->assertSame(4410, $order->payout_cents);

        // Buyer completes payment
        $payResponse = $this->actingAs($buyer)->post(route('checkout.demo-pay', $order));
        $payResponse->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);

        Notification::assertSentTo($buyer, OrderPaid::class);
        Notification::assertSentTo($seller, SellerOrderPaid::class);

        // 6. Buyer views receipt and generates signed download link
        $this->actingAs($buyer)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/receipt')
                ->where('order.id', $order->id)
                ->where('order.can_download', true)
            );

        $issueResponse = $this->actingAs($buyer)->post(route('downloads.issue', $order));
        $issueResponse->assertRedirect(route('orders.show', $order));

        $download = Download::sole();
        $this->assertSame($order->id, $download->order_id);
        $this->assertSame($project->id, $download->project_id);
        $this->assertNull($download->downloaded_at);

        // 7. Buyer accesses signed download URL
        $signedUrl = URL::temporarySignedRoute('downloads.serve', now()->addMinutes(15), [
            'order' => $order->id,
            'token' => $download->token,
        ]);

        $streamResponse = $this->get($signedUrl);
        $streamResponse->assertOk();
        $streamResponse->assertDownload('analytics-engine.zip');

        $download->refresh();
        $this->assertNotNull($download->downloaded_at);

        // 8. Dashboards display correct data
        // Buyer library shows the purchased project
        $this->actingAs($buyer)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->has('library', 1)
                ->where('library.0.project.slug', 'analytics-engine')
            );

        // Seller studio reflects gross volume, net payout, and sales count
        $this->actingAs($seller)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('is_seller', true)
                ->where('seller.metrics.gross_revenue_cents', 4900)
                ->where('seller.metrics.net_payout_cents', 4410)
                ->where('seller.metrics.total_sales_count', 1)
                ->has('seller.recent_sales', 1)
            );
    }

    public function test_security_guards_and_policy_isolation(): void
    {
        Notification::fake();

        $seller = User::factory()->seller()->create();
        $buyer = User::factory()->create();
        $stranger = User::factory()->create();

        $zip = $this->createValidZip();
        $this->actingAs($seller)->post(route('projects.store'), [
            'title' => 'Secure Tool',
            'tagline' => 'Security test',
            'description' => 'Security testing project.',
            'price_cents' => 3000,
            'currency' => 'USD',
            'completeness' => 'mvp',
            'tech_stack' => ['php'],
            'zip' => $zip,
        ]);

        $project = Project::firstOrFail();

        // 1. Seller cannot buy their own project
        $this->actingAs($seller)
            ->post(route('checkout.store', $project))
            ->assertForbidden();

        // 2. Non-reviewers cannot access review queue or approve projects
        $this->actingAs($buyer)->get(route('projects.review'))->assertForbidden();
        $this->actingAs($buyer)->post(route('projects.approve', $project))->assertForbidden();

        // Approve project via reviewer
        $reviewer = User::factory()->reviewer()->create();
        $this->actingAs($reviewer)->post(route('projects.approve', $project), ['notes' => 'ok']);

        // 3. Buyer purchases project
        $this->actingAs($buyer)->post(route('checkout.store', $project));
        $order = Order::firstOrFail();
        $this->actingAs($buyer)->post(route('checkout.demo-pay', $order));

        // 4. Buyer cannot buy the same project again
        $this->actingAs($buyer)
            ->post(route('checkout.store', $project))
            ->assertForbidden();

        // 5. Stranger cannot issue download link or access receipt
        $this->actingAs($stranger)->get(route('orders.show', $order))->assertForbidden();
        $this->actingAs($stranger)->post(route('downloads.issue', $order))->assertForbidden();

        // 6. Tampered or expired signed URL is rejected
        $this->actingAs($buyer)->post(route('downloads.issue', $order));
        $download = Download::sole();

        $validUrl = URL::temporarySignedRoute('downloads.serve', now()->addMinutes(15), [
            'order' => $order->id,
            'token' => $download->token,
        ]);

        $tamperedUrl = str_replace('signature=', 'signature=corrupted', $validUrl);
        $this->get($tamperedUrl)->assertForbidden();

        // 7. Refunding revokes download access
        $order->refresh();
        $order->markRefunded();
        $this->assertSame(OrderStatus::Refunded, $order->refresh()->status);

        // Buyer cannot issue new download links
        $this->actingAs($buyer)
            ->post(route('downloads.issue', $order))
            ->assertForbidden();

        // Previously generated signed link is also rejected
        $this->get($validUrl)->assertForbidden();
    }
}
