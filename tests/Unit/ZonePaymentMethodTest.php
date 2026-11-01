<?php

namespace Tests\Unit;

use App\Models\Zone;
use App\Scopes\ZoneScope;
use App\Services\System\BusinessSettingService;
use App\Services\Zone\ZoneService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The zone's payment columns NARROW the platform settings; they never widen them.
 *
 * Until this rule was applied, the three columns were written by the Connect Module drawer and
 * read by nothing on the ordering path: a zone configured as cash-only accepted digital and
 * offline orders all the same, and `/config` advertised whatever the platform allowed globally.
 */
class ZonePaymentMethodTest extends TestCase
{
    use DatabaseTransactions;

    private function zoneWith(bool $cod, bool $digital, bool $offline): Zone
    {
        $zone = Zone::withoutGlobalScope(ZoneScope::class)->firstOrFail();
        $zone->cash_on_delivery = $cod;
        $zone->digital_payment = $digital;
        $zone->offline_payment = $offline;

        return $zone;
    }

    /** Every method the platform permits, so the zone column is the only thing under test. */
    private function enablePlatformPayments(): void
    {
        foreach ([
            'cash_on_delivery' => json_encode(['status' => 1]),
            'digital_payment' => json_encode(['status' => 1]),
            'offline_payment_status' => 1,
        ] as $key => $value) {
            DB::table('business_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }

        app(BusinessSettingService::class)::forgetCache();
    }

    public function test_a_zone_can_switch_a_method_off_that_the_platform_allows(): void
    {
        $this->enablePlatformPayments();

        $allowed = app(ZoneService::class)->allowedPaymentMethods($this->zoneWith(true, false, false));

        $this->assertTrue($allowed['cash_on_delivery']);
        $this->assertFalse($allowed['digital_payment'], 'the zone switched digital payment off');
        $this->assertFalse($allowed['offline_payment'], 'the zone switched offline payment off');
    }

    /**
     * The COD term specifically.
     *
     * The case above leaves cash_on_delivery ON, so dropping the zone term from that one pair
     * passes it — a mutation proved exactly that. Each of the three needs a case where the zone
     * is the side saying no.
     */
    public function test_a_zone_can_switch_cash_on_delivery_off_on_its_own(): void
    {
        $this->enablePlatformPayments();

        $allowed = app(ZoneService::class)->allowedPaymentMethods($this->zoneWith(false, true, true));

        $this->assertFalse($allowed['cash_on_delivery'], 'the zone switched cash on delivery off');
        $this->assertTrue($allowed['digital_payment']);
        $this->assertTrue($allowed['offline_payment']);
    }

    /**
     * The important direction: a zone must not be able to turn a method back ON.
     *
     * Switching COD off in the third-party settings has to mean off everywhere, whatever any zone
     * row happens to hold — which is why the global setting is the first term of every pair.
     */
    public function test_a_zone_cannot_re_enable_a_method_the_platform_has_switched_off(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => 'cash_on_delivery'],
            ['value' => json_encode(['status' => 0])],
        );
        app(BusinessSettingService::class)::forgetCache();

        $allowed = app(ZoneService::class)->allowedPaymentMethods($this->zoneWith(true, true, true));

        $this->assertFalse(
            $allowed['cash_on_delivery'],
            'the zone row re-enabled a method the platform forbids',
        );
    }

    /** No zone resolved — a client that has not chosen a location still learns what is supported. */
    public function test_no_zone_falls_back_to_the_platform_settings(): void
    {
        $this->enablePlatformPayments();

        $this->assertSame(
            ['cash_on_delivery' => true, 'digital_payment' => true, 'offline_payment' => true],
            app(ZoneService::class)->allowedPaymentMethods(null),
        );
    }

    /**
     * §10.3 — the zoneId header may carry several ids for overlapping zones, and the first that
     * resolves wins. An id that matches nothing is stepped over rather than ending the walk.
     */
    public function test_the_zone_id_walk_skips_ids_that_resolve_to_nothing(): void
    {
        $this->enablePlatformPayments();

        $zone = $this->zoneWith(true, false, false);
        $zone->save();

        $allowed = app(ZoneService::class)->allowedPaymentMethodsForZoneIds([999999, $zone->id]);

        $this->assertTrue($allowed['cash_on_delivery']);
        $this->assertFalse($allowed['digital_payment'], 'the walk did not reach the zone that resolves');
    }

    public function test_an_empty_zone_id_header_falls_back_to_the_platform_settings(): void
    {
        $this->enablePlatformPayments();

        $this->assertSame(
            app(ZoneService::class)->allowedPaymentMethods(null),
            app(ZoneService::class)->allowedPaymentMethodsForZoneIds([]),
        );
    }
}
