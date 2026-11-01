<?php

namespace Tests\Unit;

use App\Http\Requests\Admin\ZoneConnectModuleRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Zero is the STORED encoding for "this zone has no COD ceiling".
 *
 * ZoneConnectModuleRequest::codLimits() writes 0.0 for every module when the Max COD toggle is
 * off, and the placement guard treats a falsy ceiling as unlimited:
 *
 *     $pivot->maximum_cod_order_amount && $order->order_amount > $pivot->maximum_cod_order_amount
 *
 * So a zero typed while the toggle is ON saved as "no limit" — the opposite of what the field
 * says it does ("A COD order above this amount is refused at checkout"), and indistinguishable
 * from having left the toggle off. The encoding is fine; the ambiguous way of reaching it is not,
 * so the form refuses it and names the toggle as the deliberate way to mean "no limit".
 */
class MaxCodLimitZeroTest extends TestCase
{
    /** @param array<string, mixed> $input */
    private function validate(array $input): \Illuminate\Contracts\Validation\Validator
    {
        $request = ZoneConnectModuleRequest::create('/', 'POST', $input);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);

        return Validator::make($request->all(), $request->rules(), $request->messages());
    }

    public function test_a_zero_ceiling_is_refused_while_the_toggle_is_on(): void
    {
        $validator = $this->validate([
            'module_id' => [1],
            'max_cod_status' => '1',
            'max_cod_order_amount' => [1 => '0'],
        ]);

        $this->assertTrue($validator->fails(), 'zero saved as "no limit", which is not what the field promises');
        $this->assertStringContainsString(
            'toggle off',
            $validator->errors()->first('max_cod_order_amount.1'),
            'the message must name the toggle as the way to mean "no limit"',
        );
    }

    public function test_a_real_ceiling_passes(): void
    {
        $validator = $this->validate([
            'module_id' => [1],
            'max_cod_status' => '1',
            'max_cod_order_amount' => [1 => '500'],
        ]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }

    /** Still refused, and still for being negative rather than for being zero. */
    public function test_a_negative_ceiling_is_refused(): void
    {
        $validator = $this->validate([
            'module_id' => [1],
            'max_cod_status' => '1',
            'max_cod_order_amount' => [1 => '-100'],
        ]);

        $this->assertTrue($validator->fails());
    }

    /**
     * With the toggle OFF the amount is not asked for at all — that is the supported way to say
     * "no limit", and requiring a figure for it would be the same trap from the other side.
     */
    public function test_the_amount_is_not_required_while_the_toggle_is_off(): void
    {
        $validator = $this->validate(['module_id' => [1]]);

        $this->assertFalse($validator->fails(), $validator->errors()->first());
    }

    /** And that path still encodes "no limit" as 0.0, which the guard reads as unlimited. */
    public function test_the_toggle_off_path_still_encodes_no_limit_as_zero(): void
    {
        $request = ZoneConnectModuleRequest::create('/', 'POST', ['module_id' => [1, 2]]);
        $request->setContainer($this->app);

        $this->assertSame([1 => 0.0, 2 => 0.0], $request->codLimits());
    }
}
