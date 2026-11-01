<?php

namespace App\Support\Notification\Fcm;

class FcmTokenResolver
{
    private const PLACEHOLDERS = ['@', '-', 'null', 'undefined'];

    public static function isUsable(mixed $token): bool
    {
        if (! is_string($token)) {
            return false;
        }

        $token = trim($token);

        if ($token === '') {
            return false;
        }

        return ! in_array(strtolower($token), self::PLACEHOLDERS, true);
    }

    public static function clean(mixed $tokens): array
    {
        $tokens = is_array($tokens) ? $tokens : [$tokens];

        $usable = [];

        foreach ($tokens as $token) {
            if (self::isUsable($token)) {
                $usable[] = trim($token);
            }
        }

        return array_values(array_unique($usable));
    }

    public static function chunks(array $tokens): array
    {
        $size = (int) config('notification.fcm.batch_size', 500);

        return $size > 0 ? array_chunk($tokens, $size) : [$tokens];
    }
}
