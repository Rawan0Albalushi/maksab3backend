<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Review;
use App\Models\Store;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A vendor must not reach another store's items or reviews by putting their id in the URL.
 *
 * The vendor item screens find items with a bare `Item::find($id)` and rely on StoreScope to add
 * the store filter. When that scope was attached at boot time it never attached at all, so every
 * one of these requests acted on the other store's row; the route sweeps never noticed, because
 * they only ever use the vendor's own ids.
 */
class VendorCrossStoreAccessTest extends TestCase
{
    use DatabaseTransactions;

    private Vendor $vendor;

    private Store $store;

    private Item $foreignItem;

    protected function setUp(): void
    {
        parent::setUp();

        $store = Store::where(['status' => 1, 'item_section' => 1])
            ->whereHas('items')
            ->first();
        $foreignItem = $store
            ? Item::withoutGlobalScopes()->where('store_id', '!=', $store->id)->first()
            : null;

        if (! $store || ! $foreignItem) {
            $this->markTestSkipped('needs an active store with items and an item from another store');
        }

        $this->store = $store;
        $this->vendor = Vendor::findOrFail($store->vendor_id);
        $this->foreignItem = $foreignItem;
    }

    public function test_item_pages_answer_404_for_another_stores_item(): void
    {
        $id = $this->foreignItem->id;

        $this->actingAsVendor()->get(route('vendor.item.edit', [$id]))->assertNotFound();
        $this->actingAsVendor()->get(route('vendor.item.view', [$id]))->assertNotFound();
        $this->actingAsVendor()->get(route('vendor.item.get_stock', ['id' => $id]))->assertNotFound();
    }

    public function test_item_writes_leave_another_stores_item_untouched(): void
    {
        $id = $this->foreignItem->id;
        $before = (array) DB::table('items')->where('id', $id)->first();

        $this->actingAsVendor()->post(route('vendor.item.update', [$id]), [
            'name' => ['hijacked'], 'lang' => ['default'], 'description' => ['hijacked'],
            'category_id' => $this->foreignItem->category_id, 'price' => 1,
        ]);
        $this->actingAsVendor()->get(route('vendor.item.status', [$id, $before['status'] ? 0 : 1]));
        $this->actingAsVendor()->get(route('vendor.item.recommended', [$id, $before['recommended'] ? 0 : 1]));
        $this->actingAsVendor()->post(route('vendor.item.stock-update'), ['product_id' => $id, 'current_stock' => 987654]);
        $this->actingAsVendor()->delete(route('vendor.item.delete', ['id' => $id]));

        $after = (array) DB::table('items')->where('id', $id)->first();

        $this->assertSame(
            array_diff_key($before, ['updated_at' => 1]),
            array_diff_key($after, ['updated_at' => 1]),
            'a vendor changed an item that belongs to another store'
        );
    }

    public function test_bulk_update_refuses_an_id_from_another_store(): void
    {
        $ownItem = Item::withoutGlobalScopes()->where('store_id', $this->store->id)->first();
        $before = (array) DB::table('items')->where('id', $this->foreignItem->id)->first();

        // One row of the vendor's own beside the foreign one: the old guard only checked that
        // *some* id existed, so the own row was what let the foreign one through.
        $header = ['Id', 'Name', 'Description', 'Image', 'CategoryId', 'SubCategoryId', 'UnitId', 'Stock',
            'Price', 'Discount', 'DiscountType', 'AvailableTimeStarts', 'AvailableTimeEnds', 'Variations',
            'ChoiceOptions', 'AddOns', 'Attributes', 'Status', 'Veg', 'Recommended'];
        $row = fn (Item $item) => [$item->id, 'hijacked', 'hijacked', '', $item->category_id, $item->category_id, '',
            5, 1, 0, 'percent', '00:00:00', '23:59:59', '[]', '[]', '[]', '[]', 'active', 'no', 'no'];

        $path = tempnam(sys_get_temp_dir(), 'bulk').'.csv';
        $handle = fopen($path, 'w');
        foreach ([$header, $row($ownItem), $row($this->foreignItem)] as $line) {
            fputcsv($handle, $line);
        }
        fclose($handle);

        $this->actingAsVendor()->post(route('vendor.item.bulk-import'), [
            'products_file' => new \Illuminate\Http\UploadedFile($path, 'items.csv', 'text/csv', null, true),
            'button' => 'update',
        ]);

        $after = (array) DB::table('items')->where('id', $this->foreignItem->id)->first();
        $this->assertSame(
            array_diff_key($before, ['updated_at' => 1]),
            array_diff_key($after, ['updated_at' => 1]),
            'bulk update overwrote an item that belongs to another store'
        );
        // With product approval on, the update is queued instead, and approving it would write it.
        $this->assertFalse(
            DB::table('temp_products')->where('item_id', $this->foreignItem->id)->where('store_id', $this->store->id)->exists(),
            'bulk update queued an edit of another store\'s item for approval'
        );
    }

    public function test_review_reply_refuses_another_stores_review(): void
    {
        $review = Review::whereHas('item', fn ($q) => $q->withoutGlobalScopes()->where('store_id', '!=', $this->store->id))
            ->first();

        if (! $review) {
            $this->markTestSkipped('no review on another store\'s item in this dataset');
        }

        $this->actingAsVendor()
            ->post(route('vendor.review-reply', [$review->id]), ['reply' => 'not my review'])
            ->assertNotFound();

        $fresh = DB::table('reviews')->where('id', $review->id)->first();
        $this->assertNotSame('not my review', $fresh->reply);
        $this->assertSame($review->store_id, $fresh->store_id);
    }

    public function test_dashboard_highlights_only_list_the_vendors_own_items(): void
    {
        $response = $this->actingAsVendor()->get(route('vendor.dashboard'))->assertOk();
        $data = $response->viewData('data');

        foreach (['top_sell', 'most_rated_items'] as $list) {
            foreach ($data[$list] as $item) {
                $this->assertSame((int) $this->store->id, (int) $item->store_id, "$list shows another store's item");
            }
        }
    }

    private function actingAsVendor(): self
    {
        return $this->actingAs($this->vendor, 'vendor')
            ->withSession(['login_remember_token' => $this->vendor->login_remember_token]);
    }
}
