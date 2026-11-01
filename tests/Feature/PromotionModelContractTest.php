<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\HappyHour;
use App\Models\HappyHourDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Behaviour tests for the promotion models -- the invariants, not the boilerplate.
 *
 * Each case here corresponds to a rule that was learned the hard way in the source: the
 * exactly-one-target guard, the custom-schedule listing bug, the per-instance memo, and the
 * module dimension that the original did not have at all.
 */
class PromotionModelContractTest extends TestCase
{
    use DatabaseTransactions;

    /** Relations must resolve without lazy loading, which throws outside production. */
    public function test_relations_resolve_under_strict_eager_loading(): void
    {
        Model::preventLazyLoading(true);

        [$offer, $enrolment] = $this->makeEnrolment();

        $loaded = BogoOffer::with(['module', 'enrollments.items', 'enrollments.store'])
            ->findOrFail($offer->id);

        $this->assertSame($offer->id, $loaded->id);
        $this->assertTrue($loaded->relationLoaded('enrollments'));
        $this->assertTrue($loaded->enrollments->first()->relationLoaded('items'));
        $this->assertSame($enrolment->id, $loaded->enrollments->first()->id);
    }

    /**
     * The model repeats the database's CHECK so the failure is a sentence rather than a driver
     * error, and so a server that parses CHECK without enforcing it still behaves.
     */
    public function test_a_bundle_line_refuses_to_save_without_exactly_one_target(): void
    {
        [, $enrolment] = $this->makeEnrolment();

        $attributes = [
            'bogo_offer_store_id' => $enrolment->id,
            'type' => BogoOfferItem::TYPE_BUY,
            'quantity' => 1,
            'item_name' => 'probe',
            'price' => 0,
            'original_price' => 0,
        ];

        $this->expectException(\InvalidArgumentException::class);
        BogoOfferItem::create($attributes);
    }

    public function test_a_bundle_line_saves_with_one_target(): void
    {
        [, $enrolment] = $this->makeEnrolment();

        $line = BogoOfferItem::create([
            'bogo_offer_store_id' => $enrolment->id,
            'item_id' => DB::table('items')->value('id'),
            'type' => BogoOfferItem::TYPE_GET,
            'quantity' => 1,
            'item_name' => 'probe',
            'price' => 0,
            'original_price' => 120.50,
        ]);

        $this->assertTrue($line->isFree(), 'a get line is the free side of the bundle');
        $this->assertSame(120.5, $line->original_price);
    }

    /** A store's buy and get sides are separate relations, because the rules differ per side. */
    public function test_buy_and_get_items_are_addressable_separately(): void
    {
        [, $enrolment] = $this->makeEnrolment();
        $itemId = DB::table('items')->value('id');

        foreach ([BogoOfferItem::TYPE_BUY, BogoOfferItem::TYPE_GET] as $type) {
            BogoOfferItem::create([
                'bogo_offer_store_id' => $enrolment->id,
                'item_id' => $itemId,
                'type' => $type,
                'quantity' => 1,
                'item_name' => 'probe '.$type,
                'price' => $type === BogoOfferItem::TYPE_BUY ? 100 : 0,
                'original_price' => 100,
            ]);
        }

        $this->assertCount(1, $enrolment->buyItems()->get());
        $this->assertCount(1, $enrolment->getItems()->get());
        $this->assertCount(2, $enrolment->items()->get());
    }

