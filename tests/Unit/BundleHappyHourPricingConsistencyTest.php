<?php

namespace Tests\Unit;

use App\Models\Bundle;
use App\Services\Promotion\BundleGroupPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression for TC_79/TC_125 (vendor-panel QA pass, same defect already on record from the
 * admin-panel pass as TC_624). BundleGroupPresenter::bundlePricing() swaps in a running happy
 * hour's percentage for discount_percentage and final_price, but bundle_price used to stay at
 * the bundle's OWN stored discounted_price regardless — so the same API response quoted two
 * different prices for the same bundle. bundle_price now tracks whichever percentage is
 * actually in effect, matching final_price.
 */
class BundleHappyHourPricingConsistencyTest extends TestCase
{
    use DatabaseTransactions;

    private function bundle(float $basePrice, float $discountPercentage, float $discountedPrice): Bundle
    {
        $bundle = new Bundle();
        $bundle->base_price = $basePrice;
        $bundle->discount_percentage = $discountPercentage;
        $bundle->discounted_price = $discountedPrice;

        return $bundle;
    }

    public function test_bundle_price_matches_final_price_when_a_happy_hour_is_running(): void
    {
        // A bundle discounted 20% (63.00 -> 50.40) sits under a 5% happy hour.
        $bundle = $this->bundle(basePrice: 63.00, discountPercentage: 20, discountedPrice: 50.40);

        $pricing = app(BundleGroupPresenter::class)->bundlePricing($bundle, happyHourPercentage: 5.0);

        $this->assertTrue($pricing['is_happy_hour']);
        $this->assertSame(5.0, $pricing['discount_percentage']);
        $this->assertEqualsWithDelta(59.85, $pricing['final_price'], 0.001);
        $this->assertSame(
            $pricing['final_price'],
            $pricing['bundle_price'],
            'bundle_price must never disagree with final_price in the same response'
        );
    }

    public function test_bundle_price_still_reflects_the_bundles_own_discount_without_a_happy_hour(): void
    {
        $bundle = $this->bundle(basePrice: 63.00, discountPercentage: 20, discountedPrice: 50.40);

        $pricing = app(BundleGroupPresenter::class)->bundlePricing($bundle, happyHourPercentage: null);

        $this->assertFalse($pricing['is_happy_hour']);
        $this->assertEqualsWithDelta(50.40, $pricing['bundle_price'], 0.001);
        $this->assertSame($pricing['final_price'], $pricing['bundle_price']);
    }
}
