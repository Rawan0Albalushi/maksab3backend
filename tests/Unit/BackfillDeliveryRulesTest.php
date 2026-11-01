<?php

namespace Tests\Unit;

use App\Models\DeliveryRule;
use App\Services\Order\DeliveryChargeService;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S16 — the pivot → rules backfill, port doc A15.
 *
 * The command's whole contract is "reproduce today's pricing exactly". Its own dry run proves
 * that against live data; these pin the two things a dry run cannot: that it refuses to write
 * when a fee would move, and that running it twice does not double up.
 */
class BackfillDeliveryRulesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_dry_run_writes_nothing(): void
    {
        $before = DeliveryRule::withoutGlobalScopes()->count();

        $this->artisan('delivery-rules:backfill')->assertSuccessful();

        $this->assertSame($before, DeliveryRule::withoutGlobalScopes()->count());
    }

    public function test_applying_creates_one_active_rule_per_unruled_pair(): void
    {
        $unruled = \DB::table('module_zone')->get()
            ->reject(fn ($p) => app(DeliveryRuleService::class)->activeRule($p->zone_id, $p->module_id) !== null)
            ->count();

        if ($unruled === 0) {
            $this->markTestSkipped('every pair already has an active rule');
        }

        $before = DeliveryRule::withoutGlobalScopes()->count();

        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();

        $this->assertSame($before + $unruled, DeliveryRule::withoutGlobalScopes()->count());
    }

    public function test_every_backfilled_rule_is_active(): void
    {
        // A15 — a rule that is switched off does not satisfy the availability gate, which is the
        // whole reason this backfill exists. Creating them off would look harmless and take the
        // platform down the moment S12 ships.
        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();

        foreach (\DB::table('module_zone')->get() as $pivot) {
            $this->assertNotNull(
                app(DeliveryRuleService::class)->activeRule($pivot->zone_id, $pivot->module_id),
                "zone {$pivot->zone_id} module {$pivot->module_id} has no ACTIVE rule after the backfill",
            );
        }
    }

    public function test_no_fee_moves(): void
    {
        $engine = app(DeliveryChargeService::class);
        $pivots = \DB::table('module_zone')->get();
        $distances = [0.0, 2.5, 18.0, 120.0];

        $quote = fn ($pivot, $distance) => $engine->quote([
            'order_type' => 'delivery',
            'distance' => $distance,
            'store' => null,
            'module_zone_pivot' => $pivot,
            'zone_id' => $pivot->zone_id,
            'module_id' => $pivot->module_id,
            'surge' => null,
        ])['delivery_charge'];

        $before = [];
        foreach ($pivots as $pivot) {
            foreach ($distances as $distance) {
                $before[$pivot->zone_id.':'.$pivot->module_id.':'.$distance] = $quote($pivot, $distance);
            }
        }

        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();

        foreach ($pivots as $pivot) {
            foreach ($distances as $distance) {
                $key = $pivot->zone_id.':'.$pivot->module_id.':'.$distance;
                $this->assertEqualsWithDelta($before[$key], $quote($pivot, $distance), 0.00001, $key);
            }
        }
    }

    public function test_running_it_twice_creates_nothing_the_second_time(): void
    {
        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();
        $after = DeliveryRule::withoutGlobalScopes()->count();

        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();

        $this->assertSame($after, DeliveryRule::withoutGlobalScopes()->count());
    }

    public function test_a_fixed_pivot_becomes_a_fixed_rule_floored_at_its_flat_amount(): void
    {
        $fixed = \DB::table('module_zone')->where('delivery_charge_type', '!=', 'distance')->first();

        if (! $fixed) {
            $this->markTestSkipped('no fixed-price pivot to observe');
        }

        $this->artisan('delivery-rules:backfill --apply')->assertSuccessful();

        $rule = app(DeliveryRuleService::class)->activeRule($fixed->zone_id, $fixed->module_id);

        $this->assertSame(DeliveryRule::METHOD_FIXED, $rule->pricing_method);
        $this->assertEqualsWithDelta((float) $fixed->fixed_shipping_charge, (float) $rule->fixed_charge, 0.001);
        // The engine ignores a fixed pivot's own minimum, so the faithful rule floors at the flat
        // amount rather than at a number that never applied.
        $this->assertEqualsWithDelta((float) $fixed->fixed_shipping_charge, (float) $rule->minimum_delivery_charge, 0.001);
    }
}
