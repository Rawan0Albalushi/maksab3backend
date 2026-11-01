<?php

namespace App\Traits\System;

use Closure;

trait MemoizesLookupsTrait
{
    private static array $lookupMemo = [];

    protected function memoize(string $key, Closure $resolver): mixed
    {
        if (! array_key_exists($key, self::$lookupMemo)) {
            self::$lookupMemo[$key] = $resolver();
        }

        return self::$lookupMemo[$key];
    }

    public static function forgetMemo(?string $key = null): void
    {
        if ($key === null) {
            self::$lookupMemo = [];

            return;
        }

        unset(self::$lookupMemo[$key]);
    }
}
