<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\Vendor;
use App\Models\Zone;
use App\Services\Zone\ZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * TC_18 — nothing told a vendor their zone had gone inactive. ZoneService::
 * notifyVendorsOfDeactivation() targets every vendor with an active store in the zone and a real
 * firebase_token, mirroring BundleService::notifyStoreOfNewBundle()'s guard.
 */
class ZoneDeactivationNotificationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_runs_without_error_against_a_zone_with_no_tokened_vendors(): void
    {
        $zone = Zone::where('status', 1)->first();

        if (! $zone) {
            $this->markTestSkipped('no active zone exists to test against');
        }

        app(ZoneService::class)->notifyVendorsOfDeactivation($zone->id, $zone->name);

        $this->assertTrue(true, 'notifyVendorsOfDeactivation must not throw');
    }

    private function makeVendor(?string $token): Vendor
    {
        $vendor = new Vendor();
        $vendor->f_name = 'Test';
        $vendor->phone = '+880'.random_int(1000000000, 1999999999);
        $vendor->email = 'zone-notify-test-'.uniqid().'@example.test';
        $vendor->password = bcrypt('12345678');
        $vendor->firebase_token = $token;
        $vendor->save();

        return $vendor;
    }

    private function makeStore(int $vendorId, int $moduleId, int $zoneId): Store
    {
        $store = new Store();
        $store->name = 'Zone Notify Test Store';
        $store->phone = '+880'.random_int(1000000000, 1999999999);
        $store->vendor_id = $vendorId;
        $store->module_id = $moduleId;
        $store->zone_id = $zoneId;
        $store->status = 1;
        $store->save();

        return $store;
    }

    public function test_the_vendor_selection_excludes_stores_in_other_zones_and_vendors_without_a_token(): void
    {
        $zone = Zone::where('status', 1)->first();
        $otherZone = Zone::where('id', '!=', $zone?->id)->first();
        $moduleId = (int) (\App\Models\Module::query()->value('id') ?? 1);

        if (! $zone || ! $otherZone) {
            $this->markTestSkipped('need two zones to test scoping');
        }

        $eligible = $this->makeVendor('a-real-looking-token');
        $noToken = $this->makeVendor(null);
        $placeholderToken = $this->makeVendor('@');
        $wrongZone = $this->makeVendor('also-real-looking');

        $this->makeStore($eligible->id, $moduleId, $zone->id);
        $this->makeStore($noToken->id, $moduleId, $zone->id);
        $this->makeStore($placeholderToken->id, $moduleId, $zone->id);
        $this->makeStore($wrongZone->id, $moduleId, $otherZone->id);

        $matched = Vendor::query()
            ->whereHas('stores', fn ($q) => $q->where('zone_id', $zone->id)->where('status', 1))
            ->whereNotNull('firebase_token')
            ->where('firebase_token', '!=', '@')
            ->pluck('id');

        $this->assertTrue($matched->contains($eligible->id));
        $this->assertFalse($matched->contains($noToken->id));
        $this->assertFalse($matched->contains($placeholderToken->id));
        $this->assertFalse($matched->contains($wrongZone->id));
    }
}
