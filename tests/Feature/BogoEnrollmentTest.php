<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Traits\Promotion\HandlesBogoEnrollment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Traits\Promotion\HandlesFrozenLines;

/**
 * Joining an offer: the quantity rule, the price freeze, and combination uniqueness.
 *
 * This is the half of the feature with money in it -- what is frozen here is what the customer
 * is billed and what the store's give-away is booked against.
 */
class BogoEnrollmentTest extends TestCase
{
    use DatabaseTransactions;
    use HandlesFrozenLines;
    use HandlesBogoEnrollment;

    /** Equality, not a minimum: an offer is a shape and a bundle that under-fills it is a different offer. */
    public function test_buy_and_get_totals_must_equal_the_offers_quantities(): void
    {
        [$offer, $item] = $this->makeOfferAndItem(['buy_qty' => 2, 'get_qty' => 1]);

        $short = $this->validateEnrollmentItems(
            $offer,
            [['item_id' => $item->id, 'quantity' => 1]],
            [['item_id' => $item->id, 'quantity' => 1]],
            $item->store_id
        );

        $this->assertContains('buy_items', array_column($short, 'code'));

        $exact = $this->validateEnrollmentItems(
            $offer,
            [['item_id' => $item->id, 'quantity' => 2]],
            [['item_id' => $item->id, 'quantity' => 1]],
            $item->store_id
        );

        $this->assertSame([], $exact);
    }

    /** Too many is refused as well as too few -- equality means both directions. */
    public function test_exceeding_the_offer_quantity_is_refused(): void
    {
        [$offer, $item] = $this->makeOfferAndItem(['buy_qty' => 1, 'get_qty' => 1]);

        $errors = $this->validateEnrollmentItems(
            $offer,
            [['item_id' => $item->id, 'quantity' => 5]],
            [['item_id' => $item->id, 'quantity' => 1]],
            $item->store_id
        );

        $this->assertContains('buy_items', array_column($errors, 'code'));
    }

    public function test_an_item_from_another_store_is_refused(): void
    {
        [$offer, $item] = $this->makeOfferAndItem();

        $errors = $this->validateEnrollmentItems(
            $offer,
            [['item_id' => $item->id, 'quantity' => 1]],
            [['item_id' => $item->id, 'quantity' => 1]],
            $item->store_id + 99999
        );

        $this->assertContains('item', array_column($errors, 'code'));
    }

    /**
     * The cap applies to the side's TOTAL for that item, not to one line -- the same item may
     * appear on several lines with different variations.
     */
    public function test_the_cart_cap_applies_to_the_sides_total_not_one_line(): void
    {
        [$offer, $item] = $this->makeOfferAndItem(['buy_qty' => 4, 'get_qty' => 1]);

        $item->forceFill(['maximum_cart_quantity' => 3])->saveQuietly();

        $errors = $this->validateEnrollmentItems(
            $offer,
            // Two lines of two: neither exceeds the cap alone, together they do.
            [['item_id' => $item->id, 'quantity' => 2], ['item_id' => $item->id, 'quantity' => 2]],
            [['item_id' => $item->id, 'quantity' => 1]],
            $item->store_id
        );

        $this->assertContains('cart_item_limit', array_column($errors, 'code'));
    }

