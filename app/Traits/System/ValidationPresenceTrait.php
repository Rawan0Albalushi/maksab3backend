<?php

namespace App\Traits\System;

trait ValidationPresenceTrait
{
    protected static function presenceRules(mixed $presence): array
    {
        return match (true) {
            $presence === null => [],
            is_string($presence) => explode('|', $presence),
            default => [$presence],
        };
    }
}
