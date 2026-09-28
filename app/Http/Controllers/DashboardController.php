<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the authenticated user's dashboard (buyer library + seller studio).
     */
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // 1. Buyer: Library of purchased projects (paid orders)
        $library = $user->orders()
            ->with(['project.seller:id,name', 'downloads' => fn ($q) => $q->latest('id')->limit(1)])
            ->where('status', OrderStatus::Paid)
            ->latest('id')
            ->get()
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'purchased_at' => $order->updated_at?->toIso8601String(),
                'amount_cents' => $order->amount_cents,
                'currency' => $order->currency,
                'can_download' => true,
                'has_downloaded' => $order->downloads->isNotEmpty() && $order->downloads->first()->downloaded_at !== null,
                'project' => [
                    'id' => $order->project->id,
                    'title' => $order->project->title,
                    'slug' => $order->project->slug,
                    'tagline' => $order->project->tagline,
                    'completeness' => $order->project->completeness->value,
                    'completeness_label' => $order->project->completeness->label(),
                    'tech_stack' => $order->project->tech_stack ?? [],
                    'seller_name' => $order->project->seller->name,
                    'cover_url' => $order->project->cover_image_path !== null
                        ? route('projects.cover', $order->project)
                        : null,
                ],
            ]);

        // 2. Seller analytics & studio data
        $isSeller = $user->isSeller() || $user->projects()->exists();
        $sellerData = null;

        if ($isSeller) {
            $sellerProjects = $user->projects()
                ->withCount(['orders as sales_count' => fn ($q) => $q->where('status', OrderStatus::Paid)])
                ->withSum(['orders as gross_volume_cents' => fn ($q) => $q->where('status', OrderStatus::Paid)], 'amount_cents')
                ->withSum(['orders as net_payout_cents' => fn ($q) => $q->where('status', OrderStatus::Paid)], 'payout_cents')
                ->latest('id')
                ->get();

            $sellerOrders = Order::query()
                ->whereIn('project_id', $sellerProjects->pluck('id'))
                ->where('status', OrderStatus::Paid)
                ->with(['project:id,title,slug', 'buyer:id,name'])
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'project_id' => $order->project_id,
                    'project_title' => $order->project->title,
                    'project_slug' => $order->project->slug,
                    'buyer_name' => $order->buyer->name,
                    'amount_cents' => $order->amount_cents,
                    'fee_amount_cents' => $order->fee_amount_cents ?? 0,
                    'payout_cents' => $order->payout_cents ?? 0,
                    'currency' => $order->currency,
                    'created_at' => $order->created_at?->toIso8601String(),
                ]);

            $totalSalesCount = (int) $sellerProjects->sum('sales_count');
            $grossRevenueCents = (int) $sellerProjects->sum('gross_volume_cents');
            $netPayoutCents = (int) $sellerProjects->sum('net_payout_cents');

            $sellerData = [
                'stripe_connected' => $user->hasStripeConnect(),
                'stripe_connect_status' => $user->stripe_connect_status,
                'metrics' => [
                    'total_sales_count' => $totalSalesCount,
                    'gross_revenue_cents' => $grossRevenueCents,
                    'net_payout_cents' => $netPayoutCents,
                    'projects_count' => $sellerProjects->count(),
                    'published_count' => $sellerProjects->where('status', ProjectStatus::Approved)->count(),
                    'pending_count' => $sellerProjects->where('status', ProjectStatus::PendingReview)->count(),
                    'draft_count' => $sellerProjects->where('status', ProjectStatus::Draft)->count(),
                ],
                'recent_sales' => $sellerOrders,
                'projects' => $sellerProjects->map(fn (Project $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'status' => $p->status->value,
                    'status_label' => $p->status->label(),
                    'price_cents' => $p->price_cents,
                    'currency' => $p->currency,
                    'sales_count' => (int) $p->sales_count,
                    'gross_volume_cents' => (int) ($p->gross_volume_cents ?? 0),
                    'net_payout_cents' => (int) ($p->net_payout_cents ?? 0),
                ]),
            ];
        }

        // 3. Reviewer extras
        $pendingReviewsCount = $user->isReviewer()
            ? Project::query()->where('status', ProjectStatus::PendingReview)->count()
            : 0;

        return Inertia::render('dashboard', [
            'library' => $library,
            'seller' => $sellerData,
            'is_seller' => $isSeller,
            'pending_reviews_count' => $pendingReviewsCount,
        ]);
    }
}
