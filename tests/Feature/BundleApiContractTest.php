<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Cart;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Store;
use App\Models\Vendor;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsBundleFixtures;
use Tests\TestCase;

class BundleApiContractTest extends TestCase
{
    use BuildsBundleFixtures, DatabaseTransactions;

    private ?Store $store = null;
    private ?Vendor $vendor = null;
    private ?Bundle $bundle = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->bundleFixtureStore(2, fn ($q) => $q
            ->whereHas('vendor', fn ($v) => $v->whereNotNull('auth_token')));

        if (! $this->store) {
            $this->markTestSkipped('no bundle-capable store with two sellable items and a tokened vendor');
        }

        $this->openFixtureStore($this->store);

        $this->vendor = Vendor::find($this->store->vendor_id);
        $this->settings();
        $this->bundle = $this->makeBundle('Family Feast Box');
    }

    public function test_offset_is_a_page_number_not_a_row_offset(): void
    {
        $second = $this->makeBundle('Second Doc Bundle');

        $p1 = $this->guest()->getJson('/api/v1/bundle/list?limit=1&offset=1')->assertOk()->json('content');
        $p2 = $this->guest()->getJson('/api/v1/bundle/list?limit=1&offset=2')->assertOk()->json('content');

        $this->assertSame(1, $p1['pagination']['current_page']);
        $this->assertSame(2, $p2['pagination']['current_page']);
        $this->assertSame(1, $p1['pagination']['per_page']);
        $this->assertNotSame($p1['data'][0]['id'], $p2['data'][0]['id'] ?? null);
        $this->assertContains($second->id, [$p1['data'][0]['id'], $p2['data'][0]['id'] ?? null]);
    }

    public function test_search_and_store_id_filter_the_customer_list_and_home(): void
    {
        $this->makeBundle('Totally Different Name');

        $hit = $this->guest()->getJson('/api/v1/bundle/list?search=Family+Feast')->assertOk()->json('content.data');
        $this->assertSame([$this->bundle->id], array_column($hit, 'id'));

        $miss = $this->guest()->getJson('/api/v1/bundle/list?search=zzz-no-such-bundle')->assertOk()->json('content.data');
        $this->assertSame([], $miss);

        $byStore = $this->guest()->getJson('/api/v1/bundle/list?store_id='.$this->store->id)->assertOk()->json('content.data');
        $this->assertNotEmpty($byStore);
        foreach ($byStore as $row) {
            $this->assertSame($this->store->id, $row['store_id']);
        }

        $home = $this->guest()->getJson('/api/v1/bundle/home?search=Family+Feast')->assertOk()->json('content.data');
        $this->assertSame([$this->bundle->id], array_column($home, 'id'));
        $this->assertArrayNotHasKey('pagination', $this->guest()->getJson('/api/v1/bundle/home')->json('content'));
    }

    public function test_the_vendor_picker_search_filters(): void
    {
        $item = $this->items(1)[0];

        $all = $this->vendorCall()->getJson('/api/v1/vendor/bundle/items')->assertOk()->json('content.items');

        $raw = $this->vendorCall()->getJson('/api/v1/vendor/bundle/items?search='.urlencode($item->getRawOriginal('name')))
            ->assertOk()->json('content.items');

        $this->assertLessThanOrEqual(count($all), count($raw));
        $this->assertContains($item->id, array_column($raw, 'id'), 'search matches the stored name');

        // updateOrCreate, not create: the seeded dataset already carries a name translation for
        // this locale, and translatedAttribute() returns the FIRST row matching (key, locale). A
        // second row therefore changed nothing -- the resolver kept answering with the original,
        // and the assertion below read as "translations are ignored" when what had actually
        // happened is that the fixture never took effect.
        \App\Models\Translation::updateOrCreate(
            [
                'translationable_type' => \App\Models\Item::class,
                'translationable_id' => $item->id,
                'locale' => app()->getLocale(),
                'key' => 'name',
            ],
            ['value' => 'Renamed For This Locale'],
        );

        $translated = $this->vendorCall()->getJson('/api/v1/vendor/bundle/items')->assertOk()->json('content.items');
        $row = collect($translated)->firstWhere('id', $item->id);

        $this->assertSame('Renamed For This Locale', $row['name'],
            'the payload shows the current locales translation');

        $byShownName = $this->vendorCall()->getJson('/api/v1/vendor/bundle/items?search=Renamed+For+This+Locale')
            ->assertOk()->json('content.items');

        $this->assertNotContains($item->id, array_column($byShownName, 'id'),
            'but search runs on the stored column, so the displayed name finds nothing');
        $this->assertArrayHasKey('uses_food_variations', $raw[0]);
        $this->assertArrayHasKey('is_available', $raw[0]);
    }

    public function test_the_vendor_list_carries_its_items(): void
    {
        $rows = $this->vendorCall()->getJson('/api/v1/vendor/bundle/list')->assertOk()->json('content.data');

        $row = collect($rows)->firstWhere('id', $this->bundle->id);

        $this->assertNotNull($row, 'the fixture bundle must reach the list');
        $this->assertIsArray($row['items'], 'a list row has to name its members, not send null');
        $this->assertCount($this->bundle->items->count(), $row['items']);

        foreach (['item_id', 'name', 'image_full_url', 'unit_price', 'variation_summary'] as $key) {
            $this->assertArrayHasKey($key, $row['items'][0], "a list item is missing {$key}");
        }

        $detail = $this->vendorCall()->getJson('/api/v1/vendor/bundle/details/'.$this->bundle->id)
            ->assertOk()->json('content');

        $this->assertSame($detail['items'], $row['items'],
            'a list row and the detail describe the same members the same way');
    }

    public function test_the_browse_routes_answer_to_the_bundle_switch_not_the_promotion_gate(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/bundle')) {
                continue;
            }

            $this->assertNotContains(
                \App\Http\Middleware\PromotionModuleCheckMiddleware::class,
                $route->gatherMiddleware(),
                $route->uri().' must be gated by BundleSettings alone, as the panel and the cart are',
            );
        }
    }

    public function test_a_create_for_another_store_is_403(): void
    {
        $other = Store::withoutGlobalScopes()->where('id', '!=', $this->store->id)->first();
        $items = $this->items(2);

        $response = $this->vendorCall()->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default'], 'name' => ['Cross Store'],
            'store_id' => $other->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
            'image' => UploadedFile::fake()->image('x.png'),
        ], ['Accept' => 'application/json']);

        $this->assertSame(403, $response->getStatusCode());
    }

    public function test_an_update_cannot_move_a_bundle_to_another_store(): void
    {
        $other = Store::withoutGlobalScopes()->where('id', '!=', $this->store->id)->first();
        $items = $this->items(2);

        $response = $this->vendorCall()->post('/api/v1/vendor/bundle/update/'.$this->bundle->id, [
            'lang' => ['default'], 'name' => ['Moved'],
            'store_id' => $other->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ], ['Accept' => 'application/json']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('store_id', $response->json('errors.0.code'));
    }

    public function test_a_second_locale_is_stored_and_served(): void
    {
        $items = $this->items(2);

        $created = $this->vendorCall()->post('/api/v1/vendor/bundle/store', [
            'lang' => ['default', 'bn'],
            'name' => ['Weekend Combo', 'Bangla Combo'],
            'description' => ['English body', 'Bangla body'],
            'store_id' => $this->store->id,
            'start_date' => now()->subMinute()->format('Y-m-d H:i'),
            'end_date' => now()->addMonth()->format('Y-m-d H:i'),
            'discount_percentage' => 10,
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
            'image' => UploadedFile::fake()->image('x.png'),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $id = $created->json('content.id');

        $this->assertSame('Weekend Combo', $created->json('content.name'),
            'a bn row must not shadow the default-language column for an en caller');
        $this->assertDatabaseHas('translations', [
            'translationable_type' => Bundle::class,
            'translationable_id' => $id,
            'locale' => 'bn',
            'key' => 'name',
            'value' => 'Bangla Combo',
        ]);
    }

    public function test_the_detail_404s_outside_the_window_but_the_cart_names_the_reason(): void
    {
        $this->bundle->forceFill(['start_date' => now()->addDay()])->save();

        $this->guest()->getJson('/api/v1/bundle/'.$this->bundle->id)->assertStatus(404);

        $refused = $this->guest()->postJson('/api/v1/customer/cart/bundle/add', [
            'guest_id' => 'verify-guest', 'bundle_id' => $this->bundle->id, 'quantity' => 1,
        ]);

        $refused->assertStatus(403);
        $this->assertSame('This bundle has not started yet', $refused->json('errors.0.message'));
    }

    public function test_a_closed_store_names_itself_on_browse(): void
    {
        $this->store->forceFill(['status' => 0])->save();

        $detail = $this->guest()->getJson('/api/v1/bundle/'.$this->bundle->id);
        $refused = $this->guest()->postJson('/api/v1/customer/cart/bundle/add', [
            'guest_id' => 'verify-guest-4', 'bundle_id' => $this->bundle->id, 'quantity' => 1,
        ]);

        $this->store->forceFill(['status' => 1])->save();

        $detail->assertStatus(404);
        $refused->assertStatus(403);
        $this->assertSame('This store is currently unavailable', $refused->json('errors.0.message'));
    }

    public function test_a_deleted_member_item_names_itself(): void
    {
        $line = $this->bundle->items->first();
        $name = $line->item_name;

        Item::withoutGlobalScopes()->where('id', $line->item_id)->delete();

        $body = $this->guest()->getJson('/api/v1/bundle/'.$this->bundle->id)->json('content');

        $this->assertFalse($body['is_available']);
        $this->assertSame('This bundle is no longer available', $body['unavailable_reason'],
            'a deleted member drops the line, so the count rule fires before the per-item one');
        $this->assertSame(1, $body['item_count']);
        $this->assertNotSame('', $name);
    }

    /**
     * Switching a bundle off over the vendor API STRANDS the carts holding it.
     *
     * The rows stay put and the group is flagged unavailable, so the customer reaches checkout and
     * is refused there by name -- rather than finding their cart silently emptied by the vendor.
     */
    public function test_switching_a_bundle_off_strands_the_carts_holding_it(): void
    {
        $add = $this->guest()->postJson('/api/v1/customer/cart/bundle/add', [
            'guest_id' => 'verify-guest-2', 'bundle_id' => $this->bundle->id, 'quantity' => 1,
        ])->assertStatus(201);

        $this->assertSame(2, Cart::where('bundle_id', $this->bundle->id)->count());

        $this->vendorCall()->postJson('/api/v1/vendor/bundle/status/'.$this->bundle->id, ['status' => 0])
            ->assertOk();

        $this->assertSame(2, Cart::where('bundle_id', $this->bundle->id)->count(),
            'the rows stay so the customer can be told what happened to them');
        $this->assertNotNull($add->json('content.bundle_group_id'));

        $this->assertNotNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => 'verify-guest-2', 'is_guest' => 1], (int) $this->store->id),
            'a switched-off bundle must still be refused at checkout',
        );
    }

    public function test_another_stores_bundle_is_404_on_every_vendor_verb(): void
    {
        $other = Store::withoutGlobalScopes()
            ->where('id', '!=', $this->store->id)
            ->whereHas('items')->first();

        $foreign = Bundle::create([
            'store_id' => $other->id, 'module_id' => $other->module_id,
            'name' => 'Foreign', 'start_date' => now()->subHour(), 'end_date' => now()->addWeek(),
        ]);

        $this->vendorCall()->getJson('/api/v1/vendor/bundle/details/'.$foreign->id)->assertStatus(404);
        $this->vendorCall()->postJson('/api/v1/vendor/bundle/status/'.$foreign->id, ['status' => 0])->assertStatus(404);
        $this->vendorCall()->deleteJson('/api/v1/vendor/bundle/delete/'.$foreign->id)->assertStatus(404);
    }

    public function test_pagination_total_counts_cart_rows_not_entries(): void
    {
        $this->guest()->postJson('/api/v1/customer/cart/bundle/add', [
            'guest_id' => 'verify-guest-3', 'bundle_id' => $this->bundle->id, 'quantity' => 2,
        ])->assertStatus(201);

        $body = $this->guest()->getJson(
            '/api/v1/customer/cart/list?guest_id=verify-guest-3&store_id='.$this->store->id
        )->assertOk()->json('content');

        $this->assertCount(1, $body['data'], 'one entry per bundle group');
        $this->assertSame(2, $body['pagination']['total'], 'total counts the underlying rows');
        $this->assertSame(0, $body['data'][0]['id']);
        $this->assertNull($body['data'][0]['item']);
        $this->assertNotNull($body['data'][0]['bundle_details']);
    }

    public function test_an_order_holding_a_bundle_is_not_barred_from_editing(): void
    {
        $order = Order::withoutGlobalScopes()
            ->whereHas('details')->orderByDesc('id')->first();

        if (! $order) {
            $this->markTestSkipped('no order fixture');
        }

        $detail = OrderDetail::where('order_id', $order->id)->first();
        $was = $detail->bundle_group_id;

        $canEdit = function () use ($order) {
            return (new \App\Http\Resources\Vendor\Order\OrderResource(
                $order->fresh()->load('details')
            ))->toArray(request())['can_edit'] ?? null;
        };

        // Asserted as a COMPARISON, not an absolute. canEdit() also turns on order_status,
        // payment_method, prescription and flash-sale state, so asserting `true` outright only
        // passed while the newest order in the fixture happened to be a pending COD one -- it
        // failed on the bundle's behalf for reasons that had nothing to do with bundles. What the
        // claim actually says is that stamping a bundle onto an order changes nothing, and that
        // holds whichever order this picks up.
        $detail->forceFill(['bundle_group_id' => null])->save();
        $withoutBundle = $canEdit();

        $detail->forceFill(['bundle_group_id' => 'verify-group'])->save();
        $withBundle = $canEdit();

        $detail->forceFill(['bundle_group_id' => $was])->save();

        $this->assertNotNull($withBundle, 'can_edit is missing from the vendor order payload');
        $this->assertSame($withoutBundle, $withBundle,
            'holding a bundle is no longer a reason to refuse an edit; only quantity may change');
    }

    private function guest()
    {
        $this->defaultHeaders = [];

        return $this->withHeaders($this->headers());
    }

    private function vendorCall()
    {
        $this->defaultHeaders = [];

        return $this->withHeaders($this->headers() + [
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'vendorType' => 'owner',
        ]);
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
            $this->markTestSkipped('too few sellable items');
        }

        return $items->values();
    }

    private function makeBundle(string $name): Bundle
    {
        $items = $this->items(2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => $name,
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        $base = 0.0;
        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id, 'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image, 'unit_price' => $item->price,
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
