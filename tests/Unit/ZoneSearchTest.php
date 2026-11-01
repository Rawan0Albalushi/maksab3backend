<?php

namespace Tests\Unit;

use App\Models\Zone;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QA case TC_152 — the Zone Setup search must find what its placeholder promises.
 *
 * The field reads "Search by Vendor name, owner info...", but the query matched `zones.name`
 * alone. Typing a vendor returned "no data found", and an admin reasonably concluded the zone did
 * not exist. The scope now reaches the zone, its stores, and the vendors behind them.
 */
class ZoneSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function ids(?string $search): array
    {
        return Zone::matchingSearch($search)->pluck('id')->sort()->values()->all();
    }

    public function test_an_empty_search_does_not_filter(): void
    {
        $this->assertSame(Zone::pluck('id')->sort()->values()->all(), $this->ids(null));
        $this->assertSame(Zone::pluck('id')->sort()->values()->all(), $this->ids('   '));
    }

    public function test_a_zone_is_found_by_its_own_name(): void
    {
        $zone = Zone::first();

        $this->assertContains($zone->id, $this->ids($zone->getRawOriginal('name')));
    }

    public function test_a_zone_is_found_by_a_store_inside_it(): void
    {
        $store = DB::table('stores')->whereNotNull('zone_id')->whereNotNull('name')->first();

        if (! $store) {
            $this->markTestSkipped('needs a store with a zone');
        }

        $this->assertContains(
            (int) $store->zone_id,
            $this->ids($store->name),
            'a store name must find the zone it sits in',
        );
    }

    public function test_a_zone_is_found_by_its_vendor_name_and_phone(): void
    {
        $row = DB::table('stores')
            ->join('vendors', 'vendors.id', '=', 'stores.vendor_id')
            ->whereNotNull('stores.zone_id')
            ->select('stores.zone_id', 'vendors.f_name', 'vendors.phone')
            ->first();

        if (! $row) {
            $this->markTestSkipped('needs a store with a vendor and a zone');
        }

        $this->assertContains((int) $row->zone_id, $this->ids($row->f_name), 'vendor name must match');
        $this->assertContains((int) $row->zone_id, $this->ids($row->phone), 'vendor phone must match');
    }

    public function test_a_search_matching_nothing_returns_nothing(): void
    {
        $this->assertSame([], $this->ids('zzzzznomatchzzzzz'));
    }

    public function test_a_zone_with_several_matching_stores_appears_once(): void
    {
        // whereHas, not a join — a join would repeat the zone per matching store and inflate the
        // count badge above the list.
        $zoneId = DB::table('stores')->whereNotNull('zone_id')
            ->select('zone_id')->groupBy('zone_id')
            ->havingRaw('COUNT(*) > 1')->value('zone_id');

        if (! $zoneId) {
            $this->markTestSkipped('needs a zone holding more than one store');
        }

        $name = DB::table('stores')->where('zone_id', $zoneId)->value('name');
        $ids = $this->ids($name);

        $this->assertSame(count($ids), count(array_unique($ids)), 'a zone must not be listed twice');
    }
}
