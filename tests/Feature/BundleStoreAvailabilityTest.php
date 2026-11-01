<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleCustomerService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * TC_33 — a bundle is only sellable while every member is.
 * TC_34 — a bundle is only sellable while its store is trading.
 *
 * Both are asked through the one rule the cart, the checkout and the customer listings all read,
 * so what is advertised and what will be accepted cannot drift apart.
 */
class BundleStoreAvailabilityTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private $bundle = null;

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
        $this->bundle = $this->makeFixtureBundle($this->store, $this->items);
    }

    private function reason(): ?string
    {
        $this->bundle->refresh()->load(['items.item.module', 'store']);

        return app(BundleCustomerService::class)->unavailableReason($this->bundle);
    }

    public function test_a_bundle_of_sellable_items_at_an_open_store_has_no_reason(): void
    {
        $this->assertNull($this->reason(), 'the fixture must start sellable or nothing below means anything');
    }

    /** TC_33 (1) and (4) — out of stock takes it off sale, restocking puts it back. */
    public function test_an_out_of_stock_member_takes_the_bundle_off_sale_until_restocked(): void
    {
        $moduleType = $this->store->module?->module_type;

        if (! config('module.'.$moduleType.'.stock')) {
            $this->markTestSkipped($moduleType.' does not keep stock in this configuration');
        }

        $member = $this->items->first();

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['stock' => 0]);

        $this->assertNotNull($this->reason(), 'a member with no stock must take the bundle off sale');

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['stock' => $member->stock]);

        $this->assertNull($this->reason(), 'restocking must put the bundle back on sale with no edit');
    }

    /** TC_33 (2) and (4) — switched off takes it off sale, switching back on restores it. */
    public function test_an_inactive_member_takes_the_bundle_off_sale_until_switched_back_on(): void
    {
        $member = $this->items->first();

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['status' => 0]);

        $this->assertNotNull($this->reason(), 'a switched-off member must take the bundle off sale');

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['status' => 1]);

        $this->assertNull($this->reason(), 'switching the member back on must restore the bundle');
    }

    /** TC_33 (3) — a deleted member takes it off sale. */
    public function test_a_deleted_member_takes_the_bundle_off_sale(): void
    {
        $member = $this->items->first();

        Item::withoutGlobalScopes()->whereKey($member->id)->delete();

        $this->assertNotNull($this->reason(), 'a deleted member must take the bundle off sale');
    }

    /** TC_34 (4) — the admin deactivated the store. */
    public function test_a_deactivated_store_takes_its_bundles_off_sale(): void
    {
        $this->store->forceFill(['status' => 0])->save();

        $this->assertSame(
            translate('messages.This_store_is_currently_unavailable'),
            $this->reason(),
        );
    }

    /** TC_34 (3) — the vendor's own temporarily-closed toggle. */
    public function test_a_temporarily_closed_store_takes_its_bundles_off_sale(): void
    {
        $this->store->forceFill(['active' => 0])->save();

        $this->assertSame(
            translate('messages.This_store_is_currently_unavailable'),
            $this->reason(),
        );
    }

    /** TC_34 (1) — outside the store's opening hours for today. */
    public function test_a_store_outside_its_opening_hours_takes_its_bundles_off_sale(): void
    {
        $this->store->schedules()->delete();
        $this->store->schedules()->create([
            'day' => now()->dayOfWeek,
            'opening_time' => '00:00:00',
            'closing_time' => '00:00:01',
        ]);
        $this->store->refresh();

        $this->assertNotNull($this->reason(), 'a closed store must not be able to sell its bundles');

        $this->openFixtureStore($this->store);

        $this->assertNull($this->reason(), 'reopening must put the bundles back with no edit');
    }

    /** TC_34 (2) — today is one of the store's off days. */
    public function test_an_off_day_takes_its_bundles_off_sale(): void
    {
        $this->store->forceFill(['off_day' => (string) now()->dayOfWeek])->save();

        $this->assertNotNull($this->reason(), 'an off day must not be able to sell its bundles');

        $this->store->forceFill(['off_day' => ''])->save();

        $this->assertNull($this->reason(), 'clearing the off day must put the bundles back');
    }

    /** TC_33 — the customer API itself reports it, not just the rule behind it. */
    public function test_the_customer_api_reports_the_member_problem(): void
    {
        $member = $this->items->first();
        $headers = [
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
        ];

        $body = $this->withHeaders($headers)->getJson('/api/v1/bundle/'.$this->bundle->id)->json('content');

        $this->assertTrue($body['is_available'], 'the fixture must start available over HTTP too');

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['status' => 0]);

        $body = $this->withHeaders($headers)->getJson('/api/v1/bundle/'.$this->bundle->id)->json('content');

        $this->assertFalse($body['is_available']);
        $this->assertNotNull($body['unavailable_reason'],
            'the customer is told which member took the bundle off sale');

        Item::withoutGlobalScopes()->whereKey($member->id)->update(['status' => 1]);

        $this->assertTrue(
            $this->withHeaders($headers)->getJson('/api/v1/bundle/'.$this->bundle->id)->json('content.is_available'),
            'and it comes back on its own once the member is sellable again',
        );
    }

    /** TC_34 — a closed store's bundles are not listed to the customer at all. */
    public function test_a_closed_store_is_left_out_of_the_customer_listings(): void
    {
        $service = app(BundleCustomerService::class);
        $filters = ['module_id' => $this->store->module_id, 'store_id' => $this->store->id];

        $this->assertContains(
            $this->bundle->id,
            collect($service->getHomeList($filters, 50))->pluck('id')->all(),
            'an open store must list its bundles',
        );

        $this->store->forceFill(['active' => 0])->save();

        $this->assertNotContains(
            $this->bundle->id,
            collect($service->getHomeList($filters, 50))->pluck('id')->all(),
            'a temporarily-closed store must not list its bundles',
        );

        $this->openFixtureStore($this->store);

        $this->assertContains(
            $this->bundle->id,
            collect($service->getHomeList($filters, 50))->pluck('id')->all(),
            'and they appear again when it reopens, with no edit',
        );
    }
}
