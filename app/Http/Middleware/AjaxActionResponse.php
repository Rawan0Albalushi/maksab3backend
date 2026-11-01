<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AjaxActionResponse
{
    public const HEADER = 'X-Ajax-Request';

    public const FRAGMENT_HEADER = 'X-Ajax-Fragment';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasHeader(self::HEADER)) {
            return $next($request);
        }

        $errorsBefore = $request->session()->get('errors');

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            return $this->normalise($request, $response);
        }

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        $messages = $request->session()->get('toastr::messages', []);

        $request->session()->forget('toastr::messages');

        $errors = $this->freshErrors($request, $errorsBefore);

        if ($errors !== []) {
            $request->session()->forget(['errors', '_old_input']);

            return response()->json([
                'ok' => false,
                'type' => 'error',
                'message' => $this->firstOf($messages) ?? reset($errors)[0] ?? translate('messages.Please check the form for errors'),
                'errors' => $errors,
            ], 422);
        }

        $target = $response->getTargetUrl();

        if ($messages === []) {
            if ($target !== $request->headers->get('referer')) {
                return response()->json(['ok' => false, 'redirect' => $target], 409);
            }

            return response()->json([
                'ok' => true,
                'type' => 'success',
                'message' => translate('messages.Saved successfully'),
                'redirect_to' => $target,
            ]);
        }

        $toast = end($messages);
        $type = $toast['type'] ?? 'success';

        return response()->json([
            'ok' => ! in_array($type, ['error', 'warning'], true),
            'type' => $type,
            'message' => $toast['message'] ?? translate('messages.Saved successfully'),
            'redirect_to' => $target,
        ]);
    }

    private function normalise(Request $request, JsonResponse $response): JsonResponse
    {
        $messages = $request->session()->get('toastr::messages', []);

        $request->session()->forget('toastr::messages');

        $payload = $response->getData(true);

        if (! is_array($payload)) {
            return $response;
        }

        $changed = false;

        if (! array_key_exists('ok', $payload)) {
            $payload = ['ok' => $response->isSuccessful() && empty($payload['errors'])] + $payload;
            $changed = true;
        }

        if ($messages !== [] && ! array_key_exists('message', $payload)) {
            $toast = end($messages);

            $payload['message'] = $toast['message'] ?? null;
            $payload['type'] ??= $toast['type'] ?? 'success';
            $changed = true;
        }

        if (! array_key_exists('type', $payload)) {
            $payload['type'] = $payload['ok'] ? 'success' : 'error';
            $changed = true;
        }

        return $changed ? $response->setData($payload) : $response;
    }

    private function freshErrors(Request $request, mixed $before): array
    {
        $after = $request->session()->get('errors');

        if ($after === null || $after === $before) {
            return [];
        }

        $bag = method_exists($after, 'getBag') ? $after->getBag('default') : $after;

        if (! $bag instanceof MessageBag || $bag->isEmpty()) {
            return [];
        }

        return $bag->messages();
    }

    private function firstOf(array $messages): ?string
    {
        if ($messages === []) {
            return null;
        }

        $toast = end($messages);

        return ($toast['type'] ?? null) === 'error' ? ($toast['message'] ?? null) : null;
    }
}
