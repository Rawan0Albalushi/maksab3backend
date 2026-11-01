<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Traits\Promotion\HandlesFrozenLines;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One case per refusal branch of the bundle availability rulebook.
 *
 * A bundle is all or nothing, so each of these is a way one member takes the whole thing off
 * sale. The messages are asserted, not just the refusal: the vendor panel shows the reason, so a
 * branch that refuses with the wrong sentence is still a bug.
 */
class BogoAvailabilityTest extends TestCase
{
    use DatabaseTransactions;
    use HandlesFrozenLines;

    public function test_an_available_bundle_has_no_reason(): void
    {
        [$enrolment] = $this->makeBundle();

        $this->assertNull($this->linesUnavailableReason($enrolment->items, 1, 'messages.Is no longer available'));
    }

    public function test_a_deleted_member_takes_the_bundle_off_sale(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        // A line whose item is gone keeps its frozen name so the sentence still reads.
        $enrolment->items()->update(['item_id' => null, 'service_id' => DB::table('services')->value('id')]);
        $enrolment->load('items');

        $reason = $this->linesUnavailableReason($enrolment->items, 1, 'messages.Is no longer available');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('no longer available', strtolower($reason));
    }

    /**
     * Item::active() is stricter than the status column: it also requires is_approved and a
     * trading store. Checking status alone advertises a bundle checkout then refuses.
     */
    public function test_an_unapproved_member_is_unavailable_even_with_status_on(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        $item->forceFill(['status' => 1, 'is_approved' => 0])->saveQuietly();

        $reason = $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 1, 'messages.Is no longer available');

