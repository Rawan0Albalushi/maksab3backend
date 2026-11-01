<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\FreeDelivery;
use App\Services\Zone\FreeDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * QA cases TC_428, TC_436, TC_444 and TC_450 on Free Delivery Setup.
 *
 * The threshold is the whole point of a Specific Criteria setup, and three things about it were
 * wrong: the list never showed it, a value beyond the column was silently rewritten, and a
 * rejected one named the raw column at the admin.
 */
class FreeDeliveryGuardsTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    private string $base = '/admin/delivery-management/free-delivery';

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

    /** A (zone, module) pair no free-delivery setup has claimed yet. */
    private function freePair(): array
    {
        $taken = DB::table('free_delivery_module as m')
            ->join('free_deliveries as f', 'f.id', '=', 'm.free_delivery_id')
            ->get(['f.zone_id', 'm.module_id'])
            ->map(fn ($r) => $r->zone_id.':'.$r->module_id)->all();

        foreach (DB::table('module_zone')->get(['zone_id', 'module_id']) as $pair) {
            if (! in_array($pair->zone_id.':'.$pair->module_id, $taken, true)) {
                return [(int) $pair->zone_id, (int) $pair->module_id];
            }
        }

        $this->markTestSkipped('every connected pair already carries a free delivery setup');
    }

    private function payload(array $overrides = []): array
    {
        [$zoneId, $moduleId] = $this->freePair();

        return $overrides + [
            'zone_id' => $zoneId,
            'module_ids' => [$moduleId],
            'type' => FreeDelivery::TYPE_CRITERIA,
            'minimum_order_amount' => 500,
        ];
    }

    /** decimal(10,2) — a larger value used to be stored as 99999999.99 with no error. */
    public function test_a_threshold_beyond_the_column_is_refused(): void
    {
        $before = FreeDelivery::count();

        $this->panel()
            ->post($this->base.'/store', $this->payload(['minimum_order_amount' => 99999999999]))
            ->assertSessionHasErrors('minimum_order_amount');

        $this->assertSame($before, FreeDelivery::count());
    }

    /** The message must name the field the screen shows, not the column. */
    public function test_validation_messages_name_the_fields_the_screen_shows(): void
    {
        $this->panel()->post($this->base.'/store', $this->payload([
            'zone_id' => 999999,
            'type' => 'totally_free',
        ]));

        $errors = session('errors');

        $this->assertStringNotContainsString('zone id', $errors->first('zone_id'));
        $this->assertStringContainsString(translate('messages.Zone'), $errors->first('zone_id'));
        $this->assertStringContainsString(translate('messages.Free Delivery Type'), $errors->first('type'));
    }

    /** The threshold has to be visible on the list — it is what the setup does. */
    public function test_the_list_shows_the_minimum_order_amount(): void
    {
        $body = $this->panel()->get($this->base)->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Minimum Order Amount'), $body);
    }

    /** TC_444 — the threshold is inclusive, and below it the normal charge stands. */
    public function test_the_threshold_is_inclusive(): void
    {
        $setup = FreeDelivery::where('type', FreeDelivery::TYPE_CRITERIA)
            ->whereNotNull('minimum_order_amount')
            ->where('status', 1)
            ->with('modules')
            ->first();

        if (! $setup || $setup->modules->isEmpty()) {
            $this->markTestSkipped('no active specific-criteria setup with a threshold exists');
        }

        $service = app(FreeDeliveryService::class);
        $zone = [$setup->zone_id];
        $module = $setup->modules->first()->id;
        $min = (float) $setup->minimum_order_amount;

        $this->assertFalse($service->frees($zone, $module, $min - 0.01), 'below the threshold pays');
        $this->assertTrue($service->frees($zone, $module, $min), 'the threshold itself is free');
        $this->assertTrue($service->frees($zone, $module, $min + 1), 'above the threshold is free');
    }

    /**
     * TC_435, TC_436 — a blank threshold made Specific Criteria behave exactly like Free Delivery
     * for all Store, with nothing on screen explaining why. Fixed by owner decision: the CREATE
     * and UPDATE requests now require the field for that type. A legacy row saved before this
     * rule existed still reads its null as "always free" — that model behaviour is deliberately
     * untouched, only new saves are blocked from leaving it blank.
     */
    public function test_a_legacy_null_threshold_still_frees_every_order(): void
    {
        $setup = new FreeDelivery(['type' => FreeDelivery::TYPE_CRITERIA, 'minimum_order_amount' => null]);

        $this->assertTrue($setup->frees(0.0), 'a null threshold frees every order');
    }

    public function test_a_blank_threshold_is_refused_for_specific_criteria(): void
    {
        $before = FreeDelivery::count();

        $this->panel()
            ->post($this->base.'/store', $this->payload(['minimum_order_amount' => null]))
            ->assertSessionHasErrors('minimum_order_amount');

        $this->assertSame($before, FreeDelivery::count());
    }

    public function test_a_blank_threshold_is_accepted_for_all_store(): void
    {
        [$zoneId, $moduleId] = $this->freePair();

        $this->panel()->post($this->base.'/store', [
            'zone_id' => $zoneId,
            'module_ids' => [$moduleId],
            'type' => FreeDelivery::TYPE_ALL,
            'minimum_order_amount' => null,
        ])->assertSessionDoesntHaveErrors('minimum_order_amount');
    }
}
