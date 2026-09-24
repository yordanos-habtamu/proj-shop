<?php

namespace Database\Factories;

use App\Enums\ProjectCompleteness;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(3, false), '.'));

        return [
            'seller_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'tagline' => fake()->sentence(6),
            'description' => fake()->paragraphs(3, true),
            'price_cents' => fake()->randomElement([0, 999, 1999, 2999, 4900, 9900]),
            'currency' => 'USD',
            'completeness' => fake()->randomElement(ProjectCompleteness::cases())->value,
            'status' => ProjectStatus::Draft->value,
            'cover_image_path' => null,
            'zip_path' => 'zips/'.fake()->uuid().'.zip',
            'tech_stack' => fake()->randomElements(
                ['Laravel', 'React', 'Vue', 'Next.js', 'Tailwind CSS', 'Django', 'Flutter', 'Node.js'],
                fake()->numberBetween(1, 4),
            ),
            'scan_report' => null,
            'review_notes' => null,
            'reviewed_at' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Draft->value,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::PendingReview->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Approved->value,
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Rejected->value,
            'reviewed_at' => now(),
        ]);
    }
}
