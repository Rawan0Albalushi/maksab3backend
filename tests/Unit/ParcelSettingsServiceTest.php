<?php

namespace Tests\Unit;

use App\Models\Dimension;
use App\Models\Weight;
use App\Services\Parcel\DimensionService;
use App\Services\Parcel\WeightService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Parcel settings CRUD — Weight Setup and Dimension Setup.
 *
 * Settings only. Nothing here prices an order: the charge a band or a size class attracts lives on
 * the delivery rule and is deferred (delivery-zone-suite-parcel-deferred.md §6a).
 *
 * DatabaseTransactions rather than RefreshDatabase: this runs against the working database, and
 * rebuilding it would destroy the data every other check in this port relies on.
 */
class ParcelSettingsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private WeightService $weights;

    private DimensionService $dimensions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->weights = app(WeightService::class);
        $this->dimensions = app(DimensionService::class);
    }

    private function band(string $name, float $from, float $to): Weight
    {
        return $this->weights->create(['name' => $name.uniqid(), 'from_weight' => $from, 'to_weight' => $to]);
    }

    private function sizeClass(string $name, float $l, float $w, float $h): Dimension
    {
        return $this->dimensions->create(['name' => $name.uniqid(), 'max_length' => $l, 'max_width' => $w, 'max_height' => $h]);
    }

    public function test_a_band_is_created_active_so_it_is_immediately_selectable(): void
    {
        $this->assertTrue($this->band('B', 0, 2)->status);
    }

    /**
     * Bands must not overlap: two rows covering 3 kg means the charge depends on whichever the
     * query returned first — a silent pricing inconsistency rather than a visible error.
     *
     * Asserts the band is AMONG the clashes rather than the only one. This suite runs against the
     * working database, where an admin's own bands may already cover the same kilograms — an
     * exact-array assertion would pass or fail depending on what happened to be set up that day.
     */
    public function test_an_overlapping_band_is_detected_and_named(): void
    {
        $existing = $this->band('Base', 2, 4);

        $this->assertContains($existing->name, $this->weights->overlappingBandNames(3, 5));
        $this->assertContains($existing->name, $this->weights->overlappingBandNames(1, 3));
        $this->assertContains($existing->name, $this->weights->overlappingBandNames(2.5, 3.5), 'a band wholly inside another still clashes');
    }

    /**
     * "0-2" then "2-4" is how the design itself writes them, so touching ends must be allowed.
     *
     * Uses kilograms far above anything an admin would configure, so a real band in the working
     * database cannot make this pass or fail by accident.
     */
    public function test_touching_endpoints_do_not_count_as_an_overlap(): void
    {
        $lower = $this->band('Lower', 1000, 1002);

        $this->assertNotContains($lower->name, $this->weights->overlappingBandNames(1002, 1004));
        $this->assertContains($lower->name, $this->weights->overlappingBandNames(1001, 1004), 'a genuine overlap is still caught');
    }

    /** Without the exception an edit that changes only the name would report itself as a clash. */
    public function test_a_band_being_edited_does_not_clash_with_itself(): void
    {
        $band = $this->band('Mine', 1100, 1102);

        $this->assertContains($band->name, $this->weights->overlappingBandNames(1100, 1102));
        $this->assertNotContains($band->name, $this->weights->overlappingBandNames(1100, 1102, $band->id));
    }

    /** The "matched top-to-bottom" note in the design is only true if the list is ascending. */
    public function test_active_bands_come_back_in_ascending_order(): void
    {
        $this->band('Third', 900, 902);
        $this->band('First', 800, 802);
        $this->band('Second', 850, 852);

        $ordered = $this->weights->activeBands()
            ->whereBetween('from_weight', [800, 902])
            ->pluck('from_weight')->values()->all();

        $this->assertSame([800.0, 850.0, 900.0], $ordered);
    }

    public function test_switching_a_band_off_removes_it_from_the_active_list(): void
    {
        $band = $this->band('Off', 700, 702);
        $this->assertTrue($this->weights->activeBands()->contains('id', $band->id));

        $this->weights->updateStatus($band->id, 0);

        $this->assertFalse($this->weights->activeBands()->contains('id', $band->id));
    }

    public function test_deleting_a_band_takes_its_translations_with_it(): void
    {
        $band = $this->band('Doomed', 600, 602);
        $band->translations()->create(['translationable_type' => Weight::class, 'locale' => 'bn', 'key' => 'name', 'value' => 'x']);

        $this->assertTrue($this->weights->delete($band->id));
        $this->assertNull(Weight::find($band->id));
        $this->assertSame(0, \DB::table('translations')
            ->where('translationable_type', Weight::class)->where('translationable_id', $band->id)->count());
    }

    public function test_updating_a_band_leaves_untouched_fields_alone(): void
    {
        $band = $this->band('Keep', 500, 502);

        $this->weights->update($band->id, ['to_weight' => 504]);

        $this->assertSame(500.0, $band->refresh()->from_weight, 'a partial update must not blank the fields it was not given');
        $this->assertSame(504.0, $band->to_weight);
    }

    /**
     * Size classes deliberately nest — every Small box fits inside Large — so smallest-first is
     * the order that reads Small → Extra Large, and volume is what orders them, not one side.
     */
    public function test_active_sizes_come_back_smallest_box_first(): void
    {
        $large = $this->sizeClass('Large', 300, 150, 240);
        $small = $this->sizeClass('Small', 100, 50, 80);
        $medium = $this->sizeClass('Medium', 200, 100, 160);

        $ordered = $this->dimensions->activeSizes()
            ->whereIn('id', [$large->id, $small->id, $medium->id])
            ->pluck('id')->values()->all();

        $this->assertSame([$small->id, $medium->id, $large->id], $ordered);
    }

    public function test_a_long_flat_box_is_ordered_by_volume_not_by_its_longest_side(): void
    {
        $longFlat = $this->sizeClass('LongFlat', 400, 2, 2);      // volume 1,600
        $compact = $this->sizeClass('Compact', 30, 30, 30);       // volume 27,000

        $ordered = $this->dimensions->activeSizes()
            ->whereIn('id', [$longFlat->id, $compact->id])
            ->pluck('id')->values()->all();

        $this->assertSame([$longFlat->id, $compact->id], $ordered, 'length alone would have put the compact box first');
    }

    public function test_deleting_a_size_takes_its_translations_with_it(): void
    {
        $size = $this->sizeClass('Doomed', 11, 11, 11);
        $size->translations()->create(['translationable_type' => Dimension::class, 'locale' => 'bn', 'key' => 'name', 'value' => 'x']);

        $this->assertTrue($this->dimensions->delete($size->id));
        $this->assertNull(Dimension::find($size->id));
        $this->assertSame(0, \DB::table('translations')
            ->where('translationable_type', Dimension::class)->where('translationable_id', $size->id)->count());
    }

    public function test_missing_records_are_reported_rather_than_thrown(): void
    {
        $this->assertNull($this->weights->update(99999999, ['to_weight' => 1]));
        $this->assertFalse($this->weights->delete(99999999));
        $this->assertFalse($this->weights->updateStatus(99999999, 1));
        $this->assertNull($this->dimensions->update(99999999, ['max_length' => 1]));
        $this->assertFalse($this->dimensions->delete(99999999));
    }
}
