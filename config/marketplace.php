<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Marketplace delivery
    |--------------------------------------------------------------------------
    |
    | Purchase delivery is handled with signed, expiring links. Each link is
    | single use: once the buyer has downloaded the archive the token is spent
    | and they request a fresh link from their receipt page instead.
    |
    */

    'download_ttl_minutes' => (int) env('MARKETPLACE_DOWNLOAD_TTL', 15),

    /*
    |--------------------------------------------------------------------------
    | Download filename
    |--------------------------------------------------------------------------
    |
    | The archive is stored under a random name on the private disk, so the
    | name the buyer sees is derived from the project slug instead.
    |
    */

    'download_filename' => '{slug}.zip',

];
