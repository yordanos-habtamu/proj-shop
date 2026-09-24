<?php

namespace Database\Factories;

use App\Enums\ReviewVerdict;
use App\Models\Project;
use App\Models\ProjectReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectReview>
 */
class ProjectReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reviewer_id' => User::factory()->reviewer(),
            'verdict' => fake()->randomElement(ReviewVerdict::cases())->value,
            'notes' => fake()->optional(0.7)->sentence(),
        ];
    }
}
