<?php

namespace Tests\Feature;

use App\Exceptions\DuplicateAdditionalDeliveryChargeException;
use App\Models\Admin;
use App\Models\AdditionalDeliveryCharge;
use App\Services\Zone\AdditionalDeliveryChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The defects QA cases TC_285 and TC_291 turned up on the Additional Delivery Charge setup.
 *
 * Two silent clamps — the database quietly rewriting a charge and a time the admin typed — and one
 * race: the duplicate guard read before it wrote, so two saves for the same (zone, module) both
 * got through.
 */
class AdditionalDeliveryChargeGuardsTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    private string $base = '/admin/delivery-management/additional-delivery-charge';

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

    /** A (zone, module) pair no setup has claimed yet. */
    private function freePair(): array
    {
        $taken = DB::table('additional_delivery_charge_module as m')
            ->join('additional_delivery_charges as c', 'c.id', '=', 'm.additional_delivery_charge_id')
            ->get(['c.zone_id', 'm.module_id'])
            ->map(fn ($row) => $row->zone_id.':'.$row->module_id)
            ->all();

        foreach (DB::table('module_zone')->get(['zone_id', 'module_id']) as $pair) {
            if (! in_array($pair->zone_id.':'.$pair->module_id, $taken, true)) {
                return [(int) $pair->zone_id, (int) $pair->module_id];
            }
        }

        $this->markTestSkipped('every connected zone/module pair already carries a setup');
    }

    private function payload(array $overrides = []): array
    {
        [$zoneId, $moduleId] = $this->freePair();

        return $overrides + [
            'zone_id' => $zoneId,
            'module_ids' => [$moduleId],
            'express_extra_charge' => 5,
            'express_reduce_delivery_time' => 15,
            'express_reduce_delivery_time_unit' => 'min',
            'delay_reduce_charge' => 3,
            'delay_add_delivery_time' => 30,
            'delay_add_delivery_time_unit' => 'min',
        ];
    }

    /** decimal(10,2) — anything larger used to be stored as 99999999.99 with no error. */
    public function test_a_charge_beyond_the_column_is_refused(): void
    {
        $before = AdditionalDeliveryCharge::count();

        $this->panel()
            ->post($this->base.'/store', $this->payload(['express_extra_charge' => 999999999999]))
            ->assertSessionHasErrors('express_extra_charge');

        $this->assertSame($before, AdditionalDeliveryCharge::count());
    }

    /** smallint unsigned — 99999 minutes used to be stored as 65535 with no error. */
    public function test_a_time_beyond_the_column_is_refused(): void
    {
        $this->panel()
            ->post($this->base.'/store', $this->payload(['express_reduce_delivery_time' => 99999]))
            ->assertSessionHasErrors('express_reduce_delivery_time');
    }

    /** Hours are multiplied out before storing, so the ceiling has to follow the unit. */
    public function test_the_time_ceiling_follows_the_chosen_unit(): void
    {
        $this->panel()->post($this->base.'/store', $this->payload([
            'express_reduce_delivery_time' => 2000,
            'express_reduce_delivery_time_unit' => 'hour',
        ]))->assertSessionHasErrors('express_reduce_delivery_time');

        $this->panel()->post($this->base.'/store', $this->payload([
            'express_reduce_delivery_time' => 1092,
            'express_reduce_delivery_time_unit' => 'hour',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            65520,
            (int) AdditionalDeliveryCharge::latest('id')->value('express_reduce_delivery_time'),
            '1092 hours is 65520 minutes — the largest the column holds',
        );
    }

    /**
     * The guard has to hold inside the transaction too: the controller's check reads, and two
     * saves racing each other both read an empty conflict list before either writes.
     */
    public function test_the_duplicate_guard_holds_under_a_locked_recheck(): void
    {
        $payload = $this->payload();

        app(AdditionalDeliveryChargeService::class)->create($payload);

        $this->expectException(DuplicateAdditionalDeliveryChargeException::class);

        // Straight to the service, skipping the controller's pre-check — exactly what the second
        // request of a race does once the first has committed.
        app(AdditionalDeliveryChargeService::class)->create($payload);
    }

    /** No message may print a raw column name at the admin. */
    public function test_validation_messages_name_the_fields_the_screen_shows(): void
    {
        $this->panel()->post($this->base.'/store', $this->payload([
            'express_extra_charge' => -1,
            'vehicle_ids' => [999999],
        ]));

        $errors = session('errors');

        $this->assertStringNotContainsString('express extra charge', $errors->first('express_extra_charge'));
        $this->assertStringNotContainsString('vehicle ids', $errors->first('vehicle_ids.0'));
    }
}
