<?php

namespace Tests\Unit;

use App\Rules\PolygonHasEnoughPoints;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * QA cases TC_39, TC_40 and TC_46 — the three Add New Zone validations that were missing.
 *
 * All three shared one cause: `name` and `display_name` arrive as ARRAYS, one entry per language
 * tab, and the rules were written as if they were strings.
 */
class ZoneCreateValidationTest extends TestCase
{
    use DatabaseTransactions;

    private function validate(array $overrides = []): \Illuminate\Contracts\Validation\Validator
    {
        $payload = array_merge([
            'name' => ['Valid Zone', '', ''],
            'display_name' => ['Valid Display', '', ''],
            'lang' => ['default', 'en', 'ar'],
            'coordinates' => '(23.90,90.30),(23.90,90.34),(23.94,90.34),(23.94,90.30)',
        ], $overrides);

        return Validator::make($payload, (new \App\Http\Requests\Admin\ZoneAddRequest)->rules());
    }

    // ---------------------------------------------------------------- TC_46, the array trap

    public function test_a_name_longer_than_the_column_is_refused_not_truncated(): void
    {
        // `name => max:191` tested the ARRAY'S COUNT, so a 320-character name passed validation
        // and the column silently cut it to 255. The admin was told the save succeeded.
        $validator = $this->validate(['name' => [str_repeat('Q', 320), '', '']]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name.0', $validator->errors()->toArray());
    }

    public function test_a_name_at_the_limit_is_accepted(): void
    {
        $this->assertFalse($this->validate(['name' => [str_repeat('Q', 191), '', '']])->fails());
    }

    // ---------------------------------------------------------------- TC_39, the display name

    public function test_a_blank_display_name_is_refused(): void
    {
        $validator = $this->validate(['display_name' => ['', '', '']]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('display_name.0', $validator->errors()->toArray());
    }

    public function test_a_blank_default_name_is_still_refused(): void
    {
        $this->assertTrue($this->validate(['name' => ['', '', '']])->fails());
    }

    // ---------------------------------------------------------------- TC_40, the half-drawn map

    public function test_a_polygon_needs_three_distinct_points(): void
    {
        foreach ([
            '' => 'empty',
            '(23.90,90.30)' => 'one point',
            '(23.90,90.30),(23.90,90.34)' => 'two points',
            '(23.90,90.30),(23.90,90.30),(23.90,90.30)' => 'the same point three times',
        ] as $coordinates => $why) {
            $failed = false;
            (new PolygonHasEnoughPoints)->validate('coordinates', $coordinates, function () use (&$failed) {
                $failed = true;
            });

            $this->assertTrue($failed, "$why should not be accepted as an area");
        }
    }

    public function test_three_distinct_points_are_enough(): void
    {
        $failed = false;
        (new PolygonHasEnoughPoints)->validate(
            'coordinates',
            '(23.90,90.30),(23.90,90.34),(23.94,90.34)',
            function () use (&$failed) { $failed = true; },
        );

        $this->assertFalse($failed);
    }
}
