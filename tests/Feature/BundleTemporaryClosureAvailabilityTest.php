<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleCustomerService;
use App\Services\System\BusinessSettingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_77 — a deactivated store (status=0) correctly hid its bundles, but a temporarily-closed
 * store (active=0, status still 1 — the vendor's own "close my store" toggle in
 * BusinessSettingsController) did not. HandlesBundleCart::bundleUnavailableReason() checked only
 * store->status, unlike CartValidationTrait (regular cart items) and HandlesPromotionEnrollment
 * (Happy Hour/BOGO), which already check both status and active.
 */
class BundleTemporaryClosureAvailabilityTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    public function test_a_temporarily_closed_store_hides_its_bundle(): void
    {
        app(BusinessSettingService::class)->saveValue('product_bundle_status', 1);

        $store = $this->bundleFixtureStore(2);

        if (! $store) {
            $this->markTestSkipped('need an open store with two sellable items to test against');
        }

        $this->enableBundlesFor($store);

        $items = $this->sellableFixtureItems($store, 2);

        $this->openFixtureStore($store);

        $bundle = new Bundle();
        $bundle->store_id = $store->id;
        $bundle->module_id = $store->module_id;
        $bundle->name = 'TC_77 Test Bundle';
        $bundle->base_price = 100;
        $bundle->discount_percentage = 10;
        $bundle->discounted_price = 90;
        $bundle->status = 1;
        $bundle->start_date = now()->subYear();
        $bundle->end_date = now()->addYear();
        $bundle->created_by = 'vendor';
        $bundle->save();

        foreach ($items as $item) {
            $bundleItem = new BundleItem();
            $bundleItem->bundle_id = $bundle->id;
            $bundleItem->item_id = $item->id;
            $bundleItem->quantity = 1;
            $bundleItem->item_name = $item->name;
            $bundleItem->unit_price = $item->price;
            $bundleItem->item_price = $item->price;
            $bundleItem->save();
        }

        $bundle->refresh();
        $bundle->load('store', 'items');

        $service = app(BundleCustomerService::class);

        $this->assertNull($service->unavailableReason($bundle), 'a bundle from an open store must be available');

        $store->active = 0;
        $store->save();
        $bundle->refresh();
        $bundle->load('store', 'items');

        $this->assertNotNull(
            $service->unavailableReason($bundle),
            'a bundle from a temporarily-closed store (active=0) must not be available, even though status is still 1'
        );
    }
}
