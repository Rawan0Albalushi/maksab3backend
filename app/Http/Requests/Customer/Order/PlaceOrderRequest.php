<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;
use App\Rules\EmailAddress;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;

class PlaceOrderRequest extends BaseRequest
{
    public static function placementRules(mixed $request): array
    {
        return [
            'payment_method' => 'required|in:cash_on_delivery,digital_payment,wallet,offline_payment',
            'order_type' => 'required|in:take_away,delivery,parcel',
            'store_id' => 'required_unless:order_type,parcel',
            'distance' => 'required_unless:order_type,take_away',
            'address' => 'required_unless:order_type,take_away',
            'longitude' => 'required_unless:order_type,take_away',
            'latitude' => 'required_unless:order_type,take_away',
            'parcel_category_id' => 'required_if:order_type,parcel',
            // The two ADDITIVE parcel tiers, beside the category. Nullable and validated
            // downstream: a (zone, module) whose delivery rule does not price by weight or by
            // size has nothing to pick, so requiring one would refuse every parcel in a zone
            // that charges a flat fee. An id posted at a tier whose switch is off costs nothing.
            'weight_id' => 'nullable|integer|exists:weights,id',
            'dimension_id' => 'nullable|integer|exists:dimensions,id',
            'receiver_details' => 'required_if:order_type,parcel',
            'charge_payer' => 'required_if:order_type,parcel|in:sender,receiver',
            'dm_tips' => 'nullable|numeric',
            'guest_id' => $request->user ? 'nullable' : 'required',
            'contact_person_name' => $request->user ? 'nullable' : 'required',
            'contact_person_number' => PhoneNumber::rules($request->user ? 'nullable' : 'required'),
            'contact_person_email' => EmailAddress::rules($request->user ? 'nullable' : 'required'),
            'password' => $request->create_new_user ? StrongPassword::basicRules('required') : 'nullable',
            'monthly_subscribe' => 'nullable',
            // The map's travel time for this journey, in SECONDS. Optional and additive (N9):
            // an app build older than the field simply omits it.
            'delivery_duration' => 'nullable|integer|min:0',
            // §5.4 — the coverage pick an area-wise or ZIP-wise delivery rule prices from.
            // Nullable here and validated against the store's zone downstream: a rule that
            // prices the whole zone has nothing to pick, so requiring one would refuse every
            // order in a distance-priced zone.
            'area_id' => 'nullable|integer',
            'zip_code_id' => 'nullable|integer',
        ];
    }

    public function rules(): array
    {
        return self::placementRules($this);
    }
}
