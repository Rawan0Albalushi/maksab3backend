<?php

namespace Tests\Unit;

use App\Http\Controllers\Admin\ParcelController;
use App\Models\ParcelCategory;
use App\Services\System\ConfigService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\AI\app\Agents\AiResponseContext;
use Modules\AI\app\Agents\Tools\GetParcelCategoriesTool;
use Tests\TestCase;

/**
 * S15 — Parcel Settings keeps the deliveryman commission and nothing else.
 *
 * Amendment A14: the global `parcel_per_km_shipping_charge` and `parcel_minimum_shipping_charge`
 * stopped pricing anything in S14, when a parcel began costing what its zone's delivery rule
 * quotes plus the category's own additional charge. This section removes the inputs that wrote
 * them, so the screen stops collecting two numbers that change no fee.
 */
class ParcelSettingsStrippedTest extends TestCase
{
    use DatabaseTransactions;

    /** The screen used to reject a save that did not carry both rates — they were `required`. */
    public function test_the_settings_screen_saves_with_only_the_commission(): void
    {
        $before = DB::table('business_settings')->where('key', 'parcel_commission_dm')->value('value');

        app(ParcelController::class)->update_settings(
            Request::create('/admin/parcel/settings', 'POST', ['parcel_commission_dm' => 17]),
        );

        $this->assertSame('17', (string) DB::table('business_settings')->where('key', 'parcel_commission_dm')->value('value'));

        DB::table('business_settings')->where('key', 'parcel_commission_dm')->update(['value' => $before]);
    }

    /** Posting the two dead fields must not resurrect them — nothing writes them any more. */
    public function test_a_stale_form_posting_the_old_rates_does_not_write_them(): void
    {
        DB::table('business_settings')->whereIn('key', [
            'parcel_per_km_shipping_charge', 'parcel_minimum_shipping_charge',
        ])->update(['value' => 3]);

        app(ParcelController::class)->update_settings(Request::create('/admin/parcel/settings', 'POST', [
            'parcel_commission_dm' => 10,
            'parcel_per_km_shipping_charge' => 99,
            'parcel_minimum_shipping_charge' => 999,
        ]));

        $this->assertSame('3', (string) DB::table('business_settings')->where('key', 'parcel_per_km_shipping_charge')->value('value'));
        $this->assertSame('3', (string) DB::table('business_settings')->where('key', 'parcel_minimum_shipping_charge')->value('value'));
    }

    /**
     * N9 — a shipped app branches on these keys, so they stay in the payload. They answer 0.00
     * because reporting the stored rate would put a fee on a customer's screen that nothing
     * charges.
     */
    public function test_config_keeps_both_keys_and_reports_them_as_zero(): void
    {
        DB::table('business_settings')->whereIn('key', [
            'parcel_per_km_shipping_charge', 'parcel_minimum_shipping_charge',
        ])->update(['value' => 42]);

        // The absolute URL is deliberate: api routes are registered under `app.host_domain`, so a
        // root-relative path 404s in tests. (The repo's one existing feature test,
        // ConfigControllerMapApiTest, fails 20 of 36 for exactly this reason — pre-existing.)
        $response = $this->withHeaders(['zoneId' => json_encode([1]), 'moduleId' => 1])
            ->getJson('http://'.config('app.host_domain').'/api/v1/config');

        $response->assertOk();
        $content = $response->json('content') ?? $response->json();

        $this->assertArrayHasKey('parcel_per_km_shipping_charge', $content);
        $this->assertArrayHasKey('parcel_minimum_shipping_charge', $content);
        $this->assertSame(0.0, (float) $content['parcel_per_km_shipping_charge']);
        $this->assertSame(0.0, (float) $content['parcel_minimum_shipping_charge']);
    }

    /** The AI assistant must not quote a rate nothing charges, the way it did before A14. */
    public function test_the_ai_tool_reports_the_additional_charge_not_the_dead_rates(): void
    {
        $category = ParcelCategory::query()->active()->first();

        if (! $category) {
            $this->markTestSkipped('needs an active parcel category');
        }

        DB::table('parcel_categories')->where('id', $category->id)->update([
            'charge' => 35,
            'parcel_per_km_shipping_charge' => 99,
            'parcel_minimum_shipping_charge' => 999,
        ]);

        $output = (new GetParcelCategoriesTool(new AiResponseContext, null))
            ->handle(new \Laravel\Ai\Tools\Request(['limit' => 10]));

        $this->assertStringContainsString('35', $output);
        $this->assertStringContainsString('on top of the delivery fee', $output);
        $this->assertStringNotContainsString('99', $output);
        $this->assertStringNotContainsString('999', $output);
        $this->assertStringNotContainsString('/km', $output);
    }

    /** Rollback safety: the rows keep their values, they are simply never read. */
    public function test_the_deprecated_settings_rows_are_left_in_place(): void
    {
        foreach (['parcel_per_km_shipping_charge', 'parcel_minimum_shipping_charge'] as $key) {
            $this->assertTrue(
                DB::table('business_settings')->where('key', $key)->exists(),
                "{$key} was deleted; it should be kept so a rollback loses nothing",
            );
        }
    }
}
