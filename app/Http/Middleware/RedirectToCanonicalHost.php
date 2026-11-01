<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = config('app.host_domain');

        if ($canonical) {
            $host  = $request->getHost();
            $strip = static fn (string $h): string => preg_replace('/^www\./i', '', $h);

            if ($host !== $canonical && $strip($host) === $strip($canonical)) {
                return redirect()->to(
                    $request->getScheme() . '://' . $canonical . $request->getRequestUri(),
                    301
                );
            }
        }

        return $next($request);
    }
}
