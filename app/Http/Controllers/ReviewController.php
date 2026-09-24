<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Enums\ReviewVerdict;
use App\Http\Requests\Review\ApproveProjectRequest;
use App\Http\Requests\Review\RejectProjectRequest;
use App\Models\Project;
use App\Notifications\ProjectReviewOutcome;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * Show the moderation queue for pending projects along with their scan reports.
     */
    public function index(): Response
    {
        $queue = Project::query()
            ->where('status', ProjectStatus::PendingReview->value)
            ->with('seller:id,name,email')
            ->latest()
            ->get()
            ->map(fn (Project $project): array => [...$project->toArray(), 'scan_report' => $project->scan_report])
            ->values()
            ->all();

        return Inertia::render('projects/review', [
            'projects' => $queue,
        ]);
    }

    /**
     * Approve a pending project.
     */
    public function approve(ApproveProjectRequest $request, Project $project): RedirectResponse
    {
        $notes = $request->validated()['notes'] ?? null;

        $project->markApproved($notes);

        $project->reviews()->create([
            'reviewer_id' => $request->user()->id,
            'verdict' => ReviewVerdict::Approved,
            'notes' => $notes,
        ]);

        $this->notifySeller($project, $notes, approved: true);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project approved and published.')]);

        return to_route('projects.review');
    }

    /**
     * Reject a pending project.
     */
    public function reject(RejectProjectRequest $request, Project $project): RedirectResponse
    {
        $notes = $request->validated()['notes'];

        $project->markRejected($notes);

        $project->reviews()->create([
            'reviewer_id' => $request->user()->id,
            'verdict' => ReviewVerdict::Rejected,
            'notes' => $notes,
        ]);

        $this->notifySeller($project, $notes, approved: false);

        Inertia::flash('toast', ['type' => 'error', 'message' => __('Project rejected.')]);

        return to_route('projects.review');
    }

    private function notifySeller(Project $project, ?string $notes, bool $approved): void
    {
        $project->seller->notify(new ProjectReviewOutcome($project, $approved, $notes));
    }
}