        $this->assertNotNull($reason, 'an unapproved item must not be servable');
        $this->assertStringContainsString('currently unavailable', strtolower($reason));
    }

    /** Serving hours are enforced for bundles only -- an ordinary line outside its window still orders. */
    public function test_a_member_outside_its_serving_hours_takes_the_bundle_off_sale(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        $item->forceFill([
            'available_time_starts' => '00:00:01',
            'available_time_ends' => '00:00:02',
        ])->saveQuietly();

        $reason = $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 1, 'messages.Is no longer available');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('not available at this time', strtolower($reason));
    }

    /**
     * The mandatory check. A renamed label prices at zero silently -- it does not raise -- so
     * without this the bundle stays listed and reaches the kitchen with an empty variation.
     */
    public function test_a_renamed_variation_label_takes_the_bundle_off_sale(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        $item->forceFill(['food_variations' => json_encode([[
            'name' => 'Size',
            'type' => 'single',
            'min' => 0,
            'max' => 0,
            'required' => 'on',
            'values' => [['label' => 'Half', 'optionPrice' => '150']],
        ]])])->saveQuietly();

        // The bundle was frozen on a label the store has since dropped.
        $enrolment->items()->update([
            'variations' => json_encode([['name' => 'Size', 'values' => ['label' => ['Large']]]]),
        ]);

        $reason = $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 1, 'messages.Is no longer available');

        $this->assertNotNull($reason, 'a frozen label the item no longer carries must refuse');
        $this->assertStringContainsString('no longer available', strtolower($reason));
        $this->assertStringContainsString('Large', $reason, 'the sentence should name the missing option');
    }

    /** A renamed GROUP is the same failure as a deleted option: nothing matches. */
    public function test_a_renamed_variation_group_takes_the_bundle_off_sale(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        $item->forceFill(['food_variations' => json_encode([[
            'name' => 'Portion',
            'values' => [['label' => 'Half', 'optionPrice' => '150']],
        ]])])->saveQuietly();

        $enrolment->items()->update([
            'variations' => json_encode([['name' => 'Size', 'values' => ['label' => ['Half']]]]),
        ]);

        $this->assertNotNull(
            $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 1, 'messages.Is no longer available'),
            'a renamed group leaves every label under it unmatched'
        );
    }

    /**
     * The shortage is told in bundles, because that is the number the customer can act on --
     * they are not buying the member by the unit.
     */
    public function test_a_stock_shortage_is_reported_in_bundles_not_units(): void
    {
        [$enrolment, $item] = $this->makeBundle(['module_type' => 'grocery']);

        if (! config('module.grocery.stock')) {
            $this->markTestSkipped('grocery is not stock-bearing in this configuration');
        }

        // Two of this member per bundle, and only enough on hand for two bundles.
        $enrolment->items()->update(['quantity' => 2, 'variations' => null]);
        $item->forceFill(['stock' => 5, 'variations' => json_encode([])])->saveQuietly();

        $reason = $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 3, 'messages.Is no longer available');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('2', $reason, 'two bundles are buildable from five units at two each');
        $this->assertStringContainsString('of this offer', strtolower($reason));
    }

    public function test_no_stock_at_all_gives_the_plain_out_of_stock_sentence(): void
    {
        [$enrolment, $item] = $this->makeBundle(['module_type' => 'grocery']);

        if (! config('module.grocery.stock')) {
            $this->markTestSkipped('grocery is not stock-bearing in this configuration');
        }

        $enrolment->items()->update(['quantity' => 1, 'variations' => null]);
        $item->forceFill(['stock' => 0, 'variations' => json_encode([])])->saveQuietly();

        $reason = $this->linesUnavailableReason($enrolment->fresh(['items.item'])->items, 1, 'messages.Is no longer available');

        $this->assertNotNull($reason);
        $this->assertStringContainsString('out of stock', strtolower($reason));
    }

    /** Stock is a module-type capability: food carries none, so it can never be the reason. */
    public function test_stock_is_not_a_question_on_a_module_type_without_stock(): void
    {
        [$enrolment, $item] = $this->makeBundle(['module_type' => 'food']);

        if (config('module.food.stock')) {
            $this->markTestSkipped('food is stock-bearing in this configuration');
        }

        $enrolment->items()->update(['quantity' => 1, 'variations' => null]);
        $item->forceFill(['stock' => 0, 'variations' => json_encode([])])->saveQuietly();

        $this->assertNull(
            $this->stockShortfallReason($item->fresh(), $enrolment->fresh('items')->items->first(), 99),
            'a module type with no stock must never refuse on stock'
        );
    }

    /** Priming answers a page of bundles in one query instead of one per member. */
    public function test_priming_answers_from_the_batch_without_further_queries(): void
    {
        [$enrolment, $item] = $this->makeBundle();

        $this->primeActiveItems([$item->id]);

        DB::enableQueryLog();
        $this->itemIsActive($item);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(0, $queries, 'a primed answer must not re-query');
    }

    /**
     * @return array{0:BogoOfferStore,1:Item}
     */
    private function makeBundle(array $options = []): array
    {
        $moduleType = $options['module_type'] ?? null;

        $item = Item::withoutGlobalScope(ZoneScope::class)
            ->active()
            ->where('price', '>', 0)
            ->when($moduleType, fn ($q) => $q->whereHas('module', fn ($m) => $m->where('module_type', $moduleType)))
            ->with(['module', 'store'])
            ->first();

        if (! $item || ! $item->store) {
            $this->markTestSkipped('dataset has no listable item'.($moduleType ? " of type {$moduleType}" : ''));
        }

        // Make the item unambiguously listable so each test controls exactly one variable.
        $item->forceFill([
            'status' => 1,
            'is_approved' => 1,
            'available_time_starts' => null,
            'available_time_ends' => null,
        ])->saveQuietly();

        Store::whereKey($item->store_id)->update(['status' => 1]);

        $offer = BogoOffer::create([
            'module_id' => $item->module_id,
            'title' => 'availability probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
        ]);

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => BogoOfferStore::STATUS_APPROVED,
        ]);

        BogoOfferItem::create([
            'bogo_offer_store_id' => $enrolment->id,
            'item_id' => $item->id,
            'type' => BogoOfferItem::TYPE_BUY,
            'quantity' => 1,
            'item_name' => $item->name,
            'price' => 100,
            'original_price' => 100,
        ]);

        return [$enrolment->load('items.item'), $item->fresh(['module', 'store'])];
    }
}
