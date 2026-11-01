<?php

namespace Tests\Feature;

use App\CentralLogics\Helpers;
use App\Models\Admin;
use App\Models\Bundle;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Support\Promotion\BundleSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The admin bundle panel, end to end.
 *
 * Worth sweeping rather than unit testing because most of what can break here is a contract
 * between three files -- the blade names a route, the route names a controller method, and the
 * method hands back the variables the blade reads. Any one can drift on its own and nothing fails
 * until the page is opened.
 */
class BundleAdminPanelTest extends TestCase
{
    use DatabaseTransactions;

    private ?Admin $admin = null;

    private ?Store $store = null;

    protected function tearDown(): void
    {
        Helpers::clearBusinessSettingsCache();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::first();

        if (! $this->admin) {
            $this->markTestSkipped('dataset has no admin');
        }

        $this->store = Store::withoutGlobalScopes()->where('module_id', $this->moduleId())->first();

        if (! $this->store) {
            $this->markTestSkipped('dataset has no store in the current module');
        }

        $this->storeSettings(status: true);
    }

    public function test_the_list_renders(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.bundle.list'))
            ->assertOk();
    }

    public function test_the_header_count_is_the_total_not_the_filtered_page(): void
    {
        $items = $this->plainItems(2);

        $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Counted Combo'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ])->assertOk();

        $total = Bundle::where('module_id', $this->moduleId())->count();

        // A search that matches nothing must not make the header read zero.
        $html = $this->actingAsAdmin()
            ->get(route('admin.bundle.list', ['search' => 'zzz-matches-nothing']))
            ->assertOk()->getContent();

        $this->assertStringContainsString(
            '<span class="badge badge-soft-dark ml-2">'.$total.'</span>',
            $html,
            'the header badge is the total bundle count, not the filtered page total',
        );
    }

    public function test_the_list_carries_no_export_and_no_per_page_footer(): void
    {
        $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString('bundleExportDropdown', $html, 'the export belongs on the list');

        $this->assertStringNotContainsString('bundle-per-page', $html,
            'the per-page selector stays out, per the design');

        $footer = file_get_contents(base_path('resources/views/partials/bundle/_list_table.blade.php'));

        $this->assertStringNotContainsString('messages.Records', $footer);
        $this->assertStringNotContainsString('messages.Showing', $footer);

        $this->assertStringContainsString('table-head', $html,
            'the header block holds the auto margin that aligns the controls with the table');

        $this->assertStringContainsString('bundle-item-row', $html,
            'the items hover reuses the same row look as the builder');

        $this->assertTrue(
            $this->tableSitsInsideTheCardNotTheHeader($html),
            'the table belongs to the card, beside the header - nesting it inside '
                .'.search--button-wrapper makes it a flex item and collapses the layout',
        );
        $this->assertStringContainsString('bundle-popover__head', $html,
            'the hover names how many items the bundle holds');
        $this->assertStringContainsString('.bundle-item-row {', $html,
            'and the list page has to load those styles, or the hover is unstyled');
    }

    public function test_the_card_header_and_the_table_share_the_cards_edges(): void
    {
        $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<div class="[^"]*card-header[^"]*search--button-wrapper/',
            $html,
            '.search--button-wrapper carries margin: 0 -7px !important, so putting it on the '
                .'card-header pulls the header 7px wider than the card on both sides while the '
                .'table stays put - the wrapper has to be a child of the header, not the header',
        );

        $this->assertMatchesRegularExpression(
            '/<div class="card-header py-2 border-0">\s*<div class="search--button-wrapper">/',
            $html,
            'the header follows the shared list layout so its controls line up with the columns',
        );

        $tableWrapper = strpos($html, 'table-responsive datatable-custom');
        $pageArea = strpos($html, 'page-area');