    /** B0: the freeze takes the BASE price, never a discounted one. */
    public function test_the_freeze_takes_the_base_price_not_the_discounted_one(): void
    {
        [$offer, $item] = $this->makeOfferAndItem();

        $item->forceFill(['price' => 200, 'discount' => 50, 'discount_type' => 'percent'])->saveQuietly();

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => BogoOfferStore::STATUS_APPROVED,
        ]);

        $bundlePrice = $this->syncEnrollmentItems(
            $enrolment,
            [['item_id' => $item->id, 'quantity' => 1]],
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $this->assertSame(200.0, $bundlePrice, 'a 50% discount must not reach the frozen price');

        $lines = $enrolment->fresh('items')->items;

        $this->assertSame(200.0, (float) $lines->firstWhere('type', 'buy')->original_price);
        // The free line costs nothing but records what it was worth, so the give-away can be booked.
        $this->assertSame(0.0, (float) $lines->firstWhere('type', 'get')->price);
        $this->assertSame(200.0, (float) $lines->firstWhere('type', 'get')->original_price);
    }

    /**
     * The same bundle split differently is the same bundle: two lines of one and one line of
     * two must hash alike, or the same combination can be submitted twice under a new shape.
     */
    public function test_the_signature_folds_identical_lines_before_hashing(): void
    {
        [, $item] = $this->makeOfferAndItem();

        $split = $this->buildCombinationSignature(
            [['item_id' => $item->id, 'quantity' => 1], ['item_id' => $item->id, 'quantity' => 1]],
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $merged = $this->buildCombinationSignature(
            [['item_id' => $item->id, 'quantity' => 2]],
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $this->assertSame($merged, $split);
    }

    /** Different combinations must not collide. */
    public function test_different_combinations_hash_differently(): void
    {
        [, $item] = $this->makeOfferAndItem();

        $one = $this->buildCombinationSignature([['item_id' => $item->id, 'quantity' => 1]], []);
        $two = $this->buildCombinationSignature([['item_id' => $item->id, 'quantity' => 2]], []);

        $this->assertNotSame($one, $two);
    }

    /** Selection order must not change the hash. */
    public function test_the_signature_ignores_selection_order(): void
    {
        [, $item] = $this->makeOfferAndItem();

        $a = $this->buildCombinationSignature([
            ['item_id' => $item->id, 'quantity' => 1, 'variations' => ['b', 'a']],
        ], []);

        $b = $this->buildCombinationSignature([
            ['item_id' => $item->id, 'quantity' => 1, 'variations' => ['a', 'b']],
        ], []);

        $this->assertSame($a, $b);
    }

    /** An expired offer's combination becomes reusable rather than burned forever. */
    public function test_an_expired_offers_combination_is_reusable(): void
    {
        [$offer, $item] = $this->makeOfferAndItem();

        $signature = $this->buildCombinationSignature([['item_id' => $item->id, 'quantity' => 1]], []);

        BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => BogoOfferStore::STATUS_APPROVED,
            'combination_signature' => $signature,
        ]);

        $this->assertTrue($this->combinationTaken($item->store_id, $signature));

        $offer->update(['end_date' => now()->subDay()]);

        $this->assertFalse(
            $this->combinationTaken($item->store_id, $signature),
            'an ended offer should release its combination'
        );
    }

    /** The C6 diagnostic: "approved" is not the same as "a customer can see it". */
    public function test_visibility_reports_why_an_approved_bundle_is_hidden(): void
    {
        [$offer, $item] = $this->makeOfferAndItem();

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => BogoOfferStore::STATUS_APPROVED,
        ]);

        $this->syncEnrollmentItems(
            $enrolment,
            [['item_id' => $item->id, 'quantity' => 1]],
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $enrolment = $enrolment->fresh(['items.item', 'store', 'bogoOffer']);

        $this->assertSame('running', $this->bogoCustomerVisibility($enrolment)['status']);

        $item->forceFill(['status' => 0])->saveQuietly();

        $visibility = $this->bogoCustomerVisibility($enrolment->fresh(['items.item', 'store', 'bogoOffer']));

        $this->assertSame('not_visible', $visibility['status']);
        $this->assertNotEmpty($visibility['reasons'], 'the vendor must be told which member is the problem');
    }

    /**
     * The picker has to be handed both variation shapes, because 6amMart has both.
     *
     * Offering only food_variations leaves a grocery, pharmacy or ecommerce item with no options
     * in the join drawer, and therefore frozen at its base price whatever the customer would
     * actually have paid for the combination.
     */
    public function test_the_picker_carries_the_non_food_variation_shape(): void
    {
        $item = $this->itemInModuleType(food: false);

        $item->forceFill([
            'choice_options' => json_encode([
                ['name' => 'choice_1', 'title' => 'Size', 'options' => ['small', 'large']],
            ]),
            'variations' => json_encode([
                ['type' => 'small', 'price' => 120, 'stock' => 5],
                ['type' => 'large', 'price' => 180, 'stock' => 3],
            ]),
        ])->saveQuietly();

        $row = collect($this->storeItemPickerOptions((int) $item->store_id))->firstWhere('id', $item->id);

        $this->assertNotNull($row, 'the item must reach the picker at all');
        $this->assertSame('Size', $row['choice_options'][0]['title'] ?? null);
        $this->assertSame(['small', 'large'], $row['choice_options'][0]['options'] ?? null);
        $this->assertSame('large', $row['variation_combinations'][1]['type'] ?? null);
    }

    /**
     * A non-food combination's price REPLACES the base; a food option's ADDS to it.
     *
     * Getting this backwards froze every grocery bundle line at roughly twice what the order would
     * charge, because PlaceNewOrderTrait assigns the matched row's price rather than adding it.
     */
    public function test_a_non_food_variation_replaces_the_base_price(): void
    {
        $item = $this->itemInModuleType(food: false);

        $item->forceFill([
            'price' => 100,
            'choice_options' => json_encode([
                ['name' => 'choice_1', 'title' => 'Size', 'options' => ['small', 'large']],
            ]),
            'variations' => json_encode([
                ['type' => 'small', 'price' => 120, 'stock' => 5],
                ['type' => 'large', 'price' => 180, 'stock' => 3],
            ]),
            'food_variations' => json_encode([]),
        ])->saveQuietly();

        $this->assertSame(
            180.0,
            $this->unitPrice($item->fresh(), [['type' => 'large', 'price' => 180, 'stock' => 3]], null, null),
            'the matched combination price replaces the base, it does not add to it'
        );

        // Nothing chosen is still the base price.
        $this->assertSame(100.0, $this->unitPrice($item->fresh(), null, null, null));
    }

    /**
     * An item carrying BOTH variation columns is priced by its module type, not by its columns.
     *
     * 9 items in this dataset have food_variations and choice_options at once. Deciding from the
     * columns put the picker on the choice branch and the server on the food branch for exactly
     * those, which fataled the save with "Undefined array key name" -- and where it did not fatal,
     * replaced the base price on a food item that the order would have added to.
     */
    public function test_a_food_item_prices_additively_even_when_it_also_has_choice_options(): void
    {
        $item = $this->itemInModuleType(food: true);

        $item->forceFill([
            'price' => 250,
            'food_variations' => json_encode([[
                'name' => 'Size', 'type' => 'single', 'min' => 0, 'max' => 0, 'required' => 'on',
                'values' => [['label' => 'Half', 'optionPrice' => '150'], ['label' => 'Full', 'optionPrice' => '300']],
            ]]),
            // The second shape, present on the same row.
            'choice_options' => json_encode([['name' => 'choice_2', 'title' => 'Capacity', 'options' => ['1:3', '1:5']]]),
            'variations' => json_encode([
                ['type' => '1:3', 'price' => 250, 'stock' => 0],
                ['type' => '1:5', 'price' => 420, 'stock' => 0],
            ]),
        ])->saveQuietly();

        $fresh = $item->fresh(['module']);

        // The picker is told which shape to render, and it must be the food one.
        $row = collect($this->storeItemPickerOptions((int) $item->store_id))->firstWhere('id', $item->id);
        $this->assertTrue($row['uses_food_variations'], 'a food item must render its food_variations');

        // 250 base + 150 for Half. Not 250, which the replacing branch would have produced.
        $this->assertSame(
            400.0,
            $this->unitPrice($fresh, [['name' => 'Size', 'values' => ['label' => ['Half']]]], null, null),
            'a food option adds to the base price'
        );
    }

    /** And a combination the item no longer offers must block approval, not price at zero. */
    public function test_a_removed_combination_blocks_approval(): void
    {
        [$offer, $item] = $this->makeOfferAndItem();

        $item->forceFill([
            'choice_options' => json_encode([
                ['name' => 'choice_1', 'title' => 'Size', 'options' => ['small']],
            ]),
            'variations' => json_encode([['type' => 'small', 'price' => 120, 'stock' => 5]]),
        ])->saveQuietly();

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $item->store_id,
            'status' => 'pending',
            'requested_by' => 'store',
            'combination_signature' => hash('sha256', 'drift probe'),
        ]);

        // Frozen against a combination that has since gone.
        $this->syncEnrollmentItems(
            $enrolment,
            [['item_id' => $item->id, 'quantity' => 1, 'variations' => [['type' => 'large', 'price' => 180, 'stock' => 3]]]],
            [['item_id' => $item->id, 'quantity' => 1]]
        );

        $problems = $this->enrollmentIntegrityProblems($enrolment->fresh(['items']));

        $this->assertNotEmpty($problems['blocking'], 'a vanished combination must block approval');
    }

    /**
     * An item whose module is (or is not) of type food, reset to a clean, listable state.
     *
     * The two shapes are chosen by module type, so a test about one of them has to name which it
     * needs rather than take whatever the generic fixture picked first.
     */
    private function itemInModuleType(bool $food): Item
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)
            ->active()
            ->with(['module', 'store'])
            ->whereHas('module', fn ($q) => $food
                ? $q->where('module_type', 'food')
                : $q->where('module_type', '!=', 'food'))
            ->first();

        if (! $item || ! $item->store) {
            $this->markTestSkipped('dataset has no listable '.($food ? 'food' : 'non-food').' item with a store');
        }

        $item->forceFill([
            'status' => 1,
            'is_approved' => 1,
            'maximum_cart_quantity' => null,
            'available_time_starts' => null,
            'available_time_ends' => null,
            'stock' => 999,
        ])->saveQuietly();

        Store::whereKey($item->store_id)->update(['status' => 1]);

        return $item->fresh(['module', 'store']);
    }

    /** @return array{0:BogoOffer,1:Item} */
    private function makeOfferAndItem(array $offerOverrides = []): array
    {
        $item = Item::withoutGlobalScope(ZoneScope::class)->active()->with(['module', 'store'])->first();

        if (! $item || ! $item->store) {
            $this->markTestSkipped('dataset has no listable item with a store');
        }

        $item->forceFill([
            'status' => 1,
            'is_approved' => 1,
            'maximum_cart_quantity' => null,
            'available_time_starts' => null,
            'available_time_ends' => null,
            'stock' => 999,
            'variations' => json_encode([]),
            'food_variations' => json_encode([]),
        ])->saveQuietly();

        Store::whereKey($item->store_id)->update(['status' => 1]);

        $offer = BogoOffer::create(array_merge([
            'module_id' => $item->module_id,
            'title' => 'enrolment probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 1,
        ], $offerOverrides));

        return [$offer, $item->fresh(['module', 'store'])];
    }
}
