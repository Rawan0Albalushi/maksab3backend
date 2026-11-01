<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a list row edit itself over ajax without every controller behind one
 * having to learn to speak JSON.
 *
 * The panel carries ~230 status switches and a row priority select on four
 * screens, and they all share one shape: hit a URL that already encodes the
 * new value, flash a Toastr line, redirect back. This turns that redirect into
 * the JSON the row needs — but only for requests carrying one of the headers
 * below, which `status-toggle.js` and `priority-select.js` send and nothing
 * else does. Every other caller sees the redirect unchanged.
 *
 * Two header names for one behaviour because the switches came first and their
 * name is baked into ~230 call sites plus the panel docs; `X-Inline-Update` is
 * what anything that is not a status switch should send.
 *
 * Registered at the end of the `web` group in bootstrap/app.php: it has to sit
 * inside StartSession, or the flashed message is already written to the session
 * by the time this runs and cannot be consumed.
 */
class StatusToggleResponse
{
    public const HEADER = 'X-Status-Toggle';

    public const HEADERS = [self::HEADER, 'X-Inline-Update'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->isInlineUpdate($request) || ! $response instanceof RedirectResponse) {
            return $response;
        }

        $messages = $request->session()->get('toastr::messages', []);
        // Drop it either way — the page is not reloading, so a message left in
        // the flash bag would surface on whatever the admin opens next.
        $request->session()->forget('toastr::messages');

        $target = $response->getTargetUrl();

        if (empty($messages)) {
            // Bounced somewhere else with nothing to say — an expired session or
            // a permission redirect, not a completed toggle. Hand the client the
            // target so it can follow it instead of reporting a false success.
            if ($target !== $request->headers->get('referer')) {
                return response()->json(['ok' => false, 'redirect' => $target], 409);
            }

            return response()->json([
                'ok' => true,
                'type' => 'success',
                'message' => translate('Updated successfully'),
            ]);
        }

        $toast = end($messages);
        $type = $toast['type'] ?? 'success';

        return response()->json([
            // `error` / `warning` mean the controller refused the change, so the
            // switch has to go back to where it was.
            'ok' => ! in_array($type, ['error', 'warning'], true),
            'type' => $type,
            'message' => $toast['message'] ?? translate('Updated successfully'),
        ]);
    }

    private function isInlineUpdate(Request $request): bool
    {
        foreach (self::HEADERS as $header) {
            if ($request->hasHeader($header)) {
                return true;
            }
        }

        return false;
    }
}
