<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Serialize a project for public storefront pages.
     *
     * @return array<string, mixed>
     */
    protected function projectListing(Project $project): array
    {
        $listing = $project->presentForMarketplace();

        $seller = $project->relationLoaded('seller') ? $project->seller : null;

        $listing['seller'] = $seller !== null
            ? ['id' => $seller->id, 'name' => $seller->name, 'email' => $seller->email]
            : ['id' => $project->seller_id, 'name' => null, 'email' => null];

        return $listing;
    }
}
