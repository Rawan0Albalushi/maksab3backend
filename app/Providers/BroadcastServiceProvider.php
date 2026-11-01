<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Broadcast::routes();

        require base_path('routes/channels.php');

        // is_file as well as the published flag: an older RideShare copy left behind by a
        // partial update is still "published" but need not ship this file, and a failed
        // require here runs on every request.
        if (addon_published_status('RideShare') && is_file(base_path('Modules/RideShare/Routes/channels.php'))) {
            require base_path('Modules/RideShare/Routes/channels.php');
        }
    }
}
