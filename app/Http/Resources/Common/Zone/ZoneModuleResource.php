<?php

namespace App\Http\Resources\Common\Zone;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ZoneModuleResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $pivot = $this->resource->pivot;

        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'module_name' => $this->resource->module_name,
            'module_type' => $this->resource->module_type,
            'stores_count' => (int) $this->resource->stores_count,
            'theme_id' => $this->resource->theme_id,
            'additional_delivery_option_status' => (bool) ($pivot->additional_delivery_option_status ?? false),
            'delivery_options' => $this->deliveryOptions(),
            'free_delivery' => $this->freeDelivery(),
            'pivot' => [
                'zone_id' => (int) $pivot->zone_id,
                'per_km_shipping_charge' => $pivot->per_km_shipping_charge,
                'minimum_shipping_charge' => $pivot->minimum_shipping_charge,
                'maximum_shipping_charge' => $pivot->maximum_shipping_charge,
                'maximum_cod_order_amount' => $pivot->maximum_cod_order_amount,
                'delivery_charge_type' => $pivot->delivery_charge_type,
                'fixed_shipping_charge' => $pivot->fixed_shipping_charge,
                'minimum_delivery_charge' => $pivot->minimum_delivery_charge,
            ],
        ]);
    }

    /**
     * The free-delivery setup covering this (zone, module), named as the setup itself names it.
     *
     * Deliberately NOT `/config`'s `admin_free_delivery` vocabulary. That block answers in
     * `free_delivery_over` and `free_delivery_distance` because shipped apps switch on those
     * words and N9 forbids retyping one -- but they describe a global setting that no longer
     * exists: there is no "distance" criterion in the zone model at all, and the amount is
     * `minimum_order_amount` on `free_deliveries`. This key is new, so it has no old contract to
     * keep and reports the columns under their real names; `type` is the stored value
     * (`all_store` / `specific_criteria`, the FreeDelivery::TYPE_* constants) rather than
     * apiType()'s legacy spelling, for the same reason.
     *
     * A module with no setup answers `status: false` and a null type rather than being omitted,
     * so a client can read the key unconditionally.
     */
    private function freeDelivery(): array
    {
        $setup = $this->resource->getAttribute('free_delivery_setup');

        return [
            'status' => (bool) $setup,
            'type' => $setup?->type,
            // Null, not 0, when the setup frees every order: `all_store` has no threshold, and a
            // 0 there reads as "free over nothing", which is the same thing said confusingly. A
            // `specific_criteria` row saved before the form required an amount is null too, and
            // FreeDelivery::frees() treats that as covering every order.
            'minimum_order_amount' => $setup?->minimum_order_amount !== null
                ? (float) $setup->minimum_order_amount
                : null,
        ];
    }

    private function deliveryOptions(): array
    {
        return collect($this->resource->getAttribute('delivery_options') ?? [])
            ->map(fn ($option) => [
                'delivery_type' => (string) $option->delivery_type,
                'extra_charge' => $option->getRawOriginal('extra_charge') !== null ? (float) $option->extra_charge : null,
                'reduce_charge' => $option->getRawOriginal('reduce_charge') !== null ? (float) $option->reduce_charge : null,
                'add_delivery_time' => $this->timeWindow($option->getAttribute('add_delivery_time')),
                'reduce_delivery_time' => $this->timeWindow($option->getAttribute('reduce_delivery_time')),
            ])->all();
    }

    private function timeWindow(mixed $window): array
    {
        return [
            'value' => (int) ($window['value'] ?? 0),
            'unit' => (string) ($window['unit'] ?? 'min'),
        ];
    }
}
