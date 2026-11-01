<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * The checkout summary's wire shape (§14.4) — five blocks, each answering one question.
 *
 * | Block      | Answers                                                              |
 * |------------|----------------------------------------------------------------------|
 * | `tax`      | what the basket costs, and what the discounts already took off it    |
 * | `delivery` | the fee, and its §12.1 breakdown. **null** where none can be quoted  |
 * | `surge`    | why the fee is higher than usual. **null** when nothing is surged    |
 * | `pro`      | what the membership was worth on THIS basket                        |
 * | `cashback` | what the customer earns back by placing it                          |
 * | `delivery_options` | Standard/Express/Slightly Delay, each with its own time window |
 *
 * `delivery`, `surge` and `delivery_options` are nullable on purpose. Null is a real answer — no
 * zone to price against, nothing surged, Additional Charge not turned on for this (zone, module)
 * — and a client renders nothing rather than a zero that reads as "free" or "no increase", or an
 * empty "Delivery Type" section with nothing in it.
 *
 * The keys are named as the port source names them, so a client written against it reads this
 * response with the same parser (N9: keys are added, never renamed).
 */
class CheckoutSummaryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return array_merge(parent::toArray($request), [
            'tax' => $summary['tax'],
            'delivery' => $summary['delivery'],
            'surge' => $summary['surge'],
            'pro' => $summary['pro'],
            'cashback' => $summary['cashback'],
            'delivery_options' => $summary['delivery_options'] ?? null,
        ]);
    }
}
