<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use App\Services\Zone\ZoneService;
use Illuminate\Http\Request;

class PaymentFailedResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $zone = $this->resource->relationLoaded('zone') ? $this->resource->zone : null;
        $module = $this->resource->relationLoaded('module') ? $this->resource->module : null;

        // This resource was the only place combining the global setting with the zone's own
        // column; it now reads that rule from the service that owns Zone, so the payload a
        // failed payment offers, the /config payload and the placement guard cannot drift apart.
        $payments = app(ZoneService::class)->allowedPaymentMethods($zone);

        return array_merge(parent::toArray($request), [
            'type' => 'order',
            'cash_on_delivery' => $payments['cash_on_delivery'],
            'digital_payment' => $payments['digital_payment'],
            'offline_payment' => $payments['offline_payment'],
            'maximum_cod_order_amount' => $module?->zones->first()?->pivot?->maximum_cod_order_amount ?? 0,
            'order_id' => (int) $this->resource->id,
            'order_amount' => (float) $this->resource->order_amount,
            'partially_paid_amount' => (float) $this->resource->partially_paid_amount,
            'order_type' => $this->resource->order_type,
            'user_id' => $this->resource->is_guest ? null : $this->resource->user_id,
            'guest_id' => $this->resource->is_guest ? $this->resource->user_id : null,
            'zone_id' => $this->resource->zone_id,
            'module' => $module?->id,
            'module_type' => $module?->module_type,
            'prescription_order' => (int) $this->resource->prescription_order,
            'payment_status' => $this->resource->payment_status,
            'payment_method' => $this->resource->payment_method,
            'contact_person_number' => $this->contactPersonNumber(),
        ]);
    }

    private function contactPersonNumber(): ?string
    {
        $address = $this->resource->delivery_address;
        $address = is_array($address) ? $address : json_decode((string) $address, true);

        return $address['contact_person_number'] ?? null;
    }
}
