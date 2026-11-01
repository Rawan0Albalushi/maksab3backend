<?php

namespace App\Http\Resources\Vendor\Order;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class OrderResource extends BaseResource
{
    private const STORE_FIELDS = [
        'store_name' => 'name',
        'store_address' => 'address',
        'store_phone' => 'phone',
        'store_lat' => 'latitude',
        'store_lng' => 'longitude',
        'store_logo' => 'logo',
        'store_logo_full_url' => 'logo_full_url',
    ];

    private function canEdit(mixed $order): bool
    {
        if ($order->order_status !== 'pending' || $order->payment_method !== 'cash_on_delivery') {
            return false;
        }

        if ((int) $order->prescription_order !== 0
            || (float) $order->ref_bonus_amount != 0
            || (float) $order->flash_admin_discount_amount != 0) {
            return false;
        }

        if ($order->relationLoaded('payments') && $order->payments->count() > 0) {
            return false;
        }

        return true;
    }

    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $store = $order->relationLoaded('store') ? $order->store : null;

        $data = $order->attributesToArray();
        $appends = [];

        foreach ($order->getAppends() as $append) {
            $appends[$append] = $data[$append] ?? null;
            unset($data[$append]);
        }

        $appends['order_attachment_full_url'] = $order->order_attachment_full_url;
        $appends['order_proof_full_url'] = $order->order_proof_full_url;

        $data['delivery_address'] = is_array($order->delivery_address)
            ? $order->delivery_address
            : json_decode((string) $order->delivery_address, true);

        foreach ($this->storeFields($store) as $key => $value) {
            $data[$key] = $value;
        }

        $data['item_campaign'] = $order->details->contains(fn ($detail) => $detail->item_campaign_id !== null) ? 1 : 0;
        $data['details_count'] = (int) $order->details->count();
        $data['can_edit'] = $this->canEdit($order);

        $data['eta'] = $order->eta;

        foreach (Helpers::pro_discount_data($order) as $key => $value) {
            $data[$key] = $value;
        }

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        $relations = $order->relationsToArray();
        unset($relations['store'], $relations['details'], $relations['order_pro_discount']);

        return $data + $relations;
    }

    private function storeFields(mixed $store): array
    {
        $fields = [];
        foreach (self::STORE_FIELDS as $key => $column) {
            $fields[$key] = $store ? $store[$column] : null;
        }

        [$min, $max] = $this->deliveryWindow($store?->delivery_time);
        $fields['min_delivery_time'] = $store ? $min : null;
        $fields['max_delivery_time'] = $store ? $max : null;
        $fields['vendor_id'] = $store ? $store['vendor_id'] : null;
        $fields['chat_permission'] = $store ? ($store['chat_permission'] ?? 0) : null;
        $fields['review_permission'] = $store ? ($store['review_permission'] ?? 0) : null;
        $fields['store_business_model'] = $store ? $store['store_business_model'] : null;

        return $fields;
    }
}
