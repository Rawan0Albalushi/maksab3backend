<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\DeliveryRuleAddRequest;
use App\Models\DeliveryRule;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * The form's field names must match what the request reads.
 *
 * WHY THIS EXISTS. Twice in this feature a control posted under one name while the FormRequest read
 * another — mistake M5 in the port doc's catalogue. Both times every half looked right on its own:
 * the markup rendered, the request validated, the service ran. And both times the value was
 * silently dropped.
 *
 *  - the wizard's Status toggles posted `weight_status`, the request read `weight_charge_status`;
 *  - the coverage tables post `area_charges[id]` / `zip_charges[id]`, and the request read a bare
 *    `charges` — so EVERY area-wise and ZIP-wise charge was discarded on save and the rule stored
 *    an empty table.
 *
 * A key mismatch cannot fail a lint, a type check or a route test. It only shows when someone saves
 * a rule and finds the charges gone, which is exactly how the second one was found.
 */
class DeliveryRuleRequestPayloadTest extends TestCase
{
    private function payloadFor(array $input): array
    {
        $request = DeliveryRuleAddRequest::createFrom(Request::create('/', 'POST', $input));

        return $request->payload();
    }

    public function test_area_wise_reads_the_area_charges_the_form_posts(): void
    {
        $payload = $this->payloadFor([
            'pricing_method' => DeliveryRule::METHOD_AREA,
            'area_charges' => [7 => '12.50', 9 => '20'],
            'zip_charges' => [3 => '99'],
        ]);

        $this->assertSame([7 => '12.50', 9 => '20'], $payload['charges']);
    }

    public function test_zip_wise_reads_the_zip_charges_the_form_posts(): void
    {
        $payload = $this->payloadFor([
            'pricing_method' => DeliveryRule::METHOD_ZIP,
            'area_charges' => [7 => '12.50'],
            'zip_charges' => [3 => '17.25'],
        ]);

        $this->assertSame([3 => '17.25'], $payload['charges']);
    }

    /**
     * The hidden pane must contribute nothing, or switching method would carry the other table's
     * amounts across and they would reappear if it were switched back.
     */
    public function test_a_non_coverage_method_takes_no_charges_from_either_table(): void
    {
        foreach ([DeliveryRule::METHOD_DISTANCE, DeliveryRule::METHOD_FIXED] as $method) {
            $payload = $this->payloadFor([
                'pricing_method' => $method,
                'area_charges' => [7 => '12.50'],
                'zip_charges' => [3 => '17.25'],
            ]);

            $this->assertSame([], $payload['charges'], $method.' must carry no coverage charges');
        }
    }

    /** The parcel tiers, pinned the same way after the first M5. */
    public function test_the_parcel_tiers_read_the_names_the_wizard_posts(): void
    {
        $payload = $this->payloadFor([
            'pricing_method' => DeliveryRule::METHOD_FIXED,
            'weight_charge_status' => '1',
            'dimension_charge_status' => '1',
            'weight_charges' => [4 => '3.5'],
            'dimension_charges' => [6 => '4.25'],
        ]);

        $this->assertTrue($payload['weight_charge_status']);
        $this->assertTrue($payload['dimension_charge_status']);
        $this->assertSame([4 => '3.5'], $payload['weight_charges']);
        $this->assertSame([6 => '4.25'], $payload['dimension_charges']);
    }

    public function test_an_absent_tier_toggle_is_false_not_missing(): void
    {
        $payload = $this->payloadFor(['pricing_method' => DeliveryRule::METHOD_FIXED]);

        $this->assertFalse($payload['weight_charge_status'], 'an unchecked checkbox posts nothing at all');
        $this->assertFalse($payload['dimension_charge_status']);
        $this->assertSame([], $payload['charges']);
    }
}
