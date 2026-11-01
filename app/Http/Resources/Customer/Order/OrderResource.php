<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Common\Parcel\OrderParcelTierResource;
use App\Http\Resources\Common\Parcel\ParcelCategoryResource;
use App\Traits\Api\OrderPayloadTrait;
use Illuminate\Http\Request;

class OrderResource extends BaseResource
{
    use OrderPayloadTrait;

    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $hasDetails = $order->relationLoaded('details');
        $items = $hasDetails ? $this->orderItemRows($order) : [];
        $preview = array_slice($items, 0, self::ITEMS_PREVIEW_LIMIT);

        return array_merge(parent::toArray($request), [
            'id' => (int) $order->id,
            'user_id' => (int) $order->user_id,
            // Filled by EtaService::attach(); null on any screen that does not attach one, and
            // on any order with nothing to estimate (§11.2).
            'eta' => $order->eta,
            'order_amount' => (float) $order->order_amount,
            'coupon_discount_amount' => (float) $order->coupon_discount_amount,
            'store_discount_amount' => (float) $order->store_discount_amount,
            'total_tax_amount' => (float) $order->total_tax_amount,
            'delivery_charge' => (float) $order->delivery_charge,
            'dm_tips' => (float) $order->dm_tips,
            'additional_charge' => (float) $order->additional_charge,
            'flash_admin_discount_amount' => (float) $order->flash_admin_discount_amount,
            'flash_store_discount_amount' => (float) $order->flash_store_discount_amount,
            'extra_packaging_amount' => (float) $order->extra_packaging_amount,
            'ref_bonus_amount' => (float) $order->ref_bonus_amount,
            'bring_change_amount' => $order->bring_change_amount,
            'payment_status' => $order->payment_status,
            'order_status' => $order->order_status,
            'payment_method' => $order->payment_method,
            'order_note' => $order->order_note,
            'order_type' => $order->order_type,
            'charge_payer' => $order->charge_payer,
            'prescription_order' => $order->prescription_order,
            'tax_status' => $order->tax_status,
            'cancellation_reason' => $order->cancellation_reason,
            'cancellation_note' => $order->cancellation_note,
            'processing_time' => $order->processing_time,
            'cutlery' => $order->cutlery,
            'unavailable_item_note' => $order->unavailable_item_note,
            'delivery_instruction' => $order->delivery_instruction,
            'delivery_type' => $order->delivery_type,
            'delivery_type_charge' => (float) $order->delivery_type_charge,
            'schedule_at' => $order->schedule_at,
            'scheduled' => $order->scheduled,
            'otp' => $order->otp,
            'module_id' => (int) $order->module_id,
            'module_type' => $order->module_type,
            'order_attachment_full_url' => $order->order_attachment_full_url,
            'order_proof_full_url' => $order->order_proof_full_url,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'pending' => $order->pending,
            'accepted' => $order->accepted,
            'confirmed' => $order->confirmed,
            'processing' => $order->processing,
            'handover' => $order->handover,
            'picked_up' => $order->picked_up,
            'delivered' => $order->delivered,
            'canceled' => $order->canceled,
            'refund_requested' => $order->refund_requested,
            'refunded' => $order->refunded,
            'failed' => $order->failed,
            'delivery_address' => $this->decodeJsonColumn($order->delivery_address),
            'receiver_details' => $order->receiver_details,
            'details_count' => (int) ($order->details_count ?? 0),
            'item_count' => $this->when($hasDetails, fn () => count($items)),
            'items_preview' => $this->when($hasDetails, fn () => $preview),
            'extra_items_count' => $this->when($hasDetails, fn () => count($items) - count($preview)),
            'can_reorder' => $this->when($hasDetails, fn () => $order->can_reorder),
            // Whether this order carries a BOGO bundle, so a row can badge it without pulling the
            // lines back. Independent of a campaign item -- an order can hold both, or neither.
            'is_bogo' => $this->when($hasDetails, fn () => $order->is_bogo),
            'bogo_discount_amount' => (float) ($order->bogo_discount_amount ?? 0),
            // Which store-wide promotion actually produced store_discount_amount. That column
            // alone cannot answer it: it also carries the items' OWN discounts when no store-wide
            // rate qualified, plus the bundle's reduction, so `store_discount_amount > 0` is true
            // on orders with no store discount set up at all. Both read plain columns -- no
            // relation needed, unlike is_bogo. Same keys OrderDetailResource already emits.
            'is_happy_hour' => $order->is_happy_hour,
            'is_store_discount' => $order->is_store_discount,
            'store' => $this->orderStoreCard($order->store),
            'delivery_man' => $this->orderDeliveryMan($order->delivery_man),
            'parcel_category' => $order->parcel_category ? (new ParcelCategoryResource($order->parcel_category))->toArray($request) : null,
            // The other two tiers the delivery charge was built from. Null on anything that is not
            // a parcel, and on a parcel whose zone does not price by that tier — render nothing
            // rather than an empty row.
            'weight' => OrderParcelTierResource::forWeight($order->weight),
            'dimension' => OrderParcelTierResource::forDimension($order->dimension),
            'refund' => $this->when($order->relationLoaded('refund'), fn () => $this->orderRefund($order->refund)),
        ], $this->orderProDiscount($order));
    }
}
