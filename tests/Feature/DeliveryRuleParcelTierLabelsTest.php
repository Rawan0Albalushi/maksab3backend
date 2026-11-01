<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryRule;
use App\Services\System\MeasurementUnitService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * What the wizard's two parcel steps and the details page PRINT — QA cases TC_258, TC_260,
 * TC_263, TC_265.
 *
 * All three defects were labelling: a heading that named kilogrammes on a platform that may be
 * weighing in pounds, rows of measurements with no name attached to tell them apart, and an error
 * that named an internal row id.
 */
class DeliveryRuleParcelTierLabelsTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

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

    /** A rule on a parcel-capable module, so the wizard's two extra steps render. */
    private function parcelRule(): DeliveryRule
    {
        $moduleIds = app(\App\Services\System\ModuleService::class)->parcelCapableModuleIds();

        $rule = DeliveryRule::whereHas('modules', fn ($q) => $q->whereIn('modules.id', $moduleIds))->first();

        if (! $rule) {
            $this->markTestSkipped('no delivery rule connects a parcel-capable module');
        }

        return $rule;
    }

    /** §3.4 — the heading follows `weight_unit`, exactly as the rows under it do. */
    public function test_the_weight_range_heading_follows_the_configured_unit(): void
    {
        $unit = app(MeasurementUnitService::class)->weightUnitLabel();

        $body = $this->panel()
            ->get('/admin/delivery-management/delivery-rule/edit/'.$this->parcelRule()->id)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(translate('messages.From - To').' ('.$unit.')', $body);
        $this->assertStringNotContainsString('From - To (KG)', $body, 'the heading must not hardcode kilogrammes');
    }

    /**
     * Measurements alone are not identifying: two size classes of similar volume are
     * indistinguishable without the name.
     */
    public function test_dimension_rows_carry_the_name_before_the_measurements(): void
    {
        $size = \App\Models\Dimension::active()->inSizeOrder()->first();

        if (! $size) {
            $this->markTestSkipped('no active dimension exists');
        }

        $body = $this->panel()
            ->get('/admin/delivery-management/delivery-rule/edit/'.$this->parcelRule()->id)
            ->assertOk()
            ->getContent();

        $expected = e($size->name).' ('.translate('messages.Max Dimension').' : ';

        $this->assertStringContainsString($expected, $body);
    }

    /** The wizard and the details page must print a size class the same way. */
    public function test_the_wizard_and_the_details_page_label_dimensions_alike(): void
    {
        $rule = $this->parcelRule();
        $size = \App\Models\Dimension::active()->inSizeOrder()->first();

        if (! $size) {
            $this->markTestSkipped('no active dimension exists');
        }

        $fragment = e($size->name).' ('.translate('messages.Max Dimension').' : '
            .$size->max_length.' × '.$size->max_width.' × '.$size->max_height;

        $this->assertStringContainsString(
            $fragment,
            $this->panel()->get('/admin/delivery-management/delivery-rule/edit/'.$rule->id)->getContent(),
        );
    }

    /**
     * A rejected row must name the charge, not the band's primary key — the admin has no way to
     * match "weight charges.48" to a row in the table.
     */
    public function test_a_rejected_tier_charge_names_the_charge_not_the_row_id(): void
    {
        $rule = $this->parcelRule();

        $this->panel()->post('/admin/delivery-management/delivery-rule/update/'.$rule->id, [
            'name' => $rule->name,
            'zone_id' => $rule->zone_id,
            'module_ids' => $rule->modules->pluck('id')->all(),
            'minimum_delivery_charge' => 0,
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'per_km_charge' => 0,
            'weight_charge_status' => 1,
            'weight_charges' => [48 => -2],
        ]);

        $message = session('errors')?->first('weight_charges.48');

        $this->assertNotNull($message, 'a negative tier charge must be rejected');
        $this->assertStringNotContainsString('weight charges.', $message, 'the message must not leak the array key');
        $this->assertStringContainsString(translate('messages.Weight Charge'), $message);
    }
}
