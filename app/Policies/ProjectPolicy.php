<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view the project.
     */
    public function view(?User $user, Project $project): bool
    {
        if ($project->isPublished()) {
            return true;
        }

        return $user !== null && ($this->owns($user, $project) || $user->isReviewer());
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Seller, UserRole::Reviewer, UserRole::Admin], true);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        if (! $this->owns($user, $project)) {
            return false;
        }

        return in_array($project->status, [
            ProjectStatus::Draft,
            ProjectStatus::PendingReview,
            ProjectStatus::Rejected,
        ], true);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        if (! $this->owns($user, $project)) {
            return false;
        }

        return ! $project->isPublished();
    }

    /**
     * Determine whether the user can submit the project for review.
     */
    public function submitForReview(User $user, Project $project): bool
    {
        return $this->owns($user, $project)
            && in_array($project->status, [
                ProjectStatus::Draft,
                ProjectStatus::Rejected,
            ], true);
    }

    /**
     * Determine whether the user can purchase the project.
     */
    public function purchase(User $user, Project $project): bool
    {
        if (! $project->isPublished()) {
            return false;
        }

        if ($project->seller_id === $user->id) {
            return false;
        }

        return ! $project->orders()
            ->where('buyer_id', $user->id)
            ->where('status', OrderStatus::Paid->value)
            ->exists();
    }

    /**
     * Determine whether the user can approve the project.
     */
    public function approve(User $user, Project $project): bool
    {
        return $user->isReviewer();
    }

    /**
     * Determine whether the user can reject the project.
     */
    public function reject(User $user, Project $project): bool
    {
        return $user->isReviewer();
    }

    /**
     * Determine whether the user can manage the review queue.
     */
    public function manageReview(User $user, Project $project): bool
    {
        return $user->isReviewer();
    }

    public function owns(User $user, Project $project): bool
    {
        return $project->seller_id === $user->id;
    }
}
