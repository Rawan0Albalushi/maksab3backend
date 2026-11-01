<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

/**
 * The two API surfaces: what a customer may read, and the CRUD a vendor drives from the store app.
 *
 * The vendor half shares BundleService and BundleStoreRequest with the panel, so these check the
 * things only the API can get wrong -- the store coming from the token rather than the payload,
 * and the module switch being honoured without route middleware.
 */
class BundleApiTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;

    private ?Vendor $vendor = null;

    private ?Bundle $bundle = null;

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The vendor API authenticates on the token, so the fixture has to be a store whose vendor
        // actually carries one -- most in this dataset do not.
        $this->store = $this->bundleFixtureStore(2, fn ($q) => $q
            ->whereHas('vendor', fn ($v) => $v->whereNotNull('auth_token')));

        if (! $this->store) {
            $this->markTestSkipped('dataset has no bundle-capable store whose vendor holds a token');
        }

        $this->openFixtureStore($this->store);

        $this->vendor = Vendor::find($this->store->vendor_id);

        $this->settings(status: true);
        $this->bundle = $this->makeBundle();
    }

    public function test_a_customer_sees_a_running_bundle_in_the_list(): void
    {
        $body = $this->customer()->getJson('/api/v1/bundle/list')->assertOk()->json();

        $ids = array_column($body['content']['data'] ?? [], 'id');

        $this->assertContains($this->bundle->id, $ids);
    }

    public function test_the_customer_detail_carries_the_items_and_the_price(): void
    {
        $body = $this->customer()->getJson('/api/v1/bundle/'.$this->bundle->id)->assertOk()->json();

        $body = $body['content'];

        $this->assertSame($this->bundle->id, $body['id']);
        $this->assertCount($this->bundle->items->count(), $body['items']);
        $this->assertEqualsWithDelta((float) $this->bundle->discounted_price, $body['final_price'], 0.02);
        $this->assertArrayHasKey('is_available', $body);
    }

    public function test_a_bundle_outside_its_window_is_not_listed(): void
    {
        $this->bundle->forceFill(['start_date' => now()->subMonth(), 'end_date' => now()->subDay()])->save();

        $body = $this->customer()->getJson('/api/v1/bundle/list')->assertOk()->json();

        $this->assertNotContains($this->bundle->id, array_column($body['content']['data'] ?? [], 'id'));
        $this->customer()->getJson('/api/v1/bundle/'.$this->bundle->id)->assertStatus(404);
    }

    public function test_the_customer_surface_closes_when_the_module_is_switched_off(): void
    {
        $this->settings(status: false);

        $body = $this->customer()->getJson('/api/v1/bundle/list')->assertOk()->json();

        $this->assertSame([], $body['content']['data'] ?? []);
    }

    public function test_a_vendor_lists_only_its_own_bundles(): void
    {
        $body = $this->vendorCall()->getJson('/api/v1/vendor/bundle/list')->assertOk()->json();

        $ids = array_column($body['content']['data'] ?? [], 'id');

        $this->assertContains($this->bundle->id, $ids);

        foreach (Bundle::whereIn('id', $ids)->pluck('store_id')->unique() as $storeId) {
            $this->assertSame($this->store->id, $storeId);
        }
    }

    public function test_a_vendor_creates_a_bundle_through_the_api(): void
    {
        $items = $this->items(2);

        $body = $this->vendorCall()->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'],
            'name' => ['Api Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 15,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ], $this->vendorHeaders())->assertSuccessful()->json();

        $this->assertSame('Api Combo', $body['content']['name']);
        $this->assertDatabaseHas('bundles', ['name' => 'Api Combo', 'created_by' => 'vendor', 'store_id' => $this->store->id]);
    }

    public function test_the_api_enforces_the_same_minimum_as_the_panel(): void
    {
        $items = $this->items(1);

        $this->vendorCall()->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'],
            'name' => ['Too Small Api'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id]],
        ], $this->vendorHeaders())->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Too Small Api']);
    }

    public function test_a_vendor_cannot_name_another_store(): void
    {
        $other = Store::withoutGlobalScopes()
            ->where('module_id', $this->store->module_id)
            ->where('id', '!=', $this->store->id)->whereHas('items')->first();

        if (! $other) {
            $this->markTestSkipped('dataset has only one store with items in this module');
        }

        $items = Item::withoutGlobalScopes()->where('store_id', $other->id)->take(2)->get();

        if ($items->count() < 2) {
            $this->markTestSkipped('the other store has too few items');
        }

        $this->vendorCall()->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'],
            'name' => ['Not Mine Api'],
            'store_id' => $other->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ], $this->vendorHeaders())->assertStatus(403);

        $this->assertDatabaseMissing('bundles', ['name' => 'Not Mine Api']);
    }

    public function test_a_vendor_cannot_reach_another_stores_bundle(): void
    {
        $other = Store::withoutGlobalScopes()
            ->where('module_id', $this->store->module_id)
            ->where('id', '!=', $this->store->id)->first();

        if (! $other) {
            $this->markTestSkipped('dataset has only one store in this module');
        }

        $foreign = Bundle::create([
            'store_id' => $other->id,
            'module_id' => $other->module_id,
            'name' => 'Foreign Api Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
        ]);

        $this->vendorCall()->getJson('/api/v1/vendor/bundle/details/'.$foreign->id, $this->vendorHeaders())->assertStatus(404);
        $this->vendorCall()->deleteJson('/api/v1/vendor/bundle/delete/'.$foreign->id, [], $this->vendorHeaders())->assertStatus(404);

        $this->assertDatabaseHas('bundles', ['id' => $foreign->id, 'deleted_at' => null]);
    }

    public function test_the_vendor_status_endpoint_toggles_and_clears_carts(): void
    {
        $this->vendorCall()->post('/api/v1/vendor/bundle/status/'.$this->bundle->id, ['status' => 0], $this->vendorHeaders())
            ->assertSuccessful();

        $this->assertSame(0, (int) $this->bundle->fresh()->status);
    }

    public function test_the_vendor_api_closes_when_the_module_is_switched_off(): void
    {
        $this->settings(status: false);

        $this->vendorCall()->getJson('/api/v1/vendor/bundle/list', $this->vendorHeaders())->assertStatus(404);
    }

    public function test_the_items_endpoint_serves_the_vendors_own_menu(): void
    {
        $body = $this->vendorCall()->getJson('/api/v1/vendor/bundle/items', $this->vendorHeaders())->assertOk()->json();

        $ownIds = Item::withoutGlobalScopes()->where('store_id', $this->store->id)->pluck('id')->all();

        foreach (array_column($body['content']['items'] ?? [], 'id') as $id) {
            $this->assertContains($id, $ownIds);
        }
    }

    /**
     * The cart over HTTP, not just through the service.
     *
     * Phase 5 was covered at the service level only, which is exactly why a null image_full_url --
     * Helpers::get_full_url() answers null on api/* requests -- reached this far unnoticed.
     */
    public function test_a_bundle_can_be_added_to_the_cart_and_listed_over_http(): void
    {
        // As a guest: APIGuestMiddleware takes either a bearer token or a guest_id, and a guest
        // exercises the same cart code without minting a token.
        $guestId = DB::table('guests')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $added = $this->withHeaders($this->headers())
            ->postJson('/api/v1/customer/cart/bundle/add', [
                'bundle_id' => $this->bundle->id,
                'quantity' => 2,
                'guest_id' => $guestId,
            ])->assertSuccessful()->json();

        $this->assertNotEmpty($added['content']['bundle_group_id']);

        $list = $this->withHeaders($this->headers())
            ->getJson('/api/v1/customer/cart/list?guest_id='.$guestId.'&store_id='.$this->store->id)->assertOk()->json();

        $entries = collect($list['content']['data'] ?? $list['content'] ?? [])
            ->filter(fn ($row) => ! empty($row['bundle_details']));

        $this->assertCount(1, $entries, 'a bundle of many items is one cart entry');
        $this->assertSame(2, $entries->first()['bundle_details']['quantity']);
    }

    /**
     * Every bundle surface, over HTTP, asserting nothing 5xx's.
     *
     * The image accessor bug was a 500 that only appeared on api/* requests, which no service-level
     * test could see. This walks the whole surface so the next one cannot hide either.
     */
    public function test_no_bundle_endpoint_returns_a_server_error(): void
    {
        $guestId = DB::table('guests')->insertGetId(['created_at' => now(), 'updated_at' => now()]);

        $this->withHeaders($this->headers())->postJson('/api/v1/customer/cart/bundle/add', [
            'bundle_id' => $this->bundle->id,
            'quantity' => 1,
            'guest_id' => $guestId,
        ])->assertSuccessful();

        $group = DB::table('carts')->where('is_guest', 1)->where('user_id', $guestId)
            ->value('bundle_group_id');

        $probes = [
            ['GET', '/api/v1/bundle/home', []],
            ['GET', '/api/v1/bundle/list', []],
            ['GET', '/api/v1/bundle/list?search=combo', []],
            ['GET', '/api/v1/bundle/store-bundles?store_id='.$this->store->id, []],
            ['GET', '/api/v1/bundle/'.$this->bundle->id, []],
            ['GET', '/api/v1/bundle/999999999', []],
            ['GET', '/api/v1/customer/cart/list?guest_id='.$guestId.'&store_id='.$this->store->id, []],
            ['GET', '/api/v1/customer/cart/get-all?guest_id='.$guestId, []],
            ['GET', '/api/v1/vendor/bundle/list', $this->vendorHeaders()],
            ['GET', '/api/v1/vendor/bundle/list?search=fixture', $this->vendorHeaders()],
            ['GET', '/api/v1/vendor/bundle/items', $this->vendorHeaders()],
            ['GET', '/api/v1/vendor/bundle/details/'.$this->bundle->id, $this->vendorHeaders()],
            ['GET', '/api/v1/vendor/bundle/details/999999999', $this->vendorHeaders()],
        ];

        foreach ($probes as [$verb, $url, $headers]) {
            $response = $this->withHeaders($headers ?: $this->headers())->json($verb, $url);

            $this->assertLessThan(500, $response->getStatusCode(),
                "{$verb} {$url} returned {$response->getStatusCode()}");
        }

        // The cart verbs that carry a group id.
        $this->withHeaders($this->headers())->postJson('/api/v1/customer/cart/bundle/update', [
            'bundle_group_id' => $group, 'quantity' => 3, 'guest_id' => $guestId,
        ])->assertStatus(200);

        $this->withHeaders($this->headers())->json('DELETE', '/api/v1/customer/cart/bundle/remove', [
            'bundle_group_id' => $group, 'guest_id' => $guestId,
        ])->assertSuccessful();

        DB::table('carts')->where('user_id', $guestId)->where('is_guest', 1)->delete();
    }

    private function customer()
    {
        $user = User::orderBy('id')->first();

        return $this->actingAs($user, 'api')->withHeaders($this->headers());
    }

    private function vendorCall()
    {
        return $this->withHeaders($this->vendorHeaders());
    }

    private function vendorHeaders(): array
    {
        return $this->headers() + [
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'vendorType' => 'owner',
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

    private function items(int $count)
    {
        $items = $this->sellableFixtureItems($this->store, $count);

        if ($items->count() < $count) {
            $this->markTestSkipped('the fixture store has too few sellable items');
        }

        return $items->values();
    }

    private function makeBundle(): Bundle
    {
        $items = $this->items(2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Api Fixture Bundle',
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

    private function settings(bool $status): void
    {
        $type = $this->store->module?->module_type;
        $map = [];

        foreach (BundleSettings::moduleTypes() as $t) {
            $map[$t] = ($status && $t === $type) ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => $status ? 1 : 0]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }
}
