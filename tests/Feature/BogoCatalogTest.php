<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\Promotion\BogoGroupPresenter;
use App\Services\Promotion\BogoOfferCatalog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BogoCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private BogoOfferCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = app(BogoOfferCatalog::class);
    }

    /**
     * The source left atServableStore() private while a controller called it, which 500'd a live
     * endpoint. This asserts every method a caller reaches for is actually reachable.
     */
    public function test_every_method_callers_use_is_public(): void
    {
        $reflection = new \ReflectionClass(BogoOfferCatalog::class);

        foreach ([
            'availableOffers', 'atServableStore', 'orderableOfferIds',
            'orderableEnrollments', 'bundleQuery', 'offerCard', 'bundleCard',
        ] as $method) {
            $this->assertTrue($reflection->hasMethod($method), "catalog is missing {$method}()");
            $this->assertTrue(
                $reflection->getMethod($method)->isPublic(),
                "{$method}() must be public -- a private one 500s the endpoint that calls it"
            );
        }
    }

    public function test_a_servable_offer_is_listed(): void
    {
        [$offer, $store] = $this->makeServableOffer();

        $ids = $this->catalog->availableOffers([$store->zone_id], $store->module_id)->pluck('id')->all();

        $this->assertContains($offer->id, $ids);
    }

    /** A bundle is all or nothing, so one dead member takes the offer off the listing entirely. */
    public function test_an_offer_whose_member_is_unavailable_is_not_listed(): void
    {
        [$offer, $store, $item] = $this->makeServableOffer();

        $item->forceFill(['status' => 0])->saveQuietly();

        $ids = $this->catalog->availableOffers([$store->zone_id], $store->module_id)->pluck('id')->all();

        $this->assertNotContains($offer->id, $ids, 'an unservable bundle must not be advertised');
    }

    public function test_an_offer_is_not_listed_in_another_module(): void
    {
        [$offer, $store] = $this->makeServableOffer();

        $otherModuleId = DB::table('modules')->where('id', '!=', $store->module_id)->value('id');

        if (! $otherModuleId) {
            $this->markTestSkipped('needs two modules');
        }

        $ids = $this->catalog->availableOffers([$store->zone_id], $otherModuleId)->pluck('id')->all();

        $this->assertNotContains($offer->id, $ids);
    }

    /** A spent whole-offer cap removes the offer, rather than leaving it visible and unaddable. */
    public function test_an_exhausted_offer_is_not_listed(): void
    {
        [$offer, $store] = $this->makeServableOffer();

        $offer->update(['usage_limit_total' => 3, 'total_uses' => 3]);

        $ids = $this->catalog->availableOffers([$store->zone_id], $store->module_id)->pluck('id')->all();

        $this->assertNotContains($offer->id, $ids);
    }

    /** bundle_id is the enrolment's id, never the offer's -- several stores run one offer. */
    public function test_bundle_card_reports_the_enrolment_id_not_the_offer_id(): void
    {
        [$offer, $store, $item, $enrolment] = $this->makeServableOffer();

        $card = $this->catalog->bundleCard($enrolment->load(['items', 'store', 'bogoOffer']));

        $this->assertSame($enrolment->id, $card['bundle_id']);
        $this->assertSame($offer->id, $card['bogo_offer_id']);
        $this->assertNotSame($card['bundle_id'], $card['bogo_offer_id'], 'the two ids must not be conflated');
        $this->assertTrue($card['is_available']);
        $this->assertCount(1, $card['buy_items']);
    }

    /** A happy hour is the one promotion that reaches a bundle; nothing else discounts it. */
    public function test_bundle_pricing_applies_a_happy_hour_and_nothing_else(): void
    {
        [, , , $enrolment] = $this->makeServableOffer();

        $enrolment->update(['bundle_price' => 200]);
        $enrolment->load(['items', 'store', 'bogoOffer']);

        $plain = $this->catalog->bundleCard($enrolment);
        $this->assertSame(200.0, $plain['final_price']);
        $this->assertFalse($plain['is_happy_hour']);

        $discounted = $this->catalog->bundleCard($enrolment, 10.0);
        $this->assertSame(20.0, $discounted['discount_amount']);
        $this->assertSame(180.0, $discounted['final_price']);
        $this->assertTrue($discounted['is_happy_hour']);
    }

    public function test_presenter_leaves_ordinary_rows_alone_and_marks_them_null(): void
    {
        $presenter = app(BogoGroupPresenter::class);

        $row = (object) ['bogo_group_id' => null, 'price' => 10, 'quantity' => 2];
        $entries = $presenter->present(collect([$row]), 1, 0);

        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->bogo_details, 'bogo_details is present and null on ordinary lines');
    }

    /**
     * @return array{0:BogoOffer,1:Store,2:Item,3:BogoOfferStore}
     */
    private function makeServableOffer(): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)
            ->active()
            ->whereHas('store', fn ($q) => $q->whereNotNull('zone_id'))
            ->with(['module', 'store'])
            ->first();

        if (! $item || ! $item->store || ! $item->store->zone_id) {
            $this->markTestSkipped('dataset has no listable item with a store in a zone');
        }

        $item->forceFill([
            'status' => 1,
            'is_approved' => 1,
            'available_time_starts' => null,
            'available_time_ends' => null,
            'stock' => 999,
            'variations' => json_encode([]),
            'food_variations' => json_encode([]),
        ])->saveQuietly();

        Store::whereKey($item->store_id)->update(['status' => 1]);

        $offer = BogoOffer::create([
            'module_id' => $item->module_id,
            'title' => 'catalog probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
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
            'price' => 100,
            'original_price' => 100,
        ]);

        return [$offer, $item->store, $item->fresh(['module', 'store']), $enrolment];
    }
}
