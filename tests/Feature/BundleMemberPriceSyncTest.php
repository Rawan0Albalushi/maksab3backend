<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleGroupPresenter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_35 — changing a member product's price re-prices every running bundle holding it, with no
 * edit to the bundle.
 *
 * base_price and discounted_price are stored columns, because a bundle's price has to survive a
 * member being deleted and has to be the same figure on the panel, in the API and in the cart. So
 * "automatically" means the columns are kept in step at the moment the item changes, rather than
 * every reader re-deriving them from today's menu and disagreeing about rounding.
 */
class BundleMemberPriceSyncTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private $items = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->bundleFixtureStore(2);

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store with two sellable items');
        }

        $this->enableBundlesFor($this->store);
        $this->openFixtureStore($this->store);

        $this->items = $this->sellableFixtureItems($this->store, 2);
        $this->bundle = $this->makeFixtureBundle($this->store, $this->items, discountPercentage: 20);
    }

    public function test_raising_a_member_price_raises_the_bundle_base_and_discounted_price(): void
    {
        $member = $this->items->first();
        $before = (float) $this->bundle->base_price;

        $member->price = (float) $member->price + 50;
        $member->save();

        $this->bundle->refresh();

        $this->assertEqualsWithDelta($before + 50, (float) $this->bundle->base_price, 0.01,
            'the bundle base price must follow its member');

        $this->assertEqualsWithDelta(
            round((float) $this->bundle->base_price * 0.8, 2),
            (float) $this->bundle->discounted_price,
            0.01,
            'the after-discount price must be re-derived from the new base',
        );
    }

    public function test_the_member_line_keeps_its_own_price_in_step(): void
    {
        $member = $this->items->first();

        $member->price = (float) $member->price + 25;
        $member->save();

        $line = $this->bundle->items()->where('item_id', $member->id)->first();

        $this->assertEqualsWithDelta((float) $member->price, (float) $line->unit_price, 0.01,
            'the frozen line price must follow the item');
        $this->assertEqualsWithDelta((float) $member->price, (float) $line->item_price, 0.01,
            'item_price is what the stale-pricing badge reads, so it must follow too');
    }

    public function test_the_customer_facing_pricing_reports_the_new_figures(): void
    {
        $member = $this->items->first();

        $member->price = (float) $member->price + 40;
        $member->save();

        $this->bundle->refresh();

        $pricing = app(BundleGroupPresenter::class)->bundlePricing($this->bundle);

        $this->assertEqualsWithDelta((float) $this->bundle->base_price, $pricing['base_price'], 0.01);
        $this->assertEqualsWithDelta((float) $this->bundle->discounted_price, $pricing['final_price'], 0.01);
    }

    /** TC_35 — the vendor list, the admin list and the customer API all report the new figures. */
    public function test_every_panel_reports_the_new_price_without_editing_the_bundle(): void
    {
        $member = $this->items->first();

        $member->price = (float) $member->price + 60;
        $member->save();

        $this->bundle->refresh();

        $panel = app(\App\Services\Promotion\BundleService::class)
            ->list(null, $this->store->module_id, $this->store->id, 50)
            ->firstWhere('id', $this->bundle->id);

        $this->assertNotNull($panel, 'the bundle must still be listed');
        $this->assertEqualsWithDelta((float) $this->bundle->base_price, (float) $panel->base_price, 0.01,
            'the vendor and admin lists read the same columns');
        $this->assertEqualsWithDelta((float) $this->bundle->discounted_price, (float) $panel->discounted_price, 0.01);

        $body = $this->withHeaders([
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
        ])->getJson('/api/v1/bundle/'.$this->bundle->id)->json('content');

        $this->assertEqualsWithDelta((float) $this->bundle->base_price, (float) $body['base_price'], 0.01,
            'the customer side follows too, with no edit to the bundle');
        $this->assertEqualsWithDelta((float) $this->bundle->discounted_price, (float) $body['final_price'], 0.01);
    }

    public function test_a_repriced_bundle_is_no_longer_flagged_as_stale(): void
    {
        $member = $this->items->first();

        $member->price = (float) $member->price + 15;
        $member->save();

        $bundle = Bundle::with('items.item')->find($this->bundle->id);

        $this->assertFalse($bundle->has_stale_pricing,
            'a bundle kept in step is current by definition');
    }

    public function test_an_edit_that_does_not_touch_the_price_leaves_the_bundle_alone(): void
    {
        $member = $this->items->first();
        $before = (float) $this->bundle->base_price;
        $stamp = $this->bundle->updated_at;

        $member->description = 'TC_35 touch '.uniqid();
        $member->save();

        $this->bundle->refresh();

        $this->assertEqualsWithDelta($before, (float) $this->bundle->base_price, 0.01);
        $this->assertEquals($stamp, $this->bundle->updated_at,
            'a description edit must not re-stamp every bundle holding the item');
    }

    /**
     * Selling a variation item writes its variations column -- the per-combination STOCK lives
     * there beside the price -- so "the column changed" is not "the price changed". A sale must
     * not re-price every bundle holding the item.
     */
    public function test_a_variation_stock_change_alone_does_not_reprice_the_bundle(): void
    {
        $member = Item::withoutGlobalScopes()
            ->with('module')
            ->where('store_id', $this->store->id)
            ->whereRaw("COALESCE(variations, '[]') NOT IN ('[]', '')")
            ->where('status', 1)
            ->first();

        if (! $member) {
            $this->markTestSkipped('the fixture store has no item with priced variations');
        }

        $variations = json_decode($member->variations, true);

        $line = $this->bundle->items()->first();
        $line->update([
            'item_id' => $member->id,
            'item_name' => $member->getRawOriginal('name'),
            'variations' => [$variations[0]],
            'unit_price' => $variations[0]['price'],
            'item_price' => $member->price,
        ]);

        app(\App\Services\Promotion\BundleService::class)->repriceBundles([$this->bundle->id]);
        $stamp = $this->bundle->fresh()->updated_at;

        $variations[0]['stock'] = (int) ($variations[0]['stock'] ?? 0) + 7;
        $member->variations = json_encode($variations);
        $member->save();

        $this->assertEquals($stamp, $this->bundle->fresh()->updated_at,
            'a stock movement must not re-stamp every bundle holding the item');
    }

    public function test_changing_a_variation_price_reprices_the_bundle(): void
    {
        $member = Item::withoutGlobalScopes()
            ->with('module')
            ->where('store_id', $this->store->id)
            ->whereRaw("COALESCE(variations, '[]') NOT IN ('[]', '')")
            ->where('status', 1)
            ->first();

        if (! $member) {
            $this->markTestSkipped('the fixture store has no item with priced variations');
        }

        $variations = json_decode($member->variations, true);
        $chosen = $variations[0];

        $line = $this->bundle->items()->first();
        $line->update([
            'item_id' => $member->id,
            'item_name' => $member->getRawOriginal('name'),
            'variations' => [$chosen],
            'unit_price' => $chosen['price'],
            'item_price' => $member->price,
        ]);

        $variations[0]['price'] = (float) $chosen['price'] + 30;
        $member->variations = json_encode($variations);
        $member->save();

        $this->assertEqualsWithDelta(
            (float) $chosen['price'] + 30,
            (float) $this->bundle->items()->whereKey($line->id)->value('unit_price'),
            0.01,
            'a variation price change must reach the line that froze that variation',
        );
    }
}
