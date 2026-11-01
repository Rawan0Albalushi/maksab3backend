<?php

namespace Tests\Unit;

use App\Models\DeliveryRule;
use App\Models\EtaConfiguration;
use App\Models\Module;
use App\Models\Zone;
use App\Scopes\ZoneScope;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\EtaConfigurationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * A (zone, module) pair must never be left with no delivery rule and no ETA.
 *
 * Losing the rule means the pair cannot price an order; losing the ETA means §11.2 has the
 * storefront show no delivery time at all rather than invent one — so neither failure announces
 * itself. Both used to be reachable from the list screen's status toggle:
 *
 *   - the delivery rule dialog asks the admin to nominate a replacement, but checked only that it
 *     belonged to the same ZONE. Handing Grocery over to the zone's Pharmacy rule was accepted and
 *     reported success, leaving Grocery with nothing active.
 *   - the ETA toggle had no guard whatsoever.
 */
class LastSetupCannotBeSwitchedOffTest extends TestCase
{
    use DatabaseTransactions;

    private function zoneId(): int
    {
        return (int) Zone::withoutGlobalScope(ZoneScope::class)->value('id');
    }

    /** Two module ids that exist, so "same zone, different module" is expressible. */
    private function twoModuleIds(): array
    {
        $ids = Module::query()->orderBy('id')->limit(2)->pluck('id')->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('needs two modules');
        }

        return $ids;
    }

    private function makeRule(int $zoneId, int $moduleId, bool $status): DeliveryRule
    {
        $rule = DeliveryRule::create([
            'name' => 'QA rule m'.$moduleId,
            'zone_id' => $zoneId,
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'status' => $status,
        ]);
        $rule->modules()->sync([$moduleId]);

        return $rule->load('modules');
    }

    public function test_a_replacement_covering_a_different_module_does_not_count_as_a_hand_over(): void
    {
        [$moduleA, $moduleB] = $this->twoModuleIds();
        $zoneId = $this->zoneId();

        $grocery = $this->makeRule($zoneId, $moduleA, true);
        $pharmacy = $this->makeRule($zoneId, $moduleB, true);

        $uncovered = app(DeliveryRuleService::class)->modulesLeftUncovered($grocery, $pharmacy);

        $this->assertNotEmpty(
            $uncovered,
            'a rule for another module was accepted as a replacement, leaving this one uncovered',
        );
    }

    public function test_a_replacement_covering_the_same_module_is_a_complete_hand_over(): void
    {
        [$moduleA] = $this->twoModuleIds();
        $zoneId = $this->zoneId();

        $current = $this->makeRule($zoneId, $moduleA, true);
        $replacement = $this->makeRule($zoneId, $moduleA, false);

        $this->assertSame(
            [],
            app(DeliveryRuleService::class)->modulesLeftUncovered($current, $replacement),
            'a rule covering the same module is a valid hand-over and must be allowed',
        );
    }

    /** A module another ACTIVE rule already covers is not left uncovered by this one. */
    public function test_a_module_another_active_rule_already_covers_is_not_uncovered(): void
    {
        [$moduleA] = $this->twoModuleIds();
        $zoneId = $this->zoneId();

        $current = $this->makeRule($zoneId, $moduleA, true);
        $this->makeRule($zoneId, $moduleA, true);

        $this->assertSame([], app(DeliveryRuleService::class)->modulesLeftUncovered($current->fresh('modules'), null));
    }

    public function test_the_only_eta_for_a_pair_reports_its_module_as_locked(): void
    {
        [$moduleA] = $this->twoModuleIds();
        $zoneId = $this->zoneId();

        $setup = EtaConfiguration::create([
            'name' => 'QA eta',
            'zone_id' => $zoneId,
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 10,
            'status' => true,
        ]);
        $setup->modules()->sync([$moduleA]);

        $this->assertNotEmpty(
            app(EtaConfigurationService::class)->modulesLeftWithoutEta($setup->id),
            'the only ETA for this pair must be reported as locked',
        );
    }

    /** An already-inactive configuration locks nothing — switching it off again changes nothing. */
    public function test_an_inactive_eta_locks_nothing(): void
    {
        [$moduleA] = $this->twoModuleIds();

        $setup = EtaConfiguration::create([
            'name' => 'QA eta off',
            'zone_id' => $this->zoneId(),
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 10,
            'status' => false,
        ]);
        $setup->modules()->sync([$moduleA]);

        $this->assertSame([], app(EtaConfigurationService::class)->modulesLeftWithoutEta($setup->id));
    }

    /** The page-wide version must agree with the per-row one, and in far fewer queries. */
    public function test_the_page_wide_lock_map_agrees_with_the_per_row_answer(): void
    {
        [$moduleA] = $this->twoModuleIds();

        $setup = EtaConfiguration::create([
            'name' => 'QA eta page',
            'zone_id' => $this->zoneId(),
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 10,
            'status' => true,
        ]);
        $setup->modules()->sync([$moduleA]);

        $service = app(EtaConfigurationService::class);
        $map = $service->lockedModulesFor(EtaConfiguration::with('modules')->whereKey($setup->id)->get());

        $this->assertSame($service->modulesLeftWithoutEta($setup->id), $map[$setup->id]);
    }
}
