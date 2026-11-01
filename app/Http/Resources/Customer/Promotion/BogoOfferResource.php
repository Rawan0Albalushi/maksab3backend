<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use App\Models\BogoOffer;
use Illuminate\Http\Request;

/**
 * The offer itself, independent of any one store's bundle.
 *
 * `remaining_uses` is null when the offer carries no per-customer cap, so a client can tell "two
 * left" apart from "no counter to show". It is resolved by the service and attached, because
 * counting a customer's redemptions is a query and a resource may not run one.
 *
 * `order_types` is omitted rather than sent empty while the restriction is switched off -- see
 * BogoOffer::ORDER_TYPES_ENABLED -- so no client starts reading a rule that is not being applied.
 */
class BogoOfferResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $payload = array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'description' => $this->resource->description,
            'image_full_url' => $this->resource->image_full_url,
            'buy_qty' => (int) $this->resource->buy_qty,
            'get_qty' => (int) $this->resource->get_qty,
            'offer_label' => translate('messages.Buy').' '.$this->resource->buy_qty
                .' '.translate('messages.Get').' '.$this->resource->get_qty,
            'start_date' => $this->resource->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $this->resource->end_date?->format('Y-m-d H:i:s'),
            'valid_until' => $this->resource->end_date?->format('d M Y'),
            'usage_limit_per_customer' => $this->resource->usage_limit_per_customer,
            'remaining_uses' => $this->resource->remaining_uses ?? null,
        ]);

        if (BogoOffer::orderTypesEnabled()) {
            $payload['order_types'] = $this->resource->order_types ?? [];
        }

        return $payload;
    }
}
