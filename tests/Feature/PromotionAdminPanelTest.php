<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\HappyHour;
use App\Models\HappyHourStore;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The two admin promotion panels, end to end.
 *
 * The screens are worth sweeping rather than unit testing because most of what can break in them
 * is a contract between three files -- the blade names a route, the route names a controller
 * method, and the method hands back the variables the blade reads. Any one of the three can drift
 * on its own and nothing fails until the page is opened.
 *
 * Admin panel requests need module context in the session, not just an authenticated admin, and
 * because both features are module-scoped that is a variable these tests set deliberately rather
 * than boilerplate they copy.
 */
class PromotionAdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    private ?Admin $admin = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::first();

        if (! $this->admin) {
            $this->markTestSkipped('dataset has no admin');
        }
    }

    public function test_the_bogo_offer_form_renders(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.bogo-offer.add-new'))
            ->assertOk()
            ->assertSee(translate('messages.Create BOGO Offer'), false);
    }

    public function test_the_happy_hour_form_renders(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.happy-hour.add-new'))
            ->assertOk();
    }

    /**
     * Every admin screen of both features, rendered against real rows.
     *
     * One test rather than a dozen because they share the fixture: building an offer, a happy
     * hour, an enrolment and its frozen items once and then opening every screen is what makes
     * this cheap enough to keep.
     */
    public function test_every_promotion_screen_renders(): void
    {
        [$offer, $enrollment, $happyHour, $store] = $this->fixture();

        $screens = [
            'bogo list' => route('admin.bogo-offer.list'),
            'bogo edit' => route('admin.bogo-offer.edit', $offer->id),
            'bogo view' => route('admin.bogo-offer.view', $offer->id),
            'bogo enrolment drawer' => route('admin.bogo-offer.enrollment-detail', [$offer->id, $enrollment->id]),
            'bogo item picker' => route('admin.bogo-offer.store-items', ['store_id' => $store->id]),
            'happy hour list' => route('admin.happy-hour.list'),
            'happy hour edit' => route('admin.happy-hour.edit', $happyHour->id),
            'happy hour view' => route('admin.happy-hour.view', $happyHour->id),
        ];

        foreach ($screens as $label => $url) {
            $this->actingAsAdmin()->get($url)->assertOk("{$label} did not render: {$url}");
        }
    }

    /** Both exports stream a file rather than 500ing on a blade the screen never exercises. */
    public function test_every_promotion_export_downloads(): void
    {
        [$offer, , $happyHour] = $this->fixture();

        $exports = [
            'bogo list' => route('admin.bogo-offer.export', ['type' => 'excel']),
            'bogo stores' => route('admin.bogo-offer.store-export', ['id' => $offer->id, 'type' => 'csv']),
            'happy hour list' => route('admin.happy-hour.export', ['type' => 'excel']),
            'happy hour stores' => route('admin.happy-hour.store-export', ['id' => $happyHour->id, 'type' => 'csv']),
        ];

        foreach ($exports as $label => $url) {
            $this->actingAsAdmin()->get($url)->assertOk("{$label} export failed: {$url}");
        }
    }

    /**
     * Neither form asks for a module or a zone.
     *
     * The whole panel is module-scoped through the header's switcher, so a promotion belongs to
     * whichever module the admin was in -- exactly as a campaign does. Posting a module_id would
     * not set an attribute, it would switch the panel, because CurrentModule middleware reads any
     * module_id in the request as the switcher being used. And a happy hour has no zone at all:
     * how far it reaches follows from which stores enrol.
     */
    public function test_neither_form_asks_for_a_module_or_a_zone(): void
    {
        foreach (['admin.bogo-offer.add-new', 'admin.happy-hour.add-new'] as $route) {
            $response = $this->actingAsAdmin()->get(route($route));

            $response->assertOk();
            $response->assertDontSee('name="module_id"', false);
            $response->assertDontSee('name="zone_id"', false);
        }
    }

    /** And what is created lands in the module the admin was looking at. */
    public function test_a_promotion_is_stamped_with_the_panels_current_module(): void
    {
        $moduleId = Module::where('status', 1)->value('id');

        $this->actingAs($this->admin, 'admin')
            ->withSession(['current_module' => $moduleId, 'login_remember_token' => $this->admin->login_remember_token])
            ->post(route('admin.bogo-offer.store'), [
                'title' => ['current module probe'],
                'lang' => ['default'],
                'buy_qty' => 1,
                'get_qty' => 1,
                'start_date' => now()->format('Y-m-d\TH:i'),
                'end_date' => now()->addWeek()->format('Y-m-d\TH:i'),
                'image' => UploadedFile::fake()->image('offer.png', 300, 100),
            ])->assertOk();

        $this->assertDatabaseHas('bogo_offers', [
            'title' => 'current module probe',
            'module_id' => $moduleId,
        ]);
    }

    /**
     * The screens 404 outside a module type that can run them.
     *
     * The sidebar hides the entries, but hiding a link is not a rule: the panel is module-scoped
     * through the header's switcher, so an admin who switches to parcel with a promotion screen
     * open -- or who types the URL -- would otherwise reach a form that can only produce an offer
     * no customer could be served.
     */
    public function test_the_promotion_screens_404_in_an_incapable_module(): void
    {
        $incapable = Module::whereIn('module_type', ['parcel', 'rental', 'ride-share', 'service'])->first();

        if (! $incapable) {
            $this->markTestSkipped('dataset has no module of an incapable type');
        }

        foreach (['admin.bogo-offer.list', 'admin.happy-hour.list', 'admin.bogo-offer.add-new'] as $route) {
            $this->actingAs($this->admin, 'admin')
                ->withSession(['current_module' => $incapable->id, 'login_remember_token' => $this->admin->login_remember_token])
                ->get(route($route))
                ->assertNotFound("{$route} must 404 in a {$incapable->module_type} module");
        }
    }

    /** And they still open in a capable one, so the guard is not simply refusing everything. */
    public function test_the_promotion_screens_open_in_a_capable_module(): void
    {
        $capable = Module::whereIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce'])->first();

        if (! $capable) {
            $this->markTestSkipped('dataset has no module of a capable type');
        }

        foreach (['admin.bogo-offer.list', 'admin.happy-hour.list'] as $route) {
            $this->actingAs($this->admin, 'admin')
                ->withSession(['current_module' => $capable->id, 'login_remember_token' => $this->admin->login_remember_token])
                ->get(route($route))
                ->assertOk("{$route} must open in a {$capable->module_type} module");
        }
    }

    public function test_a_bogo_offer_can_be_created(): void
    {
        $moduleId = Module::value('id');

        // The form submits over ajax and stays on the page, so a save answers 200 with an empty
        // body rather than redirecting.
        $this->actingAsAdmin()->post(route('admin.bogo-offer.store'), [
            'title' => ['panel probe offer'],
            'description' => ['created by a test'],
            'lang' => ['default'],
            'buy_qty' => 2,
            'get_qty' => 1,
            'start_date' => now()->format('Y-m-d\TH:i'),
            'end_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'image' => UploadedFile::fake()->image('offer.png', 300, 100),
        ])->assertOk();

        $this->assertDatabaseHas('bogo_offers', [
            'title' => 'panel probe offer',
            'module_id' => $moduleId,
            'buy_qty' => 2,
            'get_qty' => 1,
        ]);
    }

    /**
     * A window that has already passed can never serve a bundle, so it cannot be saved.
     *
     * An edit is the exception: an offer that began before today keeps its own start as the floor,
     * or a running offer could not be touched without first being dragged forward.
     */
    public function test_a_bogo_offer_cannot_start_in_the_past(): void
    {
        $this->actingAsAdmin()->post(route('admin.bogo-offer.store'), [
            'title' => ['past date probe'],
            'lang' => ['default'],
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subWeek()->format('Y-m-d\TH:i'),
            'end_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'image' => UploadedFile::fake()->image('offer.png', 300, 100),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('bogo_offers', ['title' => 'past date probe']);

        // Today is fine.
        $this->actingAsAdmin()->post(route('admin.bogo-offer.store'), [
            'title' => ['today probe'],
            'lang' => ['default'],
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->format('Y-m-d\TH:i'),
            'end_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'image' => UploadedFile::fake()->image('offer.png', 300, 100),
        ])->assertOk();
    }

    /** And an end date before the start, or behind us, is refused too. */
    public function test_a_bogo_offer_cannot_end_in_the_past(): void
    {
        $this->actingAsAdmin()->post(route('admin.bogo-offer.store'), [
            'title' => ['past end probe'],
            'lang' => ['default'],
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->format('Y-m-d\TH:i'),
            'end_date' => now()->subDay()->format('Y-m-d\TH:i'),
            'image' => UploadedFile::fake()->image('offer.png', 300, 100),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('bogo_offers', ['title' => 'past end probe']);
    }

    /** An offer that already began stays editable without moving its start forward. */
    public function test_a_running_offer_keeps_its_own_start_as_the_floor(): void
    {
        $offer = BogoOffer::create([
            'module_id' => Module::value('id'),
            'title' => 'already running',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeek(),
            'status' => 1,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->withSession(['current_module' => $offer->module_id, 'login_remember_token' => $this->admin->login_remember_token])
            ->post(route('admin.bogo-offer.update', $offer->id), [
                'title' => ['already running'],
                'lang' => ['default'],
                'buy_qty' => 1,
                'get_qty' => 1,
                // Its own past start, unchanged.
                'start_date' => $offer->start_date->format('Y-m-d\TH:i'),
                'end_date' => now()->addWeeks(2)->format('Y-m-d\TH:i'),
            ])->assertOk();

        $this->assertDatabaseHas('bogo_offers', ['id' => $offer->id, 'title' => 'already running']);
    }

    /** §20.2: 6amMart has no dine-in concept, so the third value must be refused. */
    public function test_dine_in_is_rejected_as_an_order_type(): void
    {
        if (! BogoOffer::orderTypesEnabled()) {
            $this->markTestSkipped('order types are switched off, so the rule does not bite');
        }

        $this->actingAsAdmin()->post(route('admin.bogo-offer.store'), [
            'title' => ['dine in probe'],
            'lang' => ['default'],
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->format('Y-m-d\TH:i'),
            'end_date' => now()->addWeek()->format('Y-m-d\TH:i'),
            'order_types' => ['dine_in'],
            'image' => UploadedFile::fake()->image('offer.png'),
        ])->assertStatus(403);

        $this->assertDatabaseMissing('bogo_offers', ['title' => 'dine in probe']);
    }

    /**
     * The overlap rule, through the panel.
     *
     * Two happy hours in one module cannot share a minute -- which is what makes "the happy hour
     * running here" a safe singular everywhere else. The refusal has to arrive as
     * code "conflict", because that is what the form's inline alert keys on; a plain validation
     * error would toast and be missed.
     */
    public function test_an_overlapping_happy_hour_is_refused_with_the_clashing_window(): void
    {
        $this->clearHappyHoursForCurrentModule();

        $payload = [
            'title' => ['overlap probe'],
            'lang' => ['default'],
            'discount' => 10,
            'duration_type' => 'daily',
            // The schedule builder posts one hidden "start - end" string, not two date fields.
            'date_range' => now()->toDateString().' - '.now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'cover_image' => UploadedFile::fake()->image('cover.png', 300, 100),
            'icon' => UploadedFile::fake()->image('icon.png', 100, 100),
        ];

        $this->actingAsAdmin()->post(route('admin.happy-hour.store'), $payload)->assertOk();

        $this->assertDatabaseHas('happy_hours', ['title' => 'overlap probe']);

        // Same module, and 10:30 lands inside the hour the first one occupies.
        $clashing = array_merge($payload, [
            'title' => ['overlap probe two'],
            'start_time' => '10:30',
            'cover_image' => UploadedFile::fake()->image('cover2.png', 300, 100),
            'icon' => UploadedFile::fake()->image('icon2.png', 100, 100),
        ]);

        $this->actingAsAdmin()->post(route('admin.happy-hour.store'), $clashing)
            ->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'conflict');

        $this->assertDatabaseMissing('happy_hours', ['title' => 'overlap probe two']);
    }

    /** Adjacent windows are not overlapping: 10:00-11:00 and 11:00-12:00 must both be allowed. */
    public function test_a_back_to_back_happy_hour_is_allowed(): void
    {
        $this->clearHappyHoursForCurrentModule();

        $payload = [
            'title' => ['adjacent probe'],
            'lang' => ['default'],
            'discount' => 5,
            'duration_type' => 'daily',
            'date_range' => now()->toDateString().' - '.now()->toDateString(),
            'start_time' => '14:00',
            'cover_image' => UploadedFile::fake()->image('cover.png', 300, 100),
            'icon' => UploadedFile::fake()->image('icon.png', 100, 100),
        ];

        $this->actingAsAdmin()->post(route('admin.happy-hour.store'), $payload)->assertOk();

        $this->actingAsAdmin()->post(route('admin.happy-hour.store'), array_merge($payload, [
            'title' => ['adjacent probe two'],
            'start_time' => '15:00',
            'cover_image' => UploadedFile::fake()->image('cover2.png', 300, 100),
            'icon' => UploadedFile::fake()->image('icon2.png', 100, 100),
        ]))->assertOk();

        $this->assertDatabaseHas('happy_hours', ['title' => 'adjacent probe two']);
    }

    public function test_the_offer_detail_screen_lists_its_stores(): void
    {
        [$offer, , , $store] = $this->fixture();

        $this->actingAsAdmin()
            ->get(route('admin.bogo-offer.view', $offer->id))
            ->assertOk()
            ->assertSee(translate('messages.Store list'), false)
            ->assertSee($store->name, false);
    }

    /**
     * The picker feeds a JS renderer that reads named fields off each row, so a rename here is
     * an empty dropdown rather than an error.
     */
    public function test_the_item_picker_returns_the_shape_the_drawer_reads(): void
    {
        [, , , $store] = $this->fixture();

        $response = $this->actingAsAdmin()
            ->get(route('admin.bogo-offer.store-items', ['store_id' => $store->id]))
            ->assertOk();

        $rows = $response->json();

        if (! $rows) {
            $this->markTestSkipped('the fixture store has no items');
        }

        foreach (['id', 'name', 'image_full_url', 'price', 'variations', 'add_ons', 'is_available', 'maximum_cart_quantity'] as $field) {
            $this->assertArrayHasKey($field, $rows[0], "the picker row is missing {$field}");
        }
    }

    /**
     * The admin may only answer what the store asked for. Its own invitation waits on the store,
     * so approving one from this side would decide a conversation the store has not answered.
     */
    public function test_the_admin_cannot_approve_its_own_invitation(): void
    {
        [$offer, $enrollment] = $this->fixture();

        $enrollment->update(['requested_by' => 'admin']);

        $this->actingAsAdmin()
            ->post(route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'approved']))
            ->assertRedirect();

        $this->assertDatabaseHas('bogo_offer_store', [
            'id' => $enrollment->id,
            'status' => 'pending',
        ]);
    }

    /** A store's pending request is refused with a reason, never quietly removed. */
    public function test_a_pending_request_cannot_be_removed_without_an_answer(): void
    {
        [$offer, $enrollment] = $this->fixture();

        $this->actingAsAdmin()
            ->delete(route('admin.bogo-offer.remove-store', [$offer->id, $enrollment->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('bogo_offer_store', ['id' => $enrollment->id]);
    }

    /** And a rejection has to carry one: a blank reason tells the store nothing. */
    public function test_a_rejection_requires_a_reason(): void
    {
        [$offer, $enrollment] = $this->fixture();

        $this->actingAsAdmin()
            ->post(route('admin.bogo-offer.store-confirmation', [$offer->id, $enrollment->id, 'rejected']), [
                'rejection_reason' => '   ',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bogo_offer_store', [
            'id' => $enrollment->id,
            'status' => 'pending',
        ]);
    }

    /**
     * A live window may not be switched off: every store in it would jump back to full price
     * mid-session, while customers hold baskets priced at the happy hour rate.
     */
    public function test_a_running_happy_hour_cannot_be_switched_off(): void
    {
        $happyHour = HappyHour::create([
            'module_id' => Module::value('id'),
            'title' => 'running probe',
            'discount' => 10,
            'duration_type' => 'daily',
            'is_permanent' => false,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'start_time' => now()->subMinutes(10)->format('H:i:s'),
            'end_time' => now()->addMinutes(50)->format('H:i:s'),
            'status' => 1,
            'admin_id' => $this->admin->id,
        ]);

        if (! $happyHour->isRunningNow()) {
            $this->markTestSkipped('the fixture window did not resolve as running');
        }

        $this->actingAsAdmin()->get(route('admin.happy-hour.status', [$happyHour->id, 0]))->assertRedirect();

        $this->assertDatabaseHas('happy_hours', ['id' => $happyHour->id, 'status' => 1]);

        // Turning one ON is never blocked -- that only ever adds a discount.
        $happyHour->update(['status' => 0]);

        $this->actingAsAdmin()->get(route('admin.happy-hour.status', [$happyHour->id, 1]))->assertRedirect();

        $this->assertDatabaseHas('happy_hours', ['id' => $happyHour->id, 'status' => 1]);
    }

    /**
     * Switching an offer off must NOT empty the carts holding it.
     *
     * It used to delete those rows, and that is what put "You cannot place empty orders" in front
     * of a customer whose only cart item was the bundle: the rows vanished during the admin's
     * request, so Place Order had nothing left to explain. The row now stays, is shown
     * unavailable, and is refused by name at checkout.
     */
    public function test_switching_an_offer_off_strands_it_in_carts_rather_than_emptying_them(): void
    {
        $offer = BogoOffer::create([
            'module_id' => Module::value('id'),
            'title' => 'cart purge probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 1,
        ]);

        $store = Store::withoutGlobalScopes()->first();

        DB::table('carts')->insert([
            'user_id' => 1,
            'is_guest' => 0,
            'module_id' => $offer->module_id,
            'store_id' => $store->id,
            'item_id' => DB::table('items')->value('id'),
            'item_type' => Item::class,
            'price' => 100,
            'quantity' => 1,
            'bogo_offer_id' => $offer->id,
            'bogo_group_id' => 'probe-group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->get(route('admin.bogo-offer.status', [$offer->id, 0]))->assertRedirect();

        $this->assertDatabaseHas('carts', ['bogo_group_id' => 'probe-group']);

        // And it is genuinely unorderable, which is what makes keeping it safe.
        $this->assertNotNull(
            app(\App\Services\Promotion\BogoOrderService::class)
                ->blockingReason(['user_id' => 1, 'is_guest' => 0], (int) $store->id),
            'a stranded bundle must still be refused at checkout',
        );
    }

    /**
     * One offer, one happy hour, one store on each, with the offer's items frozen.
     *
     * Built from a real item so the screens have something to render: the enrolment drawer and
     * the visibility diagnostic both read the item behind every frozen line, and an enrolment
     * with no items exercises neither.
     *
     * @return array{0: BogoOffer, 1: BogoOfferStore, 2: HappyHour, 3: Store}
     */
    private function fixture(): array
    {
        $item = Item::withoutGlobalScopes()->whereNotNull('store_id')->first();

        if (! $item) {
            $this->markTestSkipped('dataset has no item');
        }

        $store = Store::withoutGlobalScopes()->find($item->store_id);

        if (! $store) {
            $this->markTestSkipped('the fixture item has no store');
        }

        $offer = BogoOffer::create([
            'module_id' => $store->module_id,
            'title' => 'panel fixture offer',
            'description' => 'built by PromotionAdminPanelTest',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 1,
            'admin_id' => $this->admin->id,
        ]);

        $enrollment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'requested_by' => 'store',
            'joined_at' => now(),
            'combination_signature' => hash('sha256', 'panel-fixture'),
            'checked' => 0,
        ]);

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

        $happyHour = HappyHour::create([
            'module_id' => $store->module_id,
            'title' => 'panel fixture happy hour',
            'short_description' => 'built by PromotionAdminPanelTest',
            'discount' => 15,
            'duration_type' => 'daily',
            'is_permanent' => false,
            // Tomorrow, so the edit screen is reachable: a running window refuses to open.
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'status' => 1,
            'admin_id' => $this->admin->id,
        ]);

        HappyHourStore::create([
            'happy_hour_id' => $happyHour->id,
            'store_id' => $store->id,
            'status' => 'pending',
            'requested_by' => 'store',
            'joined_at' => now(),
            'checked' => 0,
        ]);

        return [$offer, $enrollment, $happyHour, $store];
    }

    /**
     * Give the overlap tests a module with no windows in it.
     *
     * Both of them assert what the overlap rule does to a schedule THEY create, so a happy hour
     * that happens to be sitting in the dev database answers for them -- and a 409 from somebody
     * else's window looks exactly like the rule working. Rolled back with the test's transaction,
     * so this empties nothing for real.
     */
    private function clearHappyHoursForCurrentModule(): void
    {
        $moduleId = Module::value('id');

        DB::table('happy_hour_dates')->whereIn(
            'happy_hour_id',
            DB::table('happy_hours')->where('module_id', $moduleId)->pluck('id')
        )->delete();

        DB::table('happy_hours')->where('module_id', $moduleId)->delete();
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin')->withSession([
            'current_module' => Module::value('id'),
            'login_remember_token' => $this->admin->login_remember_token,
        ]);
    }
}
