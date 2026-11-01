<?php

namespace Tests\Unit;

use App\Models\DMVehicle;
use App\Services\DeliveryMan\DmVehicleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Overlapping vehicle coverage is ALLOWED (decided 2026-09-08, QA case TC_230).
 *
 * The panel used to refuse it. That guard made sense while a category carried an `extra_charges`
 * value: two categories covering one distance meant two possible charges and the engine had to
 * pick. S13 deleted the charge — the fee engine no longer adds it and the API answers 0.00 — so an
 * overlap can no longer change what anyone pays. What remains is dispatch, where matching several
 * categories is useful.
 *
 * Two things have to stay true for that to be safe, and both are asserted here: the price is
 * unaffected however many categories match, and where one must be named the choice is
 * deterministic rather than whatever the database returns first.
 */
class VehicleCoverageOverlapTest extends TestCase
{
    use DatabaseTransactions;

    private function vehicle(string $type, float $from, float $to, float $charge = 0): DMVehicle
    {
        return DMVehicle::create([
            'type' => $type,
            'status' => 1,
            'extra_charges' => $charge,
            'starting_coverage_area' => $from,
            'maximum_coverage_area' => $to,
        ]);
    }

    public function test_overlapping_and_identical_ranges_can_coexist(): void
    {
        $this->vehicle('QA Base '.uniqid(), 0, 1000);
        $this->vehicle('QA Same '.uniqid(), 0, 1000);
        $this->vehicle('QA Nested '.uniqid(), 200, 800);
        $this->vehicle('QA Partial '.uniqid(), 500, 2000);

        $matching = DMVehicle::query()
            ->where('starting_coverage_area', '<=', 600)
            ->where('maximum_coverage_area', '>=', 600)
            ->count();

        $this->assertGreaterThanOrEqual(4, $matching, 'overlapping categories must be storable side by side');
    }

    /** However many categories match, the vehicle contributes nothing to the fee (S13). */
    public function test_a_matched_category_never_adds_to_the_charge(): void
    {
        $this->vehicle('QA Priced '.uniqid(), 0, 1000, 999);

        $this->assertSame(
            0.0,
            (float) app(DmVehicleService::class)->coverageCharge(600),
            'S13 removed the vehicle extra; a stored value must not reach the fee',
        );
    }

    /** The match is still single-valued and stable — lowest starting coverage wins. */
    public function test_the_match_is_deterministic_when_several_cover_the_distance(): void
    {
        $service = app(DmVehicleService::class);

        $first = $service->coverageVehicle(600)?->id;
        $second = $service->coverageVehicle(600)?->id;

        $this->assertNotNull($first, 'a covered distance must resolve to a category');
        $this->assertSame($first, $second, 'the same distance must resolve to the same category every time');

        $lowest = DMVehicle::query()
            ->where('starting_coverage_area', '<=', 600)
            ->where('maximum_coverage_area', '>=', 600)
            ->orderBy('starting_coverage_area')
            ->value('id');

        $this->assertSame($lowest, $first, 'the lowest starting coverage area is the documented winner');
    }
}
