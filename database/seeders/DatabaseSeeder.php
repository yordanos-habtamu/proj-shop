<?php

namespace Database\Seeders;

use App\Models\Fee;
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

        $admin = User::where('email', 'admin@prodhunt.test')->firstOrFail();

        Fee::setActive(5, $admin->id);

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

        $paidProject = $published->first();
        $split = Fee::computeFor($paidProject->price_cents);

        Order::factory(2)->paid()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $paidProject->id,
            'amount_cents' => $paidProject->price_cents,
            'fee_percent' => $split['percent'],
            'fee_amount_cents' => $split['fee_amount_cents'],
            'payout_cents' => $split['payout_cents'],
        ]);

        Order::factory()->refunded()->create([
            'buyer_id' => $buyer->id,
            'project_id' => $paidProject->id,
            'amount_cents' => $paidProject->price_cents,
            'fee_percent' => $split['percent'],
            'fee_amount_cents' => $split['fee_amount_cents'],
            'payout_cents' => $split['payout_cents'],
        ]);

        $secondProject = $published->get(1);
        $secondSplit = Fee::computeFor($secondProject->price_cents);

        $abandoned = [
            'buyer_id' => $buyer->id,
            'project_id' => $secondProject->id,
            'amount_cents' => $secondProject->price_cents,
            'fee_percent' => $secondSplit['percent'],
            'fee_amount_cents' => $secondSplit['fee_amount_cents'],
            'payout_cents' => $secondSplit['payout_cents'],
        ];

        Order::factory()->create($abandoned);
        Order::factory()->expired()->create($abandoned);
        Order::factory()->failed()->create($abandoned);
    }
}
