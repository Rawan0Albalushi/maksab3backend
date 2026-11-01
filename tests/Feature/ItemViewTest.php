<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Item;
use App\Models\Review;
use App\Models\TempProduct;
use App\Scopes\StoreScope;
use App\Services\Item\TempProductService;
use Tests\TestCase;

class ItemViewTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function open(mixed $id): string
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token])
            ->get('/admin/item/view/'.$id)
            ->assertOk()->getContent();
    }

    /**
     * An approved item whose store still exists.
     *
     * `whereHas('store')` rather than `whereNotNull('store_id')`: the demo data carries items
     * pointing at stores that have since been removed, and the view guards that case by dropping
     * the store card — so picking one makes the store assertions fail on the data rather than on
     * the page.
     */
    private function itemId(): int
    {
        return (int) Item::withoutGlobalScope(StoreScope::class)
            ->where('is_approved', 1)
            ->whereHas('store')
            ->value('id');
    }

    public function test_the_page_ships_the_detail_cards_and_the_review_table(): void
    {
        $html = $this->open($this->itemId());

        $this->assertStringContainsString('idt-specs', $html);
        $this->assertStringContainsString('idt-metrics', $html);
        $this->assertStringContainsString('idt-store', $html);
        $this->assertStringContainsString('table-head', $html);
        $this->assertStringContainsString('page-area', $html, 'the review list is paginated');
    }

    public function test_a_reviewed_item_renders_its_rating_breakdown(): void
    {
        $item_id = Review::query()->value('item_id');

        if (! $item_id) {
            $this->markTestSkipped('no review to render');
        }

        $html = $this->open($item_id);

        $this->assertStringContainsString('idt-rating__value', $html);
        $this->assertStringContainsString('rvw-bar__fill', $html);
        $this->assertStringContainsString('status_form_alert', $html, 'each review keeps its visibility switch');
        $this->assertStringContainsString('data-label-on', $html, 'the switch names the state it sets');
    }

    public function test_an_item_awaiting_approval_links_to_its_request(): void
    {
        $request = TempProduct::withoutGlobalScope(StoreScope::class)->whereNotNull('item_id')->first();

        if (! $request) {
            $this->markTestSkipped('no pending item request');
        }

        $this->assertSame(
            $request->id,
            app(TempProductService::class)->pendingRequestIdFor($request->item_id)
        );

        $this->assertStringContainsString(
            route('admin.item.requested_item_view', ['id' => $request->id]),
            $this->open($request->item_id)
        );
    }

    public function test_the_review_export_carries_the_current_query(): void
    {
        $item_id = $this->itemId();
        $html = $this->open($item_id);

        $export = str_replace('&amp;', '&', $html);

        $this->assertStringContainsString(route('admin.item.item_wise_reviews_export').'?', $export);
        $this->assertStringContainsString('type=excel', $export);
        $this->assertStringContainsString('id='.$item_id, $export);
        $this->assertStringNotContainsString('0=', $export, 'the query string is merged, not appended positionally');
    }
}
