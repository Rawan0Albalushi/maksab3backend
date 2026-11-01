<?php

namespace Tests\Unit;

use Modules\Builder\Defaults\NullCheckoutProvider;
use Modules\Builder\ValueObjects\Storefront\CheckoutQuoteDTO;
use Modules\Builder\ValueObjects\Storefront\CheckoutSnapshotDTO;
use ReflectionClass;
use Tests\TestCase;

/**
 * S11 — the checkout quote's contract with the storefront, port doc §15.2 (mistake M9).
 *
 * Adding a key to the quote means editing seven files. Miss the DTO's `fromArray`/`toArray` and
 * the value is computed on every quote and **silently discarded** — no error, no warning, just a
 * field that never reaches React. The first test here is the guard the port doc asks for, written
 * as a test rather than as a `rg` incantation nobody runs.
 */
class CheckoutQuoteDtoTest extends TestCase
{
    /** Every declared property must survive a round trip. */
    public function test_every_declared_property_reaches_to_array(): void
    {
        $declared = collect((new ReflectionClass(CheckoutQuoteDTO::class))->getProperties())
            ->map(fn ($property) => $property->getName())
            ->all();

        $emitted = array_keys(CheckoutQuoteDTO::fromArray(['total' => 1])->toArray());

        $missing = array_diff($declared, $emitted);

        $this->assertSame([], array_values($missing),
            'these DTO properties are computed and then discarded before React sees them: '
            .implode(', ', $missing));
    }

    public function test_the_two_line_fee_round_trips(): void
    {
        $quote = CheckoutQuoteDTO::fromArray([
            'total' => 100,
            'deliveryFee' => 0.0,
            'deliveryFeeBeforeDiscount' => 78.15,
        ]);

        $this->assertSame(0.0, $quote->deliveryFee);
        $this->assertSame(78.15, $quote->deliveryFeeBeforeDiscount);
        $this->assertSame(78.15, $quote->toArray()['deliveryFeeBeforeDiscount']);
    }

    public function test_the_pre_discount_fee_defaults_to_the_fee_itself(): void
    {
        // §12.1 — "equal to `delivery_charge` when none applied, so the client renders it
        // unconditionally". A caller that has not been widened must not produce a phantom
        // discount by defaulting to zero.
        $quote = CheckoutQuoteDTO::fromArray(['total' => 100, 'deliveryFee' => 42.0]);

        $this->assertSame(42.0, $quote->deliveryFeeBeforeDiscount);
    }

    public function test_the_surge_note_round_trips_and_defaults_to_null(): void
    {
        $this->assertSame(
            'Busy right now.',
            CheckoutQuoteDTO::fromArray(['total' => 1, 'surgeNote' => 'Busy right now.'])->surgeNote,
        );

        $this->assertNull(CheckoutQuoteDTO::fromArray(['total' => 1])->surgeNote);
    }

    /** The same M9 guard for the snapshot, which S11b widened with `coverage`. */
    public function test_every_declared_snapshot_property_reaches_to_array(): void
    {
        $declared = collect((new ReflectionClass(CheckoutSnapshotDTO::class))->getProperties())
            ->map(fn ($property) => $property->getName())
            ->all();

        $emitted = array_keys(CheckoutSnapshotDTO::fromArray([])->toArray());

        $missing = array_diff($declared, $emitted);

        $this->assertSame([], array_values($missing),
            'these snapshot properties never reach React: '.implode(', ', $missing));
    }

    public function test_the_coverage_shape_round_trips_and_defaults_to_empty(): void
    {
        $coverage = ['method' => 'area_wise', 'isZip' => false, 'options' => [['id' => 1, 'name' => 'Mirpur']]];

        $this->assertSame($coverage, CheckoutSnapshotDTO::fromArray(['coverage' => $coverage])->toArray()['coverage']);

        // Empty is what hides the picker in distance and fixed zones, so it must be the default.
        $this->assertSame([], CheckoutSnapshotDTO::fromArray([])->coverage);
    }

    public function test_the_null_provider_still_satisfies_the_widened_dto(): void
    {
        // The module works without a host adapter; a widened DTO must not break that.
        $quote = (new NullCheckoutProvider)->quote(null, null, []);

        $this->assertSame(0.0, $quote->deliveryFee);
        $this->assertSame(0.0, $quote->deliveryFeeBeforeDiscount);
        $this->assertNull($quote->surgeNote);

        $this->assertSame([], (new NullCheckoutProvider)->snapshot(null, null)->coverage);
    }
}
