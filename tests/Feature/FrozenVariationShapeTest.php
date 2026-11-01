<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Traits\Promotion\HandlesFrozenLines;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Fluent;
use Tests\TestCase;

/**
 * A frozen promotion line names its variation in whichever shape the item used when the line was
 * saved — {type} for a choice/combination item, {name, values:{label}} for a food one. An item's
 * variation setup can be switched between the two afterwards, and a line frozen in the old shape
 * then matched nothing: the bundle reported "The selected variation is no longer available" and
 * read the stock as 0, for a combination that exists and is fully stocked.
 *
 * A label list joins into a combination key the same way the picker builds one, so a food-shaped
 * selection on a choice item resolves to the row it always meant.
 */
class FrozenVariationShapeTest extends TestCase
{
    use DatabaseTransactions;

    private object $probe;

    private ?Item $item = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probe = new class
        {
            use HandlesFrozenLines {
                variationMissingReason as public;
                availableStock as public;
                unitPrice as public;
                combinationKey as public;
                combinationFor as public;
            }
        };

        $this->item = Item::withoutGlobalScopes()
            ->with('module')
            ->whereHas('module', fn ($q) => $q->where('module_type', '!=', 'food'))
            ->whereRaw("COALESCE(variations, '[]') NOT IN ('[]', '')")
            ->whereRaw("COALESCE(food_variations, '[]') IN ('[]', '')")
            ->first();

        if (! $this->item) {
            $this->markTestSkipped('dataset has no non-food item with combination variations');
        }
    }

    private function line(array $variations): Fluent
    {
        return new Fluent(['variations' => $variations, 'quantity' => 1]);
    }

    private function firstCombination(): array
    {
        return json_decode($this->item->variations, true)[0];
    }

    public function test_a_food_shaped_selection_resolves_against_a_combination_item(): void
    {
        $combination = $this->firstCombination();
        $line = $this->line([['name' => 'variation', 'values' => ['label' => [$combination['type']]]]]);

        $this->assertNull($this->probe->variationMissingReason($this->item, $line),
            'the combination exists, so a selection naming it by label is not missing');
    }

    public function test_the_recovered_selection_reads_the_combinations_real_stock(): void
    {
        if (! config('module.'.$this->item->module?->module_type.'.stock')) {
            $this->markTestSkipped('this module does not keep stock');
        }

        $combination = $this->firstCombination();
        $line = $this->line([['name' => 'variation', 'values' => ['label' => [$combination['type']]]]]);

        $this->assertSame(
            (int) ($combination['stock'] ?? 0),
            $this->probe->availableStock($this->item, $line),
            'reading 0 here is what reported a stocked combination as out of stock',
        );
    }

    public function test_the_recovered_selection_is_priced_at_the_combination_not_the_base(): void
    {
        $combination = $this->firstCombination();

        $this->assertEqualsWithDelta(
            (float) $combination['price'],
            $this->probe->unitPrice($this->item, [['name' => 'variation', 'values' => ['label' => [$combination['type']]]]], null, null),
            0.01,
            'a recovered line must be worth what the combination costs, not the item base price',
        );
    }

    public function test_the_native_shape_still_resolves(): void
    {
        $combination = $this->firstCombination();
        $line = $this->line([$combination]);

        $this->assertNull($this->probe->variationMissingReason($this->item, $line));
        $this->assertEqualsWithDelta((float) $combination['price'],
            $this->probe->unitPrice($this->item, [$combination], null, null), 0.01);
    }

    public function test_a_combination_that_really_is_gone_is_still_reported(): void
    {
        $line = $this->line([['type' => 'no-such-combination-'.uniqid()]]);

        $this->assertNotNull($this->probe->variationMissingReason($this->item, $line),
            'tolerating a shape mismatch must not tolerate a deleted combination');
        $this->assertSame(0, $this->probe->availableStock($this->item, $line));
    }

    public function test_a_label_that_matches_nothing_is_still_reported(): void
    {
        $line = $this->line([['name' => 'variation', 'values' => ['label' => ['not-a-real-option']]]]);

        $this->assertNotNull($this->probe->variationMissingReason($this->item, $line));
    }

    public function test_a_multi_group_selection_joins_in_order(): void
    {
        $combination = $this->firstCombination();
        $parts = explode('-', (string) $combination['type']);

        if (count($parts) < 2) {
            $this->markTestSkipped('the fixture item has single-part combination keys');
        }

        $line = $this->line(array_map(
            fn ($part) => ['name' => 'group', 'values' => ['label' => [$part]]],
            $parts,
        ));

        $this->assertNull($this->probe->variationMissingReason($this->item, $line),
            'one group per choice joins back into the combination key the picker built');
    }

    public function test_the_bogo_enrolment_check_recovers_the_same_selection(): void
    {
        $combination = $this->firstCombination();

        $enrolment = $this->probe;

        $chosen = [['name' => 'variation', 'values' => ['label' => [$combination['type']]]]];

        $this->assertSame($combination['type'], $enrolment->combinationKey($chosen));
        $this->assertNotNull($enrolment->combinationFor($this->item, $chosen),
            'the panel must not refuse an enrolment the cart would happily serve');
    }

    public function test_a_food_item_holding_a_combination_selection_still_blocks(): void
    {
        $foodItem = Item::withoutGlobalScopes()
            ->with('module')
            ->whereHas('module', fn ($q) => $q->where('module_type', 'food'))
            ->first();

        if (! $foodItem) {
            $this->markTestSkipped('dataset has no food item');
        }

        $this->assertNotNull(
            $this->probe->variationMissingReason($foodItem, $this->line([['type' => 'small-almond']])),
            'a food item cannot be priced from a combination key, so that mismatch still blocks',
        );
    }
}
