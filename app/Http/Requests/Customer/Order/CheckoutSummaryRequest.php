<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;

/**
 * POST customer/order/checkout-summary — everything the checkout page shows, in one call (§14.4).
 *
 * The rules are placement's, minus everything placement needs only in order to WRITE an order:
 * no payment method, no contact person, no receiver details. What is left is what the fee, the
 * tax and the surge are computed from, so the quote is asked for with the same inputs the charge
 * will be.
 *
 * `distance` is KILOMETRES, always, whatever `business_settings.distance_unit` says (§3.6 /
 * PHASE-0 decision D2). The setting never reinterprets this value.
 */
class CheckoutSummaryRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_type' => 'required|in:take_away,delivery,parcel',
            'store_id' => 'required_unless:order_type,parcel',
            'order_amount' => 'required|numeric',
            'distance' => 'required_unless:order_type,take_away',
            'longitude' => 'required_unless:order_type,take_away',
            'latitude' => 'required_unless:order_type,take_away',
            'parcel_category_id' => 'required_if:order_type,parcel',
            // The two ADDITIVE parcel tiers, beside the category. Nullable and validated
            // downstream: a (zone, module) whose delivery rule does not price by weight or by
            // size has nothing to pick, so requiring one would refuse every parcel in a zone
            // that charges a flat fee. An id posted at a tier whose switch is off costs nothing.
            'weight_id' => 'nullable|integer|exists:weights,id',
            'dimension_id' => 'nullable|integer|exists:dimensions,id',
            // Required for the same reason placement requires it: the parcel branch of
            // `getZoneAndStore()` reads the receiver's own zone and coordinates out of this
            // payload, and without them it hands a null latitude to a spatial Point and the
            // endpoint answers 500 rather than saying what is missing.
            'receiver_details' => 'required_if:order_type,parcel',
            'coupon_code' => 'nullable|string',
            'schedule_at' => 'nullable|date',
            // §5.4 — validated against the store's zone, but NOT required: a quote before the
            // customer has chosen is legitimate, and placement is where the pick becomes
            // mandatory.
            'area_id' => 'nullable|integer',
            'zip_code_id' => 'nullable|integer',
            'guest_id' => $this->user ? 'nullable' : 'required',
        ];
    }
}
