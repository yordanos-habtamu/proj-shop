<?php

namespace Database\Factories;

use App\Models\Download;
use App\Models\Order;
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
            'token' => Str::uuid()->toString().Str::random(32),
            'downloaded_at' => null,
        ];
    }

    /**
     * The project is always the one that was bought, so a download row can
     * never point at a project its order did not cover.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Download $download): void {
            $download->project_id = $download->order->project_id;
        });
    }
}
