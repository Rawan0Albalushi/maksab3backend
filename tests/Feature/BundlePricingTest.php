<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Services\Promotion\BundleService;
use Tests\TestCase;

class BundlePricingTest extends TestCase
{
    private function prices(float $base, float $discount): array
    {
        return app(BundleService::class)->prices($base, $discount);
    }

    public function test_a_discount_is_applied_to_the_full_decimal_place(): void
    {
        $prices = $this->prices(63.00, 5);

        $this->assertSame(59.85, $prices['discounted_price'], 'round() needs the digit count, not the input step "0.01".');
    }

    public function test_no_discount_leaves_the_base_price_alone(): void
    {
        $prices = $this->prices(185.50, 0);

        $this->assertSame(185.50, $prices['base_price']);
        $this->assertSame(185.50, $prices['discounted_price'], 'A zero discount is allowed and changes nothing.');
    }

    public function test_the_mockup_figures_reproduce(): void
    {
        $this->assertSame(380.00, $this->prices(400.00, 5)['discounted_price']);
        $this->assertSame(176.23, $this->prices(185.50, 5)['discounted_price'], '185.50 - 5% is 176.225, which rounds to 176.23.');
    }

    public function test_the_price_never_goes_negative(): void
    {
        $this->assertSame(0.0, $this->prices(0.0, Bundle::MAX_DISCOUNT_PERCENTAGE)['discounted_price']);
        $this->assertGreaterThanOrEqual(0, $this->prices(10.0, 99)['discounted_price']);
    }

    public function test_the_highest_allowed_discount_still_leaves_something_to_pay(): void
    {
        $prices = $this->prices(100.0, Bundle::MAX_DISCOUNT_PERCENTAGE);

        $this->assertSame(1.0, $prices['discounted_price'], '99% is the cap precisely so a bundle is never free.');
    }
}
