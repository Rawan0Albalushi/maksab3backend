<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\OrderDetail;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\Promotion\BogoOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The order editor's BOGO rules.
 *
 * A bundle is an offer, not a product: its items, variations and add-ons were frozen when the store
 * enrolled, and only the whole group's quantity may move. An unguarded editor is how a free item
 * gets separated from its bundle and the store eats the cost -- and every one of these failures is
 * silent, because the resulting order still saves and still prices.
 */
class BogoOrderEditGuardTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * N bundles is N x each enrolled quantity, on the free line as much as the bought one.
     *
     * The unit PRICE must not move with it. StackFood's editor cart holds a line total, so its
     * version rescaled price too; here order_details.price is a unit price and the line total is
     * derived downstream, so scaling it as well would charge the bundle by quantity squared.
     */
    public function test_scaling_a_bundle_moves_every_line_and_leaves_the_unit_price_alone(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 4, 'price' => 50.0, 'bogo_group_id' => 'g1', 'bogo_unit_quantity' => 2]),
            $this->row(['item_id' => 11, 'quantity' => 2, 'price' => 0.0, 'bogo_group_id' => 'g1', 'bogo_unit_quantity' => 1]),
            $this->row(['item_id' => 12, 'quantity' => 3, 'price' => 20.0]),
        ]);

        $out = app(BogoOrderService::class)->scaleEditorBundle($cart, 'g1', 3);

        $this->assertSame(6, (int) $out[0]->quantity, 'buy line scaled to 3 bundles');
        $this->assertSame(3, (int) $out[1]->quantity, 'free line scaled with it');
        $this->assertSame(50.0, (float) $out[0]->price, 'unit price untouched');
        $this->assertSame(0.0, (float) $out[1]->price, 'a free line stays free');
        $this->assertSame(3, (int) $out[0]->bogo_bundle_quantity);

        $this->assertSame(3, (int) $out[2]->quantity, 'an ordinary line is not in the group');
    }

    /** A group is never scaled below one whole bundle -- half a bundle is not expressible. */
    public function test_a_bundle_cannot_be_scaled_below_one(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 4, 'bogo_group_id' => 'g1', 'bogo_unit_quantity' => 2]),
        ]);

        $out = app(BogoOrderService::class)->scaleEditorBundle($cart, 'g1', 0);

        $this->assertSame(2, (int) $out[0]->quantity);
        $this->assertSame(1, (int) $out[0]->bogo_bundle_quantity);
    }

    /**
     * The session cart holds models today, but add_to_cart builds rows as arrays. A plain foreach
     * would have mutated only a copy of an array row and silently dropped the change.
     */
    public function test_array_rows_are_scaled_too(): void
    {
        $cart = collect([
            ['item_id' => 10, 'quantity' => 4, 'price' => 50.0, 'bogo_group_id' => 'g1', 'bogo_unit_quantity' => 2],
        ]);

        $out = app(BogoOrderService::class)->scaleEditorBundle($cart, 'g1', 3);

        $this->assertSame(6, (int) data_get($out[0], 'quantity'));
        $this->assertSame(50.0, (float) data_get($out[0], 'price'));
    }

    /** Only the named group moves; a second bundle on the same order is left alone. */
    public function test_scaling_one_group_does_not_touch_another(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 2, 'bogo_group_id' => 'g1', 'bogo_unit_quantity' => 2]),
            $this->row(['item_id' => 20, 'quantity' => 5, 'bogo_group_id' => 'g2', 'bogo_unit_quantity' => 5]),
        ]);

        $out = app(BogoOrderService::class)->scaleEditorBundle($cart, 'g1', 4);

        $this->assertSame(8, (int) $out[0]->quantity);
        $this->assertSame(5, (int) $out[1]->quantity, 'the other bundle is untouched');
    }

    /**
     * Only what the edit ADDS is judged.
     *
     * The bundles already on the order are already counted in the offer's usage, so judging the new
     * figure whole would count them twice and refuse an edit that fits. Shrinking or leaving a
     * bundle alone has to pass even after the offer has ended -- which is exactly when support
     * usually has to touch the order.
     */
    public function test_an_unchanged_or_smaller_bundle_is_never_refused(): void
    {
        [$offer, $store, $item] = $this->makeEnrolledOffer();

        if (! $offer) {
            $this->markTestSkipped('dataset has no store with an item to enrol');
        }

        // The offer has ended, so a NEW bundle would be refused outright.
        $offer->update(['end_date' => now()->subDay()->toDateString()]);

        $order = (object) [
            'id' => 0, 'user_id' => 1, 'is_guest' => 0, 'order_type' => 'delivery',
            'scheduled' => 0, 'schedule_at' => null, 'delivery_address' => '{}',
        ];

        $rows = [['bogo_group_id' => 'g1', 'bogo_offer_id' => $offer->id, 'item_id' => $item->id, 'quantity' => 2]];
        $before = [$offer->id => 1];

        $service = app(BogoOrderService::class);

        $this->assertNull(
            $service->editRefusalReason($order, $rows, $before, (int) $store->id),
            'a bundle that did not grow must save even after the offer ends'
        );

        $this->assertNull(
            $service->editRefusalReason($order, [], [$offer->id => 1], (int) $store->id),
            'removing a bundle is never refused'
        );
    }

    /** Growing a bundle past what the offer still allows is refused, with a reason. */
    public function test_growing_a_bundle_on_an_ended_offer_is_refused(): void
    {
        [$offer, $store, $item] = $this->makeEnrolledOffer();

        if (! $offer) {
            $this->markTestSkipped('dataset has no store with an item to enrol');
        }

        $offer->update(['end_date' => now()->subDay()->toDateString()]);

        $order = (object) [
            'id' => 0, 'user_id' => 1, 'is_guest' => 0, 'order_type' => 'delivery',
            'scheduled' => 0, 'schedule_at' => null, 'delivery_address' => '{}',
        ];

        // Two bundles now where there was one: the extra copy faces the rules a new bundle faces.
        $rows = [['bogo_group_id' => 'g1', 'bogo_offer_id' => $offer->id, 'item_id' => $item->id, 'quantity' => 4]];

        $reason = app(BogoOrderService::class)
            ->editRefusalReason($order, $rows, [$offer->id => 1], (int) $store->id);

        $this->assertNotNull($reason, 'an added bundle on an ended offer must be refused');
    }

    /**
     * A bundle is one thing the customer chose, so the editor shows it as one row.
     *
     * Listing its members separately invited edits the save then has to refuse, and showed a free
     * member sitting at 0.00 as if it were a mistake.
     */
    public function test_the_editor_folds_a_bundle_into_one_entry(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 4, 'price' => 50.0, 'status' => true, 'bogo_group_id' => 'g1', 'bogo_bundle_quantity' => 2]),
            $this->row(['item_id' => 11, 'quantity' => 2, 'price' => 0.0, 'status' => true, 'bogo_group_id' => 'g1', 'is_free_item' => 1, 'bogo_bundle_quantity' => 2]),
            $this->row(['item_id' => 12, 'quantity' => 3, 'price' => 20.0, 'status' => true]),
        ]);

        $entries = app(BogoOrderService::class)->editorEntries($cart);

        $this->assertCount(2, $entries, 'three cart rows, two things the customer chose');
        $this->assertTrue($entries[0]['is_bogo']);
        $this->assertFalse($entries[1]['is_bogo']);

        // The group's FIRST line, because that is the key the existing quantity and delete
        // handlers already post -- they keep working and now act on the whole group.
        $this->assertSame(0, $entries[0]['key']);
        $this->assertSame(2, $entries[0]['bundles'], 'the quantity shown is bundles, not line items');
        $this->assertSame(200.0, $entries[0]['total'], '4 x 50 paid, the free member contributing nothing');
        $this->assertCount(2, $entries[0]['lines']);
    }

    /** A group whose members were all removed must not come back as an entry. */
    public function test_a_removed_bundle_produces_no_entry(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 4, 'price' => 50.0, 'status' => false, 'bogo_group_id' => 'g1']),
            $this->row(['item_id' => 11, 'quantity' => 2, 'price' => 0.0, 'status' => false, 'bogo_group_id' => 'g1']),
            $this->row(['item_id' => 12, 'quantity' => 3, 'price' => 20.0, 'status' => true]),
        ]);

        $entries = app(BogoOrderService::class)->editorEntries($cart);

        $this->assertCount(1, $entries);
        $this->assertFalse($entries[0]['is_bogo']);
    }

    /** Two bundles on one order stay two rows, each with its own group. */
    public function test_two_bundles_stay_separate_entries(): void
    {
        $cart = collect([
            $this->row(['item_id' => 10, 'quantity' => 2, 'price' => 10.0, 'status' => true, 'bogo_group_id' => 'g1', 'bogo_bundle_quantity' => 1]),
            $this->row(['item_id' => 20, 'quantity' => 5, 'price' => 30.0, 'status' => true, 'bogo_group_id' => 'g2', 'bogo_bundle_quantity' => 1]),
        ]);

        $entries = app(BogoOrderService::class)->editorEntries($cart);

        $this->assertCount(2, $entries);
        $this->assertSame('g1', $entries[0]['group_id']);
        $this->assertSame('g2', $entries[1]['group_id']);
    }

    private function row(array $attributes): OrderDetail
    {
        $detail = new OrderDetail;

        foreach ($attributes as $key => $value) {
            $detail->{$key} = $value;
        }

        return $detail;
    }

    /** @return array{0:?BogoOffer,1:?Store,2:?Item} */
    private function makeEnrolledOffer(): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)->with('store')->first();
        $store = $item?->store;

        if (! $store) {
            return [null, null, null];
        }

        $offer = BogoOffer::create([
            'module_id' => $store->module_id,
            'title' => 'edit guard probe',
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'status' => 1,
        ]);

        $enrollment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $store->id,
            'status' => BogoOfferStore::STATUS_APPROVED,
            'requested_by' => 'store',
            'joined_at' => now(),
        ]);

        BogoOfferItem::create([
            'bogo_offer_store_id' => $enrollment->id,
            'bogo_offer_id' => $offer->id,
            'item_id' => $item->id,
            'type' => 'buy',
            'quantity' => 2,
            'price' => (float) $item->price,
        ]);

        return [$offer, $store, $item];
    }
}
