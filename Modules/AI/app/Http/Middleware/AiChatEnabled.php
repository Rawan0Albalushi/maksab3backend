<?php

namespace Modules\AI\app\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AI\app\Core\AiModule;
use Symfony\Component\HttpFoundation\Response;

class AiChatEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! AiModule::isChatActive()) {
            $config = config('response.service_unavailable_503');

            return response()->json([
                'identical_code' => $config['identical_code'],
                'message' => __($config['message']),
                'content' => null,
                'errors' => [[
                    'code' => 'ai_disabled',
                    'message' => translate('AI chat is currently disabled.'),
                ]],
            ], $config['http_response_code']);
        }

        return $next($request);
    }
}
