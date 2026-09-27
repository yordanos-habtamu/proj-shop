<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'project_id' => Project::factory()->approved(),
            'amount_cents' => 1999,
            'currency' => 'USD',
            'provider' => 'stripe',
            'provider_tx_id' => fake()->regexify('[A-Z0-9]{24}'),
            'status' => OrderStatus::Pending->value,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid->value,
        ]);
    }

    public function refunded(): static
    {
        return $this->paid()->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded->value,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Expired->value,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Failed->value,
        ]);
    }
}
