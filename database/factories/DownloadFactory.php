<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\Order;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Download>
 */
class DownloadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory()->paid(),
            'project_id' => Project::factory()->approved(),
            'token' => Str::uuid()->toString().Str::random(32),
            'downloaded_at' => null,
        ];
    }
}
