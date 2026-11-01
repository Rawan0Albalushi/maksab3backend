<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

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
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            $namespace = $this->namespace;

            // Panel routes only. These are the ones the storefront's unconstrained
            // `/` can shadow, so they are pinned to the canonical host.
            $registerHostRoutes = function () use ($namespace) {
                Route::middleware('web')
                    ->namespace($namespace)
                    ->group(base_path('routes/web.php'));

                Route::prefix('admin')
                    ->middleware('web')
                    ->group(base_path('routes/admin.php'));

                Route::prefix('vendor-panel')
                    ->middleware('web')
                    ->namespace($namespace)
                    ->group(base_path('routes/vendor.php'));
            };

            // The API is deliberately left unconstrained, the way every module's API
            // already is. An `api/v1/...` path cannot be shadowed by the storefront,
            // and the apps may be pointed at either the www or the apex host: pinning
            // the API to one of them means the other 404s, or gets redirected - and a
            // client downgrades a redirected POST to GET, which every POST-only
            // endpoint then rejects with "The GET method is not supported".
            Route::prefix('api/v1')
                ->middleware('api')
                ->namespace($namespace)
                ->group(base_path('routes/api/v1/api.php'));

            $hostDomain = config('app.host_domain');
            if ($hostDomain) {
                Route::domain($hostDomain)->group($registerHostRoutes);
            } else {
                $registerHostRoutes();
            }
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(600)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
