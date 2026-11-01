<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `add_on_summary` on the bundle detail payload — the add-on counterpart of `variation_summary`.
 *
 * A bundle line freezes the add-on IDS it was built with, never their names, so the two id/qty
 * arrays alone left every client resolving names itself before it could draw the line.
 */
class BundleAddOnSummaryTest extends TestCase
{
    use DatabaseTransactions;

    private Store $store;

    private Bundle $bundle;

    private array $addOns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::whereHas('module')->whereHas('items')->first();

        if (! $this->store) {
            $this->markTestSkipped('needs a store with items');
        }

        $this->addOns = AddOn::limit(2)->get()->all();

        if (count($this->addOns) < 2) {
            $this->markTestSkipped('needs two add-ons');
        }

        $this->enableBundlesForStoreModule();
        $this->bundle = $this->makeBundle();
    }

    private function enableBundlesForStoreModule(): void
    {
        $map = [];

        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === $this->store->module?->module_type ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    private function makeBundle(): Bundle
    {
        $items = Item::where('store_id', $this->store->id)->limit(2)->get();

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'AddOn summary fixture',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 10,
        ]);

        $base = 0.0;
        $first = true;

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
                // Only the first line carries add-ons, so the empty case is covered too.
                'add_on_ids' => $first ? [$this->addOns[0]->id, $this->addOns[1]->id] : null,
                'add_on_qtys' => $first ? [2, 1] : null,
            ]);
            $base += (float) $item->price;
            $first = false;
        }

        $bundle->forceFill(app(BundleService::class)->prices($base, 10))->save();

        return $bundle->fresh('items');
    }

    public function test_the_detail_endpoint_summarises_add_ons_by_name_and_quantity(): void
    {
        $response = $this->withHeaders([
            'moduleId' => $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
        ])->get('/api/v1/bundle/'.$this->bundle->id);

        $response->assertOk();

        $items = $response->json('content.items');
        $this->assertNotEmpty($items);

        $withAddOns = collect($items)->firstWhere('add_on_summary', '!=', '');
        $this->assertNotNull($withAddOns, 'a line carrying add-ons must report a summary');

        $expected = $this->addOns[0]->name.' (2), '.$this->addOns[1]->name.' (1)';
        $this->assertSame($expected, $withAddOns['add_on_summary']);

        // the raw arrays are untouched (N9)
        $this->assertSame([$this->addOns[0]->id, $this->addOns[1]->id], $withAddOns['add_on_ids']);
        $this->assertSame([2, 1], $withAddOns['add_on_qtys']);
    }

    public function test_a_line_without_add_ons_reports_an_empty_summary(): void
    {
        $response = $this->withHeaders([
            'moduleId' => $this->store->module_id,
            'zoneId' => json_encode([$this->store->zone_id]),
        ])->get('/api/v1/bundle/'.$this->bundle->id);

        $bare = collect($response->json('content.items'))
            ->first(fn (array $line) => ($line['add_on_ids'] ?? []) === []);

        $this->assertNotNull($bare, 'the fixture has a line with no add-ons');
        $this->assertSame('', $bare['add_on_summary'], 'empty string, never null — same as variation_summary');
    }

    public function test_the_vendor_bundle_payload_carries_the_same_summary(): void
    {
        $vendor = $this->store->vendor;
        $this->assertNotNull($vendor, 'the fixture store must have a vendor');

        $rendered = (new \App\Http\Resources\Vendor\Promotion\BundleResource(
            $this->bundle->load('items'),
            withItems: true,
            addOnLines: \App\Support\Promotion\AddOnLabels::forParents([$this->bundle]),
        ))->render();

        $withAddOns = collect($rendered['items'])->firstWhere('add_on_summary', '!=', '');

        $this->assertNotNull($withAddOns);
        $this->assertSame(
            $this->addOns[0]->name.' (2), '.$this->addOns[1]->name.' (1)',
            $withAddOns['add_on_summary'],
            'the vendor payload must read identically to the customer one'
        );
    }

    /** The list endpoints render every bundle's lines, so the name lookup must not be per bundle. */
    public function test_the_name_lookup_is_one_query_for_any_number_of_bundles(): void
    {
        $bundles = Bundle::with('items')->get();

        DB::enableQueryLog();
        DB::flushQueryLog();

        app(BundleService::class)->addOnLinesFor($bundles);

        $this->assertLessThanOrEqual(
            2,
            count(DB::getQueryLog()),
            'add-on names must be resolved in one batched query, not one per bundle'
        );
    }
}
