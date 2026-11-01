<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Contracts\View\View;
use Tests\TestCase;

class ProviderViewComposerTest extends TestCase
{
    private const APP_PROVIDER_VIEWS = [
        'layouts.admin.partials._sidebar_v2',
        'layouts.vendor.partials._sidebar',
        'layouts.vendor.partials._sidebar_v2',
        'layouts.vendor.partials._header',
        'layouts.vendor.partials._header_v2',
        'vendor-views.wallet.index',
        'vendor-views.wallet.payment_list',
        'vendor-views.wallet.disbursement',
        'vendor-views.pos._cart',
    ];

    private const RENTAL_PROVIDER_VIEWS = [
        'rental::admin.partials._sidebar_v2_rental',
        'rental::provider.partials._sidebar_rental',
        'rental::provider.partials._sidebar_v2_rental',
    ];

    private const VENDOR_SIDEBAR_KEYS = [
        'count_all', 'count_pending', 'count_confirmed', 'count_processing',
        'count_handover', 'count_picked_up', 'count_delivered', 'count_refunded',
        'count_scheduled',
    ];

    private const TRIP_COUNT_KEYS = [
        'total_trips', 'scheduled_trips', 'pending_trips', 'confirmed_trips',
        'ongoing_trips', 'completed_trips', 'canceled_trips', 'payment_failed_trips',
    ];

    public function test_app_service_provider_registers_every_composer(): void
    {
        foreach (self::APP_PROVIDER_VIEWS as $view) {
            $this->assertTrue(
                app('events')->hasListeners('composing: '.$view),
                "AppServiceProvider did not register a composer for [{$view}]."
            );
        }
    }

    public function test_rental_service_provider_registers_every_composer(): void
    {
        foreach (self::RENTAL_PROVIDER_VIEWS as $view) {
            $this->assertTrue(
                app('events')->hasListeners('composing: '.$view),
                "RentalServiceProvider did not register a composer for [{$view}]."
            );
        }
    }

    public function test_vendor_sidebar_composer_supplies_all_count_keys(): void
    {
        foreach (['layouts.vendor.partials._sidebar', 'layouts.vendor.partials._sidebar_v2'] as $view) {
            $data = $this->compose($view);

            foreach (self::VENDOR_SIDEBAR_KEYS as $key) {
                $this->assertArrayHasKey($key, $data, "[{$view}] is missing [{$key}].");
                $this->assertIsInt($data[$key]);
            }
        }
    }

    public function test_vendor_header_composer_supplies_all_chrome_keys(): void
    {
        $vendor = Vendor::first();

        if (! $vendor) {
            $this->markTestSkipped('The header composer reads the authenticated vendor; no vendor rows available.');
        }

        $this->actingAs($vendor, 'vendor');

        foreach (['layouts.vendor.partials._header', 'layouts.vendor.partials._header_v2'] as $view) {
            $data = $this->compose($view);

            foreach (['system_language_setting', 'system_languages', 'unread_message_count', 'store_wallet'] as $key) {
                $this->assertArrayHasKey($key, $data, "[{$view}] is missing [{$key}].");
            }

            $this->assertIsArray($data['system_languages']);
            $this->assertIsInt($data['unread_message_count']);
        }
    }

    public function test_pos_cart_composer_supplies_a_usable_summary(): void
    {
        $data = $this->compose('vendor-views.pos._cart');

        foreach (['cart_rows', 'subtotal', 'total', 'paid', 'change', 'pos_order_type'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $this->assertIsArray($data['cart_rows']);
    }

    public function test_rental_provider_composer_supplies_trip_counts_without_a_vendor_session(): void
    {
        foreach (['rental::provider.partials._sidebar_rental', 'rental::provider.partials._sidebar_v2_rental'] as $view) {
            $data = $this->compose($view);

            $this->assertArrayHasKey('tripCount', $data, "[{$view}] is missing [tripCount].");

            foreach (self::TRIP_COUNT_KEYS as $key) {
                $this->assertArrayHasKey($key, $data['tripCount'], "[{$view}] tripCount is missing [{$key}].");
            }
        }
    }

    public function test_admin_sidebar_composer_supplies_its_own_count_keys(): void
    {
        $data = $this->compose('layouts.admin.partials._sidebar_v2');

        foreach (['count_all', 'count_scheduled', 'count_unassigned', 'count_new_items', 'count_new_stores'] as $key) {
            $this->assertArrayHasKey($key, $data, "The admin sidebar composer is missing [{$key}].");
        }
    }

    public function test_sidebar_composers_degrade_to_zero_without_a_vendor_session(): void
    {
        $vendor = $this->compose('layouts.vendor.partials._sidebar');
        $rental = $this->compose('rental::provider.partials._sidebar_rental');

        foreach (self::VENDOR_SIDEBAR_KEYS as $key) {
            $this->assertSame(0, $vendor[$key], "Vendor sidebar [{$key}] should be 0 with no store in scope.");
        }

        foreach (self::TRIP_COUNT_KEYS as $key) {
            $this->assertSame(0, $rental['tripCount'][$key], "Rental sidebar [{$key}] should be 0 with no provider in scope.");
        }
    }

    private function compose(string $view): array
    {
        $stub = new class($view) implements View
        {
            public array $captured = [];

            public function __construct(private string $view) {}

            public function name()
            {
                return $this->view;
            }

            public function with($key, $value = null)
            {
                $this->captured = array_merge($this->captured, is_array($key) ? $key : [$key => $value]);

                return $this;
            }

            public function getData()
            {
                return $this->captured;
            }

            public function render()
            {
                return '';
            }
        };

        app('events')->dispatch('composing: '.$view, [$stub]);

        return $stub->captured;
    }
}
