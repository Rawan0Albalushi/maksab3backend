<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SanitizeBearerToken
{
    protected array $placeholders = [
        'null',
        'undefined',
        'nil',
        'none',
        'false',
        '(null)',
    ];

    public function handle(Request $request, Closure $next)
    {
        if (! $request->headers->has('Authorization')) {
            return $next($request);
        }

        $token = trim((string) $request->bearerToken());

        if ($token === '' || in_array(strtolower($token), $this->placeholders, true)) {
            $request->headers->remove('Authorization');
            $request->server->remove('HTTP_AUTHORIZATION');
            $request->server->remove('REDIRECT_HTTP_AUTHORIZATION');
        }

        return $next($request);
    }
}
