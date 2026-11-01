<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Store;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The vendor promotion screens, rendered against real rows.
 *
 * Same reasoning as the admin sweep: what breaks here is a contract between the blade, the route
 * and the controller, and none of the three fails until the page is opened.
 */
class VendorPromotionPanelTest extends TestCase
{
    use DatabaseTransactions;

    private ?Store $store = null;

    private ?Vendor $vendor = null;

    protected function setUp(): void
    {
        parent::setUp();

        // A store in a module that can actually run these, or the route guard 404s by design.
        $this->store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce']))
            ->whereNotNull('vendor_id')
            ->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no store in a promotion-capable module');
        }

        $this->vendor = Vendor::find($this->store->vendor_id);

        if (! $this->vendor) {
            $this->markTestSkipped('the fixture store has no vendor');
        }
    }

    public function test_both_vendor_lists_render(): void
    {
        $this->fixture();

        foreach (['vendor.bogo-offer.list', 'vendor.happy-hour.list'] as $route) {
            $this->actingAsVendor()->get(route($route))->assertOk("{$route} did not render");
        }
    }

    public function test_both_detail_drawers_render(): void
    {
        [$offer, $happyHour] = $this->fixture();

        $this->actingAsVendor()->get(route('vendor.bogo-offer.detail', $offer->id))->assertOk();
        $this->actingAsVendor()->get(route('vendor.happy-hour.detail', $happyHour->id))->assertOk();
    }

    /** The picker feeds a JS renderer, so a renamed field is an empty dropdown rather than an error. */
    public function test_the_item_picker_returns_the_stores_own_menu(): void
    {
        $response = $this->actingAsVendor()->get(route('vendor.bogo-offer.store-items'))->assertOk();

        foreach ($response->json() as $row) {
            $this->assertSame(
                (int) $this->store->id,
                (int) Item::withoutGlobalScopes()->find($row['id'])->store_id,
                'the picker must only ever return this store\'s own items'
            );
        }
    }

    /**
     * "Approved" is not "running", and the vendor is told which.
     *
     * The four live states exist because a vendor shown nothing cannot tell a bundle that is
     * selling from one whose only item sold out, nor a scheduled promotion from a broken one.
     */
    public function test_the_live_state_is_shown_alongside_the_enrolment_status(): void
    {
        [$offer] = $this->fixture();

        // Approved and in date: a customer can order it now.
        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.list'))
            ->assertOk()
            ->assertSee(translate('messages.Running now'), false);

        // Approved but not yet started reads as scheduled, not as broken.
        $offer->update(['start_date' => now()->addWeek(), 'end_date' => now()->addWeeks(2)]);

        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.list'))
            ->assertOk()
            ->assertSee(translate('messages.Scheduled'), false);
    }

    /** A row nobody has answered has no customer-facing state to report, so it shows none. */
    public function test_an_unanswered_request_shows_no_live_state(): void
    {
        [$offer] = $this->fixture();

        BogoOfferStore::where('bogo_offer_id', $offer->id)->update([
            'status' => 'pending',
            'requested_by' => 'admin',
        ]);

        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.list'))
            ->assertOk()
            ->assertDontSee(translate('messages.Running now'), false)
            ->assertDontSee(translate('messages.Not visible to customers'), false);
    }

    /**
     * A denial from the admin leaves reworking the selection as the only thing the store may do,
     * so that is exactly when the button has to be there.
     *
     * It is also when every other action is off: an admin's refusal may not be cancelled -- that
     * would erase it along with its reason -- and there is nothing to leave. Asking for the
     * actions as a chain therefore reached the delete first, found it blocked, and drew a footer
     * with no way out of the state at all.
     */
    public function test_an_admin_denial_still_offers_the_rework(): void
    {
        [$offer] = $this->fixture();

        BogoOfferStore::where('bogo_offer_id', $offer->id)->update([
            'status' => 'rejected',
            'rejected_by' => 'admin',
            'rejection_reason' => 'not enough stock',
        ]);

        foreach (['vendor.bogo-offer.list', 'vendor.bogo-offer.detail'] as $route) {
            $this->actingAsVendor()
                ->get(route($route, str_contains($route, 'detail') ? [$offer->id] : []))
                ->assertOk()
                ->assertSee('edit-items', false)
                ->assertDontSee(translate('messages.Cancel Request'), false);
        }
    }

    /**
     * Edit opens on the selection already saved.
     *
     * Rebuilding it from an empty drawer is not merely tedious: the buy and get totals must EQUAL
     * the offer's quantities, so a store changing one line had to reconstruct every other line
     * exactly or the save was refused wholesale.
     */
    public function test_the_rework_carries_the_frozen_selection(): void
    {
        [$offer] = $this->fixture();

        $enrollment = BogoOfferStore::with('items')->where('bogo_offer_id', $offer->id)->firstOrFail();

        if ($enrollment->items->isEmpty()) {
            $this->markTestSkipped('the fixture store has no item to freeze');
        }

        $payload = $enrollment->pickerPayload();

        $this->assertNotEmpty($payload['buy'], 'the payload must carry the buy side');
        $this->assertArrayHasKey('get', $payload, 'both sides travel, even when one is empty');
        $this->assertSame(
            (int) $enrollment->items->firstWhere('type', 'buy')->item_id,
            $payload['buy'][0]['item_id']
        );

        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.detail', $offer->id))
            ->assertOk()
            ->assertSee('data-payload', false);
    }

    /**
     * TC_153.3 — a rework empties the carts holding the old bundle.
     *
     * The frozen selection is what a cart row was priced against, so replacing it leaves the
     * customer holding a bundle the store no longer serves. Every other path that ends an
     * arrangement cleared carts; resubmit was the one that did not.
     */
    /**
     * A rework leaves the carts holding the old selection STRANDED, not emptied.
     *
     * Deleting them silently emptied a customer's cart from the vendor's request, and Place Order
     * then reported an empty cart rather than a changed offer. The row stays; the enrolment drops
     * to pending, so the bundle is unorderable and says so.
     */
    public function test_a_rework_strands_the_carts_holding_the_old_bundle(): void
    {
        [$offer] = $this->fixture();

        $enrollment = BogoOfferStore::with('items')->where('bogo_offer_id', $offer->id)->firstOrFail();
        $item = $enrollment->items->firstWhere('type', 'buy');

        if (! $item) {
            $this->markTestSkipped('the fixture store has no frozen item');
        }

        DB::table('carts')->insert([
            'user_id' => 1,
            'module_id' => $this->store->module_id,
            'is_guest' => 0,
            'item_id' => $item->item_id,
            'store_id' => $this->store->id,
            'item_type' => \App\Models\Item::class,
            'is_free_item' => 0,
            'price' => 10,
            'quantity' => 1,
            'bogo_offer_id' => $offer->id,
            'bogo_group_id' => 'probe-group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, DB::table('carts')->where('bogo_group_id', 'probe-group')->count());

        $payload = $enrollment->pickerPayload();

        $this->actingAsVendor()->post(route('vendor.bogo-offer.resubmit', $offer->id), [
            'buy_items' => $payload['buy'],
            'get_items' => $payload['get'],
        ]);

        $this->assertSame(1, DB::table('carts')->where('bogo_group_id', 'probe-group')->count(),
            'the row stays so the customer can be told what happened to it');

        // Unorderable is what makes that safe: the rework drops the enrolment to pending, so the
        // old selection can never be checked out at the price it was frozen at.
        $this->assertNotNull(
            app(\App\Services\Promotion\BogoOrderService::class)
                ->blockingReason(['user_id' => 1, 'is_guest' => 0], (int) $this->store->id),
            'a bundle priced against a reworked selection must be refused at checkout',
        );
    }

    /** TC_153.1 — the vendor is warned before the edit drawer opens, not after saving. */
    public function test_editing_the_items_is_confirmed_first(): void
    {
        [$offer] = $this->fixture();

        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.list'))
            ->assertOk()
            ->assertSee('editItemsModal', false)
            ->assertSee(translate('messages.Changing any item or combination removes this BOGO offer from every customer cart that already holds it'), false);
    }

    /** Rework runs through join's own guard, which refuses an offer that has finished. */
    public function test_the_rework_goes_with_the_offer(): void
    {
        [$offer] = $this->fixture();

        $offer->update(['start_date' => now()->subMonth(), 'end_date' => now()->subDay()]);

        $this->actingAsVendor()
            ->get(route('vendor.bogo-offer.detail', $offer->id))
            ->assertOk()
            ->assertDontSee('edit-items', false);
    }

    /** The routes are only reachable if something links to them. */
    public function test_the_sidebar_links_to_both_promotion_screens(): void
    {
        $response = $this->actingAsVendor()->get(route('vendor.happy-hour.list'))->assertOk();

        $response->assertSee(route('vendor.bogo-offer.list'), false);
        $response->assertSee(route('vendor.happy-hour.list'), false);
    }

    /**
     * TC_136.3 / TC_155.5 — an admin invitation is raised until it is ANSWERED.
     *
     * Not until the vendor opens the list. That was the first build, copied from the new-order
     * popup, and it is the wrong contract: a new order is information, an invitation is a decision
     * the admin is blocked on. Opening the list and closing it again used to silence the prompt
     * with nobody any further forward.
     */
    public function test_an_admin_invitation_is_raised_until_it_is_answered(): void
    {
        [$offer] = $this->fixture();

        // The fixture's BOGO row is the store's own request, so it must NOT prompt.
        $this->actingAsVendor()
            ->get(route('vendor.happy-hour.list'))
            ->assertOk()
            ->assertDontSee('BOGO Campaign Request', false);

        BogoOfferStore::where('bogo_offer_id', $offer->id)->update([
            'status' => 'pending',
            'requested_by' => 'admin',
            'checked' => 0,
        ]);

        // Raised on a page that is not the BOGO list itself.
        $this->actingAsVendor()
            ->get(route('vendor.happy-hour.list'))
            ->assertOk()
            ->assertSee('BOGO Campaign Request', false);

        // Opening the list marks the rows seen -- and must NOT silence the prompt.
        $this->actingAsVendor()->get(route('vendor.bogo-offer.list'))->assertOk();

        $this->assertSame(1, (int) BogoOfferStore::where('bogo_offer_id', $offer->id)->value('checked'));

        $this->actingAsVendor()
            ->get(route('vendor.happy-hour.list'))
            ->assertOk()
            ->assertSee('BOGO Campaign Request', false);

        // Answering it is what stops it: approving moves the row off pending.
        BogoOfferStore::where('bogo_offer_id', $offer->id)->update(['status' => 'approved']);

        $this->actingAsVendor()
            ->get(route('vendor.happy-hour.list'))
            ->assertOk()
            ->assertDontSee('BOGO Campaign Request', false);
    }

    /** @return array{0: BogoOffer, 1: HappyHour} */
    private function fixture(): array
    {
        $item = Item::withoutGlobalScopes()->where('store_id', $this->store->id)->first();

        $offer = BogoOffer::create([
            'module_id' => $this->store->module_id,
            'title' => 'vendor panel probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 1,
        ]);

        $enrollment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $this->store->id,
            'status' => 'approved',
            'requested_by' => 'store',
            'joined_at' => now(),
            'combination_signature' => hash('sha256', 'vendor-probe'),
        ]);

        if ($item) {
            foreach (['buy', 'get'] as $type) {
                BogoOfferItem::create([
                    'bogo_offer_store_id' => $enrollment->id,
                    'item_id' => $item->id,
                    'type' => $type,
                    'quantity' => 1,
                    'item_name' => $item->getRawOriginal('name'),
                    'item_image' => $item->image,
                    'price' => $type === 'buy' ? $item->price : 0,
                    'original_price' => $item->price,
                ]);
            }
        }

        $happyHour = HappyHour::create([
            'module_id' => $this->store->module_id,
            'title' => 'vendor happy hour probe',
            'discount' => 10,
            'duration_type' => 'daily',
            'is_permanent' => 0,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 1,
        ]);

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $this->store->id,
            'status' => 'pending',
            'requested_by' => 'admin',
            'joined_at' => now(),
        ]);

        return [$offer, $happyHour];
    }

    /**
     * VendorMiddleware compares session('login_remember_token') with the vendor's own and logs
     * the session out when they differ, so authenticating alone is not enough -- and the store
     * must be switched on, or the same middleware redirects home.
     */
    private function actingAsVendor(): self
    {
        return $this->actingAs($this->vendor, 'vendor')
            ->withSession(['login_remember_token' => $this->vendor->login_remember_token]);
    }
}
