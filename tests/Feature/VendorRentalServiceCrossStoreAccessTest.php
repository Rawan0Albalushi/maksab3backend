<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Service\Services\ServiceSettings;
use Tests\TestCase;

/**
 * The rental and service vendor panels, held to the vendor's own store.
 *
 * Neither module's models carry StoreScope -- rental tables key on provider_id, which the scope
 * does not know -- so every lookup has to filter by store itself, and most rental ones did not:
 * any id in the URL edited, toggled or deleted another store's vehicle or driver. Same shape as
 * VendorCrossStoreAccessTest for items: act as one store against another store's rows.
 */
class VendorRentalServiceCrossStoreAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_vehicle_pages_answer_404_and_writes_leave_another_stores_vehicle_untouched(): void
    {
        [$vendor, $foreignId] = $this->rentalPair('vehicles');
        $before = (array) DB::table('vehicles')->where('id', $foreignId)->first();

        $this->as($vendor)->get("/vendor-panel/vehicle/update/{$foreignId}")->assertNotFound();
        $this->as($vendor)->get("/vendor-panel/vehicle/details/{$foreignId}")->assertNotFound();
        $this->as($vendor)->post("/vendor-panel/vehicle/update/{$foreignId}", ['name' => ['hijacked'], 'lang' => ['default']]);
        $this->as($vendor)->get("/vendor-panel/vehicle/status/{$foreignId}");
        $this->as($vendor)->get("/vendor-panel/vehicle/new-tag/{$foreignId}");
        $this->as($vendor)->delete("/vendor-panel/vehicle/delete/{$foreignId}");

        $this->assertUnchanged('vehicles', $foreignId, $before);
    }

    public function test_driver_pages_answer_404_and_writes_leave_another_stores_driver_untouched(): void
    {
        [$vendor, $foreignId] = $this->rentalPair('vehicle_drivers');
        $before = (array) DB::table('vehicle_drivers')->where('id', $foreignId)->first();

        $this->as($vendor)->get("/vendor-panel/driver/details/{$foreignId}")->assertNotFound();
        $this->as($vendor)->get("/vendor-panel/driver/update/{$foreignId}")->assertNotFound();
        $this->as($vendor)->get("/vendor-panel/driver/trip-export?id={$foreignId}")->assertNotFound();
        $this->as($vendor)->post("/vendor-panel/driver/update/{$foreignId}", ['first_name' => 'hijacked']);
        $this->as($vendor)->get("/vendor-panel/driver/status/{$foreignId}");
        $this->as($vendor)->delete("/vendor-panel/driver/delete/{$foreignId}");

        $this->assertUnchanged('vehicle_drivers', $foreignId, $before);
    }

    public function test_assigning_another_stores_driver_to_a_trip_is_refused(): void
    {
        $row = DB::table('trip_vehicle_details')->join('trips', 'trips.id', '=', 'trip_vehicle_details.trip_id')
            ->select('trip_vehicle_details.id', 'trips.id as trip_id', 'trips.provider_id', 'trip_vehicle_details.vehicle_driver_id')
            ->first();
        $foreignDriver = $row ? DB::table('vehicle_drivers')->where('provider_id', '!=', $row->provider_id)->value('id') : null;
        $vendor = $row ? Vendor::find(DB::table('stores')->where('id', $row->provider_id)->value('vendor_id')) : null;

        if (! $foreignDriver || ! $vendor) {
            $this->markTestSkipped('needs a trip with an assigned vehicle and a driver from another store');
        }

        $this->as($vendor)->post('/vendor-panel/trip/assign/driver', [
            'trip_id' => $row->trip_id,
            'driver_ids' => [$row->id => $foreignDriver],
        ]);

        $this->assertEquals($row->vehicle_driver_id, DB::table('trip_vehicle_details')->where('id', $row->id)->value('vehicle_driver_id'));
    }

    public function test_service_gallery_view_hides_other_stores_services_unless_access_all_is_on(): void
    {
        $service = DB::table('services')->where('is_approved', 1)->first();
        $vendorStore = $service
            ? DB::table('stores')->where('module_id', $service->module_id)->where('id', '!=', $service->store_id)->where('status', 1)->first()
            : null;

        if (! $vendorStore) {
            $this->markTestSkipped('needs an approved service and another active store in its module');
        }

        $this->setServiceSettings(['service_gallery' => 1, 'service_access_all_services' => 0]);

        $this->as(Vendor::find($vendorStore->vendor_id))
            ->get("/vendor-panel/service/gallery-view/{$service->id}")
            ->assertNotFound();
    }

    /** A vendor whose store owns rows in $table, and the id of a row another store owns. */
    private function rentalPair(string $table): array
    {
        $owner = DB::table($table)->whereNotNull('provider_id')->value('provider_id');
        $attackerStore = DB::table('stores')->join('modules', 'modules.id', '=', 'stores.module_id')
            ->where('modules.module_type', 'rental')->where('stores.status', 1)
            ->where('stores.id', '!=', $owner)->select('stores.vendor_id')->first();
        $foreignId = DB::table($table)->where('provider_id', $owner)->value('id');

        if (! $attackerStore || ! $foreignId) {
            $this->markTestSkipped("needs {$table} rows and a second active rental store");
        }

        return [Vendor::findOrFail($attackerStore->vendor_id), $foreignId];
    }

    private function assertUnchanged(string $table, mixed $id, array $before): void
    {
        $after = (array) DB::table($table)->where('id', $id)->first();

        $this->assertSame(
            array_diff_key($before, ['updated_at' => 1]),
            array_diff_key($after, ['updated_at' => 1]),
            "a vendor changed a {$table} row that belongs to another store"
        );
    }

    private function setServiceSettings(array $values): void
    {
        $settings = new \ReflectionClass(ServiceSettings::class);
        $settings->setStaticPropertyValue('cache', $values + $settings->getStaticPropertyValue('cache'));
        $settings->setStaticPropertyValue('loaded', true);
    }

    private function as(Vendor $vendor): self
    {
        return $this->actingAs($vendor, 'vendor')
            ->withSession(['login_remember_token' => $vendor->login_remember_token]);
    }
}
