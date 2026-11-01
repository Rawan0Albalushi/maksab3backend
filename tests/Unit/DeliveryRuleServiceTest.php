<?php

namespace Tests\Unit;

use App\Models\Area;
use App\Models\DeliveryRule;
use App\Models\ZipCode;
use App\Services\Zone\AreaService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\ZipCodeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S4 gate — delivery rules.
 *
 * Covers §18's test 14 (one active rule survives all three activation paths) and test 6 (module
 * isolation), plus baseCharge for each of the four pricing methods.
 *
 * DatabaseTransactions rather than RefreshDatabase: this runs against the working database, and
 * rebuilding it would destroy the data every other check in this port relies on.
 */
class DeliveryRuleServiceTest extends TestCase
{
    use DatabaseTransactions;

    private int $zoneId;

    private int $moduleId;

    private DeliveryRuleService $rules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rules = app(DeliveryRuleService::class);
        $this->zoneId = (int) \DB::table('zones')->value('id');
        $this->moduleId = (int) \DB::table('modules')->value('id');
    }

    private function rule(array $overrides = []): DeliveryRule
    {
        return $this->rules->create($overrides + [
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'name' => 'Test rule',
            'pricing_method' => DeliveryRule::METHOD_FIXED,
            'fixed_charge' => 20,
            'minimum_delivery_charge' => 10,
        ]);
    }

    /**
     * The FIRST rule a (zone, module) has ever had is created active; a later one is not.
     *
     * Rules used to be created switched off across the board, so that one could not "price orders
     * the moment it saved, before its charges were checked". The add form posts every charge map
     * in the same request and create() syncs them inside the same transaction, so a rule is
     * complete when it commits — while a pair whose only rule arrived switched off stayed unable
     * to price anything, with no status field on the form for anyone to have chosen that.
     *
     * The second rule keeps the old default: it is a REPLACEMENT, and handing over is
     * updateStatus()'s job, so creating it active would silently switch the live one off.
     */
    public function test_the_first_rule_for_a_pair_is_created_active_and_later_ones_are_not(): void
    {
        $first = $this->rule(['name' => 'First rule for this pair']);

        $this->assertTrue((bool) $first->status, 'the pair had no rule, so this one must arrive active');

        $second = $this->rule(['name' => 'Second rule for this pair']);

        $this->assertFalse((bool) $second->status, 'a replacement must not switch the live rule off by being saved');
    }

    public function test_no_active_rule_returns_null_so_the_caller_falls_back_to_pivot_pricing(): void
    {
        // Explicitly inactive: the first rule for a pair is now created ACTIVE, so relying on the
        // default here would have tested the opposite of what it says.
        $this->rule(['status' => false]);

        $this->assertNull(
            $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0),
            'null means "no opinion" — returning 0.0 would make every unconfigured zone free',
        );
    }

    /**
     * Test 14 — the invariant must hold whichever path switched the rule on. A controller-level
     * check would be bypassable by the other two.
     */
    public function test_only_one_rule_stays_active_whichever_path_activates_it(): void
    {
        $first = $this->rule(['name' => 'First']);
        $second = $this->rule(['name' => 'Second']);
        $third = $this->rule(['name' => 'Third']);

        // Path 1 — the status endpoint.
        $this->rules->updateStatus($first->id, 1);
        $this->assertSame([$first->id], $this->activeIds());

        // Path 2 — a direct model save.
        $second->update(['status' => true]);
        $this->assertSame([$second->id], $this->activeIds());

        // Path 3 — created already active.
        $fourth = $this->rules->create([
            'zone_id' => $this->zoneId, 'module_ids' => [$this->moduleId], 'name' => 'Fourth',
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 5,
            'minimum_delivery_charge' => 0, 'status' => true,
        ]);
        $this->assertSame([$fourth->id], $this->activeIds());

        $this->assertFalse($third->refresh()->status);
    }

    /** Test 6 — a rule for module A must never price module B. */
    public function test_a_rule_is_isolated_to_its_own_module(): void
    {
        $otherModuleId = (int) \DB::table('modules')->where('id', '!=', $this->moduleId)->value('id');

        if (! $otherModuleId) {
            $this->markTestSkipped('needs a second module');
        }

        $mine = $this->rule(['fixed_charge' => 20, 'minimum_delivery_charge' => 0]);
        $this->rules->updateStatus($mine->id, 1);

        $this->assertSame(20.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0));
        $this->assertNull(
            $this->rules->baseCharge($this->zoneId, $otherModuleId, 5.0),
            'activating a rule for one module must not price another',
        );
    }

    public function test_fixed_amount_charges_its_amount_and_respects_the_floor(): void
    {
        $rule = $this->rule(['fixed_charge' => 20, 'minimum_delivery_charge' => 10]);
        $this->rules->updateStatus($rule->id, 1);
        $this->assertSame(20.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0));

        $floored = $this->rule(['name' => 'Floored', 'fixed_charge' => 3, 'minimum_delivery_charge' => 12]);
        $this->rules->updateStatus($floored->id, 1);
        $this->assertSame(12.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0));
    }

    public function test_distance_wise_multiplies_clamps_and_floors_in_that_order(): void
    {
        $rule = $this->rule([
            'name' => 'Distance',
            'pricing_method' => DeliveryRule::METHOD_DISTANCE,
            'per_km_charge' => 2,
            'maximum_delivery_charge' => 100,
            'minimum_delivery_charge' => 10,
        ]);
        $this->rules->updateStatus($rule->id, 1);

        $this->assertSame(30.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 15.0), 'plain multiplication');
        $this->assertSame(100.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 200.0), 'clamped to the maximum');
        $this->assertSame(10.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 0.5), 'lifted to the floor');
    }

    public function test_area_wise_prices_the_pick_and_an_unpriced_area_falls_to_the_floor(): void
    {
        $area = app(AreaService::class)->create(['zone_id' => $this->zoneId, 'name' => 'Test Area '.uniqid()]);

        $rule = $this->rule([
            'name' => 'Area',
            'pricing_method' => DeliveryRule::METHOD_AREA,
            'minimum_delivery_charge' => 5,
            'charges' => [$area->id => 15],
        ]);
        $this->rules->updateStatus($rule->id, 1);

        $this->assertSame(15.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0, $area->id));

        // §5.3 says an unpriced area is 0 — but the rule's floor still applies on top, so it
        // costs the minimum rather than shipping free.
        $this->assertSame(5.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0, 99999999));
    }

    public function test_zip_code_wise_prices_the_pick(): void
    {
        $zip = app(ZipCodeService::class)->create(['zone_id' => $this->zoneId, 'zip_code' => 'T-'.uniqid()]);

        $rule = $this->rule([
            'name' => 'Zip',
            'pricing_method' => DeliveryRule::METHOD_ZIP,
            'minimum_delivery_charge' => 5,
            'charges' => [$zip->id => 25],
        ]);
        $this->rules->updateStatus($rule->id, 1);

        $this->assertSame(25.0, $this->rules->baseCharge($this->zoneId, $this->moduleId, 5.0, null, $zip->id));
    }

    /** §14.3 — empty is the answer for whole-zone methods, not a missing one. */
    public function test_coverage_is_empty_for_the_methods_that_price_the_whole_zone(): void
    {
        $rule = $this->rule(['name' => 'Distance', 'pricing_method' => DeliveryRule::METHOD_DISTANCE, 'per_km_charge' => 2]);
        $this->rules->updateStatus($rule->id, 1);

        $coverage = $this->rules->coverageForZone($this->zoneId, $this->moduleId);

        $this->assertSame(DeliveryRule::METHOD_DISTANCE, $coverage['type']);
        $this->assertSame([], $coverage['coverage']);
    }

    public function test_coverage_is_null_typed_when_no_rule_is_active(): void
    {
        // Explicit, for the same reason as the fallback test above.
        $this->rule(['status' => false]);

        $this->assertSame(['type' => null, 'coverage' => []], $this->rules->coverageForZone($this->zoneId, $this->moduleId));
    }

    /** §5.4 — a pick from another zone must be refused on every path. */
    public function test_a_coverage_pick_from_another_zone_is_refused(): void
    {
        $area = app(AreaService::class)->create(['zone_id' => $this->zoneId, 'name' => 'Sec '.uniqid()]);

        $this->assertTrue($this->rules->coverageBelongsToZone($this->zoneId, $area->id));
        $this->assertFalse($this->rules->coverageBelongsToZone($this->zoneId + 99999, $area->id));
        $this->assertFalse($this->rules->coverageBelongsToZone($this->zoneId, null, null), 'no pick is not a valid pick');
    }

    /** Switching method clears coverage rows, so switching back cannot resurrect stale charges. */
    public function test_switching_away_from_a_coverage_method_clears_its_charges(): void
    {
        $area = app(AreaService::class)->create(['zone_id' => $this->zoneId, 'name' => 'Sw '.uniqid()]);

        $rule = $this->rule([
            'pricing_method' => DeliveryRule::METHOD_AREA,
            'minimum_delivery_charge' => 0,
            'charges' => [$area->id => 15],
        ]);
        $this->assertCount(1, $rule->charges);

        $this->rules->update($rule->id, [
            'zone_id' => $this->zoneId, 'module_ids' => [$this->moduleId], 'name' => 'Now fixed',
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 9,
            'minimum_delivery_charge' => 0,
        ]);

        $this->assertCount(0, $rule->refresh()->charges);
        $this->assertNull($rule->per_km_charge, 'columns of the methods not chosen are nulled, not left stale');
    }

    private function activeIds(): array
    {
        return DeliveryRule::active()
            ->forZoneModule($this->zoneId, $this->moduleId)
            ->orderBy('id')->pluck('id')->all();
    }

    /**
     * The turn-off dialog makes the admin nominate a replacement, so the zone is never left
     * without an active rule. These pin the swap and the two ways it must refuse.
     */
    public function test_turning_a_rule_off_activates_the_replacement(): void
    {
        $outgoing = $this->rule(['name' => 'Outgoing']);
        $incoming = $this->rule(['name' => 'Incoming']);
        $this->rules->updateStatus($outgoing->id, 1);

        $this->assertTrue($this->rules->deactivateWithReplacement($outgoing->id, $incoming->id));

        $this->assertFalse($outgoing->refresh()->status, 'the outgoing rule is switched off');
        $this->assertTrue($incoming->refresh()->status, 'and the zone still has an active rule');
    }

    /** Activating then deactivating one row would leave the zone with nothing active. */
    public function test_a_rule_cannot_replace_itself(): void
    {
        $rule = $this->rule(['name' => 'Only']);
        $this->rules->updateStatus($rule->id, 1);

        $this->assertFalse($this->rules->deactivateWithReplacement($rule->id, $rule->id));
        $this->assertTrue($rule->refresh()->status, 'and it stays active');
    }

    /** A replacement from another zone would switch this zone off and price nothing. */
    public function test_a_replacement_must_belong_to_the_same_zone(): void
    {
        $otherZoneId = (int) \DB::table('zones')->where('id', '!=', $this->zoneId)->value('id');

        if (! $otherZoneId) {
            $this->markTestSkipped('needs a second zone');
        }

        $rule = $this->rule(['name' => 'Mine']);
        $this->rules->updateStatus($rule->id, 1);
        $foreign = $this->rules->create([
            'zone_id' => $otherZoneId, 'module_ids' => [$this->moduleId], 'name' => 'Foreign',
            'pricing_method' => DeliveryRule::METHOD_FIXED, 'fixed_charge' => 5, 'minimum_delivery_charge' => 0,
        ]);

        $this->assertFalse($this->rules->deactivateWithReplacement($rule->id, $foreign->id));
        $this->assertTrue($rule->refresh()->status);
    }

    public function test_the_replacement_picker_offers_the_zones_other_rules_only(): void
    {
        $mine = $this->rule(['name' => 'Picker mine']);
        $other = $this->rule(['name' => 'Picker other']);

        $offered = $this->rules->selectableForZone($this->zoneId, $mine->id)->pluck('id')->all();

        $this->assertContains($other->id, $offered);
        $this->assertNotContains($mine->id, $offered, 'a rule is never offered as its own replacement');
    }
}
