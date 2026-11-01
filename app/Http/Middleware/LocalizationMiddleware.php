<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;

class LocalizationMiddleware
{
    public function handle($request, Closure $next)
    {
        App::setLocale($this->resolveLocale($request->header('X-localization')));

        return $next($request);
    }

    private function resolveLocale(?string $requested): string
    {
        $requested = trim((string) $requested);

        if (preg_match('/^[A-Za-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/', $requested)) {
            return $requested;
        }

        return config('app.fallback_locale') ?: 'en';
    }
}
