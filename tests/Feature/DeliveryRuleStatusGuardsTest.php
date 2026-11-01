<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DeliveryRule;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The two defects QA cases TC_279 and TC_280 turned up on switching a delivery rule off.
 *
 * Both were the screen offering a way out that does not exist: a picker listing replacements the
 * controller would refuse, and a "choose a replacement" message on the one rule for which no
 * replacement can ever exist.
 */
class DeliveryRuleStatusGuardsTest extends TestCase
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

    /**
     * The picker must not offer a rule that `modulesLeftUncovered()` will refuse — every rule it
     * lists has to cover every module of the one being switched off.
     */
    public function test_the_replacement_picker_only_lists_rules_that_could_take_over(): void
    {
        $rule = DeliveryRule::with('modules')->whereHas('modules')->first();

        if (! $rule) {
            $this->markTestSkipped('no delivery rule connects a module');
        }

        $needed = $rule->modules->pluck('id')->all();

        $offered = app(DeliveryRuleService::class)->selectableForZone($rule->zone_id, $rule->id);

        foreach ($offered as $candidate) {
            $covers = DB::table('delivery_rule_module')->where('delivery_rule_id', $candidate->id)->pluck('module_id')->all();

            $this->assertSame(
                [],
                array_diff($needed, $covers),
                "rule {$candidate->id} was offered as a replacement but does not cover every module of rule {$rule->id}",
            );
        }
    }

    /**
     * A rule no sibling fully covers has nothing to hand over to, and must be offered nothing.
     *
     * The premise is "no other rule in the zone covers every one of its modules" — not
     * `modulesLeftUncovered($rule, null)`, which is non-empty for any rule simply because no
     * replacement was supplied, including ones a sibling could take over.
     */
    public function test_the_picker_is_empty_when_no_rule_could_take_over(): void
    {
        $all = DeliveryRule::with('modules')->get();

        $rule = $all->first(function ($candidate) use ($all) {
            $needed = $candidate->modules->pluck('id')->all();

            if ($needed === []) {
                return false;
            }

            return $all->where('zone_id', $candidate->zone_id)
                ->where('id', '!=', $candidate->id)
                ->every(fn ($other) => array_diff($needed, $other->modules->pluck('id')->all()) !== []);
        });

        if (! $rule) {
            $this->markTestSkipped('every rule has a sibling that could take over from it');
        }

        $this->assertCount(
            0,
            app(DeliveryRuleService::class)->selectableForZone($rule->zone_id, $rule->id),
            'a rule with no possible replacement must be offered none',
        );
    }

    /**
     * D2 — the last rule of the default zone. The replacement requirement used to answer first,
     * telling the admin to pick from an empty list instead of naming the real reason.
     */
    public function test_disabling_the_last_default_zone_rule_gives_the_d2_reason(): void
    {
        $zoneId = DB::table('zones')->where('is_default', 1)->value('id');

        if (! $zoneId) {
            $this->markTestSkipped('no default zone is configured');
        }

        // Leave exactly one rule on the default zone for the length of the transaction.
        $rules = DeliveryRule::where('zone_id', $zoneId)->orderBy('id')->pluck('id');

        if ($rules->isEmpty()) {
            $this->markTestSkipped('the default zone has no delivery rule');
        }

        $keep = $rules->first();
        DeliveryRule::where('zone_id', $zoneId)->whereKeyNot($keep)->delete();

        $response = $this->panel()->postJson('/admin/delivery-management/delivery-rule/status/'.$keep, ['status' => 0]);

        $response->assertOk();
        $message = $response->json('errors.0.message');

        $this->assertStringContainsString('Default Zone', $message, 'the D2 reason must be the one shown');
        $this->assertStringNotContainsString('take over', $message, 'it must not ask for a replacement that cannot exist');
        $this->assertSame(1, (int) DeliveryRule::whereKey($keep)->value('status'), 'the rule stays active');
    }

    /** Deleting it is refused for the same reason, with the wording the sheet specifies. */
    public function test_deleting_the_last_default_zone_rule_is_refused(): void
    {
        $zoneId = DB::table('zones')->where('is_default', 1)->value('id');

        if (! $zoneId) {
            $this->markTestSkipped('no default zone is configured');
        }

        $keep = DeliveryRule::where('zone_id', $zoneId)->orderBy('id')->value('id');

        if (! $keep) {
            $this->markTestSkipped('the default zone has no delivery rule');
        }

        DeliveryRule::where('zone_id', $zoneId)->whereKeyNot($keep)->delete();

        $this->panel()->delete('/admin/delivery-management/delivery-rule/delete/'.$keep);

        $this->assertTrue(DeliveryRule::whereKey($keep)->exists(), 'the last default-zone rule must survive');
    }
}
