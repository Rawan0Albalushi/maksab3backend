<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Store;
use App\Models\Vendor;
use App\Scopes\ZoneScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BogoApiContractTest extends TestCase
{
    use DatabaseTransactions;

    private const ENROLLMENT_STATES = ['not_joined', 'pending', 'admin_requested', 'approved', 'rejected'];

    private const VISIBILITY_STATES = ['running', 'scheduled', 'ended', 'not_visible', 'not_applicable'];

    private const BOGO_ACTIONS = ['can_join', 'can_respond', 'can_resubmit', 'can_cancel', 'can_leave'];

    private ?Store $store = null;
    private ?Item $item = null;
    private ?BogoOffer $offer = null;
    private ?Vendor $vendor = null;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->offer, $this->store, $this->item] = $this->makeServableOffer();
        $this->vendor = Vendor::find($this->store->vendor_id);
    }

    public function test_every_endpoint_the_doc_names_is_registered(): void
    {
        $documented = [
            'GET api/v1/happy-hour/running', 'GET api/v1/happy-hour/stores',
            'GET api/v1/bogo/home', 'GET api/v1/bogo/offers', 'GET api/v1/bogo/offers/{id}',
            'GET api/v1/bogo/store-offers',
            'POST api/v1/customer/cart/bogo/add', 'POST api/v1/customer/cart/bogo/update',
            'DELETE api/v1/customer/cart/bogo/remove',
            'GET api/v1/customer/cart/discount-eligibility', 'GET api/v1/customer/cart/list',
            'GET api/v1/vendor/bogo-offer/list', 'GET api/v1/vendor/bogo-offer/details/{id}',
            'GET api/v1/vendor/bogo-offer/items',
            'POST api/v1/vendor/bogo-offer/join/{id}', 'POST api/v1/vendor/bogo-offer/resubmit/{id}',
            'POST api/v1/vendor/bogo-offer/respond/{id}', 'DELETE api/v1/vendor/bogo-offer/leave/{id}',
            'GET api/v1/vendor/happy-hour/list', 'GET api/v1/vendor/happy-hour/details/{id}',
            'POST api/v1/vendor/happy-hour/join/{id}', 'POST api/v1/vendor/happy-hour/respond/{id}',
            'DELETE api/v1/vendor/happy-hour/leave/{id}',
        ];

        $registered = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->methods() as $method) {
                $registered[] = $method.' '.$route->uri();
            }
        }

        foreach ($documented as $signature) {
            $this->assertContains($signature, $registered, "the doc names {$signature}, which is not routed");
        }
    }

    public function test_the_offer_detail_resolves_a_slug_as_well_as_an_id(): void
    {
        $this->offer->forceFill(['slug' => 'contract-probe-offer'])->save();

        $byId = $this->guest()->getJson('/api/v1/bogo/offers/'.$this->offer->id)->assertOk()->json('content');
        $bySlug = $this->guest()->getJson('/api/v1/bogo/offers/contract-probe-offer')->assertOk()->json('content');

        $this->assertSame(['offer', 'data', 'pagination'], array_keys($byId),
            'the detail is the offer card plus every stores bundle, as documented');
        $this->assertSame($this->offer->id, $byId['offer']['id']);
        $this->assertSame($byId['offer']['id'], $bySlug['offer']['id']);
    }

    public function test_remaining_uses_is_null_when_the_offer_carries_no_cap(): void
    {
        $this->offer->forceFill(['usage_limit_per_customer' => null])->save();

        $card = $this->guest()->getJson('/api/v1/bogo/offers/'.$this->offer->id)
            ->assertOk()->json('content.offer');

        $this->assertArrayHasKey('remaining_uses', $card);
        $this->assertNull($card['remaining_uses'], 'no cap must read as null, not 0');

        $this->offer->forceFill(['usage_limit_per_customer' => 3])->save();

        $capped = $this->guest()->getJson('/api/v1/bogo/offers/'.$this->offer->id)
            ->assertOk()->json('content.offer');

        $this->assertSame(3, $capped['remaining_uses'], 'a guest sees the full allowance');
    }

    public function test_offset_is_a_page_number_on_the_offer_list(): void
    {
        $body = $this->guest()->getJson('/api/v1/bogo/offers?limit=1&offset=2')->assertOk()->json('content');

        $this->assertSame(2, $body['pagination']['current_page']);
        $this->assertSame(1, $body['pagination']['per_page']);
    }

    public function test_the_vendor_row_uses_only_the_documented_state_words(): void
    {
        $rows = $this->vendorCall()->getJson('/api/v1/vendor/bogo-offer/list')->assertOk()->json('content.data');

        $this->assertNotEmpty($rows, 'the fixture offer must reach the vendor list');

        foreach ($rows as $row) {
            $this->assertContains($row['enrollment_state'], self::ENROLLMENT_STATES,
                'enrollment_state left the documented set: '.$row['enrollment_state']);
            $this->assertContains($row['visibility_status'], self::VISIBILITY_STATES,
                'visibility_status left the documented set: '.$row['visibility_status']);
            $this->assertArrayHasKey('actions_locked_reason', $row);
        }
    }

    public function test_the_bogo_actions_object_carries_exactly_the_five_documented_keys(): void
    {
        $rows = $this->vendorCall()->getJson('/api/v1/vendor/bogo-offer/list')->assertOk()->json('content.data');

        $this->assertSame(self::BOGO_ACTIONS, array_keys($rows[0]['actions']));
    }

    public function test_happy_hour_offers_no_resubmit(): void
    {
        $this->makeHappyHour();

        $rows = $this->vendorCall()->getJson('/api/v1/vendor/happy-hour/list')->assertOk()->json('content.data');

        $this->assertNotEmpty($rows, 'the fixture happy hour must reach the vendor list');

        $this->assertSame(self::BOGO_ACTIONS, array_keys($rows[0]['actions']),
            'the shared builder emits every key; happy hour differs by value, not by shape');

        foreach ($rows as $row) {
            $this->assertFalse($row['actions']['can_resubmit'],
                'a happy hour carries no selection to rework, so resubmit is always false');
        }

        foreach ($rows as $row) {
            $this->assertContains($row['enrollment_state'], self::ENROLLMENT_STATES);
            $this->assertContains($row['visibility_status'], self::VISIBILITY_STATES);
        }
    }

    public function test_the_vendor_detail_resolves_a_slug_as_well_as_an_id(): void
    {
        $this->offer->forceFill(['slug' => 'contract-probe-vendor'])->save();

        $byId = $this->vendorCall()->getJson('/api/v1/vendor/bogo-offer/details/'.$this->offer->id)
            ->assertOk()->json('content');
        $bySlug = $this->vendorCall()->getJson('/api/v1/vendor/bogo-offer/details/contract-probe-vendor')
            ->assertOk()->json('content');

        $this->assertSame($byId['id'], $bySlug['id']);
    }

    public function test_two_adds_of_one_bundle_make_two_groups_against_one_offer(): void
    {
        $card = $this->guest()->getJson('/api/v1/bogo/offers/'.$this->offer->id)->assertOk()->json('content');

        $bundleId = data_get($card, 'data.0.bundle_id');

        if (! $bundleId) {
            $this->markTestSkipped('offer card exposes no bundle_id in this dataset: '.json_encode(array_keys($card)));
        }

        $first = $this->guest()->postJson('/api/v1/customer/cart/bogo/add', [
            'guest_id' => 'bogo-contract-guest', 'bundle_id' => $bundleId, 'quantity' => 1,
        ]);
        $second = $this->guest()->postJson('/api/v1/customer/cart/bogo/add', [
            'guest_id' => 'bogo-contract-guest', 'bundle_id' => $bundleId, 'quantity' => 1,
        ]);

        $first->assertStatus(201);
        $second->assertStatus(201);

        $this->assertNotSame(
            $first->json('content.bogo_group_id'),
            $second->json('content.bogo_group_id'),
            'Rule 3: two adds are two groups'
        );
    }

    public function test_the_envelope_shape_holds_on_a_bogo_endpoint(): void
    {
        $body = $this->guest()->getJson('/api/v1/bogo/home')->assertOk()->json();

        $this->assertSame(['identical_code', 'message', 'content', 'errors'], array_keys($body));
        $this->assertSame('default_200', $body['identical_code']);
        $this->assertSame([], $body['errors']);
    }

    public function test_a_vendor_call_without_vendor_type_is_403(): void
    {
        $this->defaultHeaders = [];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->vendor->auth_token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/vendor/bogo-offer/list')->assertStatus(403);
    }

    private function makeHappyHour(): HappyHour
    {
        $happyHour = HappyHour::create([
            'module_id' => $this->store->module_id,
            'title' => 'contract probe hour',
            'discount' => 10,
            'is_permanent' => 1,
            'duration_type' => 'daily',
            'start_time' => '00:00:00',
            'end_time' => '23:59:00',
            'status' => 1,
        ]);

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $this->store->id,
            'status' => HappyHourStore::STATUS_APPROVED,
        ]);

        return $happyHour;
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

    private function makeServableOffer(): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)
            ->whereHas('store', fn ($q) => $q->withoutGlobalScopes()
                ->whereNotNull('zone_id')
                ->whereHas('vendor', fn ($v) => $v->whereNotNull('auth_token')))
            ->with(['module', 'store'])
            ->first();

        if (! $item) {
            $this->markTestSkipped('dataset has no item whose store has a zone and a tokened vendor');
        }

        $item->forceFill([
            'status' => 1, 'is_approved' => 1,
            'available_time_starts' => null, 'available_time_ends' => null,
            'stock' => 999, 'variations' => json_encode([]), 'food_variations' => json_encode([]),
        ])->saveQuietly();

        Store::whereKey($item->store_id)->update(['status' => 1]);

        $offer = BogoOffer::create([
            'module_id' => $item->module_id,
            'title' => 'contract probe',
            'buy_qty' => 1, 'get_qty' => 1,
            'start_date' => now()->subDay(), 'end_date' => now()->addWeek(),
            'status' => 1,
        ]);

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => BogoOfferStore::STATUS_APPROVED,
            'bundle_price' => 100,
        ]);

        BogoOfferItem::create([
            'bogo_offer_store_id' => $enrolment->id,
            'item_id' => $item->id,
            'type' => BogoOfferItem::TYPE_BUY,
            'quantity' => 1,
            'item_name' => $item->name,
            'price' => 100, 'original_price' => 100,
        ]);

        return [$offer, $item->store, $item->fresh(['module', 'store'])];
    }
}
