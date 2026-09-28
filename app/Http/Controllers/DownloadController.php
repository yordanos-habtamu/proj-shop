<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    /**
     * Mint a signed, expiring, single-use download link for a paid order.
     */
    public function issue(Order $order): RedirectResponse
    {
        $this->authorize('viewDownload', $order);

        $download = Download::create([
            'order_id' => $order->getKey(),
            'project_id' => $order->project_id,
            'token' => Str::random(64),
        ]);

        $expiresAt = now()->addMinutes((int) config('marketplace.download_ttl_minutes'));

        $url = $this->signedUrl($order, $download, $expiresAt);

        Inertia::flash([
            'toast' => [
                'type' => 'success',
                'message' => __('Your download link is ready.'),
            ],
            'download' => [
                'url' => $url,
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ]);

        return to_route('orders.show', $order);
    }

    /**
     * Stream the archive for a signed link. The signature is the only
     * credential here — there is no session — so the token, its single use and
     * the order status are all re-checked before a byte is sent.
     */
    public function serve(Order $order, string $token): StreamedResponse
    {
        $download = Download::query()
            ->where('order_id', $order->getKey())
            ->where('project_id', $order->project_id)
            ->where('token', $token)
            ->first();

        abort_if($download === null, 404);
        abort_unless($order->isPaid(), 403);

        $project = $order->project;
        $path = $project->zip_path;

        abort_if($path === null, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        // Claim the token with a conditional update so two simultaneous requests
        // for the same link cannot both win the single use.
        $claimed = Download::query()
            ->whereKey($download->getKey())
            ->whereNull('downloaded_at')
            ->update(['downloaded_at' => now()]);

        abort_if($claimed === 0, 403);

        $filename = str_replace(
            '{slug}',
            $project->slug,
            (string) config('marketplace.download_filename'),
        );

        return Storage::disk('local')->download($path, $filename, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Build the signed, expiring URL for a download token.
     */
    private function signedUrl(Order $order, Download $download, CarbonInterface $expiresAt): string
    {
        return URL::temporarySignedRoute(
            'downloads.serve',
            $expiresAt,
            [
                'order' => $order->getKey(),
                'token' => $download->token,
            ],
        );
    }
}
