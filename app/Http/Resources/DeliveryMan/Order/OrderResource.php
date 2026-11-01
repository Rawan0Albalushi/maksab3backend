<?php

namespace App\Http\Resources\DeliveryMan\Order;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use App\Http\Resources\Common\Parcel\OrderParcelTierResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class OrderResource extends BaseResource
{
    private const DROPPED_RELATIONS = ['details', 'order_pro_discount'];

    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $dropped = $order->store ? array_merge(self::DROPPED_RELATIONS, ['store']) : self::DROPPED_RELATIONS;

        return array_merge(
            Arr::except($order->toArray(), $dropped),
            $this->storeFields($order->store),
            [
                'order_attachment_full_url' => $order->order_attachment_full_url,
                'order_proof_full_url' => $order->order_proof_full_url,
                'item_campaign' => (int) $order->details->contains(fn ($detail) => $detail->item_campaign_id !== null),
                'delivery_address' => is_array($order->delivery_address)
                    ? $order->delivery_address
                    : json_decode((string) $order->delivery_address, true),
                'details_count' => (int) $order->details->count(),
                // Filled by EtaService::attach() in the controller — a resource never queries
                // (rule 3). Null where there is nothing to estimate (§11.2).
                'eta' => $order->eta,
                // The parcel tiers, in the same shape the customer app receives. The rider needs
                // these to know what they are collecting; the raw `weight_id` / `dimension_id`
                // arrive with the spread above and are kept for clients that match on the id.
                'weight' => OrderParcelTierResource::forWeight($order->weight),
                'dimension' => OrderParcelTierResource::forDimension($order->dimension),
            ],
            Helpers::pro_discount_data($order)
        );
    }

    private function storeFields(mixed $store): array
    {
        if (! $store) {
            return array_fill_keys([
                'store_name', 'store_address', 'store_phone', 'store_lat', 'store_lng', 'store_logo',
                'store_logo_full_url', 'min_delivery_time', 'max_delivery_time', 'vendor_id',
                'chat_permission', 'review_permission', 'store_business_model',
            ], null);
        }

        [$minDeliveryTime, $maxDeliveryTime] = $this->deliveryWindow($store->delivery_time);

        return [
            'store_name' => $store->name,
            'store_address' => $store->address,
            'store_phone' => $store->phone,
            'store_lat' => $store->latitude,
            'store_lng' => $store->longitude,
            'store_logo' => $store->logo,
            'store_logo_full_url' => $store->logo_full_url,
            'min_delivery_time' => $minDeliveryTime,
            'max_delivery_time' => $maxDeliveryTime,
            'vendor_id' => $store->vendor_id,
            'chat_permission' => $store->chat_permission ?? 0,
            'review_permission' => $store->review_permission ?? 0,
            'store_business_model' => $store->store_business_model,
        ];
    }
}
