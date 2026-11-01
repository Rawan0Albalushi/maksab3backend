<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleCartService;
use App\Services\Promotion\BundleGroupPresenter;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * A bundle in the cart is N rows sharing a bundle_group_id, folded back into one entry only for
 * display. These cover the group staying atomic through every verb, the availability rules that
 * take a whole bundle off sale, and the pricing rule that a happy hour replaces the bundle
 * discount rather than stacking with it.
 */
class BundleCartTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private const USER_ID = 987654;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private BundleCartService $service;

    protected function tearDown(): void
    {
        Cart::where('user_id', self::USER_ID)->forceDelete();
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BundleCartService::class);

        $this->store = $this->bundleFixtureStore(3);

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store with three sellable items');
        }

        $this->openFixtureStore($this->store);
        $this->enable();
        $this->bundle = $this->makeBundle();
        Cart::where('user_id', self::USER_ID)->forceDelete();
    }

    public function test_adding_writes_one_row_per_member_sharing_a_group(): void
    {
        $result = $this->service->addBundle($this->payload());

        $this->assertSame(200, $result['status_code']);
        $this->assertNotEmpty($result['bundle_group_id']);

        $rows = $this->rows();

        $this->assertCount($this->bundle->items->count(), $rows);
        $this->assertCount(1, $rows->pluck('bundle_group_id')->unique(), 'one group per add');
        $this->assertSame([$this->bundle->id], $rows->pluck('bundle_id')->unique()->all());
    }

    public function test_each_row_carries_the_frozen_price_not_a_discounted_share(): void
    {
        $this->service->addBundle($this->payload());

        foreach ($this->rows() as $row) {
            $line = $this->bundle->items->firstWhere('item_id', $row->item_id);

            $this->assertEqualsWithDelta((float) $line->unit_price, (float) $row->price, 0.001,
                'the bundle discount is a property of the group, applied at placement');
        }
    }

    public function test_quantity_multiplies_every_member(): void
    {
        $this->service->addBundle($this->payload(quantity: 3));

        foreach ($this->rows() as $row) {
            $line = $this->bundle->items->firstWhere('item_id', $row->item_id);

            $this->assertSame(max(1, (int) $line->quantity) * 3, (int) $row->quantity);
        }
    }

    public function test_a_second_add_is_its_own_group(): void
    {
        $first = $this->service->addBundle($this->payload());
        $second = $this->service->addBundle($this->payload());

        $this->assertSame(200, $second['status_code']);
        $this->assertNotSame($first['bundle_group_id'], $second['bundle_group_id']);
        $this->assertCount(2, $this->rows()->pluck('bundle_group_id')->unique());
    }

    public function test_update_rewrites_the_group_at_the_new_quantity(): void
    {
        $added = $this->service->addBundle($this->payload());

        $result = $this->service->updateBundle($this->payload(quantity: 4) + [
            'bundle_group_id' => $added['bundle_group_id'],
        ]);

        $this->assertSame(200, $result['status_code']);
        $this->assertCount($this->bundle->items->count(), $this->rows(), 'rewritten, not appended to');

        foreach ($this->rows() as $row) {
            $line = $this->bundle->items->firstWhere('item_id', $row->item_id);
            $this->assertSame(max(1, (int) $line->quantity) * 4, (int) $row->quantity);
        }
    }

    public function test_remove_takes_the_whole_group(): void
    {
        $added = $this->service->addBundle($this->payload());

        $this->assertSame(200, $this->service->removeBundle([
            'user_id' => self::USER_ID,
            'is_guest' => 0,
            'bundle_group_id' => $added['bundle_group_id'],
        ])['status_code']);

        $this->assertCount(0, $this->rows());
    }

    public function test_a_member_row_is_not_editable_on_its_own(): void
    {
        $this->service->addBundle($this->payload());

        $this->assertTrue($this->service->belongsToBundle($this->rows()->first()));
        $this->assertFalse($this->service->belongsToBundle(new Cart));
    }

    public function test_a_switched_off_bundle_cannot_be_added(): void
    {
        $this->bundle->forceFill(['status' => 0])->save();

        $result = $this->service->addBundle($this->payload());

        $this->assertSame(403, $result['status_code']);
        $this->assertCount(0, $this->rows());
    }

    public function test_an_ended_bundle_cannot_be_added(): void
    {
        $this->bundle->forceFill(['start_date' => now()->subMonth(), 'end_date' => now()->subDay()])->save();

        $this->assertSame(403, $this->service->addBundle($this->payload())['status_code']);
    }

    public function test_a_bundle_in_a_module_the_admin_switched_off_cannot_be_added(): void
    {
        $this->disable();

        $this->assertSame(403, $this->service->addBundle($this->payload())['status_code']);
    }

    public function test_a_member_that_left_the_menu_takes_the_bundle_off_sale(): void
    {
        $line = $this->bundle->items->first();
        Item::withoutGlobalScopes()->whereKey($line->item_id)->update(['status' => 0]);

        $this->assertSame(403, $this->service->addBundle($this->payload())['status_code']);

        Item::withoutGlobalScopes()->whereKey($line->item_id)->update(['status' => 1]);
    }

    /**
     * Editing or withdrawing a bundle STRANDS the carts holding it. It must not empty them.
     *
     * Deleting those rows ran from the admin's or vendor's request, so a customer whose only cart
     * item was this bundle had their cart emptied by somebody else's edit, and Place Order then
     * reported an empty cart rather than a withdrawn bundle. The rows stay, the group is shown
     * unavailable, and checkout refuses it by name -- that refusal is what makes keeping it safe.
     */
    public function test_withdrawing_a_bundle_strands_the_carts_holding_it(): void
    {
        $this->service->addBundle($this->payload());
        $before = $this->rows()->count();
        $this->assertGreaterThan(0, $before);

        $this->assertSame($before, $this->service->strandCarts($this->bundle),
            'strandCarts reports the rows it left behind, it does not remove them');

        app(\App\Services\Promotion\BundleService::class)->setStatus($this->bundle, 0);

        $this->assertCount($before, $this->rows(), 'the rows must survive a deactivation');

        $entry = app(BundleGroupPresenter::class)->present($this->rows(), self::USER_ID, 0)[0];
        $this->assertFalse($entry->bundle_details['is_available']);
        $this->assertNotNull($entry->bundle_details['unavailable_reason'],
            'the customer has to be told why it is sitting there');

        $this->assertNotNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id),
            'a stranded bundle must still be refused at checkout',
        );
    }

    /**
     * A member swap is detected by identity, so the stale group cannot be ordered.
     *
     * The cart rows freeze the member set at add time. Once the vendor swaps a member out, those
     * rows describe one bundle while the record describes another -- and the two price
     * differently. Before this, the rows were simply deleted; now they are refused.
     */
    public function test_swapping_a_member_refuses_the_group_already_in_the_cart(): void
    {
        $this->service->addBundle($this->payload());

        $replacement = $this->sellableFixtureItems($this->store, 3)
            ->whereNotIn('id', $this->bundle->items->pluck('item_id')->all())
            ->first();

        if (! $replacement) {
            $this->markTestSkipped('the fixture store has no third sellable item to swap in');
        }

        $this->bundle->items()->first()->update(['item_id' => $replacement->id]);
        $this->bundle->load('items');

        $reason = app(\App\Services\Promotion\BundleOrderService::class)
            ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id);

        $this->assertSame(translate('messages.The added bundle has changed. Please add it again.'), $reason);
    }

    /**
     * A member QUANTITY edit is a changed bundle too.
     *
     * Cart rows hold the member quantity times the copy count, so they cannot be compared to the
     * bundle's quantities directly -- they have to divide evenly. Two copies of a 1+1 bundle leave
     * rows of 2 and 2; once a member goes to 3 those rows are no longer a whole number of copies,
     * and charging them would bill the old quantities at the new bundle's price.
     */
    public function test_changing_a_member_quantity_refuses_the_group_already_in_the_cart(): void
    {
        $this->service->addBundle($this->payload(quantity: 2));

        $this->bundle->items()->first()->update(['quantity' => 3]);
        $this->bundle->load('items');

        $this->assertSame(
            translate('messages.The added bundle has changed. Please add it again.'),
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id),
        );
    }

    /**
     * Scaling every member by the same factor is NOT a changed bundle.
     *
     * Two copies of a 1+1 bundle are rows of 2 and 2, which is exactly one copy of a 2+2 bundle.
     * The cart genuinely holds a whole number of the new thing, so refusing it would be wrong.
     */
    public function test_scaling_the_whole_bundle_leaves_the_cart_orderable(): void
    {
        $this->service->addBundle($this->payload(quantity: 2));

        $this->bundle->items()->update(['quantity' => 2]);
        $this->bundle->load('items');

        $this->assertNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id),
            'the rows are a whole number of copies of the new definition',
        );
    }

    /**
     * A description edit is NOT a changed bundle.
     *
     * syncItems() deletes and recreates every member row on every save, so comparing row ids
     * would flag a typo fix as a member swap and refuse a cart that is perfectly orderable.
     */
    public function test_an_edit_that_leaves_the_members_alone_stays_orderable(): void
    {
        $this->service->addBundle($this->payload());

        $this->assertNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id),
            'the members are untouched, so nothing should block checkout',
        );

        $rebuilt = $this->bundle->items->map(fn ($line) => [
            'item_id' => $line->item_id,
            'unit_price' => $line->unit_price,
            'quantity' => $line->quantity,
        ])->all();

        app(\App\Services\Promotion\BundleService::class)->syncItems($this->bundle, $rebuilt);
        $this->bundle->load('items');

        $this->assertNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => self::USER_ID, 'is_guest' => 0], (int) $this->store->id),
            'recreated member rows with the same members must not read as a changed bundle',
        );
    }

    /**
     * There is exactly ONE pricing method, and every surface reads it.
     *
     * A second one that read bundles.discounted_price directly used to live on the cart service; it
     * ignored happy hours, so the cart and the listing would have disagreed during a window.
     */
    public function test_only_the_shared_pricing_method_prices_a_group(): void
    {
        $this->service->addBundle($this->payload(quantity: 2));

        $this->assertFalse(method_exists($this->service, 'groupPricing'),
            'a second pricing path must not come back');

        $entry = app(BundleGroupPresenter::class)->present($this->rows(), self::USER_ID, 0)[0];

        $this->assertSame(2, $entry->bundle_details['quantity']);
        $this->assertEqualsWithDelta((float) $this->bundle->base_price, $entry->bundle_details['base_price'], 0.02);
        $this->assertEqualsWithDelta(
            (float) $this->bundle->discounted_price * 2,
            $entry->bundle_details['total_final_price'],
            0.02
        );
    }

    /** §8.1: a happy hour outranks the bundle discount rather than stacking with it. */
    public function test_a_happy_hour_replaces_the_bundle_discount(): void
    {
        $presenter = app(BundleGroupPresenter::class);
        $base = (float) $this->bundle->base_price;

        $withoutHappyHour = $presenter->bundlePricing($this->bundle);
        $this->assertFalse($withoutHappyHour['is_happy_hour']);
        $this->assertEqualsWithDelta((float) $this->bundle->discounted_price, $withoutHappyHour['final_price'], 0.02);

        $withHappyHour = $presenter->bundlePricing($this->bundle, 20.0);
        $this->assertTrue($withHappyHour['is_happy_hour']);
        $this->assertEqualsWithDelta(round($base * 0.8, 2), $withHappyHour['final_price'], 0.02,
            'the happy hour figure, not the bundle discount stacked on top of it');
        $this->assertSame(20.0, $withHappyHour['discount_percentage']);
    }

    public function test_the_presenter_folds_a_group_into_one_entry(): void
    {
        $this->service->addBundle($this->payload(quantity: 2));

        $entries = app(BundleGroupPresenter::class)->present($this->rows(), self::USER_ID, 0);

        $this->assertCount(1, $entries, 'a bundle of many items is one cart entry');

        $entry = $entries[0];

        $this->assertNull($entry->item_id, 'a bundle is not one item');
        $this->assertSame(2, $entry->quantity);
        $this->assertSame($this->bundle->id, $entry->bundle_details['bundle_id']);
        $this->assertTrue($entry->bundle_details['is_available']);
        $this->assertCount($this->bundle->items->count(), $entry->bundle_details['items']);
    }

    private function rows()
    {
        return Cart::where('user_id', self::USER_ID)->get();
    }

    private function payload(int $quantity = 1): array
    {
        return [
            'bundle_id' => $this->bundle->id,
            'user_id' => self::USER_ID,
            'is_guest' => 0,
            'module_id' => $this->store->module_id,
            'quantity' => $quantity,
        ];
    }

    private function makeBundle(): Bundle
    {
        $items = $this->sellableFixtureItems($this->store, 2);

        if ($items->count() < 2) {
            $this->markTestSkipped('the fixture store has too few sellable items');
        }

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Cart Fixture Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        $base = 0.0;

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
            ]);
            $base += (float) $item->price;
        }

        $bundle->forceFill(app(\App\Services\Promotion\BundleService::class)->prices($base, 10))->save();

        return $bundle->fresh('items');
    }

    private function enable(): void
    {
        $type = $this->store->module?->module_type;
        $map = [];

        foreach (BundleSettings::moduleTypes() as $t) {
            $map[$t] = $t === $type ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    private function disable(): void
    {
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 0]);
        Helpers::clearBusinessSettingsCache();
    }
}
