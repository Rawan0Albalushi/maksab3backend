<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Service\Entities\Service;
use Tests\TestCase;

class BundleServiceCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private ?Store $store = null;

    private ?Bundle $bundle = null;

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->where('module_type', 'service'))
            ->whereHas('services')->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no service provider');
        }

        $map = [];
        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === 'service' ? 1 : 0;
        }
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();

        $this->bundle = $this->serviceBundle();
    }

    public function test_the_bundle_reduction_reaches_the_booking_as_a_vendor_borne_discount(): void
    {
        $lines = $this->bundle->items->map(fn ($line) => [
            'service_id' => $line->service_id,
            'quantity' => 1,
            'price' => (float) $line->unit_price,
            'variation' => $line->variations ?: null,
            'bundle_id' => $this->bundle->id,
            'bundle_group_id' => 'completion-probe',
        ])->all();

        $built = app(\Modules\Service\Services\ServiceBookingService::class)
            ->buildBookingDetails($this->store, $lines, 1);

        $this->assertArrayNotHasKey('code', $built, json_encode($built));

        $expected = (float) $this->bundle->base_price - (float) $this->bundle->discounted_price;

        $this->assertEqualsWithDelta($expected, (float) $built['discount'], 0.02,
            'what the booking records as its discount must be what the bundle took off');

        $this->assertSame('vendor', $built['discount_by'],
            'a bundle reduction is the providers own promotion, so completion splits it by commission');
    }

    public function test_completion_splits_a_vendor_discount_between_admin_and_provider(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingLogicTrait.php'));

        $this->assertStringContainsString(
            "if (\$booking->discount_amount > 0 && \$booking->discount_by == 'vendor') {",
            $trait,
            'the vendor-borne branch is what a bundle booking lands in',
        );

        $this->assertStringContainsString(
            '$amount_admin = $comission ? ($booking->discount_amount / 100) * $comission : 0;',
            $trait,
            'the admin bears the commission-rate share of the discount',
        );

        $this->assertStringContainsString(
            '$store_d_amount = $booking->discount_amount - $amount_admin;',
            $trait,
            'and the provider bears the rest',
        );

        $this->assertGreaterThanOrEqual(
            1,
            substr_count($trait, "type: 'discount_on_booking', created_by: 'vendor'"),
            'the provider half is booked as its own expense row',
        );
        $this->assertGreaterThanOrEqual(
            1,
            substr_count($trait, "type: 'discount_on_booking', created_by: 'admin'"),
            'and so is the admin half',
        );
    }

    public function test_commission_is_charged_before_the_bundle_discount(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingLogicTrait.php'));

        $this->assertStringContainsString('+ $store_d_amount + $amount_admin', $trait,
            'both halves of the discount are added back before commission is worked out, '
                .'so a bundle does not shrink what the provider is charged commission on');
    }

    public function test_every_booking_path_records_what_the_bundle_gave(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingCheckoutTrait.php'));

        $this->assertStringContainsString(
            "\$booking->bundle_discount_amount = \$data['bundle_discount_amount'] ?? 0;",
            $trait,
            'the booking row itself must carry the column, or only some paths fill it',
        );

        $this->assertSame(
            3,
            substr_count($trait, "'bundle_discount_amount' => \$details_response['bundle_discount_amount'] ?? 0,"),
            'all three booking paths pass it: regular, repeat parent and repeat child',
        );
    }

    public function test_the_discount_survives_the_pricing_cap(): void
    {
        $capped = \App\CentralLogics\Helpers::minDiscountCheck(
            productPrice: (float) $this->bundle->base_price,
            discount: (float) $this->bundle->base_price - (float) $this->bundle->discounted_price,
        );

        $this->assertEqualsWithDelta(
            (float) $this->bundle->base_price - (float) $this->bundle->discounted_price,
            $capped['discount_applied'],
            0.01,
            'the pricing step caps a discount at the price, so a bundle reduction passes through whole',
        );
    }

    public function test_the_booking_list_names_the_bundle_not_its_members(): void
    {
        $resource = file_get_contents(
            base_path('Modules/Service/Http/Resources/ServiceBookingListResource.php')
        );

        $this->assertStringContainsString('->orderGroups($this->details)', $resource,
            'the list preview must name what the customer bought');
        $this->assertStringContainsString("'is_bundle' => true", $resource,
            'and say which entries are bundles');
    }

    public function test_every_service_surface_folds_through_one_method(): void
    {
        $surfaces = [
            'Modules/Service/Traits/BookingInvoiceTrait.php' => 'invoice',
            'Modules/Service/Http/Resources/ServiceBookingDetailsResource.php' => 'booking detail api',
            'Modules/Service/Http/Resources/ServiceBookingListResource.php' => 'booking list api',
            'Modules/Service/Resources/views/admin/booking/partials/_repeat-booking-details-left-section.blade.php' => 'panel booking view',
        ];

        foreach ($surfaces as $file => $name) {
            $this->assertStringContainsString('orderGroups(', file_get_contents(base_path($file)),
                "the {$name} must fold a bundle through the shared method, not its own rule");
        }
    }

    public function test_changing_a_bundle_booking_quantity_keeps_it_whole(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingEditTrait.php'));

        $this->assertStringContainsString("if (\$stored && \$stored->bundle_group_id) {", $trait,
            'a quantity change must not fall through to the live-service branch, '
                .'which reprices from today menu and drops the group');

        $this->assertStringContainsString("'original_price' => \$unitPrice,", $trait);
        $this->assertStringContainsString('app(BundleOrderService::class)->distributeReduction', $trait,
            'an edit redistributes the bundle reduction over the new quantities');

        $this->assertStringContainsString('$providerDiscount = $hasBundleLines ? null : Helpers::get_store_discount($provider);', $trait,
            'and a provider-wide rate still does not take over while a bundle is present');

        $this->assertStringContainsString("\$booking->bundle_discount_amount = \$calc['bundle_discount_amount'] ?? 0;", $trait,
            'the edit restates what the bundle gave');
    }

    public function test_an_edited_bundle_line_never_takes_the_service_own_discount(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingEditTrait.php'));

        $this->assertStringContainsString("\$lineServiceMeta[\$bundleKey] = ['type' => 'amount', 'pct' => 0.0];", $trait,
            'a bundle line carries the bundle reduction, never a percentage off the service');
    }

    public function test_the_booking_editor_shows_a_bundle_as_one_quantity_only_row(): void
    {
        $modal = file_get_contents(base_path(
            'Modules/Service/Resources/views/admin/booking/partials/_service-update-modal.blade.php'
        ));

        $this->assertStringContainsString('function displayRows()', $modal,
            'the editor must fold the members into one row');
        $this->assertStringContainsString('edit-bundle-qty', $modal,
            'one quantity control drives the whole group');
        $this->assertStringContainsString('edit-bundle-remove', $modal,
            'and one delete removes it whole');
        $this->assertStringContainsString("lines = lines.filter(row => row.bundle_group_id !== group);", $modal,
            'removing a bundle drops every member, never just the first');
        $this->assertStringContainsString('row.quantity = unit * copies;', $modal,
            'the quantity posted is copies of the bundle, scaled across its members');
        $this->assertStringContainsString('!l.bundle_group_id && String(l.service_id)', $modal,
            'adding a service must never merge into a bundle member');

        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingEditTrait.php'));

        $this->assertStringContainsString("'bundle_group_id' => \$d->bundle_group_id,", $trait,
            'the editor payload has to say which lines belong to a bundle');
        $this->assertStringContainsString("'bundle_name' =>", $trait,
            'and name the bundle, since the folded row shows it');
    }

    private function serviceBundle(): Bundle
    {
        $services = Service::active(null, (int) $this->store->module_id)
            ->where('store_id', $this->store->id)->take(2)->get();

        if ($services->count() < 2) {
            $this->markTestSkipped('the fixture provider has too few bookable services');
        }

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Completion Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 25,
        ]);

        app(BundleService::class)->syncItems($bundle, [
            ['service_id' => $services[0]->id],
            ['service_id' => $services[1]->id],
        ]);

        return $bundle->fresh('items');
    }
}
