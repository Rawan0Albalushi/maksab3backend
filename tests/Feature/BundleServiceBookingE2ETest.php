<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Cart;
use App\Models\Store;
use App\Models\User;
use App\Services\Promotion\BundleCartService;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Service\Entities\Service;
use Tests\TestCase;

class BundleServiceBookingE2ETest extends TestCase
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
            ->whereHas('services')
            ->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no service store');
        }

        $map = [];
        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = $type === 'service' ? 1 : 0;
        }
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();

        $this->bundle = $this->makeServiceBundle();
    }

    public function test_a_service_bundle_goes_from_cart_to_booking_whole(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->markTestSkipped('dataset has no customer');
        }

        Cart::where('user_id', $user->id)->delete();

        $added = app(BundleCartService::class)->addBundle([
            'bundle_id' => $this->bundle->id,
            'quantity' => 2,
            'user_id' => $user->id,
            'is_guest' => 0,
            'module_id' => $this->store->module_id,
            'zone_ids' => [],
        ]);

        $this->assertSame(200, $added['status_code'], json_encode($added));

        $carts = Cart::where('bundle_group_id', $added['bundle_group_id'])->get();

        $lines = $carts->map(fn ($cart) => [
            'service_id' => $cart->item_id,
            'quantity' => (int) $cart->quantity,
            'price' => (float) $cart->price,
            'variation' => $cart->variation ? json_decode($cart->variation, true) : null,
            'bundle_id' => $cart->bundle_id,
            'bundle_group_id' => $cart->bundle_group_id,
        ])->all();

        $built = app(\Modules\Service\Services\ServiceBookingService::class)
            ->buildBookingDetails($this->store, $lines, 1);

        $this->assertArrayNotHasKey('code', $built, json_encode($built));

        $rows = $built['details_data'];

        foreach ($rows as $index => $row) {
            $this->assertSame($added['bundle_group_id'], $row['bundle_group_id'],
                'the group survives cart to booking');
            $this->assertSame($this->bundle->id, $row['bundle_id']);
            $this->assertSame(2, (int) $row['quantity'], 'both copies reach the booking');

            $frozen = (float) $this->bundle->items[$index]->unit_price;

            $this->assertEqualsWithDelta($frozen, (float) $row['original_price'], 0.01,
                'a member is booked at the price the bundle froze');
        }

        $reduction = collect($rows)->sum(fn ($row) => (float) $row['discount_amount'] * (int) $row['quantity']);
        $expected = ((float) $this->bundle->base_price - (float) $this->bundle->discounted_price) * 2;

        $this->assertEqualsWithDelta($expected, $reduction, 0.02,
            'two copies give twice the reduction, split across the members');

        Cart::where('bundle_group_id', $added['bundle_group_id'])->delete();
    }

    public function test_the_booking_view_folds_the_members_into_one_row(): void
    {
        $booking = DB::table('service_bookings')->where('module_id', $this->store->module_id)->first();

        if (! $booking) {
            $this->markTestSkipped('dataset has no service booking');
        }

        $group = 'e2e-booking-probe';

        foreach ($this->bundle->items as $line) {
            DB::table('service_booking_details')->insert([
                'booking_id' => $booking->id,
                'service_id' => $line->service_id,
                'service_name' => $line->item_name,
                'quantity' => 2,
                'price' => (float) $line->unit_price * 2,
                'original_price' => (float) $line->unit_price,
                'calculated_price' => (float) $line->unit_price * 2,
                'discount_amount' => 1,
                'discount_by' => 'vendor',
                'bundle_id' => $this->bundle->id,
                'bundle_group_id' => $group,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $model = \Modules\Service\Entities\ServiceBooking::with('details')->find($booking->id);

        $groups = app(\App\Services\Promotion\BundleOrderService::class)->orderGroups($model->details);

        DB::table('service_booking_details')->where('bundle_group_id', $group)->delete();

        $folded = collect($groups['bundles'])->firstWhere('group_id', $group);

        $this->assertNotNull($folded, 'the booking view must fold the members into one entry');
        $this->assertSame(2, $folded['quantity'], 'the entry counts copies, not members');
        $this->assertCount($this->bundle->items->count(), $folded['lines']);
        $this->assertSame($this->bundle->name, $folded['name']);

        foreach ($groups['plain'] as $plain) {
            $this->assertNotSame($group, $plain->bundle_group_id,
                'a member must not also appear as a loose line');
        }
    }

    public function test_the_invoice_shows_one_line_per_bundle(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingInvoiceTrait.php'));

        $this->assertStringContainsString('->orderGroups($booking->details)', $trait,
            'an invoice must not bill a bundle as loose services');
        $this->assertStringContainsString("\$groups['plain']->map", $trait,
            'members are folded out of the ordinary lines');
        $this->assertStringContainsString("foreach (\$groups['bundles'] as \$bundle)", $trait,
            'and the bundle gets its own line');
    }

    public function test_the_booking_api_names_the_bundle_on_its_lines(): void
    {
        $resource = file_get_contents(
            base_path('Modules/Service/Http/Resources/ServiceBookingDetailsResource.php')
        );

        foreach (['bundle_id', 'bundle_group_id'] as $key) {
            $this->assertStringContainsString("'{$key}' => \$detail->{$key},", $resource,
                "the app cannot group a booking's members without {$key}");
        }
    }

    public function test_the_customer_cart_shows_a_service_bundle_as_one_entry(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->markTestSkipped('dataset has no customer');
        }

        Cart::where('user_id', $user->id)->delete();

        $added = app(BundleCartService::class)->addBundle([
            'bundle_id' => $this->bundle->id,
            'quantity' => 2,
            'user_id' => $user->id,
            'is_guest' => 0,
            'module_id' => $this->store->module_id,
            'zone_ids' => [],
        ]);

        $this->assertSame(200, $added['status_code'], json_encode($added));

        config(['module.current_module_data' => ['id' => $this->store->module_id, 'module_type' => 'service']]);

        $rows = app(\Modules\Service\Services\ServiceCartService::class)->getCarts([
            'user' => $user,
        ]);

        Cart::where('bundle_group_id', $added['bundle_group_id'])->delete();

        $entries = collect($rows)->filter(fn ($row) => ! empty($row['bundle_details']));

        $this->assertCount(1, $entries,
            'two members are one cart entry, not two loose services');

        $entry = $entries->first();
        $details = $entry['bundle_details'];

        $this->assertSame($added['bundle_group_id'], $details['bundle_group_id']);
        $this->assertSame($this->bundle->id, $details['bundle_id']);
        $this->assertSame($this->bundle->name, $details['name']);
        $this->assertSame(2, $details['quantity'], 'the entry counts copies');
        $this->assertCount($this->bundle->items->count(), $details['items']);
        $this->assertTrue($details['is_available']);

        $this->assertEqualsWithDelta(
            (float) $this->bundle->discounted_price * 2,
            (float) $details['total_final_price'],
            0.02,
            'the cart quotes the same price the browse endpoint does',
        );

        $this->assertNull($entry['service_id'],
            'a folded entry is not one service, so it must not pretend to be');
    }

    public function test_the_app_booking_flow_keeps_the_bundle_from_cart_to_lines(): void
    {
        $user = User::orderBy('id')->first();

        if (! $user) {
            $this->markTestSkipped('dataset has no customer');
        }

        Cart::where('user_id', $user->id)->delete();

        $added = app(BundleCartService::class)->addBundle([
            'bundle_id' => $this->bundle->id,
            'quantity' => 2,
            'user_id' => $user->id,
            'is_guest' => 0,
            'module_id' => $this->store->module_id,
            'zone_ids' => [],
        ]);

        $this->assertSame(200, $added['status_code'], json_encode($added));

        $context = new \Modules\Service\Support\BookingContext([
            'provider_id' => $this->store->id,
        ]);

        $resolved = app(\Modules\Service\Services\ServiceBookingService::class)
            ->resolveBookingLines($context, $user->id, 0);

        Cart::where('bundle_group_id', $added['bundle_group_id'])->delete();

        $this->assertSame('cart', $resolved['source'], json_encode($resolved));

        foreach ($resolved['lines'] as $line) {
            $this->assertSame($added['bundle_group_id'], $line['bundle_group_id'],
                'the cart line must hand its group to the booking, or the bundle silently becomes loose services');
            $this->assertSame($this->bundle->id, $line['bundle_id']);
        }

        $built = app(\Modules\Service\Services\ServiceBookingService::class)
            ->buildBookingDetails($this->store, $resolved['lines'], 1);

        $this->assertArrayNotHasKey('code', $built, json_encode($built));

        $reduction = collect($built['details_data'])
            ->sum(fn ($row) => (float) $row['discount_amount'] * (int) $row['quantity']);

        $this->assertGreaterThan(0, $reduction,
            'a booking placed from the app must still get the bundle discount');

        $this->assertEqualsWithDelta(
            ((float) $this->bundle->base_price - (float) $this->bundle->discounted_price) * 2,
            $reduction,
            0.02,
        );
    }

    public function test_editing_a_booking_does_not_dissolve_its_bundle(): void
    {
        $trait = file_get_contents(base_path('Modules/Service/Traits/BookingEditTrait.php'));

        $this->assertStringContainsString("'bundle_id' => \$stored->bundle_id,", $trait,
            'an edit rebuilds every line, so a kept bundle line must carry its bundle');
        $this->assertStringContainsString("'bundle_group_id' => \$stored->bundle_group_id,", $trait,
            'without the group the booking silently stops being a bundle');

        $checkout = file_get_contents(base_path('Modules/Service/Traits/BookingCheckoutTrait.php'));

        $this->assertStringContainsString("'bundle_group_id' => \$cart->bundle_group_id,", $checkout,
            'and a cart line must hand its group to the booking it becomes');
    }

    public function test_the_customer_booking_view_shows_one_bundle_not_loose_services(): void
    {
        $booking = DB::table('service_bookings')->where('module_id', $this->store->module_id)->first();

        if (! $booking) {
            $this->markTestSkipped('dataset has no service booking');
        }

        $kept = DB::table('service_booking_details')->where('booking_id', $booking->id)->get();
        DB::table('service_booking_details')->where('booking_id', $booking->id)->delete();

        foreach ($this->bundle->items as $line) {
            DB::table('service_booking_details')->insert([
                'booking_id' => $booking->id,
                'service_id' => $line->service_id,
                'service_name' => $line->item_name,
                'quantity' => 2,
                'price' => (float) $line->unit_price * 2,
                'original_price' => (float) $line->unit_price,
                'calculated_price' => (float) $line->unit_price * 2,
                'discount_amount' => 2,
                'discount_by' => 'vendor',
                'bundle_id' => $this->bundle->id,
                'bundle_group_id' => 'customer-view-probe',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $model = \Modules\Service\Entities\ServiceBooking::with('details')->find($booking->id);

        $payload = (new \Modules\Service\Http\Resources\ServiceBookingDetailsResource($model))
            ->toArray(request());

        DB::table('service_booking_details')->where('booking_id', $booking->id)->delete();

        foreach ($kept as $row) {
            DB::table('service_booking_details')->insert((array) $row);
        }

        $lines = $payload['details'];

        $this->assertCount(1, $lines,
            'two members are one booking line, exactly as they were one cart entry');

        $line = $lines[0];

        $this->assertSame($this->bundle->name, $line['service_name'],
            'the customer sees the bundle they bought, not its parts');
        $this->assertNull($line['service_id'], 'a folded line is not one service');
        $this->assertSame(2, (int) $line['quantity'], 'the line counts copies');

        $this->assertNotNull($line['bundle_details']);
        $this->assertSame('customer-view-probe', $line['bundle_details']['bundle_group_id']);
        $this->assertCount($this->bundle->items->count(), $line['bundle_details']['items'],
            'and can still open to show what is inside');
    }

    private function makeServiceBundle(): Bundle
    {
        $services = Service::active(null, (int) $this->store->module_id)
            ->where('store_id', $this->store->id)
            ->take(2)->get();

        if ($services->count() < 2) {
            $this->markTestSkipped('the fixture provider has too few bookable services');
        }

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'E2E Service Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 20,
        ]);

        app(BundleService::class)->syncItems($bundle, [
            ['service_id' => $services[0]->id],
            ['service_id' => $services[1]->id],
        ]);

        return $bundle->fresh('items');
    }
}
