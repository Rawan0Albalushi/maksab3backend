<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\Notification\Fcm\FcmCredentials;
use Kreait\Firebase\Factory;
class FirebaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('firebase.messaging', function ($app) {
            $serviceAccount = FcmCredentials::serviceAccount();

            if (count($serviceAccount) > 0) {
                return (new Factory)->withServiceAccount($serviceAccount)->createMessaging();
            }

            return false;
        });
    }

    public function boot(): void
    {
    }
}
