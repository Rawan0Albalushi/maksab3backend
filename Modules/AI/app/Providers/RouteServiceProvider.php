<?php

namespace Modules\AI\app\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected string $moduleNamespace = 'Modules\AI\app\Http\Controllers';

    public function boot(): void
    {
        $this->configureAiChatRateLimiting();

        parent::boot();
    }

    protected function configureAiChatRateLimiting(): void
    {
        $isDemo = fn (): bool => function_exists('getEnvMode') && getEnvMode() === 'demo';

        RateLimiter::for('ai-chat-send', function (Request $request) use ($isDemo) {
            return $isDemo()
                ? Limit::none()
                : Limit::perMinute(12)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('ai-chat-group', function (Request $request) use ($isDemo) {
            return $isDemo()
                ? Limit::none()
                : Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });
    }

    public function map(): void
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/web.php'));

        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/admin/routes.php'));
    }

        protected function mapApiRoutes()
    {
        Route::prefix('api/v1')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('AI', '/routes/api/v1/api.php'));
    }
}
