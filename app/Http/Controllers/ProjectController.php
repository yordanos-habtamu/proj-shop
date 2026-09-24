<?php

namespace App\Http\Controllers;

use App\Enums\ProjectCompleteness;
use App\Enums\ProjectStatus;
use App\Http\Requests\Projects\StoreProjectRequest;
use App\Http\Requests\Projects\UpdateProjectRequest;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    /**
     * Show the authenticated seller's projects ("My listings").
     */
    public function index(Request $request): Response
    {
        $projects = $request->user()->projects()
            ->with('seller:id,name')
            ->withCount('orders')
            ->latest()
            ->get();

        return Inertia::render('projects/index', [
            'projects' => $projects,
        ]);
    }

    /**
     * Show the project upload form.
     */
    public function create(Request $request): Response
    {
        abort_unless($request->user()->can('create', Project::class), 403);

        return Inertia::render('projects/create', [
            'completenessOptions' => $this->completenessOptions(),
        ]);
    }

    /**
     * Store a newly uploaded project.
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $project = new Project([
            ...$data,
            'seller_id' => $request->user()->id,
            'slug' => $this->uniqueSlug($data['title']),
            'status' => $request->input('save') === 'draft'
                ? ProjectStatus::Draft
                : ProjectStatus::PendingReview,
        ]);

        $project->save();

        $project->fill([
            'zip_path' => $this->storeArchive($request->file('zip'), $project),
            'cover_image_path' => $this->storeCover($request->file('cover_image'), $project),
        ])->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $project->isPendingReview()
                ? __('Project submitted for review.')
                : __('Project saved as draft.'),
        ]);

        return to_route('projects.mine');
    }

    /**
     * Show the project edit form.
     */
    public function edit(Request $request, Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('projects/edit', [
            'project' => $project,
            'completenessOptions' => $this->completenessOptions(),
        ]);
    }

    /**
     * Update the project's metadata and/or its archive.
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['title']) === $project->slug
            ? $project->slug
            : $this->uniqueSlug($data['title'], $project->id);

        if ($request->hasFile('zip')) {
            $this->deleteArchive($project);
            $data['zip_path'] = $this->storeArchive($request->file('zip'), $project);
            $data['scan_report'] = null;
        }

        if ($request->hasFile('cover_image')) {
            $this->deleteCover($project);
            $data['cover_image_path'] = $this->storeCover($request->file('cover_image'), $project);
        }

        $project->fill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('projects.edit', $project);
    }

    /**
     * Delete a project that has not been published yet.
     */
    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->deleteArchive($project);
        $this->deleteCover($project);

        $project->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project deleted.')]);

        return to_route('projects.mine');
    }

    /**
     * Submit a draft or rejected project for review.
     */
    public function submitForReview(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('submitForReview', $project);

        $project->update([
            'status' => ProjectStatus::PendingReview,
            'review_notes' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project submitted for review.')]);

        return to_route('projects.mine');
    }

    private function storeArchive(UploadedFile $file, Project $project): ?string
    {
        $name = 'archive-'.now()->timestamp.'.zip';

        return $file->storeAs($this->fileDirectory($project), $name, 'local') ?: null;
    }

    private function storeCover(?UploadedFile $file, Project $project): ?string
    {
        if ($file === null) {
            return null;
        }

        $name = 'cover-'.time().'.'.$file->extension();

        return $file->storeAs($this->fileDirectory($project), $name, 'local') ?: null;
    }

    private function deleteArchive(Project $project): void
    {
        if ($project->zip_path !== null) {
            Storage::disk('local')->delete($project->zip_path);
        }
    }

    private function deleteCover(Project $project): void
    {
        if ($project->cover_image_path !== null) {
            Storage::disk('local')->delete($project->cover_image_path);
        }
    }

    private function fileDirectory(Project $project): string
    {
        return 'projects/'.$project->id;
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 1;

        while (Project::where('slug', $slug)->where('id', '!=', $ignoreId)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
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
}
