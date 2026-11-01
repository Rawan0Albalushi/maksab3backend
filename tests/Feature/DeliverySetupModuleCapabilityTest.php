<?php

namespace Tests\Feature;

use App\Models\Zone;
use App\Services\System\ModuleService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Zone\SurgePriceService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rental, ride-share and service carry neither an ETA nor a surge price.
 *
 * Both settings describe an order the platform delivers: an ETA is "how long until this reaches
 * you", a surge is an addition to the delivery charge. A ride quotes its own arrival, a rental is
 * booked for a period, a service is scheduled for a slot — none has a delivery window, and none
 * reaches EtaService or DeliveryChargeService.
 *
 * Each has to hold in three places at once or the feature contradicts itself: the picker must not
 * offer them, the request must refuse them, and — for ETA — zone readiness must not ask them for
 * something they can never have.
 */
class DeliverySetupModuleCapabilityTest extends TestCase
{
    private const NO_ETA = ['rental', 'ride-share', 'service'];

    public function test_the_capability_is_off_for_exactly_those_three_types(): void
    {
        foreach (config('module.module_type') as $type) {
            $expected = ! in_array($type, self::NO_ETA, true);

            $this->assertSame($expected, (bool) config("module.$type.eta"), "module.$type.eta is wrong");
            $this->assertSame($expected, (bool) config("module.$type.surge"), "module.$type.surge is wrong");
            $this->assertSame($expected, (bool) config("module.$type.delivery_charge_setup"),
                "module.$type.delivery_charge_setup is wrong");
            $this->assertSame($expected, (bool) config("module.$type.delivery_rule"),
                "module.$type.delivery_rule is wrong");
        }
    }

