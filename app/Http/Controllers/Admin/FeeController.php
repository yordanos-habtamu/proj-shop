<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeeSettingsRequest;
use App\Models\Fee;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FeeController extends Controller
{
    /**
     * Show the current platform fee and its history.
     */
    public function index(): Response
    {
        $history = Fee::query()
            ->with('creator:id,name')
            ->latest()
            ->get()
            ->map(fn (Fee $fee): array => [
                'id' => $fee->id,
                'percent' => $fee->percent,
                'is_active' => $fee->is_active,
                'creator' => $fee->creator?->name,
                'created_at' => $fee->created_at,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/fees', [
            'currentPercent' => Fee::activePercent(),
            'history' => $history,
        ]);
    }

    /**
     * Update the active platform fee.
     */
    public function update(FeeSettingsRequest $request): RedirectResponse
    {
        $percent = $request->validated()['percent'];

        Fee::setActive($percent, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Platform fee updated.')]);

        return to_route('admin.fees.index');
    }
}
