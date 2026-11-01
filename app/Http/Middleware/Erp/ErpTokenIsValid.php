<?php

namespace App\Http\Middleware\Erp;

use App\Models\ErpApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ErpTokenIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->bearerToken();
        $apiSecret = $request->header('X-API-Secret');

        if (! $apiKey || ! $apiSecret) {
            return $this->unauthorized();
        }

        $token = ErpApiToken::where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (! $token || ! hash_equals($token->api_secret, hash('sha256', $apiSecret))) {
            return $this->unauthorized();
        }

        $token->update(['last_used_at' => now()]);

        return $next($request);
    }

    protected function unauthorized(): Response
    {
        return response()->json(['errors' => [['code' => 'auth', 'message' => 'Unauthorized.']]], 401);
    }
}
