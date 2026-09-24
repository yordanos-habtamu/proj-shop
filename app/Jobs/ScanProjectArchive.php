<?php

namespace App\Jobs;

use App\Models\Project;
use App\Services\ProjectArchiveScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ScanProjectArchive implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?Project $project) {}

    public function handle(ProjectArchiveScanner $scanner): void
    {
        if ($this->project === null || $this->project->zip_path === null) {
            return;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($this->project->zip_path)) {
            return;
        }

        $report = $scanner->scan($disk->path($this->project->zip_path));

        $this->project->update(['scan_report' => $report]);
    }
}
