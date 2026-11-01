<?php

namespace App\Exceptions;

use App\Support\ApiEnvelope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Renders every uncaught exception on an `api/*` route as the standard envelope.
 *
 * Without this, Laravel answers with its own shapes — `{message, exception, file, line, trace}`
 * for a 500 and `{message, errors:{field:[…]}}` for a validation failure — so a client parsing
 * the envelope breaks on exactly the responses it most needs to read, and a 500 leaks server
 * paths and a stack trace. Web routes keep their normal HTML error pages.
 */
class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $request->is('api/*')) {
            return null;
        }

        // Already carries a fully-formed response (this is how FormRequest validation
        // returns its envelope) — returning null lets Laravel use it as-is.
        if ($e instanceof HttpResponseException) {
            return null;
        }

        [$config, $errors] = self::map($e);

        return response()->json(
            ApiEnvelope::make($config, null, $errors),
            $config['http_response_code'] ?? 500
        );
    }

    private static function map(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return [config('response.unprocessable_entity_422'), ApiEnvelope::errorList($e->errors())];
        }

        if ($e instanceof AuthenticationException) {
            return [config('response.unauthorized_401'), ApiEnvelope::singleError('auth-001', $e->getMessage() ?: 'Unauthenticated.')];
        }

        if ($e instanceof AuthorizationException) {
            return [config('response.forbidden_403'), ApiEnvelope::singleError('forbidden', $e->getMessage() ?: 'This action is unauthorized.')];
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return [config('response.default_404'), ApiEnvelope::singleError('not-found', 'Not found')];
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return [config('response.method_not_allowed_405'), ApiEnvelope::singleError('method', 'Method not allowed')];
        }

        if ($e instanceof TooManyRequestsHttpException) {
            return [config('response.too_many_requests_429'), ApiEnvelope::singleError('throttle', 'Too many attempts, please try again later')];
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $config = ApiEnvelope::configForStatus($status);

            return [$config, ApiEnvelope::singleError('http-'.$status, $e->getMessage() ?: ($config['message'] ?? 'Error'))];
        }

        // Anything genuinely unexpected. The real message is only useful to a developer and
        // can leak internals, so it rides along only while debug is on.
        return [
            config('response.default_500'),
            ApiEnvelope::singleError('server-error', config('app.debug') ? $e->getMessage() : 'Something went wrong'),
        ];
    }
}
