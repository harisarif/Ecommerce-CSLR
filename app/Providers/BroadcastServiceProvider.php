<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register broadcast routes
        Broadcast::routes([
            'middleware' => ['auth:api'], // your JWT middleware
        ]);

        // Load channels file
        require base_path('routes/channels.php');
    }
}
