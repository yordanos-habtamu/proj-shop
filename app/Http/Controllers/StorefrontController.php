<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ProjectCompleteness;
use App\Enums\ProjectStatus;
use App\Http\Requests\Storefront\StorefrontQueryRequest;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StorefrontController extends Controller
{
    /**
     * Browse the public marketplace with filtering and sorting.
     */
    public function index(StorefrontQueryRequest $request): Response
    {
        $filters = $request->validated();

        $query = Project::query()
            ->where('status', ProjectStatus::Approved->value)
            ->with('seller:id,name,email')
            ->withCount('orders');

        if (filled($filters['q'] ?? null)) {
            $term = '%'.trim((string) $filters['q']).'%';

            $query->where(function ($nested) use ($term): void {
                $nested->where('title', 'like', $term)
                    ->orWhere('tagline', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if (filled($filters['completeness'] ?? null)) {
            $query->where('completeness', $filters['completeness']);
        }

        if (filled($filters['stack'] ?? null)) {
            $query->whereJsonContains('tech_stack', $filters['stack']);
        }

        if (filled($filters['min_price'] ?? null)) {
            $query->where('price_cents', '>=', (int) $filters['min_price']);
        }

        if (filled($filters['max_price'] ?? null)) {
            $query->where('price_cents', '<=', (int) $filters['max_price']);
        }

        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price_cents'),
            'price_desc' => $query->orderByDesc('price_cents'),
            'sales' => $query->orderByDesc('orders_count'),
            default => $query->latest(),
        };

        $projects = $query->get()
            ->map(fn (Project $project): array => $this->projectListing($project))
            ->values()
            ->all();

        return Inertia::render('market/browse', [
            'projects' => $projects,
            'filters' => $filters,
            'completenessOptions' => $this->completenessOptions(),
            'stacks' => $this->availableStacks(),
            'count' => count($projects),
        ]);
    }

    /**
     * Show a single project on the public marketplace.
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->loadMissing('seller:id,name,email');
        $project->loadCount('orders');

        $user = $request->user();

        $isOwner = $user !== null && $project->seller_id === $user->id;
        $isBought = $user !== null && $project->orders()
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::Paid->value)
            ->exists();

        return Inertia::render('market/show', [
            'project' => $this->projectListing($project),
            'is_owner' => $isOwner,
            'is_bought' => $isBought,
            'can_buy' => $user !== null && $user->can('purchase', $project),
        ]);
    }

    /**
     * Stream the private cover image for an approved (or owned) project.
     */
    public function cover(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        if ($project->cover_image_path === null || ! Storage::disk('local')->exists($project->cover_image_path)) {
            abort(404);
        }

        $response = Storage::disk('local')->response($project->cover_image_path);
        $response->headers->set('Cache-Control', 'private, max-age=86400');

        return $response;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function completenessOptions(): array
    {
        return collect(ProjectCompleteness::cases())
            ->map(fn (ProjectCompleteness $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function availableStacks(): array
    {
        return Project::query()
            ->where('status', ProjectStatus::Approved->value)
            ->get(['tech_stack'])
            ->pluck('tech_stack')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