    public function test_the_surge_picker_never_offers_a_module_that_cannot_have_one(): void
    {
        $modules = app(ModuleService::class);
        $capable = $modules->surgeCapableModuleIds();

        $offered = $modules->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $capable, true));

        $this->assertNotEmpty($offered, 'some module must be able to carry a surge');

        foreach ($offered as $module) {
            $this->assertNotContains($module->module_type, self::NO_ETA,
                "the surge picker offered {$module->module_type}");
        }
    }

    /** Hiding is not enforcing, for surge as for ETA. */
    public function test_the_surge_request_guard_refuses_an_incapable_module(): void
    {
        $service = app(SurgePriceService::class);
        $capable = app(ModuleService::class)->surgeCapableModuleIds();

        $incapable = DB::table('modules')->whereNotIn('id', $capable ?: [0])->value('id');

        if (! $incapable) {
            $this->markTestSkipped('needs a module with no surge capability');
        }

        $this->assertNotEmpty($service->surgeIncapableModuleNames([$incapable]));
        $this->assertSame([], $service->surgeIncapableModuleNames($capable));
    }

    public function test_the_picker_never_offers_a_module_that_cannot_have_one(): void
    {
        $zoneId = DB::table('module_zone')->value('zone_id');

        if (! $zoneId) {
            $this->markTestSkipped('needs a zone with connected modules');
        }

        foreach ([null, $zoneId] as $zone) {
            $offered = app(EtaConfigurationService::class)->modulePickerForZone($zone)['modules'];

            foreach ($offered as $module) {
                $this->assertNotContains($module->module_type, self::NO_ETA,
                    "the picker offered {$module->module_type}".($zone ? " for zone $zone" : ' with no zone'));
            }
        }
    }

    /** Hiding is not enforcing: a crafted POST naming one must be refused. */
    public function test_the_request_guard_refuses_an_incapable_module(): void
    {
        $service = app(EtaConfigurationService::class);
        $capable = app(ModuleService::class)->etaCapableModuleIds();

        $incapable = DB::table('modules')->whereNotIn('id', $capable ?: [0])->value('id');

        if (! $incapable) {
            $this->markTestSkipped('needs a module with no ETA capability');
        }

        $this->assertNotEmpty($service->etaIncapableModuleNames([$incapable]));
        $this->assertSame([], $service->etaIncapableModuleNames($capable));
    }

    /**
     * Free delivery and the additional delivery charge share one capability — they are the same
     * question asked twice, and both move the delivery charge.
     */
    public function test_the_delivery_charge_setups_never_offer_an_incapable_module(): void
    {
        $zoneId = DB::table('module_zone')->value('zone_id');

        if (! $zoneId) {
            $this->markTestSkipped('needs a zone with connected modules');
        }

        $services = [
            'free delivery' => app(FreeDeliveryService::class),
            'additional charge' => app(AdditionalDeliveryChargeService::class),
        ];

        $capable = app(ModuleService::class)->deliveryChargeSetupModuleIds();
        $incapable = DB::table('modules')->whereNotIn('id', $capable ?: [0])->pluck('id')->all();

        foreach ($services as $label => $service) {
            foreach ([null, $zoneId] as $zone) {
                foreach ($service->modulePickerForZone($zone)['modules'] as $module) {
                    $this->assertNotContains($module->module_type, self::NO_ETA,
                        "$label offered {$module->module_type}");
                }
            }

            if ($incapable) {
                $this->assertNotEmpty($service->incapableModuleNames($incapable), "$label must refuse them");
            }

            $this->assertSame([], $service->incapableModuleNames($capable), "$label must allow the capable ones");
        }
    }

    /**
     * Delivery rules exclude the same three, with one difference: a rule that ALREADY covers an
     * incapable module keeps it. Eight such rules exist from the readiness backfill, and dropping
     * them from the edit picker would silently unassign the module on save.
     */
    public function test_delivery_rules_refuse_new_incapable_modules_but_keep_assigned_ones(): void
    {
        $service = app(DeliveryRuleService::class);
        $capable = app(ModuleService::class)->deliveryRuleModuleIds();

        // The SOLE rule covering that module in its zone, and a module the zone is connected to.
        // Both matter, and neither is about capability: a module a second rule also covers is
        // "taken" by that one, and a module the zone does not serve is filtered out — the picker
        // has applied both rules since long before this change.
        $existing = collect(DB::table('delivery_rule_module as drm')
            ->join('delivery_rules as dr', 'dr.id', '=', 'drm.delivery_rule_id')
            ->whereNotIn('drm.module_id', $capable ?: [0])
            ->get(['dr.id as rule_id', 'dr.zone_id', 'drm.module_id']))
            ->first(function ($row) {
                $connected = DB::table('module_zone')
                    ->where('zone_id', $row->zone_id)->where('module_id', $row->module_id)->exists();

                $others = DB::table('delivery_rule_module as drm')
                    ->join('delivery_rules as dr', 'dr.id', '=', 'drm.delivery_rule_id')
                    ->where('dr.zone_id', $row->zone_id)
                    ->where('drm.module_id', $row->module_id)
                    ->where('dr.id', '!=', $row->rule_id)
                    ->exists();

                return $connected && ! $others;
            });

        if (! $existing) {
            $this->markTestSkipped('needs a rule that is the sole holder of a connected incapable module');
        }

        // A NEW rule may not name it.
        $this->assertNotEmpty($service->incapableModuleNames([$existing->module_id]));

        // The rule that already holds it may keep it.
        $this->assertSame([], $service->incapableModuleNames([$existing->module_id], $existing->rule_id),
            'editing a rule must not refuse the module it already covers');

        // And the edit picker still offers it, so saving cannot silently drop it.
        $offered = $service->modulePickerForZone($existing->zone_id, $existing->rule_id)['modules']
            ->pluck('id')->map('intval');

        $this->assertContains((int) $existing->module_id, $offered->all());
    }

    /** Readiness must not ask an incapable module for a delivery rule either. */
    public function test_readiness_exempts_modules_that_cannot_carry_a_delivery_rule(): void
    {
        $capable = app(ModuleService::class)->deliveryRuleModuleIds();
        $incapable = DB::table('modules')->whereNotIn('id', $capable ?: [0])->pluck('id')->map('intval')->all();

        if (! $incapable || ! $capable) {
            $this->markTestSkipped('needs both capable and incapable modules');
        }

        $connected = array_merge($capable, $incapable);

        // Capable modules are ruled and timed; the incapable ones have neither.
        $gaps = Zone::gapsBetween($connected, $capable, $capable);

        $this->assertSame([], $gaps, 'an incapable module must not be counted as missing either setup');
    }

    /**
     * The regression this exists for: readiness must ask only the modules that can answer.
     *
     * Requiring an ETA from every connected module made a zone connected to rental or service
     * permanently unready — it could never be switched on, however much the admin configured.
     */
    public function test_readiness_exempts_modules_that_cannot_have_an_eta(): void
    {
        $capable = app(ModuleService::class)->etaCapableModuleIds();
        $incapable = DB::table('modules')->whereNotIn('id', $capable ?: [0])->pluck('id')->map('intval')->all();

        if (! $incapable || ! $capable) {
            $this->markTestSkipped('needs both capable and incapable modules');
        }

        $connected = array_merge($capable, $incapable);

        // Every capable module has a rule and an ETA; the incapable ones have neither.
        $gaps = Zone::gapsBetween($connected, $connected, $capable);

        $this->assertNotContains('eta', $gaps,
            'a module that cannot have an ETA must not be counted as missing one');
        $this->assertSame([], $gaps);
    }

    /**
     * S19 — a capable module with no ETA is unavailable, not a gap.
     *
     * This asserted the opposite: one capable module short of an estimate used to hold the whole
     * zone back. It no longer does, and the module is reported as unavailable instead — so the
     * new truth is asserted here, in both directions.
     */
    public function test_a_capable_module_without_an_eta_is_unavailable_not_a_gap(): void
    {
        $capable = app(ModuleService::class)->etaCapableModuleIds();

        if (count($capable) < 2) {
            $this->markTestSkipped('needs two eta-capable modules');
        }

        $timed = [reset($capable)];

        // All connected and ruled, but only one of the capable modules is timed. That one is
        // complete, so the zone may be switched on...
        $this->assertSame([], Zone::gapsBetween($capable, $capable, $timed));

        // ...and the untimed ones are what it costs. Sorted, as completeBetween() returns them —
        // callers compare these with `==` and json-encode them, so the order is part of the answer.
        $untimed = array_values(array_diff($capable, $timed));
        sort($untimed);

        $this->assertSame(
            $untimed,
            Zone::incompleteBetween($capable, $capable, $timed),
            'the modules with no estimate must be reported as unavailable',
        );
    }

    /** With NO capable module timed, nothing is complete and the ETA gap still refuses. */
    public function test_a_zone_with_no_timed_module_at_all_still_reports_the_eta_gap(): void
    {
        $capable = app(ModuleService::class)->etaCapableModuleIds();

        if ($capable === []) {
            $this->markTestSkipped('needs an eta-capable module');
        }

        $gaps = Zone::gapsBetween($capable, $capable, []);

        $this->assertContains('eta', $gaps, 'the rule must still catch a real missing ETA');
        $this->assertNotContains('delivery_rule', $gaps, 'the rules are all there — do not ask for them again');
    }
}
