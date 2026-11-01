<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PromotionSharedPartialsTest extends TestCase
{
    private const SHARED = [
        'partials.promotion._item_options_modal',
        'partials.promotion._item_picker_styles',
    ];

    private const BOGO_ONLY = [
        'partials.bogo._item_picker_scripts',
        'partials.bogo._enrollment_items',
    ];

    public function test_the_shared_partials_exist_where_every_promotion_can_reach_them(): void
    {
        foreach (self::SHARED as $view) {
            $this->assertTrue(View::exists($view), "{$view} is missing.");
        }
    }

    public function test_the_shared_partials_carry_nothing_specific_to_one_promotion(): void
    {
        foreach (self::SHARED as $view) {
            $source = file_get_contents(View::getFinder()->find($view));

            $this->assertStringNotContainsStringIgnoringCase('bogo', $source, "{$view} must not name one promotion.");
            $this->assertStringNotContainsString("'buy'", $source, "{$view} must not know about buy/get sides.");
            $this->assertStringNotContainsString("'get'", $source, "{$view} must not know about buy/get sides.");
        }
    }

    public function test_the_buy_get_partials_stayed_with_bogo(): void
    {
        foreach (self::BOGO_ONLY as $view) {
            $this->assertTrue(View::exists($view), "{$view} is missing.");
        }

        $this->assertFalse(
            View::exists('partials.promotion._item_picker_scripts'),
            'The picker script encodes buy/get, so it is not shared — Bundle needs its own.',
        );
    }

    public function test_bogo_still_includes_the_shared_partials_from_their_new_home(): void
    {
        foreach ([
            'resources/views/admin-views/promotions/bogo-offer/view.blade.php',
            'resources/views/vendor-views/promotions/bogo-offer/list.blade.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));

            $this->assertStringContainsString('partials.promotion._item_options_modal', $source);
            $this->assertStringContainsString('partials.promotion._item_picker_styles', $source);
            $this->assertStringNotContainsString('partials.bogo._item_config_modal', $source, 'Stale include left behind.');
        }
    }
}