        $this->assertNotFalse($tableWrapper);
        $this->assertNotFalse($pageArea);
        $this->assertGreaterThan(
            $this->closingOffset($html, strrpos(substr($html, 0, $tableWrapper), '<div')),
            $pageArea,
            'the pagination sits beside the scroll container, not inside it - within '
                .'.table-responsive its px-4 measures the scrolled width, not the card',
        );
    }

    public function test_a_search_with_no_match_shows_the_shared_empty_state(): void
    {
        $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()
            ->get(route('admin.bundle.list', ['search' => 'no-bundle-answers-to-this']))
            ->assertOk()->getContent();

        $this->assertStringContainsString('empty--data', $html,
            'an empty search result uses the shared illustration block, like every other list');
        $this->assertStringContainsString('illustrations/sorry.svg', $html);
        $this->assertStringContainsString(translate('messages.No data found'), $html);

        $this->assertStringNotContainsString('colspan=', $html,
            'the old inline row is not the system design');
        $this->assertStringNotContainsString('page-area', $html,
            'nothing to page through, so the pager goes with it');

        $this->assertStringContainsString('card-header', $html,
            'the search box has to stay reachable or the list cannot be recovered');
    }

    public function test_the_detail_drawer_follows_the_design(): void
    {
        $bundle = $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()
            ->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        $this->assertStringContainsString(translate('messages.Bundle Item').' #'.$bundle->id, $html,
            'the header names the bundle by id');

        foreach ([
            'bundle-detail__card', 'bundle-detail__summary',
            'bundle-detail__thumb', 'bundle-detail__title',
            'bundle-detail__status', 'bundle-detail__heading',
            'bundle-item-row', 'bundle-item-row__qty',
        ] as $hook) {
            $this->assertStringContainsString($hook, $html, "the drawer is missing {$hook}");
        }

        $this->assertStringContainsString('btn-soft-danger', $html,
            'the delete button is the soft red of the design, not a bordered danger button');

        $this->assertStringNotContainsString('badge-soft-', $html,
            'the visibility badge left the header; the toggle carries that meaning now');

        $this->assertStringContainsString(translate('messages.Bundle Activity Status'), $html);
        $this->assertStringNotContainsString(
            translate('messages.Customers only see a bundle while this is on'), $html,
            'the design carries no helper line under the toggle',
        );

        $this->assertMatchesRegularExpression(
            '/bundle-detail__card.*bundle-detail__status.*bundle-detail__heading/s',
            $html,
            'image, summary and toggle share one card, and Items follows it',
        );

        $styles = file_get_contents(base_path('resources/views/partials/bundle/_row_styles.blade.php'));

        $this->assertMatchesRegularExpression(
            '/\.bundle-detail__card\s*\{[^}]*background-color:\s*var\(--bs-body-bg\)/s',
            $styles,
            'the card carries the one background both of its halves sit on',
        );

        foreach (['bundle-detail__summary', 'bundle-detail__status'] as $half) {
            $this->assertDoesNotMatchRegularExpression(
                '/\.'.$half.'\s*\{[^}]*background/s',
                $styles,
                "{$half} must not paint its own background - the card is one colour, split by a divider",
            );
        }
        $this->assertMatchesRegularExpression('/\.bundle-detail__thumb\s*\{[^}]*align-self:\s*center/s', $styles,
            'a square thumbnail centred against a taller summary, never stranded at the top');
    }

    public function test_the_drawer_is_the_width_the_design_was_drawn_at(): void
    {
        $this->makeBundleWithImage();

        $list = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString('--offcanvas-width: 465px', $list,
            'the design is 465px wide; a wider drawer strands the thumbnail and spreads the rows');

        $page = file_get_contents(base_path('resources/views/partials/bundle/_view_page.blade.php'));

        $this->assertStringContainsString('max-width: 465px', $page,
            'the standalone view holds the same column width as the drawer');
    }

    public function test_the_drawer_dates_go_through_the_helper(): void
    {
        $bundle = $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()
            ->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        $this->assertStringContainsString(
            \App\CentralLogics\Helpers::time_date_format($bundle->start_date), $html,
            'the drawer validity uses the same helper as the list',
        );
    }

    public function test_status_delete_and_edit_all_raise_the_same_confirm_modal(): void
    {
        $bundle = $this->makeBundleWithImage();

        $list = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();
        $drawer = $this->actingAsAdmin()
            ->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('id="bundleConfirmModal"', $list, 'one modal serves all three');
        $this->assertStringContainsString('status-warning-modal', $list,
            'and it is the house confirm dialog, not a bespoke one');

        foreach (['bundle-status-toggle', 'bundle-delete-trigger', 'bundle-edit-trigger'] as $trigger) {
            $this->assertStringContainsString($trigger, $list, "{$trigger} must go through the modal");
        }

        $this->assertStringContainsString('bundle-delete-trigger', $drawer);
        $this->assertStringContainsString('bundle-edit-trigger', $drawer);

        foreach (glob(base_path('resources/views/partials/bundle/*.blade.php')) as $partial) {
            $source = file_get_contents($partial);
            $name = basename($partial);

            $this->assertStringNotContainsString('form-alert', $source,
                "{$name}: delete no longer uses the toastr confirm");
            $this->assertStringNotContainsString('Swal.fire', $source,
                "{$name}: status no longer uses the sweetalert confirm");
        }

        $this->assertMatchesRegularExpression(
            '/bundle-status-toggle[^>]*data-title="[^"]+"[^>]*data-message="[^"]+"/s',
            $list,
            'every trigger carries its own title and body for the shared modal',
        );

        $this->assertMatchesRegularExpression(
            '/data-dismiss="modal"[^>]*>\s*'.preg_quote(translate('messages.Cancel'), '/').'/s',
            $list,
            'the dialog offers a way out beside Ok, not only the header cross',
        );
    }

    public function test_the_list_dates_go_through_the_helper(): void
    {
        $bundle = $this->makeBundleWithImage();

        $html = $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertOk()->getContent();

        $this->assertStringContainsString(
            \App\CentralLogics\Helpers::time_date_format($bundle->start_date),
            $html,
            'dates must respect the configured time format and locale',
        );
    }

    public function test_the_create_form_renders(): void
    {
        $this->actingAsAdmin()
            ->get(route('admin.bundle.create'))
            ->assertOk()
            ->assertSee(translate('messages.Create Bundle'), false)
            ->assertSee(translate('messages.Total Base Price'), false);
    }

    public function test_the_thumbnail_is_marked_required_only_while_it_is(): void
    {
        $create = $this->actingAsAdmin()->get(route('admin.bundle.create'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/Thumbnail\s*<span class="text-danger">\*<\/span>/s',
            $create,
            'a new bundle must have an image, so the marker belongs on the create screen',
        );

        $bundle = $this->makeBundleWithImage();

        $edit = $this->actingAsAdmin()->get(route('admin.bundle.edit', $bundle->id))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/Thumbnail\s*<span class="text-danger">\*<\/span>/s',
            $edit,
            'an edit keeps the saved image, so the marker would promise a rule that is not enforced',
        );
    }

    private function tableSitsInsideTheCardNotTheHeader(string $html): bool
    {
        $cardStart = strpos($html, '<div class="card">');
        $cardEnd = $this->closingOffset($html, $cardStart);
        $headerStart = strrpos(substr($html, 0, strpos($html, 'card-header')), '<div');
        $headerEnd = $this->closingOffset($html, $headerStart);
        $table = strpos($html, '<table');

        return $table > $cardStart && $table < $cardEnd && $table > $headerEnd;
    }

    /** Offset just past the </div> that closes the element opening at $from. */
    private function closingOffset(string $html, int $from): int
    {
        preg_match_all('/<div\b|<\/div>/', substr($html, $from), $matches, PREG_OFFSET_CAPTURE);

        $depth = 0;

        foreach ($matches[0] as $match) {
            $depth += $match[0] === '<div' ? 1 : -1;

            if ($depth === 0) {
                return $from + $match[1] + strlen($match[0]);
            }
        }

        return strlen($html);
    }

    private function makeBundleWithImage(): Bundle
    {
        $items = $this->plainItems(2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Thumbnail Marker Fixture',
            'image' => 'existing.png',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
        ]);

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
            ]);
        }

        return $bundle->fresh('items');
    }

    public function test_the_form_offers_the_shared_picker_and_the_shared_engine(): void
    {
        $html = $this->actingAsAdmin()->get(route('admin.bundle.create'))->getContent();

        $this->assertStringContainsString('bundle-item-picker', $html);
        $this->assertStringContainsString('foodConfigModal', $html, 'the shared item options modal must be on the page');
        $this->assertStringContainsString('function resolveItemPrice', $html, 'the shared pricing engine must be on the page');
        $this->assertStringContainsString('function onItemConfigured', $html, "bundle's own selection half must be on the page");
    }

    public function test_the_item_picker_shows_the_price_and_bogo_is_unchanged(): void
    {
        $create = $this->actingAsAdmin()->get(route('admin.bundle.create'))->assertOk()->getContent();

        $this->assertStringContainsString('const itemPickerWithPrice = true;', $create,
            'the bundle picker opts into showing the price');
        $this->assertStringContainsString('food-option__price', $create);

        $engine = file_get_contents(base_path('resources/views/partials/promotion/_item_options_scripts.blade.php'));

        $this->assertStringContainsString('itemPickerShowsPrice()', $engine,
            'the price is opt-in, never unconditional');

        $bogo = file_get_contents(base_path('resources/views/partials/bogo/_item_picker_scripts.blade.php'));

        $this->assertStringNotContainsString('itemPickerWithPrice', $bogo,
            'BOGO must not opt in, so its picker keeps the look it shipped with');

        $styles = file_get_contents(base_path('resources/views/partials/promotion/_item_picker_styles.blade.php'));

        $this->assertStringNotContainsString('food-search--detailed', $styles,
            'the search box follows BOGO, so there is no second variant of it');
    }

    public function test_a_bundle_can_be_created(): void
    {
        $items = $this->plainItems(2);

        $response = $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Weekend Combo'],
            'description' => ['Two of our best'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 10,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [
                ['item_id' => $items[0]->id],
                ['item_id' => $items[1]->id],
            ],
        ]);

        $response->assertOk()->assertJsonPath('redirect', route('admin.bundle.list'));

        $bundle = Bundle::where('name', 'Weekend Combo')->firstOrFail();

        $this->assertSame($this->store->id, $bundle->store_id);
        $this->assertSame($this->store->module_id, $bundle->module_id, 'the module is taken from the store, never from the request');
        $this->assertSame('admin', $bundle->created_by);
        $this->assertSame(2, $bundle->items()->count());

        $expectedBase = round((float) $items[0]->price + (float) $items[1]->price, (int) config('round_up_to_digit'));
        $this->assertSame($expectedBase, $bundle->base_price);
        $this->assertSame(round($expectedBase * 0.9, (int) config('round_up_to_digit')), $bundle->discounted_price);
    }

    /**
     * Translations must round-trip: written on create, overwritten on edit, and read back through
     * the model's translated accessors.
     *
     * The mechanism moved from the translation repository to TranslationService, and nothing else
     * covered it -- a swap like that is exactly where a silent regression lives.
     */
    public function test_translations_are_written_and_overwritten(): void
    {
        $items = $this->plainItems(2);
        $locales = getWebConfig('language') ?: [];

        if (! $locales) {
            $this->markTestSkipped('no extra locales configured');
        }

        $locale = $locales[0];

        $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default', $locale],
            'name' => ['Translated Combo', 'Localised Name'],
            'description' => ['Default text', 'Localised text'],
            'discount_percentage' => 0,
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ])->assertOk();

        $bundle = Bundle::where('name', 'Translated Combo')->firstOrFail();

        $this->assertDatabaseHas('translations', [
            'translationable_type' => Bundle::class,
            'translationable_id' => $bundle->id,
            'locale' => $locale,
            'key' => 'name',
            'value' => 'Localised Name',
        ]);

        $this->actingAsAdmin()->post(route('admin.bundle.update', $bundle->id), [
            'lang' => ['default', $locale],
            'name' => ['Translated Combo', 'Renamed Localised'],
            'description' => ['Default text', 'Localised text'],
            'discount_percentage' => 0,
            'store_id' => $bundle->store_id,
            'start_date' => $bundle->start_date->format('Y-m-d H:i:s'),
            'end_date' => $bundle->end_date->format('Y-m-d H:i:s'),
            'items' => [['item_id' => $items[0]->id], ['item_id' => $items[1]->id]],
        ])->assertOk();

        $this->assertDatabaseHas('translations', [
            'translationable_type' => Bundle::class,
            'translationable_id' => $bundle->id,
            'locale' => $locale,
            'key' => 'name',
            'value' => 'Renamed Localised',
        ]);

        $this->assertSame(
            1,
            (int) \Illuminate\Support\Facades\DB::table('translations')
                ->where('translationable_type', Bundle::class)
                ->where('translationable_id', $bundle->id)
                ->where('locale', $locale)->where('key', 'name')->count(),
            'an edit must overwrite the row, not add a second one',
        );
    }

    public function test_one_item_is_refused_in_the_shape_the_form_reads(): void
    {
        $items = $this->plainItems(1);

        $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Too Small'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $items[0]->id]],
        ])
            ->assertStatus(403)
            ->assertJsonStructure(['errors' => [['code', 'message']]]);
    }

    public function test_the_same_item_twice_is_two_rows_and_twice_the_price(): void
    {
        $item = $this->plainItems(1)[0];

        $this->actingAsAdmin()->post(route('admin.bundle.store'), [
            'lang' => ['default'],
            'name' => ['Double Up'],
            'store_id' => $this->store->id,
            'start_date' => now()->addHour()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'discount_percentage' => 0,
            'image' => UploadedFile::fake()->image('combo.png'),
            'items' => [['item_id' => $item->id], ['item_id' => $item->id]],
        ])->assertOk();

        $bundle = Bundle::where('name', 'Double Up')->firstOrFail();

        $this->assertSame(2, $bundle->items()->count());
        $this->assertSame(round((float) $item->price * 2, (int) config('round_up_to_digit')), $bundle->base_price);
    }

    public function test_the_edit_form_prefills_the_saved_rows(): void
    {
        $bundle = $this->makeBundle();

        $html = $this->actingAsAdmin()->get(route('admin.bundle.edit', $bundle->id))->assertOk()->getContent();

        $this->assertStringContainsString('const seedItems = ', $html);
        $this->assertStringContainsString('"item_id":'.$bundle->items->first()->item_id, $html);
        $this->assertStringContainsString('function seedBundleItems', $html);
    }

    public function test_the_detail_answers_a_drawer_body_over_ajax_and_a_page_otherwise(): void
    {
        $bundle = $this->makeBundle();

        $drawer = $this->actingAsAdmin()
            ->get(route('admin.bundle.view', $bundle->id), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('custom-offcanvas-header', $drawer);
        $this->assertStringNotContainsString('<html', $drawer, 'the ajax answer is a drawer body, not a page');

        $page = $this->actingAsAdmin()->get(route('admin.bundle.view', $bundle->id))->assertOk()->getContent();

        $this->assertStringContainsString('<html', $page);
        $this->assertStringContainsString(translate('messages.Bundle Activity Status'), $page);
    }

    public function test_the_items_endpoint_answers_the_stores_menu(): void
    {
        $item = $this->plainItems(1)[0];

        $payload = $this->actingAsAdmin()
            ->get(route('admin.bundle.items', ['store_id' => $this->store->id]))
            ->assertOk()
            ->json();

        $row = collect($payload)->firstWhere('id', $item->id);

        $this->assertNotNull($row);
        $this->assertArrayHasKey('uses_food_variations', $row);
        $this->assertArrayHasKey('choice_options', $row);
        $this->assertEqualsWithDelta((float) $item->price, $row['price'], 0.001, 'the picker leads with the base price, never the discounted one');
    }

    public function test_the_panel_is_closed_while_the_module_is_not_enabled(): void
    {
        $this->storeSettings(status: false);

        $this->actingAsAdmin()->get(route('admin.bundle.list'))->assertNotFound();
        $this->actingAsAdmin()->get(route('admin.bundle.create'))->assertNotFound();
    }

    /**
     * Deleting a bundle must STRAND it in the carts holding it, not empty them.
     *
     * The delete used to run from the admin's request, so a customer whose only cart item was this
     * bundle had their cart emptied by somebody else's click, and Place Order then reported an
     * empty cart instead of a withdrawn bundle. The row stays and is refused by name at checkout.
     */
    public function test_deleting_a_bundle_strands_it_in_carts_rather_than_emptying_them(): void
    {
        $bundle = $this->makeBundle();

        DB::table('carts')->insert([
            'user_id' => 1,
            'item_id' => $bundle->items->first()->item_id,
            'is_guest' => 0,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'admin-delete-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->delete(route('admin.bundle.delete', $bundle->id))->assertRedirect();

        $this->assertSame(1, DB::table('carts')->where('bundle_id', $bundle->id)->count(),
            'the row stays so the customer can be told what happened to it');

        // Unorderable is what makes keeping it safe.
        $this->assertNotNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => 1, 'is_guest' => 0], (int) $bundle->store_id),
            'a deleted bundle must still be refused at checkout',
        );
    }

    public function test_turning_a_bundle_off_from_the_panel_strands_it_in_carts(): void
    {
        $bundle = $this->makeBundle();

        $this->seedCartRow($bundle);

        $this->actingAsAdmin()
            ->patch(route('admin.bundle.status', [$bundle->id, 0]))
            ->assertRedirect();

        $this->assertSame(0, (int) $bundle->fresh()->status, 'the switch actually flipped');
        $this->assertSame(1, DB::table('carts')->where('bundle_id', $bundle->id)->count(),
            'a bundle a customer can no longer buy stays in their cart, flagged, rather than vanishing');

        $this->assertNotNull(
            app(\App\Services\Promotion\BundleOrderService::class)
                ->blockingReason(['user_id' => 1, 'is_guest' => 0], (int) $bundle->store_id),
            'a switched-off bundle must still be refused at checkout',
        );
    }

    public function test_turning_a_bundle_back_on_leaves_carts_alone(): void
    {
        $bundle = $this->makeBundle();
        $bundle->forceFill(['status' => 0])->save();

        $this->seedCartRow($bundle);

        $this->actingAsAdmin()
            ->patch(route('admin.bundle.status', [$bundle->id, 1]))
            ->assertRedirect();

        $this->assertSame(1, (int) $bundle->fresh()->status);
        $this->assertSame(1, DB::table('carts')->where('bundle_id', $bundle->id)->count(),
            'switching on is not a reason to empty anyone cart');
    }

    private function seedCartRow(Bundle $bundle): void
    {
        DB::table('carts')->insert([
            'user_id' => 1,
            'item_id' => $bundle->items->first()->item_id,
            'is_guest' => 0,
            'quantity' => 1,
            'price' => 10,
            'bundle_id' => $bundle->id,
            'bundle_group_id' => 'panel-status-probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeBundle(): Bundle
    {
        $items = $this->plainItems(2);

        $bundle = Bundle::create([
            'store_id' => $this->store->id,
            'module_id' => $this->store->module_id,
            'name' => 'Panel Fixture Bundle',
            'start_date' => now()->subHour(),
            'end_date' => now()->addWeek(),
            'discount_percentage' => 5,
        ]);

        foreach ($items as $item) {
            BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $item->image,
                'unit_price' => $item->price,
            ]);
        }

        return $bundle->fresh('items');
    }

    /** @return \Illuminate\Support\Collection<int, Item> */
    private function plainItems(int $count)
    {
        $items = Item::withoutGlobalScopes()
            ->where('store_id', $this->store->id)
            ->whereIn('food_variations', ['[]', '', '"[]"'])
            ->orWhereNull('food_variations')
            ->where('store_id', $this->store->id)
            ->take($count)
            ->get();

        if ($items->count() < $count) {
            $this->markTestSkipped('dataset has too few plain items in this store');
        }

        return $items->values();
    }

    private function storeSettings(bool $status): void
    {
        $currentType = Module::where('id', $this->moduleId())->value('module_type');
        $map = [];

        foreach (BundleSettings::moduleTypes() as $type) {
            $map[$type] = ($status && $type === $currentType) ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::STATUS_KEY], ['value' => $status ? 1 : 0]);
        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], ['value' => json_encode($map)]);
        Helpers::clearBusinessSettingsCache();
    }

    private function moduleId(): ?int
    {
        return Module::value('id');
    }

    private function actingAsAdmin(): self
    {
        return $this->actingAs($this->admin, 'admin')->withSession([
            'current_module' => $this->moduleId(),
            'login_remember_token' => $this->admin->login_remember_token,
        ]);
    }
}
