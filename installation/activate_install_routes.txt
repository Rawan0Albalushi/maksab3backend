<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * Install mode.
 *
 * prepare:installable copies this file over app/Providers/RouteServiceProvider.php, so a prepared
 * package boots serving routes/install.php and nothing else. InstallController copies
 * app/Providers/RouteServiceProvider.txt back over it once the wizard finishes, which is what
 * returns the site to normal routing.
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // booted(), so the wizard registers after every module provider and keeps `/`. The
        // Builder storefront claims `/` with no domain constraint and boots later, and a
        // later registration replaces an earlier one for the same method + domain + URI -
        // which left the wizard 404ing from the storefront with this file correctly in place.
        // The core copy of that module guards the panel host, but an update package carries
        // no Modules/, so the add-on already on disk may predate the guard.
        //
        // $this->routes() is unusable here: it only stores a callback for the parent's
        // booting hook, which has already fired. The lookups need refreshing for the same
        // reason - route() will not resolve without it.
        $this->app->booted(function () {
            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/install.php'));

            $this->app['router']->getRoutes()->refreshNameLookups();
            $this->app['router']->getRoutes()->refreshActionLookups();
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
