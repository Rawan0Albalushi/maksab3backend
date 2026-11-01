<?php

namespace App\Http\Middleware;

use App\Support\ApiEnvelope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guarantees every JSON response on an api/* route carries the standard envelope.
 *
 * Most endpoints already build it through BaseApiController::responseFormatter, and uncaught
 * exceptions go through ApiExceptionRenderer. This catches the remainder — controllers that
 * hand back a bare `{data: …}` or `{message: …}`, several of which are shared with the admin
 * and vendor Blade panels and so cannot be changed at the controller without breaking that JS.
 *
 * Anything that is not a JsonResponse (file downloads, streamed exports) passes through
 * untouched: a binary body has no envelope to carry.
 */
class EnforceApiEnvelope
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (ApiEnvelope::isEnvelope($payload)) {
            // Correct except, possibly, for `errors` arriving as a MessageBag-style object
            // keyed by field. Re-encode only when there is something to change.
            if (! empty($payload['errors']) && is_array($payload['errors']) && ! array_is_list($payload['errors'])) {
                $payload['errors'] = ApiEnvelope::errorList($payload['errors']);
                $response->setData($payload);
            }

            return $response;
        }

        $response->setEncodingOptions($response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION);

        return $response->setData(self::wrap($payload, $response->getStatusCode()));
    }

    private static function wrap(mixed $payload, int $status): array
    {
        $config = ApiEnvelope::configForStatus($status);

        $message = is_array($payload) && isset($payload['message']) && is_string($payload['message'])
            ? $payload['message']
            : __($config['message'] ?? '');

        if ($status >= 200 && $status < 300) {
            return ['identical_code' => $config['identical_code'] ?? null,
                'message' => $message, 'content' => $payload, 'errors' => []];
        }

        $errors = [];

        if (is_array($payload) && isset($payload['errors']) && is_array($payload['errors'])) {
            $errors = array_is_list($payload['errors'])
                ? $payload['errors']
                : ApiEnvelope::errorList($payload['errors']);
        }

        return ['identical_code' => $config['identical_code'] ?? null,
            'message' => $message, 'content' => null,
            'errors' => $errors ?: ApiEnvelope::singleError('error', $message)];
    }
}
