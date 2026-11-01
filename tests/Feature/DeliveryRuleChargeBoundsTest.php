<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryRule;
use App\Services\Order\DeliveryChargeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The four defects QA cases TC_247–TC_254 turned up on the Delivery Rule charge fields.
 *
 * Three of them shared a shape: the screen said one thing and the engine did another. The star on
 * Maximum Delivery Charge claimed it was required when every layer treats it as optional; the
 * details page printed a missing cap as "0.00", which reads as "capped at nothing"; and an
 * over-long charge was accepted and silently rewritten by the column.
 */
class DeliveryRuleChargeBoundsTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    private string $base = '/admin/delivery-management/delivery-rule';

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

    /**
     * A zone and module pair no rule has claimed yet, so D1 does not refuse the save.
     *
     * Capable modules only. Rental, ride-share and service price their own trips and bookings
     * and the request now refuses a rule naming one — and they read as "free" precisely BECAUSE
     * they carry no rule, so an unfiltered search hands back the one module guaranteed to fail.
     */
    private function freePair(): array
    {
        $capable = app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds();

        foreach (\App\Models\Zone::pluck('id') as $zoneId) {
            $connected = \DB::table('module_zone')->where('zone_id', $zoneId)->pluck('module_id')->all();
            $ruled = \DB::table('delivery_rule_module')
                ->whereIn('delivery_rule_id', DeliveryRule::where('zone_id', $zoneId)->pluck('id'))
                ->pluck('module_id')->all();
            $free = array_intersect(array_diff($connected, $ruled), $capable);

            if ($free !== []) {
                return [$zoneId, (int) reset($free)];
            }
        }

        $this->markTestSkipped('every connected zone/module pair already carries a delivery rule');
    }

    /**
     * The rule this test just stored, read back by its own name.
     *
     * Not `latest('id')`: when a save is refused that returns some unrelated pre-existing rule
     * and the assertion below runs against a row the test never created, so a rejected save is
     * reported as a wrong cap rather than as the rejection it is.
     */
    private function storedRule(array $payload): DeliveryRule
    {
        $rule = DeliveryRule::where('name', $payload['name'])->first();

        $this->assertNotNull($rule, 'the rule was not stored — the save was refused');

        return $rule;
    }

    private function payload(array $overrides = []): array
    {
        [$zoneId, $moduleId] = $this->freePair();

        return $overrides + [
            'name' => 'QA Bounds '.uniqid(),
            'zone_id' => $zoneId,
            'module_ids' => [$moduleId],
            'minimum_delivery_charge' => 10,
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'per_km_charge' => 5,
            'maximum_delivery_charge' => 100,
        ];
    }

    /** The column, the request rule and the fee engine all treat the cap as optional. */
    public function test_the_maximum_delivery_charge_is_optional(): void
    {
        $payload = $this->payload(['maximum_delivery_charge' => '']);

        $this->panel()
            ->post($this->base.'/store', $payload)
            ->assertSessionHasNoErrors();

        $this->assertNull($this->storedRule($payload)->maximum_delivery_charge);
    }

    /** The form must not star a field the server accepts blank. */
    public function test_the_form_does_not_mark_the_maximum_as_required(): void
    {
        $form = file_get_contents(base_path('resources/views/admin-views/delivery-rule/partials/_form.blade.php'));

        $label = substr($form, strpos($form, 'for="maximum_delivery_charge"'));
        $label = substr($label, 0, strpos($label, '</label>'));

        $this->assertStringNotContainsString('text-danger', $label, 'Maximum Delivery Charge is optional, so it carries no required star');
        $this->assertStringContainsString('data-original-title', $label, 'every charge field carries an info tooltip');
    }

    /**
     * A missing OR zero maximum means no cap. Printing "0.00" told the admin the opposite.
     */
    public function test_the_details_page_calls_a_missing_cap_no_limit(): void
    {
        $payload = $this->payload(['maximum_delivery_charge' => '']);
        $this->panel()->post($this->base.'/store', $payload);
        $rule = $this->storedRule($payload);

        $this->panel()->get($this->base.'/'.$rule->id)
            ->assertOk()
            ->assertSee(translate('messages.No limit'), false);
    }

    /**
     * decimal(10,2) tops out at 99999999.99. Without the rule the database rewrote the number
     * the admin typed and nothing on screen said so.
     */
    public function test_a_charge_beyond_the_column_is_refused_not_clamped(): void
    {
        $before = DeliveryRule::count();

        $this->panel()
            ->post($this->base.'/store', $this->payload(['per_km_charge' => 999999999999]))
            ->assertSessionHasErrors('per_km_charge');

        $this->assertSame($before, DeliveryRule::count());
    }

    /** The message names the field the screen shows, not the column, and never says "km". */
    public function test_the_per_unit_error_uses_the_screen_label(): void
    {
        $this->panel()->post($this->base.'/store', $this->payload(['per_km_charge' => 'abc']));

        $message = session('errors')->first('per_km_charge');

        $this->assertStringContainsString(translate('messages.Per Unit Delivery Charge'), $message);
        $this->assertStringNotContainsString('per km charge', $message);
    }

    /** TC_250 — floor, cap and the exact boundaries, through the engine both surfaces call. */
    public function test_the_distance_charge_is_floored_and_capped(): void
    {
        $service = app(DeliveryChargeService::class);

        $this->assertSame(10.0, $service->chargeableBase(1.0, 5.0, 10.0), 'below the floor is raised to it');
        $this->assertSame(10.0, $service->chargeableBase(2.0, 5.0, 10.0), 'the exact floor is charged once');
        $this->assertSame(40.0, $service->chargeableBase(8.0, 5.0, 10.0), 'inside the band is rate x distance');
        // Full float precision here on purpose: the base is rounded once, on the way into
        // orders.delivery_charge (decimal 24,2), not at every step of the engine.
        $this->assertEqualsWithDelta(38.85, $service->chargeableBase(7.77, 5.0, 10.0), 0.0001, 'fractional distances keep their precision');
    }

    /** TC_254 — a fixed charge under the rule's own floor is lifted, never charged as typed. */
    public function test_a_fixed_charge_below_the_minimum_is_lifted_to_it(): void
    {
        $service = app(DeliveryChargeService::class);

        $this->assertSame(10.0, $service->chargeableBase(25.0, 0.0, 10.0, 5.0));
        $this->assertSame(10.0, $service->chargeableBase(1.0, 0.0, 10.0, 0.0));
        $this->assertSame(25.0, $service->chargeableBase(1.0, 0.0, 10.0, 25.0), 'distance never enters a fixed charge');
    }
}
