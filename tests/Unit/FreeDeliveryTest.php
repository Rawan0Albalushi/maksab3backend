<?php

namespace Tests\Unit;

use App\Models\FreeDelivery;
use App\Models\Store;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Zone\ModuleZoneService;
use App\Traits\Order\DeliveryFeeTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * S6 — free delivery per (zone, module), and step 7a of §12.
 *
 * The three global business settings this replaces are DEPRECATED by owner decision: only the new
 * setups count, with no fallback. `test_the_deprecated_global_setting_is_ignored` is the one that
 * pins that, and it is deliberately written against the live settings rather than a fixture —
 * this install has the global switched on at 5000, so a regression would show as a free order.
 */
class FreeDeliveryTest extends TestCase
{
    use DatabaseTransactions;

    private FreeDeliveryService $free;

    private Store $store;

    private int $zoneId;

    private int $moduleId;

    private object $fees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->free = app(FreeDeliveryService::class);

        $row = \DB::table('stores')->whereNotNull('zone_id')->whereNotNull('module_id')->first();

        if (! $row) {
            $this->markTestSkipped('needs a store with a zone and a module');
        }

        $this->store = Store::find($row->id);
        $this->zoneId = (int) $row->zone_id;
        $this->moduleId = (int) $row->module_id;
        $this->fees = new class
        {
            use DeliveryFeeTrait;
        };
    }

    private function makeSetup(string $type, ?float $amount = null): FreeDelivery
    {
        return $this->free->create([
            'zone_id' => $this->zoneId,
            'module_ids' => [$this->moduleId],
            'type' => $type,
            'minimum_order_amount' => $amount,
        ]);
    }

    private function fee(float $orderAmount): array
    {
        return $this->fees->effectiveFee(50.0, $this->store, $orderAmount, null);
    }

    /** The whole point of the deprecation: the old global must not free anything. */
    public function test_the_deprecated_global_setting_is_ignored(): void
    {
        $result = $this->fee(999999.0);

        $this->assertSame(50.0, $result['fee'], 'no setup exists, so nothing is free however large the order');
        $this->assertNull($result['free_by']);
    }

    public function test_all_store_frees_every_order(): void
    {
        $this->makeSetup(FreeDelivery::TYPE_ALL);

        foreach ([1.0, 100.0, 99999.0] as $amount) {
            $this->assertSame(0.0, $this->fee($amount)['fee'], "at {$amount}");
            $this->assertSame('admin', $this->fee($amount)['free_by'], 'the admin bears the cost (F3)');
        }
    }

    public function test_specific_criteria_frees_only_at_the_threshold(): void
    {
        $this->makeSetup(FreeDelivery::TYPE_CRITERIA, 500.0);

        $this->assertSame(50.0, $this->fee(499.0)['fee']);
        $this->assertSame(0.0, $this->fee(500.0)['fee'], 'the threshold itself qualifies');
        $this->assertSame(0.0, $this->fee(501.0)['fee']);
    }

    /**
     * §10.3 — a criteria setup with NO amount covers every order. Rows saved before the form
     * required an amount carry null, and reading that as "never free" would silently switch off
     * setups an admin believes are live.
     */
    public function test_a_criteria_setup_without_an_amount_covers_everything(): void
    {
        $this->makeSetup(FreeDelivery::TYPE_CRITERIA, null);

        $this->assertSame(0.0, $this->fee(1.0)['fee']);
    }

    public function test_a_switched_off_setup_frees_nothing(): void
    {
        $setup = $this->makeSetup(FreeDelivery::TYPE_ALL);
        $this->free->updateStatus($setup->id, 0);

        $this->assertSame(50.0, $this->fee(9999.0)['fee']);
    }

    /** N5 — a setup for one module says nothing about another. */
    public function test_a_setup_does_not_leak_to_another_module(): void
    {
        $other = collect(app(ModuleZoneService::class)->connectedModuleIds($this->zoneId))
            ->reject(fn ($id) => $id === $this->moduleId)
            ->first();

        if (! $other) {
            $this->markTestSkipped('needs a second module connected to this zone');
        }

        $this->makeSetup(FreeDelivery::TYPE_ALL);
        $this->store->module_id = $other;

        $this->assertSame(50.0, $this->fee(9999.0)['fee']);
    }

    /** A zero fee short-circuits before any lookup — there is nothing to free. */
    public function test_a_zero_fee_is_left_alone(): void
    {
        $this->makeSetup(FreeDelivery::TYPE_ALL);

        $result = $this->fees->effectiveFee(0.0, $this->store, 9999.0, null);

        $this->assertSame(0.0, $result['fee']);
        $this->assertFalse($result['is_free'], 'nothing was freed — it was already free');
    }

    /** §10.2 — the wire names shipped clients switch on must not change. */
    public function test_the_api_type_keeps_the_old_wire_names(): void
    {
        $this->assertSame('free_delivery_to_all_store', $this->makeSetup(FreeDelivery::TYPE_ALL)->apiType());

        $criteria = new FreeDelivery(['type' => FreeDelivery::TYPE_CRITERIA]);
        $this->assertSame('free_delivery_by_specific_criteria', $criteria->apiType());
    }

    /** §10.3 — the first zone with a setup wins when the header carries several. */
    public function test_the_first_zone_with_a_setup_wins(): void
    {
        $otherZoneId = (int) \DB::table('zones')->where('id', '!=', $this->zoneId)->value('id');

        if (! $otherZoneId || ! in_array($this->moduleId, app(ModuleZoneService::class)->connectedModuleIds($otherZoneId), true)) {
            $this->markTestSkipped('needs a second zone carrying the same module');
        }

        $mine = $this->makeSetup(FreeDelivery::TYPE_ALL);
        $theirs = $this->free->create([
            'zone_id' => $otherZoneId, 'module_ids' => [$this->moduleId],
            'type' => FreeDelivery::TYPE_ALL,
        ]);

        $this->assertSame($theirs->id, $this->free->activeSetup([$otherZoneId, $this->zoneId], $this->moduleId)->id);
        $this->assertSame($mine->id, $this->free->activeSetup([$this->zoneId, $otherZoneId], $this->moduleId)->id);
    }
}
