<?php

namespace Tests\Feature;

use App\Models\ItemCampaign;
use App\Services\Item\ItemService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `items/details/{id}?campaign=1` answered 404 for every campaign, always.
 *
 * `findDetail($id, campaign: true)` returns an ItemCampaign, but `loadDetailRelations()` was
 * written for Item and eager-loaded six relations a campaign does not define — `rating`,
 * `storeCategory`, `seoData`, `pharmacy_item_details`, `ecommerce_item_details`, `flashSaleItems`.
 * `loadMissing()` THROWS on an undefined relation rather than skipping it, and the controller's
 * catch block reported the RelationNotFoundException as "Not Found".
 */
class CampaignItemDetailTest extends TestCase
{
    private const CAMPAIGN_ONLY_RELATIONS = [
        'rating', 'storeCategory', 'seoData',
        'pharmacy_item_details', 'ecommerce_item_details', 'flashSaleItems',
    ];

    private function activeCampaign(): ?ItemCampaign
    {
        return ItemCampaign::active()->first();
    }

    public function test_the_campaign_detail_endpoint_returns_the_campaign(): void
    {
        $campaign = $this->activeCampaign();

        if (! $campaign) {
            $this->markTestSkipped('needs an active item campaign');
        }

        $response = $this->withHeaders([
            'moduleId' => $campaign->module_id,
            'zoneId' => json_encode([1]),
        ])->get('/api/v1/items/details/'.$campaign->id.'?campaign=1');

        $response->assertOk();
        $this->assertSame($campaign->id, $response->json('content.id'));
    }

    /** The regression itself: loading Item relations onto a campaign must not throw. */
    public function test_the_shared_loader_skips_relations_a_campaign_does_not_define(): void
    {
        $campaign = $this->activeCampaign();

        if (! $campaign) {
            $this->markTestSkipped('needs an active item campaign');
        }

        foreach (self::CAMPAIGN_ONLY_RELATIONS as $relation) {
            $this->assertFalse(
                $campaign->isRelation($relation),
                "ItemCampaign gained a [$relation] relation — this test's premise needs revisiting"
            );
        }

        // Threw RelationNotFoundException before the fix.
        $loaded = app(ItemService::class)->loadDetailRelations(collect([$campaign]));

        $this->assertCount(1, $loaded);
    }

    /** Items must keep every relation they define — the filter drops nothing that exists. */
    public function test_plain_items_still_load_their_full_relation_set(): void
    {
        $item = \App\Models\Item::active()->first();

        if (! $item) {
            $this->markTestSkipped('needs an active item');
        }

        $loaded = app(ItemService::class)->loadDetailRelations(collect([$item]))->first();

        foreach (['rating', 'storage', 'module', 'store'] as $relation) {
            $this->assertTrue($loaded->relationLoaded($relation), "[$relation] must still be eager-loaded for an Item");
        }
    }

    /**
     * An expired campaign is a real 404 and must stay one — but with the reason that says so,
     * not the catch block's generic "Not Found", which is how the crash used to present.
     */
    public function test_an_expired_campaign_reports_unavailable_rather_than_a_crash(): void
    {
        // "Expired" as the APP defines it, not as the dates suggest: `ItemCampaign::active()`
        // also requires a live store on a valid subscription, so a date check alone picks rows
        // the endpoint still serves.
        $activeIds = ItemCampaign::active()->pluck('id')->all();

        $expired = DB::table('item_campaigns')
            ->where('status', 1)
            ->whereNotIn('id', $activeIds ?: [0])
            ->first();

        if (! $expired) {
            $this->markTestSkipped('needs an expired campaign');
        }

        $response = $this->withHeaders([
            'moduleId' => $expired->module_id,
            'zoneId' => json_encode([1]),
        ])->get('/api/v1/items/details/'.$expired->id.'?campaign=1');

        $response->assertNotFound();
        $this->assertSame('Item currently unavailable', $response->json('errors.0.message'));
    }
}
