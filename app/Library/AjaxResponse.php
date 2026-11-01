<?php

namespace App\Library;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AjaxResponse implements Responsable
{
    private array $payload;

    private int $status;

    private function __construct(bool $ok, ?string $message, string $type, int $status)
    {
        $this->payload = array_filter([
            'ok' => $ok,
            'type' => $type,
            'message' => $message,
        ], static fn ($value) => $value !== null);

        $this->status = $status;
    }

    public static function success(?string $message = null): self
    {
        return new self(true, $message, 'success', 200);
    }

    public static function fail(?string $message = null, int $status = 200): self
    {
        return new self(false, $message, 'error', $status);
    }

    public static function warning(?string $message = null): self
    {
        return new self(false, $message, 'warning', 200);
    }

    public static function invalid(array $errors, ?string $message = null): self
    {
        $response = new self(false, $message, 'error', 422);

        $response->payload['errors'] = array_map(
            static fn ($messages) => is_array($messages) ? array_values($messages) : [$messages],
            $errors
        );

        return $response;
    }

    public function fragment(string $selector, mixed $html): self
    {
        return $this->put('fragments', $selector, $this->render($html));
    }

    public function fragments(array $fragments): self
    {
        foreach ($fragments as $selector => $html) {
            $this->fragment($selector, $html);
        }

        return $this;
    }

    public function append(string $selector, mixed $html): self
    {
        return $this->put('append', $selector, $this->render($html));
    }

    public function prepend(string $selector, mixed $html): self
    {
        return $this->put('prepend', $selector, $this->render($html));
    }

    public function remove(string ...$selectors): self
    {
        return $this->push('remove', $selectors);
    }

    public function refresh(string ...$selectors): self
    {
        return $this->push('refresh', $selectors);
    }

    public function reset(bool $reset = true): self
    {
        $this->payload['reset'] = $reset;

        return $this;
    }

    public function close(string $selector): self
    {
        $this->payload['close'] = $selector;

        return $this;
    }

    public function redirect(string $url): self
    {
        $this->payload['redirect'] = $url;

        return $this;
    }

    public function with(string $key, mixed $value): self
    {
        $this->payload['data'] ??= [];
        $this->payload['data'][$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->payload;
    }

    public function toResponse($request): JsonResponse
    {
        return response()->json($this->payload, $this->status);
    }

    public static function wantsFragment(Request $request, ?string $selector = null): bool
    {
        $header = $request->header(\App\Http\Middleware\AjaxActionResponse::FRAGMENT_HEADER);

        if ($header === null || $header === '') {
            return false;
        }

        if ($selector === null) {
            return true;
        }

        $wanted = array_map('trim', explode(',', $header));

        return in_array(trim($selector), $wanted, true);
    }

    private function render(mixed $html): string
    {
        if ($html instanceof Renderable) {
            return $html->render();
        }

        if ($html instanceof Htmlable) {
            return $html->toHtml();
        }

        return (string) $html;
    }

    private function put(string $bucket, string $key, string $value): self
    {
        $this->payload[$bucket] ??= [];
        $this->payload[$bucket][$key] = $value;

        return $this;
    }

    private function push(string $bucket, array $values): self
    {
        $this->payload[$bucket] = array_values(array_unique(
            array_merge($this->payload[$bucket] ?? [], $values)
        ));

        return $this;
    }
}
