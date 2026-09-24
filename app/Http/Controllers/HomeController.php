<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $projects = Project::query()
            ->where('status', ProjectStatus::Approved->value)
            ->with('seller:id,name')
            ->withCount('orders')
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Project $project): array => $this->projectListing($project))
            ->values()
            ->all();

        return Inertia::render('welcome', [
            'projects' => $projects,
        ]);
    }
}
