<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\Order\OrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * DeliveryFeeTrait::adjustedFeeForOrder() backs partials.delivery-type-row, included on the
 * admin, vendor and POS order views and every invoice. Regression for the vendor-panel QA pass
 * (TC_38): an order with delivery_charge=0 (free) but delivery_type=express and a real
 * delivery_type_charge — the exact shape PlaceNewOrderTrait/DeliveryFeeTrait were fixed to
 * produce earlier this session (TC_445, "keep express honoured under free delivery") — used to
 * require delivery_charge > 0 to be recognised as express at all, so the premium was charged and
 * recorded correctly but rendered nowhere: the row fell through to is_free instead and the
 * express designation vanished from every screen that includes this partial.
 */
class AdjustedFeeForOrderTest extends TestCase
{
    use DatabaseTransactions;

    private function order(array $attributes): Order
    {
        $order = new Order();
        foreach ($attributes as $key => $value) {
            $order->{$key} = $value;
        }

        return $order;
    }

    public function test_express_is_recognised_even_when_the_base_delivery_is_free(): void
    {
        $result = app(OrderService::class)->adjustedFeeForOrder($this->order([
            'delivery_charge' => 0,
            'delivery_type' => 'express',
            'delivery_type_charge' => 10,
            'free_delivery_by' => 'admin',
        ]));

        $this->assertTrue($result['is_express']);
        $this->assertFalse($result['is_free'], 'a charged express premium must not be reported as a free delivery row');
        $this->assertSame(10.0, $result['adjusted']);
    }

    public function test_express_is_still_recognised_with_a_normal_paid_base(): void
    {
        $result = app(OrderService::class)->adjustedFeeForOrder($this->order([
            'delivery_charge' => 30,
            'delivery_type' => 'express',
            'delivery_type_charge' => 10,
            'free_delivery_by' => null,
        ]));

        $this->assertTrue($result['is_express']);
        $this->assertSame(40.0, $result['adjusted']);
    }

    public function test_a_genuinely_free_standard_order_still_reports_as_free(): void
    {
        $result = app(OrderService::class)->adjustedFeeForOrder($this->order([
            'delivery_charge' => 0,
            'delivery_type' => 'standard',
            'delivery_type_charge' => 0,
            'free_delivery_by' => 'admin',
        ]));

        $this->assertFalse($result['is_express']);
        $this->assertFalse($result['is_slightly']);
        $this->assertTrue($result['is_free']);
    }

    public function test_slightly_delay_still_shows_with_a_paid_base(): void
    {
        $result = app(OrderService::class)->adjustedFeeForOrder($this->order([
            'delivery_charge' => 30,
            'delivery_type' => 'slightly_delay',
            'delivery_type_charge' => 5,
            'free_delivery_by' => null,
        ]));

        $this->assertTrue($result['is_slightly']);
        $this->assertSame(25.0, $result['adjusted']);
    }
}
