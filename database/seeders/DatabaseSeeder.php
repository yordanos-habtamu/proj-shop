<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->admin()->create([
            'name' => 'Prodhunt Admin',
            'email' => 'admin@prodhunt.test',
        ]);

        User::factory()->reviewer()->create([
            'name' => 'Code Reviewer',
            'email' => 'reviewer@prodhunt.test',
        ]);

        $sellers = User::factory(4)->seller()->create();

        $published = $sellers->map(
            fn (User $seller) => Project::factory()->approved()->create([
                'seller_id' => $seller->id,
                'price_cents' => fake()->randomElement([0, 1999, 2999, 4900]),
            ]),
        );

        Project::factory()->pendingReview()->create([
            'seller_id' => $sellers->first()->id,
        ]);

        $buyer = User::factory()->create();

        Order::factory(2)->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $published->first()->id,
            'amount_cents' => $published->first()->price_cents,
        ]);
    }
}
