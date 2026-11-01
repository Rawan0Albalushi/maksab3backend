<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Dimension;
use App\Models\DMVehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Vehicles Category, after the 2026-09-08 move out of Users › Delivery Man.
 *
 * The move is the point of most of these: the screen has to answer on its new URL, be gone from
 * the old one, and appear in the Delivery Management panel rather than the Users one. The rest
 * cover the two fields the redesign added — Max. Weight and Dimension Connect — which have no
 * older behaviour to fall back on.
 */
class VehicleCategoryScreenTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    private string $base = '/admin/delivery-management/vehicle-category';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function panel()
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'type' => ['QA Vehicle '.uniqid()],
            'lang' => ['default'],
            'starting_coverage_area' => 3,
            'maximum_coverage_area' => 30,
            'max_weight' => 120.5,
            'dimension_ids' => [$this->dimension()->id],
        ];
    }

    private function dimension(): Dimension
    {
        return Dimension::active()->first() ?? Dimension::create([
            'name' => 'QA Size '.uniqid(),
            'max_length' => 10,
            'max_width' => 10,
            'max_height' => 10,
            'status' => 1,
        ]);
    }

    public function test_the_screen_answers_on_its_delivery_management_url(): void
    {
        $this->panel()->get($this->base)->assertOk();
        $this->panel()->get($this->base.'/create')->assertOk();
    }

    /** The move is only done when the old URL is gone — a live duplicate is worse than either. */
    public function test_the_old_delivery_man_url_no_longer_exists(): void
    {
        $this->panel()->get('/admin/users/delivery-man/vehicle')->assertNotFound();

        $this->assertNull(
            app('router')->getRoutes()->getByName('admin.users.delivery-man.vehicle.list'),
            'the old route name must not survive the move',
        );
    }

    public function test_it_is_listed_under_delivery_management_and_not_under_users(): void
    {
        $settings = file_get_contents(base_path('resources/views/layouts/admin/partials/_sidebar_v2_settings.blade.php'));
        $users = file_get_contents(base_path('resources/views/layouts/admin/partials/_sidebar_v2_users.blade.php'));

        $panel = substr($settings, strpos($settings, 'data-panel="delivery"'));
        $panel = substr($panel, 0, strpos($panel, 'data-panel="mods"'));

        $this->assertStringContainsString('vehicle-category.list', $panel, 'the nav item must sit in the Delivery Management panel');
        // Rider vehicles are a separate RideShare feature and stay under Users; only the
        // deliveryman vehicle CATEGORY moved.
        $this->assertStringNotContainsString('delivery-man/vehicle', $users, 'the old Users nav item must be gone');
        $this->assertStringNotContainsString('delivery-man.vehicle', $users, 'the old route name must be gone from the Users nav');
    }

    public function test_it_saves_the_weight_and_the_connected_dimensions(): void
    {
        $dimension = $this->dimension();

        $this->panel()->post($this->base.'/store', $this->payload([
            'max_weight' => 742.25,
            'dimension_ids' => [$dimension->id],
        ]))->assertRedirect(route('admin.business-settings.zone.vehicle-category.list'));

        $vehicle = DMVehicle::withoutGlobalScope('delivery_only')->latest('id')->first();

        $this->assertSame(742.25, (float) $vehicle->max_weight);
        $this->assertSame([$dimension->id], $vehicle->dimensions->pluck('id')->all());
    }

    public function test_the_weight_is_required(): void
    {
        $before = DMVehicle::withoutGlobalScope('delivery_only')->count();

        $payload = $this->payload();
        unset($payload['max_weight']);

        $this->panel()->post($this->base.'/store', $payload)->assertSessionHasErrors('max_weight');

        $this->assertSame($before, DMVehicle::withoutGlobalScope('delivery_only')->count());
    }

    /** Required only once there is something to pick — otherwise the screen would be unusable. */
    public function test_the_dimension_link_is_required_whenever_a_dimension_exists(): void
    {
        $this->dimension();

        $payload = $this->payload();
        unset($payload['dimension_ids']);

        $this->panel()->post($this->base.'/store', $payload)->assertSessionHasErrors('dimension_ids');
    }

    public function test_the_coverage_band_must_run_upwards(): void
    {
        $this->panel()->post($this->base.'/store', $this->payload([
            'starting_coverage_area' => 40,
            'maximum_coverage_area' => 40,
        ]))->assertSessionHasErrors('maximum_coverage_area');
    }

    /**
     * Refused, not cascaded. A deliveryman whose `vehicle_id` stops resolving drops out of every
     * assignable list with nothing on screen saying why.
     */
    public function test_a_category_a_delivery_man_rides_cannot_be_deleted(): void
    {
        $vehicleId = DB::table('delivery_men')->whereNotNull('vehicle_id')->value('vehicle_id');

        if (! $vehicleId) {
            $this->markTestSkipped('no delivery man is registered with a vehicle category');
        }

        $this->panel()->delete($this->base.'/delete/'.$vehicleId);

        $this->assertTrue(
            DMVehicle::withoutGlobalScope('delivery_only')->whereKey($vehicleId)->exists(),
            'the category must survive the refused delete',
        );
    }

    public function test_the_export_streams_the_new_columns(): void
    {
        $response = $this->panel()->get($this->base.'/export/csv');

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString('Max Weight', $body);
        $this->assertStringContainsString('Dimension', $body);
    }
}
