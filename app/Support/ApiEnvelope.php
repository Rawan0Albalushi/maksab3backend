<?php

namespace App\Support;

/**
 * The single definition of the API response envelope:
 *
 *   { "identical_code": …, "message": …, "content": …, "errors": [ {code, message} ] }
 *
 * Both the exception renderer and the response middleware build it, so the shape, the
 * status→code table and the error-list conversion live here rather than being copied
 * into each — otherwise a change in one quietly makes the two disagree.
 */
final class ApiEnvelope
{
    public const KEYS = ['identical_code', 'message', 'content', 'errors'];

    private const STATUS_CODES = [
        200 => 'default_200',
        201 => 'default_store_201',
        400 => 'bad_request_400',
        401 => 'unauthorized_401',
        403 => 'forbidden_403',
        404 => 'default_404',
        405 => 'method_not_allowed_405',
        406 => 'not_acceptable_406',
        409 => 'already_exists_409',
        422 => 'unprocessable_entity_422',
        429 => 'too_many_requests_429',
        500 => 'default_500',
        503 => 'service_unavailable_503',
    ];

    /**
     * `translate()`, not `__()`. The messages come from config/response.php, and `__()`
     * resolves against JSON language files this app does not have — so every envelope
     * went out in English whatever locale the app asked for. `translate()` reads
     * resources/lang/<locale>/messages.php, which is where the rest of the API's copy
     * lives.
     *
     * translation.md §2 forbids a variable inside translate() because translate() learns
     * whatever it is handed. The exception holds here: the argument can only be one of
     * the 16 fixed values in config/response.php (plus the two literals in
     * configForStatus() below), so the set it can grow by is bounded and every one of
     * them is seeded in messages.php by hand. Do not widen this to a caller-supplied
     * message.
     */
    public static function make(array $config, mixed $content = null, array $errors = []): array
    {
        return [
            'identical_code' => $config['identical_code'] ?? null,
            'message' => isset($config['message']) ? translate($config['message']) : null,
            'content' => $content,
            'errors' => $errors,
        ];
    }

    public static function isEnvelope(mixed $payload): bool
    {
        if (! is_array($payload) || array_is_list($payload)) {
            return false;
        }

        $keys = array_keys($payload);
        sort($keys);
        $want = self::KEYS;
        sort($want);

        return $keys === $want;
    }

    public static function configForStatus(int $status): array
    {
        if (isset(self::STATUS_CODES[$status]) && is_array(config('response.'.self::STATUS_CODES[$status]))) {
            return config('response.'.self::STATUS_CODES[$status]);
        }

        $successful = $status >= 200 && $status < 300;

        return [
            'identical_code' => ($successful ? 'default_' : 'error_').$status,
            'message' => $successful ? 'Successfully fetched' : 'Something went wrong',
            'http_response_code' => $status,
        ];
    }

    /**
     * Turn a MessageBag-style map (`['field' => ['msg', …]]`) into the `{code, message}`
     * list the envelope carries. A raw MessageBag serialises as an object keyed by field,
     * which is a different shape for every failing request.
     */
    public static function errorList(array $errors): array
    {
        $list = [];

        foreach ($errors as $field => $messages) {
            $list[] = [
                'code' => is_string($field) ? $field : 'error',
                'message' => is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages,
            ];
        }

        return $list;
    }

    public static function singleError(string $code, string $message): array
    {
        return [['code' => $code, 'message' => $message]];
    }
}
