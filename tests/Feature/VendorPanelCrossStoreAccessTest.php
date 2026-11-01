<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The core vendor panel and vendor API, held to the vendor's own store -- the pieces outside the
 * item screens (VendorCrossStoreAccessTest) and the rental/service modules
 * (VendorRentalServiceCrossStoreAccessTest).
 *
 * Most of these models carry no StoreScope, so a bare find() or route-model binding reached every
 * store's row. The worst of them changed money or ownership: another store's settings columns,
 * another vendor's withdraw request, another store's plan bought from its wallet.
 */
class VendorPanelCrossStoreAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // VendorNav memoises permission answers per process; an earlier test's vendor would
        // otherwise decide which pages this one may open.
        \App\Navigation\VendorNav::flush();
    }

    public function test_vendor_api_refuses_an_unknown_vendor_type(): void
    {
        $this->getJson('/api/v1/vendor/profile', ['Authorization' => 'Bearer anything', 'vendorType' => 'nobody'])
            ->assertUnauthorized();
    }

    public function test_business_plan_without_a_login_only_reaches_a_store_still_registering(): void
    {
        // Not on commission already, or switching it to commission would change nothing.
        $store = DB::table('stores')->whereIn('store_business_model', ['subscription', 'unsubscribed'])->first();

        $this->postJson('/api/v1/vendor/business_plan', ['store_id' => $store->id, 'business_plan' => 'commission']);
        $this->post('/vendor/business-plan', ['store_id' => $store->id, 'business_plan' => 'commission-base']);

        $this->assertSame($store->store_business_model, DB::table('stores')->where('id', $store->id)->value('store_business_model'));
    }

    public function test_store_settings_toggle_refuses_another_store_and_columns_off_the_list(): void
    {
        [$vendor, $own, $other] = $this->twoStores();

        $this->as($vendor)->get("/vendor-panel/business-settings/toggle-settings-status/{$other->id}/0/delivery")->assertNotFound();
        $this->as($vendor)->get("/vendor-panel/business-settings/toggle-settings-status/{$own->id}/999/vendor_id")->assertNotFound();

        $this->assertSame($other->delivery, DB::table('stores')->where('id', $other->id)->value('delivery'));
        $this->assertSame($own->vendor_id, DB::table('stores')->where('id', $own->id)->value('vendor_id'));
    }

    public function test_closing_another_vendors_withdraw_request_is_refused(): void
    {
        $request = DB::table('withdraw_requests')->whereNotNull('vendor_id')->first();
        $store = $request ? DB::table('stores')->where('vendor_id', '!=', $request->vendor_id)->where('status', 1)->first() : null;

        if (! $store) {
            $this->markTestSkipped('needs a vendor withdraw request and a store of another vendor');
        }

        $this->as(Vendor::find($store->vendor_id))->delete("/vendor-panel/wallet/close/{$request->id}");

        $this->assertTrue(DB::table('withdraw_requests')->where('id', $request->id)->exists());
    }

    public function test_another_stores_order_and_an_admin_coupon_are_left_alone(): void
    {
        [$vendor, $own] = $this->twoStores();
        $order = DB::table('orders')->where('store_id', '!=', $own->id)->first();
        $coupon = DB::table('coupons')->where('created_by', 'admin')->first();

        $this->as($vendor)->post('/vendor-panel/order/update-order-amount', ['order_id' => $order->id, 'order_amount' => 1]);
        if ($coupon) {
            $this->as($vendor)->get("/vendor-panel/coupon/status/{$coupon->id}/".($coupon->status ? 0 : 1));
            $this->assertSame($coupon->status, DB::table('coupons')->where('id', $coupon->id)->value('status'));
        }

        $this->assertEquals($order->order_amount, DB::table('orders')->where('id', $order->id)->value('order_amount'));
    }

    public function test_another_stores_delivery_man_cannot_be_edited(): void
    {
        $dm = DB::table('delivery_men')->whereNotNull('store_id')->first();
        // The delivery-man pages are only open to a store that runs its own delivery, and an
        // expired subscription would turn the request away before it reached the check.
        $store = $dm ? DB::table('stores')->where('id', '!=', $dm->store_id)->where(['status' => 1, 'self_delivery_system' => 1, 'store_business_model' => 'commission'])
            ->whereIn('module_id', DB::table('modules')->whereIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce'])->pluck('id'))
            ->first() : null;

        if (! $store) {
            $this->markTestSkipped('needs a store-owned delivery man and another store');
        }

        $this->as(Vendor::find($store->vendor_id))->get("/vendor-panel/delivery-man/edit/{$dm->id}")->assertNotFound();
        $this->as(Vendor::find($store->vendor_id))->get("/vendor-panel/delivery-man/status/{$dm->id}/".($dm->status ? 0 : 1));

        $this->assertSame($dm->status, DB::table('delivery_men')->where('id', $dm->id)->value('status'));
    }

    /** A vendor with an active store, that store, and another active store. */
    private function twoStores(): array
    {
        $own = DB::table('stores')->where('status', 1)->whereIn('module_id', DB::table('modules')->whereIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce'])->pluck('id'))->first();
        $other = DB::table('stores')->where('status', 1)->where('vendor_id', '!=', $own->vendor_id)->first();

        return [Vendor::findOrFail($own->vendor_id), $own, $other];
    }

    private function as(Vendor $vendor): self
    {
        return $this->actingAs($vendor, 'vendor')
            ->withSession(['login_remember_token' => $vendor->login_remember_token]);
    }
}
