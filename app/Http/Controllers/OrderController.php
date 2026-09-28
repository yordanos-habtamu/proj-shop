<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * Show a buyer's receipt for an order.
     */
    public function show(Order $order): Response
    {
        $this->authorize('view', $order);

        $order->load('project:id,title,slug,cover_image_path');

        return Inertia::render('checkout/receipt', [
            'order' => $this->orderReceipt($order),
            'project' => [
                'id' => $order->project->id,
                'title' => $order->project->title,
                'slug' => $order->project->slug,
                'cover_url' => $order->project->cover_image_path !== null
                    ? route('projects.cover', $order->project)
                    : null,
            ],
            'downloads' => $order->downloads()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (Download $download) => [
                    'id' => $download->id,
                    'downloaded_at' => $download->downloaded_at?->toIso8601String(),
                    'created_at' => $download->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderReceipt(Order $order): array
    {
        return [
            'id' => $order->id,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'amount_cents' => $order->amount_cents,
            'fee_percent' => $order->fee_percent ?? 0,
            'fee_amount_cents' => $order->fee_amount_cents ?? 0,
            'payout_cents' => $order->payout_cents ?? 0,
            'currency' => $order->currency,
            'provider' => $order->provider,
            'can_download' => request()->user()?->can('viewDownload', $order) ?? false,
            'paid_at' => $order->updated_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
