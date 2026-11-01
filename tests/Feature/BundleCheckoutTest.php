<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * A bundle all the way through checkout, over HTTP.
 *
 * Phase 6 was covered at the service level, which proves the arithmetic but not that the arithmetic
 * is reached. This places a real order and reads the rows back, because this is where money lands:
 * the line has to be charged its full frozen price so commission is taken pre-discount, and the
 * reduction has to arrive as per-line discount_on_item that sums exactly to the bundle's.
 */
class BundleCheckoutTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    private int $guestId = 0;

    protected function tearDown(): void
    {
        if ($this->guestId) {
            DB::table('carts')->where('user_id', $this->guestId)->where('is_guest', 1)->delete();
        }

        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Placement resolves the store through findWithOpenStateAt(), so the fixture has to be one
        // that is actually open right now -- a closed store is refused before any bundle code runs.
        $this->store = $this->bundleFixtureStore(2, fn ($q) => $q->whereNotNull('zone_id'));

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store in a zone with sellable items');
        }

        // Placement refuses a store that is shut at order time, and whether the seeded schedule
        // happens to cover the moment the suite runs is not what these tests are about -- run at
        // 2am they failed on "Store is closed at order time". A schedule spanning today is added
        // inside the transaction so the outcome does not depend on the clock.
        DB::table('store_schedule')->insert([
            'store_id' => $this->store->id,
            'day' => (int) now()->format('w'),
            'opening_time' => '00:00:01',
            'closing_time' => '23:59:59',
        ]);

        $this->settings();
        $this->bundle = $this->makeBundle();
        $this->guestId = (int) DB::table('guests')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
    }

    public function test_an_order_carrying_a_bundle_is_placed_and_settles_correctly(): void
    {
        $this->addBundleToCart();

        $before = Order::max('id') ?? 0;

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/customer/order/place', $this->orderPayload());

        $this->assertLessThan(500, $response->getStatusCode(),
            'placement must not fatal: '.substr((string) $response->getContent(), 0, 400));

        $this->assertTrue($response->isSuccessful(),
            'placement refused: '.substr((string) $response->getContent(), 0, 600));

        $order = Order::where('id', '>', $before)->latest('id')->first();

        $this->assertNotNull($order, 'the order row must exist');

        $details = DB::table('order_details')->where('order_id', $order->id)->get();
        $bundleLines = $details->filter(fn ($d) => $d->bundle_group_id !== null);

        $this->assertCount($this->bundle->items->count(), $bundleLines,
            'one order line per bundle member, each tagged with the group');

        foreach ($bundleLines as $line) {
            $this->assertSame($this->bundle->id, (int) $line->bundle_id);

            $frozen = $this->bundle->items->firstWhere('item_id', $line->item_id);

            $this->assertEqualsWithDelta((float) $frozen->unit_price, (float) $line->price, 0.02,
                'the line is charged its FULL frozen price so commission is taken pre-discount');
        }

        $lineDiscount = $bundleLines->sum(fn ($l) => (float) $l->discount_on_item * (int) $l->quantity);
        $expected = (float) $this->bundle->base_price - (float) $this->bundle->discounted_price;

        $this->assertEqualsWithDelta($expected, $lineDiscount, 0.02,
            'the per-line shares must sum to the bundle reduction');

        // Exact, not approximate: what the order books has to be what the stored lines add up to,
        // or the settlement and the invoice disagree by a cent that nothing can explain. A delta
        // here is what let a rounding gap hide.
        $this->assertSame(round($lineDiscount, 2), (float) $order->bundle_discount_amount,
            'the order must book exactly the sum of its stored line discounts');

        $this->assertGreaterThanOrEqual($lineDiscount - 0.02, (float) $order->store_discount_amount,
            'store_discount_amount must carry the bundle reduction, or nothing books it');

        $this->assertSame('vendor', $order->discount_on_product_by,
            'a bundle reduction is vendor-borne and split with the admin by commission');
    }

    public function test_placement_is_refused_while_the_bundle_is_off(): void
    {
        $this->addBundleToCart();

        $this->bundle->forceFill(['status' => 0])->save();

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/customer/order/place', $this->orderPayload());

        $this->assertLessThan(500, $response->getStatusCode());
        $this->assertFalse($response->isSuccessful(), 'a switched-off bundle must not reach an order');
    }

    /**
     * The placement rules make latitude/longitude and distance optional for take_away, so a payload
     * that omits them must work. Both were refused before: the store was resolved only inside an
     * `if (latitude && longitude)` guard, and orders.distance is NOT NULL while the request passed
     * null explicitly, defeating the column default.
     */
    public function test_a_take_away_order_places_without_coordinates_or_distance(): void
    {
        $this->addBundleToCart();

        $payload = $this->orderPayload();
        unset($payload['latitude'], $payload['longitude'], $payload['distance']);

        $response = $this->withHeaders($this->headers())->postJson('/api/v1/customer/order/place', $payload);

        $this->assertTrue($response->isSuccessful(),
            'a take-away payload following the documented rules must place: '
            .substr((string) $response->getContent(), 0, 400));
    }

    private function addBundleToCart(): void
    {
        $this->withHeaders($this->headers())->postJson('/api/v1/customer/cart/bundle/add', [
            'bundle_id' => $this->bundle->id,
            'quantity' => 1,
            'guest_id' => $this->guestId,
        ])->assertSuccessful();
    }

    private function orderPayload(): array
    {
        return [
            'payment_method' => 'cash_on_delivery',
            'order_type' => 'take_away',
            'store_id' => $this->store->id,
            'guest_id' => $this->guestId,
            'contact_person_name' => 'Checkout Probe',
            'contact_person_number' => '+8801812345678',
            'contact_person_email' => 'checkout.probe@example.com',
            // getZoneAndStore() resolves the store only inside an `if (latitude && longitude)`
            // guard, so a take-away order still has to carry coordinates even though the
            // validation rules make them optional for it.
            // orders.distance is NOT NULL while the rules make it optional for take_away, so a
            // payload without it is refused at the insert. Pre-existing, unrelated to bundles.
            'distance' => 0,
            'latitude' => $this->store->latitude,
            'longitude' => $this->store->longitude,
        ];
    }

    private function headers(): array
    {
        return [
            'moduleId' => (string) $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
            'Accept' => 'application/json',
        ];
    }

    private function makeBundle(): Bundle
    {
        $items = $this->sellableFixtureItems($this->store, 2);

        if ($items->count() < 2) {
            $this->markTestSkipped('the fixture store has too few active items');
        }

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Checkout Fixture Bundle',
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

        $bundle->forceFill(app(BundleService::class)->prices($base, 10))->save();

        return $bundle->fresh('items');
    }

    private function settings(): void
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
}
