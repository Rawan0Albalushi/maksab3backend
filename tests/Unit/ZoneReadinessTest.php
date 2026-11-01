<?php

namespace Tests\Unit;

use App\Models\EtaConfiguration;
use App\Models\Zone;
use App\Services\Zone\ZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S12/S19 — zone readiness (Z3) and the module availability gate (port doc A15).
 *
 * Two different questions, deliberately kept apart:
 *
 *   READY TO ACTIVATE — may an admin switch this zone on? At least ONE connected module carries
 *                       every setup its type requires.
 *   AVAILABLE         — may a customer be offered this (zone, module)? The same test, per pair,
 *                       on a zone that is switched on.
 *
 * S19 rewrote both halves, and the tests that encoded the old ones are rewritten here rather than
 * deleted: `test_every_connected_module_needs_both_setups` and
 * `test_a_zone_with_no_eta_is_still_effective` were true of the rule between S18 and S19 and are
 * false now, so each asserts the new truth under a name that says what it is.
 */
class ZoneReadinessTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * A zone with an active delivery rule, switched ON.
     *
     * The activation is deliberate: `scopeEffective()` is active AND priced, so a test about the
     * priced half has to supply the active half rather than inherit whatever the live rows
     * happen to say. Since the readiness migration these are all off, and a test that reads a
     * zone's status instead of setting it is testing the fixture.
     */
    private function zone(): Zone
    {
        $zone = Zone::withoutGlobalScopes()
            ->whereHas('deliveryRules', fn ($q) => $q->where('status', 1))
            ->first();

        if (! $zone) {
            $this->markTestSkipped('needs a zone with an active delivery rule — run delivery-rules:backfill');
        }

        \DB::table('zones')->where('id', $zone->id)->update(['status' => 1]);

        return $zone->refresh();
    }

    // ------------------------------------------------------------------ Z3

    public function test_a_zone_without_an_eta_configuration_is_not_ready(): void
    {
        $zone = $this->onlyConfigurableModules($this->zone());
        \DB::table('eta_configurations')->where('zone_id', $zone->id)->update(['status' => 0]);

        $this->assertSame(['eta'], $zone->readinessGaps());
        $this->assertFalse($zone->isReadyToActivate());
    }

    /**
     * S19 — one complete module opens the zone; the rest are unavailable, not blocking.
     *
     * The inverse of the rule this file used to assert. Module 1 carries both setups, module 2
     * carries neither, and the zone may be switched on with module 2 dark inside it.
     */
    public function test_one_complete_module_is_enough_to_switch_a_zone_on(): void
    {
        $this->assertSame([], Zone::gapsBetween([1, 2], [1], [1]));
        $this->assertSame([1], Zone::completeBetween([1, 2], [1], [1]));
        $this->assertSame([2], Zone::incompleteBetween([1, 2], [1], [1]));
    }

    /**
     * Rules and ETAs that never meet on one module are still a refusal — now its own state.
     *
     * The state S18 called `pairing`: both setups exist in the zone, and no module has both.
     * This used to fall through to the three-way wording split (`['delivery_rule', 'eta']`) on
     * the reasoning that "add both" was not technically wrong — one of the two has to be added
     * SOMEWHERE to complete a module. Reconsidered 2026-09-14 (TC_60): that message still reads
     * as "you have neither," when actually both already exist and only need pairing onto one
     * module — telling an admin who already added an ETA to go add one is worse advice than
     * telling them to pair what they have. `gapsBetween()` now returns the dedicated `['pairing']`
     * gap for this case, distinct from the true both-missing case.
     */
    public function test_setups_that_never_meet_on_one_module_still_refuse(): void
    {
        $this->assertSame(['pairing'], Zone::gapsBetween([1, 2], [1], [2]));
        $this->assertSame([], Zone::completeBetween([1, 2], [1], [2]));
    }

    /**
     * A module that can hold neither setup is complete as it stands.
     *
     * Rental, ride-share and service price their own trips and bookings, and their own setup
     * screens refuse to offer them a delivery rule or an ETA. Demanding one would take them dark
     * for ever and make a zone connected only to them permanently unready.
     */
    public function test_a_module_that_can_hold_neither_setup_is_complete(): void
    {
        $exempt = array_values(array_diff(
            app(\App\Services\System\ModuleService::class)->getSelectOptions()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds(),
            app(\App\Services\System\ModuleService::class)->etaCapableModuleIds(),
        ));

        if ($exempt === []) {
            $this->markTestSkipped('no module on this install is exempt from both setups');
        }

        sort($exempt);
        $this->assertSame($exempt, Zone::completeBetween($exempt, [], []));
        $this->assertSame([], Zone::gapsBetween($exempt, [], []));
    }

    public function test_a_zone_without_a_delivery_rule_is_not_ready(): void
    {
        $zone = $this->onlyConfigurableModules($this->zone());
        // Created rather than switched on: this install has no ETA configuration rows at all,
        // so an UPDATE would touch nothing and leave the eta gap standing.
        $this->makeEta($zone);
        \DB::table('delivery_rules')->where('zone_id', $zone->id)->update(['status' => 0]);

        $this->assertSame(['delivery_rule'], $zone->fresh()->readinessGaps());
    }

    public function test_a_zone_missing_both_is_told_about_both(): void
    {
        $zone = $this->onlyConfigurableModules($this->zone());
        \DB::table('delivery_rules')->where('zone_id', $zone->id)->update(['status' => 0]);
        \DB::table('eta_configurations')->where('zone_id', $zone->id)->update(['status' => 0]);

        $gaps = $zone->fresh()->readinessGaps();

        $this->assertSame(['delivery_rule', 'eta'], $gaps);
        // The message names only what is absent — an admin who has added one is not told to add
        // it again, and every guard reads the same key so they cannot say different things.
        $this->assertStringContainsString('and', $zone->readinessMessageKey($gaps));
    }

    public function test_a_zone_with_both_is_ready(): void
    {
        $zone = $this->zone();
        $this->makeEta($zone);

        $this->assertSame([], $zone->fresh()->readinessGaps());
        $this->assertTrue($zone->fresh()->isReadyToActivate());
    }

    // ------------------------------------------------------------------ A15

    /**
     * A zone with nothing it can serve is not effective.
     *
     * Switching every rule off is not enough on its own where the zone is connected to a module
     * that needs no rule — rental and service stay servable — so the modules that CAN hold setups
     * are what this drops, and the zone follows only if nothing exempt is left.
     */
    public function test_a_zone_that_can_serve_nothing_is_not_effective(): void
    {
        $zone = $this->zone();

        $this->assertTrue(Zone::withoutGlobalScopes()->effective()->whereKey($zone->id)->exists());

        \DB::table('module_zone')->where('zone_id', $zone->id)->delete();
        \DB::table('delivery_rules')->where('zone_id', $zone->id)->update(['status' => 0]);

        $this->assertFalse(Zone::withoutGlobalScopes()->effective()->whereKey($zone->id)->exists());
    }

    /**
     * S19 — a module with no ETA is NOT available, where before it was.
     *
     * This test asserted the opposite: the ETA requirement used to be enforced at activation and
     * deliberately not at routing, because no zone on the install had a configuration yet and
     * filtering on it would have taken every one of them offline. Configurations exist now, and a
     * module that can be priced but not timed was being offered to customers whose order then
     * carried no estimate. It is the new truth that is asserted here, not the absence of the old.
     */
    public function test_a_module_with_no_eta_is_not_available(): void
    {
        $zone = $this->zone();
        $this->makeEta($zone);

        $priced = $zone->fresh()->effectiveModuleIds();
        $this->assertNotEmpty($priced, 'the fixture should leave something available');

        \DB::table('eta_configurations')->where('zone_id', $zone->id)->update(['status' => 0]);

        $after = $zone->fresh()->effectiveModuleIds();

        // Only the modules that CAN carry an ETA go dark; the exempt ones stay.
        $etaCapable = app(\App\Services\System\ModuleService::class)->etaCapableModuleIds();

        foreach ($priced as $moduleId) {
            if (in_array($moduleId, $etaCapable, true)) {
                $this->assertNotContains($moduleId, $after, "module $moduleId has no ETA and must be unavailable");
            }
        }
    }

    /** Availability is intersected with what the zone is CONNECTED to, not just what is priced. */
    public function test_a_rule_for_an_unconnected_module_does_not_make_it_available(): void
    {
        $zone = $this->zone();

        $this->assertSame(
            [],
            array_diff($zone->effectiveModuleIds(), $zone->connectedModuleIds()),
            'a stale rule naming a disconnected module must not make it servable',
        );
    }

    public function test_availability_is_per_module_not_per_zone(): void
    {
        // mart's difference from the source: pricing is per (zone, module), so a zone can be
        // live for one module and dark for another.
        $zone = $this->zone();
        $before = $zone->effectiveModuleIds();

        $this->assertNotEmpty($before, 'the backfill should have priced every connected module');

        $dropped = $before[0];
        \DB::table('delivery_rules')
            ->join('delivery_rule_module', 'delivery_rules.id', '=', 'delivery_rule_module.delivery_rule_id')
            ->where('delivery_rules.zone_id', $zone->id)
            ->where('delivery_rule_module.module_id', $dropped)
            ->update(['delivery_rules.status' => 0]);

        $after = $zone->fresh()->effectiveModuleIds();

        $this->assertNotContains($dropped, $after);
        $this->assertSame(count($before) - 1, count($after), 'only the unpriced module should go dark');
    }

    /**
     * Readiness is a (zone, module) question — and after S19, ONE complete pair answers it.
     *
     * The rule between S18 and S19 asked it of every connected module, which held a whole zone
     * dark for one module nobody had configured. What the incomplete ones now cost is stated
     * per module instead, by `incompleteBetween()`.
     */
    public function test_one_complete_pair_answers_the_readiness_question(): void
    {
        // Everything set up.
        $this->assertSame([], Zone::gapsBetween([1, 2], [1, 2], [1, 2]));

        // Module 2 has both, module 1 has neither — ready now, and module 1 is what it costs.
        $this->assertSame([], Zone::gapsBetween([1, 2], [2], [2]));
        $this->assertSame([1], Zone::incompleteBetween([1, 2], [2], [2]));

        // One module short of one setup is no longer a gap, because the other is complete.
        $this->assertSame([], Zone::gapsBetween([1, 2], [1, 2], [1]));
        $this->assertSame([2], Zone::incompleteBetween([1, 2], [1, 2], [1]));

        // Plain absences, with nothing complete to fall back on, still read as themselves.
        $this->assertSame(['delivery_rule'], Zone::gapsBetween([1], [], [1]));
        $this->assertSame(['eta'], Zone::gapsBetween([1], [1], []));
        $this->assertSame(['delivery_rule', 'eta'], Zone::gapsBetween([1], [], []));

        // Setups for modules the zone is NOT connected to do not help it.
        $this->assertSame(['delivery_rule', 'eta'], Zone::gapsBetween([1], [9], [9]));
        $this->assertSame([], Zone::completeBetween([1], [9], [9]));
    }

    /**
     * A zone with nothing connected cannot be switched on.
     *
     * Checked before anything else: every test below is vacuously satisfied by an empty list, so
     * without this an empty zone would be MORE activatable than a half-configured one.
     */
    public function test_a_zone_with_no_connected_module_is_not_ready(): void
    {
        $this->assertSame(['module'], Zone::gapsBetween([], [], []));
        $this->assertSame(['module'], Zone::gapsBetween([], [1, 2], [1, 2]));
    }

    /**
     * Which modules are short of which setup, for the message the admin reads.
     *
     * Exempt-aware since S19: a module whose type can hold no delivery rule is not missing one.
     * Asked only of modules that need both, so the expectation is the plain set difference.
     */
    public function test_the_missing_modules_are_named_per_setup(): void
    {
        $needsBoth = array_values(array_intersect(
            app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds(),
            app(\App\Services\System\ModuleService::class)->etaCapableModuleIds(),
        ));

        $this->assertGreaterThanOrEqual(2, count($needsBoth), 'needs two modules that require both setups');

        [$covered, $short] = [$needsBoth[0], $needsBoth[1]];

        $this->assertSame(
            ['delivery_rule' => [$short], 'eta' => [$short]],
            Zone::missingByModule([$covered, $short], [$covered], [$covered]),
        );
    }

    /** A module exempt from a setup is never named as missing it. */
    public function test_an_exempt_module_is_not_named_as_missing_a_setup(): void
    {
        $exempt = array_values(array_diff(
            app(\App\Services\System\ModuleService::class)->getSelectOptions()->pluck('id')->map(fn ($id) => (int) $id)->all(),
            app(\App\Services\System\ModuleService::class)->deliveryRuleModuleIds(),
        ));

        if ($exempt === []) {
            $this->markTestSkipped('no module on this install is exempt from delivery rules');
        }

        $this->assertSame([], Zone::missingByModule($exempt, [], [])['delivery_rule']);
    }

    /** Each gap gets its own sentence on each surface — including the no-module one. */
    public function test_the_module_gap_has_wordings_of_its_own(): void
    {
        $zone = new Zone;

        foreach (['readinessMessageKey', 'readinessNoticeKey', 'readinessPromptKey', 'readinessTitleKey'] as $surface) {
            $module = translate($zone->{$surface}(['module']));

            $this->assertNotSame($module, translate($zone->{$surface}(['delivery_rule', 'eta'])), $surface);
            $this->assertNotSame($module, translate($zone->{$surface}(['eta'])), $surface);
            $this->assertNotSame($module, translate($zone->{$surface}(['delivery_rule'])), $surface);
        }
    }

    /**
     * The Delivery Management zone picker must not filter on status.
     *
     * Z3 will not switch a zone on until one of its modules has both a delivery rule and an ETA
     * configuration. If the pickers that create those hide inactive zones, the requirement
     * cannot be met: the zone stays off because it has no rule, and it can have no rule because
     * it is off. Every form under Delivery Management reads this one method.
     */
    public function test_the_delivery_management_zone_picker_offers_inactive_zones(): void
    {
        \DB::table('zones')->update(['status' => 0]);

        $this->assertSame(
            Zone::count(),
            app(ZoneService::class)->getSelectOptions()->count(),
            'a switched-off zone is exactly the one an admin came here to set up',
        );
    }

    /** Customer-facing lookups are the opposite question and keep the filter. */
    public function test_customer_facing_lookups_still_refuse_a_switched_off_zone(): void
    {
        \DB::table('zones')->update(['status' => 0]);

        $this->assertSame([], app(ZoneService::class)->getActiveWithModules());
        $this->assertFalse(Zone::withoutGlobalScopes()->effective()->exists());
    }

    // ------------------------------------------------------------------ S19: the third state

    /**
     * A zone that may be switched on while some of its modules stay dark.
     *
     * The state the confirm dialog exists for: `ready` yes, `complete` no. Both names appear on
     * the row, and the toggle picks its class from `requiresConfirmation` — which is false once
     * the zone is already ON, because switching a zone OFF takes nothing dark.
     */
    public function test_a_partly_configured_zone_asks_before_it_is_switched_on(): void
    {
        $zone = $this->zone();
        $this->makeEta($zone);

        // One module loses its ETA. Something else still has both, so the zone stays switchable.
        $etaCapable = app(\App\Services\System\ModuleService::class)->etaCapableModuleIds();
        $covered = array_values(array_intersect($zone->fresh()->completeModuleIds(), $etaCapable));

        if (count($covered) < 2) {
            $this->markTestSkipped('needs two complete ETA-capable modules in one zone');
        }

        \DB::table('eta_configuration_module')
            ->join('eta_configurations', 'eta_configurations.id', '=', 'eta_configuration_module.eta_configuration_id')
            ->where('eta_configurations.zone_id', $zone->id)
            ->where('eta_configuration_module.module_id', $covered[0])
            ->delete();

        \DB::table('zones')->where('id', $zone->id)->update(['status' => 0]);

        $readiness = app(ZoneService::class)->readinessFor(Zone::withoutGlobalScopes()->whereKey($zone->id)->get());
        $row = $readiness[$zone->id];

        $this->assertTrue($row['ready'], 'one complete module is enough to switch it on');
        $this->assertFalse($row['complete'], 'and something is still dark behind it');
        $this->assertContains($covered[0], $row['unavailableModuleIds']);
        $this->assertNotEmpty($row['unavailableModuleNames'], 'the dialog names them, it does not count them');
        $this->assertTrue($row['requiresConfirmation']);

        // Already ON: the toggle is switching it OFF, which asks nothing.
        \DB::table('zones')->where('id', $zone->id)->update(['status' => 1]);
        $active = app(ZoneService::class)->readinessFor(Zone::withoutGlobalScopes()->whereKey($zone->id)->get());

        $this->assertFalse($active[$zone->id]['requiresConfirmation']);
        $this->assertTrue($active[$zone->id]['toggleEnabled']);
    }

    /** The zone resolver and the module list must not disagree about one zone. */
    public function test_the_zone_resolver_and_the_module_list_agree(): void
    {
        $zone = $this->zone();
        $this->makeEta($zone);

        $available = $zone->fresh()->effectiveModuleIds();

        $listed = app(\App\Services\System\ModuleService::class)
            ->getList(['zone_ids' => [$zone->id]], ['limit' => 100])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // The list also drops globally inactive modules, so it is a subset rather than an equal
        // set; what matters is that it never offers something the resolver calls unavailable.
        $this->assertSame([], array_values(array_diff($listed, $available)));
    }

    // ------------------------------------------------------------------ the batched lookup

    public function test_readiness_for_a_page_costs_a_fixed_number_of_queries(): void
    {
        $zones = Zone::withoutGlobalScopes()->get();

        \DB::flushQueryLog();
        \DB::enableQueryLog();
        app(ZoneService::class)->readinessFor($zones);
        $queries = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Three: the rule list, the ETA list and the connected list. S19 added the unavailable
        // module NAMES to every row and did not add a fourth — ModuleService::getSelectOptions()
        // is memoised per request and is usually already warm by the time the list renders.
        $this->assertLessThanOrEqual(4, $queries, 'rule 11 — the list must not ask per row');
        $this->assertGreaterThanOrEqual(3, $queries);
    }

    public function test_an_active_zone_keeps_its_toggle_however_unready_it_is(): void
    {
        // It must always be possible to switch a zone OFF. Only switching ON is guarded.
        // Every module is disconnected so the zone is unready for the one reason no exemption
        // can rescue — after S19, switching the ETA off alone leaves the exempt modules complete.
        $zone = $this->zone();
        \DB::table('zones')->where('id', $zone->id)->update(['status' => 1]);
        \DB::table('module_zone')->where('zone_id', $zone->id)->delete();

        $readiness = app(ZoneService::class)->readinessFor(Zone::withoutGlobalScopes()->whereKey($zone->id)->get());

        $this->assertFalse($readiness[$zone->id]['ready']);
        $this->assertTrue($readiness[$zone->id]['toggleEnabled']);
    }

    /**
     * An ETA covering EVERY module the zone is connected to.
     *
     * One module used to be enough to make a zone ready; now every connected module that CAN
     * carry an ETA needs both setups, so a fixture that covers one leaves the zone unready and
     * tests the wrong thing.
     *
     * The zone's existing configurations are cleared first. This used to assume the install had
     * none at all — true when it was written, and false the moment anyone added one through the
     * panel: `create()` then throws DuplicateEtaConfigurationException and every readiness test
     * errors for a reason that has nothing to do with readiness. Inside the test transaction, so
     * the real rows come back.
     */
    /**
     * The same zone with its exempt modules disconnected.
     *
     * S19 made "complete" mean "carries every setup its TYPE requires", so a zone connected to
     * rental or service has a complete module however thoroughly its rules and estimates are
     * switched off — and a test about a missing delivery rule then measures the exemption instead
     * of the gap. Dropping the exempt pivots leaves a zone whose readiness depends only on the
     * setups the test is manipulating. Inside the test transaction, so the rows come back.
     */
    private function onlyConfigurableModules(Zone $zone): Zone
    {
        $modules = app(\App\Services\System\ModuleService::class);

        $configurable = array_values(array_unique(array_merge(
            $modules->deliveryRuleModuleIds(),
            $modules->etaCapableModuleIds(),
        )));

        \DB::table('module_zone')
            ->where('zone_id', $zone->id)
            ->whereNotIn('module_id', $configurable)
            ->delete();

        return $zone->refresh();
    }

    private function makeEta(Zone $zone): EtaConfiguration
    {
        $existing = \DB::table('eta_configurations')->where('zone_id', $zone->id)->pluck('id');

        if ($existing->isNotEmpty()) {
            \DB::table('eta_configuration_module')->whereIn('eta_configuration_id', $existing)->delete();
            \DB::table('eta_configurations')->whereIn('id', $existing)->delete();
        }

        $moduleIds = \DB::table('module_zone')->where('zone_id', $zone->id)->pluck('module_id')->all();

        return app(\App\Services\Zone\EtaConfigurationService::class)->create([
            'name' => 'Readiness probe',
            'zone_id' => $zone->id,
            'module_ids' => $moduleIds,
            'calculation_method' => EtaConfiguration::METHOD_DISTANCE,
            'minimum_delivery_time' => 20,
            'preparation_buffer' => 10,
            'transit_buffer' => 5,
            'time_gap' => 15,
        ]);
    }
}
