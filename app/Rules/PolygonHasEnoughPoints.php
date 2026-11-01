<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The map's `coordinates` string must describe something that can actually enclose an area.
 *
 * The field arrives as `(lat,lng),(lat,lng),...`. `required` only proved it was not empty, so a
 * half-drawn shape — two points, or the same point clicked twice — passed validation, reached
 * ZoneService's LineString and threw a database error. The admin saw a 500 instead of being told
 * the area was incomplete (QA case TC_40).
 *
 * Three DISTINCT points is the test, not three points: three clicks in the same place is still a
 * dot, and duplicates are dropped before counting.
 */
class PolygonHasEnoughPoints implements ValidationRule
{
    private const MINIMUM_POINTS = 3;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $points = $this->distinctPoints((string) $value);

        if (count($points) < self::MINIMUM_POINTS) {
            $fail(translate('Please select a valid area on the map by connecting at least three points'));
        }
    }

    /** @return array<string> the "lat,lng" pairs, de-duplicated and in order */
    private function distinctPoints(string $value): array
    {
        $trimmed = trim($value, " \t\n\r\0\x0B()");

        if ($trimmed === '') {
            return [];
        }

        $points = [];

        foreach (explode('),(', $trimmed) as $pair) {
            $parts = array_map('trim', explode(',', $pair));

            // A pair that is not two numbers is not a point — it cannot contribute a corner.
            if (count($parts) !== 2 || ! is_numeric($parts[0]) || ! is_numeric($parts[1])) {
                continue;
            }

            $points[$parts[0].','.$parts[1]] = true;
        }

        return array_keys($points);
    }
}
