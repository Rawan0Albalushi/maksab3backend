<?php

namespace Tests\Unit;

use App\Models\AdditionalDeliveryCharge;
use App\Models\DeliveryRule;
use App\Models\EtaConfiguration;
use App\Models\FreeDelivery;
use App\Models\SurgePrice;
use App\Models\Zone;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Zone\SurgePriceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * S19 · W3 and W4 — what the edit forms warn about before a save drops a module.
 *
 * Every one of the five zone setups answers the same question: which of MY modules would be left
 * with no cover at all if I stopped covering them? The edit form compares the ids it opened with
 * against the ids at submit and names only the removed ones that appear in this answer.
 *
 * The distinction the two groups draw is the point of W4 and is asserted here:
 *
 *   delivery rule / ETA  → the module becomes UNAVAILABLE in the zone
 *   free delivery / surge / additional charge  → only that ADD-ON stops applying
 *
 * The second group must not touch availability, and the last test says so directly rather than
 * trusting that nobody adds them to `completeBetween()` later.
 */
class ModuleRemovalWarningTest extends TestCase
{
    use DatabaseTransactions;

    /** A rule with two modules, active, in a zone of its own so nothing else covers them. */
    private function ruleWithTwoModules(): DeliveryRule
    {
        $zoneId = DB::table('delivery_rules')->value('zone_id');

        if (! $zoneId) {
            $this->markTestSkipped('needs a delivery rule to borrow a zone from');
        }

        $moduleIds = array_slice(app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds(), 0, 2);

        if (count($moduleIds) < 2) {
            $this->markTestSkipped('needs two modules that can hold a delivery rule');
        }

        // Everything else in the zone stands down, so "the only cover" is unambiguous.
        DB::table('delivery_rules')->where('zone_id', $zoneId)->update(['status' => 0]);

        $rule = DeliveryRule::create([
            'zone_id' => $zoneId,
            'name' => 'Solo cover probe',
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'minimum_delivery_charge' => 10,
            'per_km_charge' => 5,
            'status' => 1,
        ]);

        $rule->modules()->sync($moduleIds);

        return $rule->load('modules');
    }

    public function test_a_rule_names_the_modules_it_alone_covers(): void
    {
        $rule = $this->ruleWithTwoModules();

        $solo = app(DeliveryRuleService::class)->soloModules($rule);

        $this->assertSame(
            $rule->modules->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            collect(array_keys($solo))->sort()->values()->all(),
            'with no other active rule in the zone, every module on this one is losing its cover',
        );
    }

    /**
     * Prompt item 6 — a module another active rule still covers is not going dark.
     *
     * The half that matters. Warning about every removed module would train the admin to click
     * through the dialog without reading it, which costs more than showing no dialog at all.
     */
    public function test_a_module_another_active_rule_covers_is_not_named(): void
    {
        $rule = $this->ruleWithTwoModules();
        $covered = (int) $rule->modules->first()->id;

        $second = DeliveryRule::create([
            'zone_id' => $rule->zone_id,
            'name' => 'Second cover',
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'minimum_delivery_charge' => 10,
            'per_km_charge' => 5,
            'status' => 1,
        ]);
        $second->modules()->sync([$covered]);

        $solo = app(DeliveryRuleService::class)->soloModules($rule->fresh()->load('modules'));

        $this->assertArrayNotHasKey($covered, $solo, 'another active rule already covers it');
        $this->assertNotEmpty($solo, 'the module nothing else covers is still named');
    }

    /** An inactive setup covers nothing, so editing it can take nothing away. */
    public function test_an_inactive_setup_warns_about_nothing(): void
    {
        $rule = $this->ruleWithTwoModules();
        $rule->forceFill(['status' => 0])->save();

        $this->assertSame([], app(DeliveryRuleService::class)->soloModules($rule->fresh()->load('modules')));
    }

    /** The ETA side of W3 answers the same shape. */
    public function test_an_eta_configuration_names_the_modules_it_alone_covers(): void
    {
        $configuration = EtaConfiguration::with('modules')->where('status', 1)->first();

        if (! $configuration || $configuration->modules->isEmpty()) {
            $this->markTestSkipped('needs an active ETA configuration with a module');
        }

        // DB::table() is the query builder, which has no whereKeyNot() — that is Eloquent's.
        DB::table('eta_configurations')
            ->where('zone_id', $configuration->zone_id)
            ->where('id', '!=', $configuration->getKey())
            ->update(['status' => 0]);

        $solo = app(EtaConfigurationService::class)->soloModules($configuration->fresh()->load('modules'));

        $this->assertNotEmpty($solo);
        $this->assertSame(
            $configuration->modules->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all(),
            collect(array_keys($solo))->sort()->values()->all(),
        );
    }

    /**
     * Prompt item 7 — an add-on's module list is not an availability list.
     *
     * Free delivery, surge and additional charge answer the same "who else covers it" question
     * for their own warning, and none of them may reach availability. Asserted by removing every
     * one of them from a zone and watching the available module set stand still.
     */
    public function test_removing_an_add_on_does_not_change_availability(): void
    {
        $zone = Zone::withoutGlobalScopes()
            ->whereHas('deliveryRules', fn ($q) => $q->where('status', 1))
            ->first();

        if (! $zone) {
            $this->markTestSkipped('needs a zone with an active delivery rule');
        }

        $before = $zone->effectiveModuleIds();

        DB::table('free_deliveries')->where('zone_id', $zone->id)->update(['status' => 0]);
        DB::table('surge_prices')->where('zone_id', $zone->id)->update(['status' => 0]);
        DB::table('additional_delivery_charges')->where('zone_id', $zone->id)->update(['status' => 0]);

        $this->assertSame($before, $zone->fresh()->effectiveModuleIds(), 'add-ons must not gate availability');
    }

    /** And the three add-on services still answer the warning question when asked. */
    public function test_the_add_on_services_answer_the_same_question(): void
    {
        $checked = 0;

        foreach ([
            [FreeDelivery::class, FreeDeliveryService::class],
            [AdditionalDeliveryCharge::class, AdditionalDeliveryChargeService::class],
        ] as [$model, $service]) {
            $setup = $model::with('modules')->where('status', 1)->first();

            if (! $setup) {
                continue;
            }

            $checked++;
            $solo = app($service)->soloModules($setup);

            foreach (array_keys($solo) as $moduleId) {
                $this->assertContains(
                    (int) $moduleId,
                    $setup->modules->pluck('id')->map(fn ($id) => (int) $id)->all(),
                    'a setup can only be the sole cover for a module it actually carries',
                );
            }
        }

        // Surge keeps its module ids in a JSON column, so its service answers separately.
        $surge = SurgePrice::where('status', 1)->first();

        if ($surge) {
            $checked++;
            foreach (array_keys(app(SurgePriceService::class)->soloModules($surge)) as $moduleId) {
                $this->assertContains((int) $moduleId, array_map('intval', (array) $surge->module_ids));
            }
        }

        if ($checked === 0) {
            $this->markTestSkipped('no active add-on setup on this install');
        }

        $this->assertGreaterThan(0, $checked);
    }
}
