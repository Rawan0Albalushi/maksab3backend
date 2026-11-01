<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\Zone\ZoneController;
use App\Http\Requests\Admin\ZoneConnectModuleRequest;
use App\Models\Zone;
use App\Services\Zone\ZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * S18 — the Zone Setup flow.
 *
 * The zone list drops its Default Status column for a badge and a menu item, gains the Surge
 * Price quick link back, and answers Connect Module with a drawer instead of a page. The drawer
 * carries payment methods, the modules the zone serves, and each module's COD ceiling — and no
 * delivery pricing at all, which now belongs to Rule Setup.
 */
class ZoneSetupFlowTest extends TestCase
{
    use DatabaseTransactions;

    private function controller(): ZoneController
    {
        auth('admin')->login(\App\Models\Admin::first());
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);

        return app(ZoneController::class);
    }

    private function request(array $payload): ZoneConnectModuleRequest
    {
        $request = ZoneConnectModuleRequest::create('/x', 'POST', $payload);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request;
    }

    private function drawer(int $zoneId): string
    {
        return json_decode($this->controller()->connectModuleView($zoneId)->getContent(), true)['view'];
    }

    /* ── the list ─────────────────────────────────────────────────────────── */

    public function test_the_default_status_column_became_a_badge_and_a_menu_item(): void
    {
        // "Mark As Default" force-activates the zone it's clicked on (TC_141), so it only
        // renders for a zone that is already active — none of the seeded non-default zones are,
        // so one is switched on here (rolled back by DatabaseTransactions) purely to give the
        // menu item a row to appear on.
        $activeNonDefault = Zone::where('is_default', false)->first();
        $activeNonDefault->update(['status' => 1]);

        $html = $this->controller()->index(\Illuminate\Http\Request::create('/x'))->render();

        $this->assertStringNotContainsString('Default Status', $html);
        $this->assertStringNotContainsString('Zone ID', $html, 'the Zone ID column was removed');
        $this->assertStringContainsString('zone-default-badge', $html);
        $this->assertStringContainsString('Mark As Default', $html);
    }

    /** They moved into the drawer, beside the modules they apply to. */
    public function test_the_payment_toggles_left_the_row_menu(): void
    {
        $html = $this->controller()->index(\Illuminate\Http\Request::create('/x'))->render();

        foreach (['digital_paymentCheckbox', 'offline_paymentCheckbox', 'cashOnDeliveryCheckbox'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    /**
     * Z3 — an INACTIVE zone with gaps gets the dialog. An active one keeps a plain toggle
     * whatever its gaps, because switching a zone OFF must never be blocked.
     */
    public function test_only_an_inactive_unready_zone_opens_the_readiness_dialog(): void
    {
        $zone = Zone::first();
        $this->setReadiness($zone->id, rule: false, eta: false, status: 0);

        // Inactive and unready: the row's own toggle carries the class that opens the dialog.
        $this->assertMatchesRegularExpression(
            '/zone-not-ready[^>]*id="status-'.$zone->id.'"/s',
            $this->rowFor($zone->id, $this->controller()->index(\Illuminate\Http\Request::create('/x'))->render()),
        );

        // Active with the same gap: a plain toggle, because switching OFF is never blocked.
        $this->setReadiness($zone->id, rule: false, eta: false, status: 1);
        $this->assertStringNotContainsString(
            'zone-not-ready',
            $this->rowFor($zone->id, $this->controller()->index(\Illuminate\Http\Request::create('/x'))->render()),
        );
    }

    /**
     * S19's three states, on the control that shows them: block, confirm, plain.
     *
     * `zone-not-ready` refuses the switch-on; `zone-partial` allows it after naming what goes
     * dark; `dynamic-checkbox` is the plain confirm. They are mutually exclusive on one row —
     * which is the whole point, since each opens a different dialog.
     */
    public function test_the_toggle_has_three_states(): void
    {
        $zone = Zone::first();

        // 1. Nothing complete → blocked.
        $this->setReadiness($zone->id, rule: false, eta: false, status: 0);
        $blocked = $this->rowMarkup($zone->id);
        $this->assertStringContainsString('zone-not-ready', $blocked);
        $this->assertStringNotContainsString('zone-partial', $blocked);

        // 2. Everything complete → plain.
        $this->setReadiness($zone->id, rule: true, eta: true, status: 0);
        $plain = $this->rowMarkup($zone->id);
        $this->assertStringContainsString('dynamic-checkbox', $plain);
        $this->assertStringNotContainsString('zone-partial', $plain);
        $this->assertStringNotContainsString('zone-not-ready', $plain);

        // 3. One module complete, another not → confirm, naming the one that goes dark.
        $covered = Zone::find($zone->id)->completeModuleIds();

        if (count($covered) < 2) {
            $this->markTestSkipped('needs two complete modules in one zone');
        }

        DB::table('eta_configuration_module')
            ->join('eta_configurations', 'eta_configurations.id', '=', 'eta_configuration_module.eta_configuration_id')
            ->where('eta_configurations.zone_id', $zone->id)
            ->where('eta_configuration_module.module_id', $covered[0])
            ->delete();

        app(\App\Services\Zone\EtaConfigurationService::class)->forgetActive();

        $partial = $this->rowMarkup($zone->id);
        $this->assertStringContainsString('zone-partial', $partial);
        $this->assertStringNotContainsString('zone-not-ready', $partial);
        $this->assertStringContainsString('data-unavailable-modules', $partial);
        $this->assertStringContainsString('zone-setup-warning__mark', $partial, 'a zone leaving modules dark still carries the mark');
    }

    /* ── Z3 across the three surfaces (StackFood's logic) ─────────────────── */

    /** One row's markup, cut at the <tr> boundaries so a neighbour cannot answer for it. */
    private function rowMarkup(int|string $zoneId): string
    {
        $html = $this->controller()->index(\Illuminate\Http\Request::create('/x'))->render();
        $at = strpos($html, 'id="status-'.$zoneId.'"');

        $this->assertNotFalse($at, "no toggle rendered for zone {$zoneId}");

        $start = strrpos(substr($html, 0, $at), '<tr>');

        return substr($html, $start, strpos($html, '</tr>', $at) - $start);
    }

    /**
     * Puts one zone into a known readiness state.
     *
     * Readiness is a (zone, module) question, so an ETA configuration has to cover a module the
     * zone also has a rule for — an unattached configuration covers nothing and leaves the zone
     * unready however many rows exist.
     */
    private function setReadiness(int|string $zoneId, bool $rule, bool $eta, int $status = 0): void
    {
        // S19 — "complete" means a module carries every setup its TYPE requires, so rental,
        // ride-share and service are complete with nothing configured at all and a zone
        // connected to one of them is ready however thoroughly this switches its rules off.
        // Disconnected here so the zone's readiness depends only on what the test sets.
        $modules = app(\App\Services\System\ModuleService::class);

        DB::table('module_zone')
            ->where('zone_id', $zoneId)
            ->whereNotIn('module_id', array_unique(array_merge(
                $modules->deliveryRuleModuleIds(),
                $modules->etaCapableModuleIds(),
            )))
            ->delete();

        DB::table('delivery_rules')->where('zone_id', $zoneId)->update(['status' => $rule ? 1 : 0]);
        DB::table('eta_configurations')->where('zone_id', $zoneId)->update(['status' => $eta ? 1 : 0]);

        if ($eta) {
            $configurationId = DB::table('eta_configurations')->where('zone_id', $zoneId)->value('id')
                ?: DB::table('eta_configurations')->insertGetId([
                    'zone_id' => $zoneId, 'calculation_method' => 'distance_based', 'status' => 1,
                    'minimum_delivery_time' => 10, 'time_gap' => 5, 'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('eta_configurations')->where('id', $configurationId)->update(['status' => 1]);

            // Whatever the zone's rules cover, so the two actually meet on a module.
            $moduleIds = Zone::find($zoneId)->ruledModuleIds()
                ?: DB::table('module_zone')->where('zone_id', $zoneId)->pluck('module_id')->all();

            DB::table('eta_configuration_module')->where('eta_configuration_id', $configurationId)->delete();

            foreach ($moduleIds as $moduleId) {
                DB::table('eta_configuration_module')->insert([
                    'eta_configuration_id' => $configurationId, 'module_id' => $moduleId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        DB::table('zones')->where('id', $zoneId)->update(['status' => $status]);
        app(\App\Services\Zone\EtaConfigurationService::class)->forgetActive();
    }

    /** The mark is a flag on an unfinished zone, so it does not care whether the zone is on. */
    public function test_the_warning_mark_shows_for_any_unready_zone_and_hides_when_ready(): void
    {
        $zone = Zone::first();

        $this->setReadiness($zone->id, rule: false, eta: false);
        $this->assertStringContainsString('zone-setup-warning__mark', $this->rowMarkup($zone->id));

        // Still unready, but switched ON — the mark stays.
        $this->setReadiness($zone->id, rule: false, eta: false, status: 1);
        $this->assertStringContainsString('zone-setup-warning__mark', $this->rowMarkup($zone->id));

        $this->setReadiness($zone->id, rule: true, eta: true);
        $this->assertStringNotContainsString('zone-setup-warning__mark', $this->rowMarkup($zone->id));
    }

    /**
     * Z3's asymmetry: switching a zone ON is guarded, switching it OFF never is. An active zone
     * keeps a working toggle however incomplete it is, or it could not be taken down.
     */
    public function test_an_active_unready_zone_keeps_a_working_toggle(): void
    {
        $zone = Zone::first();

        $this->setReadiness($zone->id, rule: false, eta: false, status: 0);
        $this->assertStringContainsString('zone-not-ready', $this->rowMarkup($zone->id));

        $this->setReadiness($zone->id, rule: false, eta: false, status: 1);
        $this->assertStringNotContainsString('zone-not-ready', $this->rowMarkup($zone->id));
    }

    /**
     * An admin who has already added one of the two is not told to add it again.
     *
     * The row no longer prints the shortcuts — the dialog builds them from these two flags, so
     * they are what decides which links appear. The drawer still prints them directly.
     */
    public function test_every_surface_names_only_the_setup_that_is_missing(): void
    {
        $zone = Zone::first();

        foreach ([
            'neither' => [false, false],
            'rule only' => [true, false],
            'eta only' => [false, true],
        ] as $case => [$rule, $eta]) {
            $this->setReadiness($zone->id, $rule, $eta);

            $at = strpos($row = $this->rowMarkup($zone->id), 'zone-setup-warning__mark');
            $open = strrpos(substr($row, 0, $at), '<');
            $mark = substr($row, $open, strpos($row, '>', $at) - $open);

            $this->assertStringContainsString('data-has-rule="'.(int) $rule.'"', $mark, "{$case}: wrong rule flag");
            $this->assertStringContainsString('data-has-eta="'.(int) $eta.'"', $mark, "{$case}: wrong eta flag");

            // The drawer prints its shortcuts, so it can be read directly.
            $drawer = $this->drawer((int) $zone->id);

            foreach ([
                'Add Delivery Charge Setup' => $rule,
                'Add ETA Configuration' => $eta,
            ] as $link => $alreadyDone) {
                $alreadyDone
                    ? $this->assertStringNotContainsString($link, $drawer, "{$case}: {$link} is already done")
                    : $this->assertStringContainsString($link, $drawer, "{$case}: expected the {$link} shortcut");
            }
        }
    }

    /**
     * Both triggers carry what the dialog needs, so it can be built the same way whichever
     * opened it — and each carries its OWN wording, because they explain different things.
     */
    public function test_both_dialog_triggers_carry_their_own_wording_and_the_same_gaps(): void
    {
        $zone = Zone::first();
        $this->setReadiness($zone->id, rule: true, eta: false);
        $row = $this->rowMarkup($zone->id);

        // Every attribute the shared opener reads, on both triggers, and the wording each
        // one carries. Read off the trigger ELEMENT rather than the row: the popover prints
        // the notice too, so a row-wide search passes even when the mark is wrong.
        foreach ([
            'zone-not-ready' => ['data-prompt', 'cannot be activated'],
            'zone-setup-warning__mark' => ['data-notice', 'will NOT work'],
        ] as $trigger => [$wordingAttribute, $expected]) {
            $at = strpos($row, $trigger);
            $this->assertNotFalse($at, "{$trigger} missing");

            $open = strrpos(substr($row, 0, $at), '<');
            $element = substr($row, $open, strpos($row, '>', $at) - $open);

            foreach (['data-zone-id', 'data-has-rule', 'data-has-eta', $wordingAttribute] as $attribute) {
                $this->assertStringContainsString($attribute, $element, "{$trigger} is missing {$attribute}");
            }

            // The toggle explains a refused switch; the mark explains a zone that is not
            // working. Sharing one dialog must not collapse them into one sentence.
            $this->assertStringContainsString($expected, $element, "{$trigger} carries the wrong wording");
        }
    }

    /** The mark is a control now, so it has to be one — a span is not focusable or pressable. */
    public function test_the_warning_mark_is_a_button(): void
    {
        $zone = Zone::first();
        $this->setReadiness($zone->id, rule: false, eta: false);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*zone-setup-warning__mark/',
            $this->rowMarkup($zone->id),
        );
    }

    /** The three surfaces read one source, so they cannot disagree about one zone. */
    public function test_the_notice_and_the_prompt_split_the_same_three_ways(): void
    {
        $wordings = [];

        foreach ([['delivery_rule'], ['eta'], ['delivery_rule', 'eta']] as $gaps) {
            $wordings[] = [
                translate((new Zone)->readinessMessageKey($gaps)),
                translate((new Zone)->readinessNoticeKey($gaps)),
                translate((new Zone)->readinessPromptKey($gaps)),
            ];
        }

        // Three distinct sentences per surface — none of them the fallback three times over.
        foreach ([0, 1, 2] as $surface) {
            $this->assertCount(3, array_unique(array_column($wordings, $surface)));
        }
    }

    public function test_the_dialog_title_narrows_to_what_is_missing(): void
    {
        $zone = Zone::first();

        $this->assertStringContainsString('delivery charge &', translate($zone->readinessTitleKey(['delivery_rule', 'eta'])));
        $this->assertStringNotContainsString('&', translate($zone->readinessTitleKey(['eta'])));
        $this->assertStringNotContainsString('&', translate($zone->readinessTitleKey(['delivery_rule'])));
        $this->assertStringContainsString('ETA', translate($zone->readinessTitleKey(['eta'])));
        $this->assertStringContainsString('delivery charge', translate($zone->readinessTitleKey(['delivery_rule'])));

        // Every real caller supplies the zone's name (_table_rows.blade.php, TC_58's post-save
        // prompt) — with it, nothing is left unresolved. Bare, the raw :zone token would leak.
        $this->assertDoesNotMatchRegularExpression(
            '/\d|:[a-z_]/i',
            translate($zone->readinessTitleKey(), ['zone' => $zone->labelStem()]),
        );
    }

    /** A finished zone is told so rather than being shown a step it has already taken. */
    public function test_the_drawer_confirms_a_ready_zone_and_prompts_an_unready_one(): void
    {
        $zone = Zone::first();

        $this->setReadiness($zone->id, rule: true, eta: true);
        $this->assertStringContainsString('admin-alert--success', $this->drawer((int) $zone->id));

        $this->setReadiness($zone->id, rule: false, eta: false);
        $drawer = $this->drawer((int) $zone->id);
        $this->assertStringContainsString('admin-alert--sticky', $drawer);
        $this->assertStringContainsString('Add Delivery Charge Setup', $drawer);
    }

    /* ── creating a zone ──────────────────────────────────────────────────── */

    /**
     * Creating a zone re-renders the list rows over AJAX, so that payload has to carry
     * everything the row partial reads — a variable the partial indexes and the response omits
     * throws rather than degrading, and only this path renders it.
     */
    public function test_creating_a_zone_re_renders_the_rows_it_can_actually_draw(): void
    {
        $data = json_decode($this->controller()->add($this->newZoneRequest('Zone Flow Test'))->getContent(), true);

        $this->assertNotEmpty($data['id']);
        // Was `assertMatchesRegularExpression('/#\d\d/', ...)` labelled "the Zone ID column did
        // not render". There is no Zone ID column — the row draws Name, Modules, Vendors,
        // Deliverymen, Created At, Status, Action. The pattern was matching `#03` inside
        // `don&#039;t`, the escaped apostrophe in the "select your business module" popover, so
        // it went green for six months while testing nothing, then failed the moment that popover
        // was removed. Replaced with the identity the row actually carries.
        $this->assertStringContainsString('data-zone-id="'.$data['id'].'"', $data['view'],
            'the re-rendered row must identify the zone it is for');
        $this->assertStringContainsString('Zone Flow Test', $data['view'], 'the zone name did not render');

        // The id the response returns is what the form opens the drawer with.
        $this->assertStringContainsString('connect-module/'.$data['id'], $data['view']);

        // A zone with nothing configured yet: it cannot be switched on, and it says so.
        $this->assertStringContainsString('zone-setup-warning__mark', $data['view']);
    }

    /**
     * A brand new zone has no delivery rule and no ETA, so it cannot satisfy Z3 — and
     * `zones.status` defaults to 1 in the schema, which used to switch it on anyway. Creation
     * was the one route that could not possibly produce a ready zone and the one that skipped
     * the check.
     */
    public function test_a_new_zone_is_created_switched_off(): void
    {
        $data = json_decode($this->controller()->add($this->newZoneRequest('Zone Flow Off'))->getContent(), true);
        $zone = Zone::find($data['id']);

        $this->assertSame(0, (int) $zone->status, 'a zone with nothing configured must not start on');
        $this->assertFalse(Zone::effective()->whereKey($zone->id)->exists());

        // And its row offers the way forward rather than a switch that would be refused.
        $this->assertStringContainsString('zone-not-ready', $this->rowMarkup($zone->id));
    }

    /** And that drawer opens on the zone just made, with nothing connected to it yet. */
    public function test_a_new_zones_drawer_opens_empty(): void
    {
        // Through the controller, because that is the only path that builds a zone's
        // coordinates from what the form posts.
        $zoneId = json_decode($this->controller()->add($this->newZoneRequest('Zone Flow Empty'))->getContent(), true)['id'];

        $drawer = $this->drawer((int) $zoneId);

        $this->assertStringContainsString('Zone Flow Empty', $drawer);
        $this->assertStringContainsString('Choose Module To Connect', $drawer);
        $this->assertStringNotContainsString('selected', $drawer, 'a brand new zone connects nothing yet');
        // Nothing is configured, so the drawer prompts rather than confirming.
        $this->assertStringContainsString('admin-alert--sticky', $drawer);
    }

    private function newZoneRequest(string $name): \App\Http\Requests\Admin\ZoneAddRequest
    {
        $request = \App\Http\Requests\Admin\ZoneAddRequest::create('/x', 'POST', [
            'name' => [$name],
            'display_name' => [$name],
            'lang' => ['default'],
            'coordinates' => '(23.80,90.36),(23.80,90.44),(23.86,90.44),(23.86,90.36)',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        return $request;
    }

    /* ── the drawer ───────────────────────────────────────────────────────── */

    public function test_the_drawer_carries_the_three_sections_the_design_shows(): void
    {
        $html = $this->drawer((int) Zone::first()->id);

        // The three sections the design shows, whatever state the zone is in.
        foreach ([
            'Select Payment Method',
            'Module to connect',
            'Choose Module To Connect',
            'Max COD Order Amount',
        ] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    /**
     * The footer is the one part that changes with the zone, so it is asserted against a zone
     * whose readiness this test controls rather than against whatever the first zone happens to
     * be. Asserting "Rule Setup" unconditionally passed only while no admin had configured the
     * first zone, and broke the moment somebody legitimately did.
     */
    public function test_the_drawer_footer_follows_the_zones_readiness(): void
    {
        $zone = Zone::first();
        $ready = $zone->readinessGaps() === [];

        $html = $this->drawer((int) $zone->id);

        if ($ready) {
            $this->assertStringNotContainsString('Rule Setup', $html, 'a ready zone is not asked to set anything up');
        } else {
            $this->assertStringContainsString('Rule Setup', $html, 'an unready zone is pointed at the two setups it is missing');
        }
    }

    /** "Surge Price and other delivery pricing settings are not configured here." */
    public function test_the_drawer_carries_no_delivery_pricing(): void
    {
        $html = $this->drawer((int) Zone::first()->id);

        foreach ([
            'per_km_shipping_charge',
            'minimum_shipping_charge',
            'Delivery Charge Setup',
            'Choose Delivery Charge Type',
            'Surge',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    /* ── saving ───────────────────────────────────────────────────────────── */

    /**
     * The drawer writes one pivot column. §4.3 keeps the delivery-charge columns for another
     * release and the saver options have no other home, so neither may be collateral.
     */
    public function test_saving_preserves_pivot_pricing_and_saver_options(): void
    {
        $zone = Zone::first();
        $moduleIds = DB::table('module_zone')->where('zone_id', $zone->id)->pluck('module_id')->all();
        $pivot = fn () => DB::table('module_zone')->where('zone_id', $zone->id)->where('module_id', $moduleIds[0])->first();

        $before = $pivot();
        $saverBefore = DB::table('module_zone_delivery_options')->where('zone_id', $zone->id)->count();

        $this->controller()->connectModule($this->request([
            'cash_on_delivery' => 1,
            'module_id' => $moduleIds,
            'max_cod_status' => 1,
            'max_cod_order_amount' => array_fill_keys($moduleIds, 1234),
        ]), $zone->id);

        $after = $pivot();

        $this->assertEquals($before->per_km_shipping_charge, $after->per_km_shipping_charge);
        $this->assertEquals($before->minimum_shipping_charge, $after->minimum_shipping_charge);
        $this->assertEquals($before->maximum_shipping_charge, $after->maximum_shipping_charge);
        $this->assertSame($before->delivery_charge_type, $after->delivery_charge_type);
        $this->assertEquals(1234, $after->maximum_cod_order_amount);
        $this->assertSame($saverBefore, DB::table('module_zone_delivery_options')->where('zone_id', $zone->id)->count());
    }

    /** Off means no ceiling, and the order path already reads 0 that way. */
    public function test_switching_the_cod_toggle_off_clears_every_ceiling(): void
    {
        $zone = Zone::first();
        $moduleIds = DB::table('module_zone')->where('zone_id', $zone->id)->pluck('module_id')->all();

        $this->controller()->connectModule($this->request([
            'cash_on_delivery' => 1, 'module_id' => $moduleIds, 'max_cod_status' => 0,
        ]), $zone->id);

        $this->assertSame(
            0,
            DB::table('module_zone')->where('zone_id', $zone->id)->where('maximum_cod_order_amount', '>', 0)->count(),
        );
    }

    public function test_the_drawer_refuses_a_save_with_no_payment_method(): void
    {
        $this->expectException(ValidationException::class);
        $this->request(['module_id' => [1]]);
    }

    public function test_the_drawer_refuses_a_save_with_no_module(): void
    {
        $this->expectException(ValidationException::class);
        $this->request(['cash_on_delivery' => 1, 'module_id' => []]);
    }

    public function test_the_cod_amount_is_required_only_while_the_toggle_is_on(): void
    {
        // Off: no amounts, and the save is fine.
        $this->request(['cash_on_delivery' => 1, 'module_id' => [1], 'max_cod_status' => 0]);

        $this->expectException(ValidationException::class);
        $this->request(['cash_on_delivery' => 1, 'module_id' => [1], 'max_cod_status' => 1]);
    }

    /** A method switched off platform-wide cannot be switched on for a zone by posting it. */
    public function test_a_payment_method_the_platform_forbids_cannot_be_posted_on(): void
    {
        // The settings are memoised AND cached forever, so writing the row is not enough — the
        // gate has to be asked again from a cleared cache or the test proves nothing.
        DB::table('business_settings')->where('key', 'offline_payment_status')->update(['value' => 0]);
        \App\Services\System\BusinessSettingService::forgetCache();

        $request = $this->request([
            'cash_on_delivery' => 1, 'offline_payment' => 1, 'module_id' => [1],
        ]);

        $this->assertFalse(ZoneConnectModuleRequest::platformAllows()['offline_payment']);
        $this->assertSame(0, $request->paymentColumns()['offline_payment'], 'posting a forbidden method must not switch it on');
        $this->assertSame(1, $request->paymentColumns()['cash_on_delivery']);

        \App\Services\System\BusinessSettingService::forgetCache();
    }

    /** The one row of the rendered list that belongs to this zone. */
    private function rowFor(int|string $zoneId, string $html): string
    {
        $marker = 'id="status-'.$zoneId.'"';
        $at = strpos($html, $marker);

        $this->assertNotFalse($at, "no toggle rendered for zone {$zoneId}");

        return substr($html, max(0, $at - 1500), 1600);
    }
}