    /** Quantities are frozen once a store has built its bundle against them. */
    public function test_quantities_lock_once_a_store_enrols(): void
    {
        $offer = $this->makeOffer();
        $this->assertFalse($offer->isQuantityLocked());

        BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => DB::table('stores')->value('id'),
        ]);

        $this->assertTrue($offer->fresh()->isQuantityLocked());
    }

    /** The whole-offer cap removes the offer; the per-customer cap does not. */
    public function test_not_exhausted_scope_hides_a_spent_offer(): void
    {
        $offer = $this->makeOffer(['usage_limit_total' => 5, 'total_uses' => 5]);

        $this->assertFalse(
            BogoOffer::notExhausted()->whereKey($offer->id)->exists(),
            'an offer at its total cap should disappear'
        );

        $offer->update(['total_uses' => 4]);

        $this->assertTrue(BogoOffer::notExhausted()->whereKey($offer->id)->exists());
    }

    /**
     * The bug this guards: a CUSTOM schedule leaves end_date null, so an "end_date is null OR
     * end_date >= today" listing passed every custom happy hour for ever, however long ago its
     * last day had been.
     */
    public function test_a_custom_schedule_whose_dates_have_passed_is_not_listed(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_CUSTOM,
            'is_permanent' => 0,
            'end_date' => null,
        ]);

        HappyHourDate::create([
            'happy_hour_id' => $happyHour->id,
            'module_id' => $happyHour->module_id,
            'applicable_date' => now()->subDays(10)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->assertTrue($happyHour->fresh()->hasEnded());
        $this->assertFalse(
            HappyHour::notEnded()->whereKey($happyHour->id)->exists(),
            'a custom schedule whose last day has passed must drop off the listing'
        );
    }

    /** The row rule and the query rule must agree, or a list disagrees with the row it shows. */
    public function test_a_custom_schedule_with_a_future_date_is_still_listed(): void
    {
        $happyHour = $this->makeHappyHour([
            'duration_type' => HappyHour::DURATION_CUSTOM,
            'is_permanent' => 0,
            'end_date' => null,
        ]);

        HappyHourDate::create([
            'happy_hour_id' => $happyHour->id,
            'module_id' => $happyHour->module_id,
            'applicable_date' => now()->addDays(3)->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $this->assertFalse($happyHour->fresh()->hasEnded());
        $this->assertTrue(HappyHour::notEnded()->whereKey($happyHour->id)->exists());
    }

    /** A permanent rule never ends, whatever its dates say. */
    public function test_a_permanent_happy_hour_never_ends(): void
    {
        $happyHour = $this->makeHappyHour([
            'is_permanent' => 1,
            'end_date' => now()->subYear()->toDateString(),
        ]);

        $this->assertFalse($happyHour->hasEnded());
        $this->assertNull($happyHour->endsOn());
    }

    /**
     * A dated happy hour answers isRunningNow() with a query, and it is asked once per card on a
     * listing. The memo must be per instance -- a static one outlives the row it was computed for.
     */
    public function test_running_now_is_memoised_per_instance(): void
    {
        $happyHour = $this->makeHappyHour(['duration_type' => HappyHour::DURATION_CUSTOM, 'is_permanent' => 0]);

        DB::enableQueryLog();
        $happyHour->isRunningNow();
        $first = count(DB::getQueryLog());
        $happyHour->isRunningNow();
        $happyHour->isRunningNow();
        $after = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($first, $after, 'repeat calls on one instance must not re-query');

        // A different instance of the same row must resolve independently.
        $other = HappyHour::findOrFail($happyHour->id);
        $this->assertIsBool($other->isRunningNow());
    }

    /** Module is a required dimension here; the source had no equivalent. */
    public function test_offers_and_happy_hours_are_module_scoped(): void
    {
        $moduleId = DB::table('modules')->value('id');
        $otherModuleId = DB::table('modules')->where('id', '!=', $moduleId)->value('id');

        if (! $otherModuleId) {
            $this->markTestSkipped('needs two modules to prove scoping');
        }

        $offer = $this->makeOffer(['module_id' => $moduleId]);

        $this->assertTrue(BogoOffer::forModule($moduleId)->whereKey($offer->id)->exists());
        $this->assertFalse(BogoOffer::forModule($otherModuleId)->whereKey($offer->id)->exists());

        $happyHour = $this->makeHappyHour(['module_id' => $moduleId]);

        $this->assertTrue(
            HappyHour::forModule($moduleId)->whereKey($happyHour->id)->exists()
        );

        // D2 in one assertion: a different module, and it must not match.
        $this->assertFalse(
            HappyHour::forModule($otherModuleId)->whereKey($happyHour->id)->exists(),
            'a happy hour must not reach another module'
        );
    }

    /**
     * A scope that shares a relation's name is silently unreachable, which is how a module filter
     * can look applied and do nothing. Guards the naming, not the filtering.
     */
    public function test_module_scopes_are_not_shadowed_by_relations(): void
    {
        foreach ([BogoOffer::class, HappyHour::class] as $model) {
            foreach (['module'] as $relation) {
                $this->assertFalse(
                    method_exists($model, 'scope'.ucfirst($relation)),
                    class_basename($model)." defines both a {$relation}() relation and a scope{$relation}() — the scope is unreachable"
                );
            }
        }
    }

    /**
     * The promotion engine reads `carts` and `order_details`, and only module types that use them
     * may switch promotions on.
     *
     * This replaces the PromotionVerticalProvider contract, which existed so Rental and Service
     * could be added as a configuration change rather than a refactor. They were then ruled out of
     * scope, leaving an interface nobody implemented -- so the rule it protected is asserted
     * directly against the flag instead. Rental and Service carry RentalCart/Trips and
     * ServiceBooking/ServiceBookingDetails; turning the flag on for either would point the engine
     * at tables their orders never touch.
     */
    public function test_promotions_are_off_for_types_the_engine_cannot_reach(): void
    {
        foreach (['parcel', 'rental', 'ride-share', 'service'] as $type) {
            $this->assertFalse(
                (bool) config("module.{$type}.promotions"),
                "{$type} has no line-item cart the promotion engine can read"
            );
        }
    }

    private function makeOffer(array $overrides = []): BogoOffer
    {
        $moduleId = DB::table('modules')->value('id');

        if (! $moduleId) {
            $this->markTestSkipped('dataset has no module');
        }

        return BogoOffer::create(array_merge([
            'module_id' => $moduleId,
            'title' => 'contract probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now(),
            'end_date' => now()->addDay(),
        ], $overrides));
    }

    /** @return array{0:BogoOffer,1:BogoOfferStore} */
    private function makeEnrolment(): array
    {
        $storeId = DB::table('stores')->value('id');

        if (! $storeId || ! DB::table('items')->value('id')) {
            $this->markTestSkipped('dataset has no store or item');
        }

        $offer = $this->makeOffer();

        $enrolment = BogoOfferStore::create([
            'bogo_offer_id' => $offer->id,
            'store_id' => $storeId,
            'status' => BogoOfferStore::STATUS_APPROVED,
        ]);

        return [$offer, $enrolment];
    }

    private function makeHappyHour(array $overrides = []): HappyHour
    {
        $moduleId = DB::table('modules')->value('id');
        if (! $moduleId) {
            $this->markTestSkipped('dataset has no module');
        }

        return HappyHour::create(array_merge([
            'module_id' => $moduleId,
            'title' => 'contract probe',
            'discount' => 10,
            'duration_type' => HappyHour::DURATION_DAILY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addWeek()->toDateString(),
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ], $overrides));
    }

    /**
     * Both variation shapes render, and neither renders as punctuation on its own.
     *
     * Every panel, drawer and export formatted a frozen line as "<group> : <labels>", which for a
     * non-food line -- a flat {type} with no group and no labels -- printed a bare " : " and
     * dropped the customer's actual choice.
     */
    public function test_a_frozen_line_renders_both_variation_shapes(): void
    {
        $food = new BogoOfferItem;
        $food->variations = [['name' => 'Size', 'values' => ['label' => ['Half', 'Full']]]];

        $this->assertSame(['Size : Half, Full'], $food->variationDisplayLines());
        $this->assertSame(['Half', 'Full'], $food->variationLabels());

        $nonFood = new BogoOfferItem;
        $nonFood->variations = [['type' => 'uki-jbiub', 'price' => 300, 'stock' => 1]];

        $this->assertSame(['uki / jbiub'], $nonFood->variationDisplayLines());
        $this->assertSame(['uki-jbiub'], $nonFood->variationLabels());

        // No selection is no line, rather than a stray separator.
        $none = new BogoOfferItem;
        $none->variations = [];

        $this->assertSame([], $none->variationDisplayLines());
    }
}
