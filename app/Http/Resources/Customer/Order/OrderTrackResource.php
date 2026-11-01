<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\Customer\Store\StoreResource;
use Illuminate\Http\Request;

class OrderTrackResource extends OrderResource
{
    private ?string $saverDeliveryTime = null;

    public function withSaverDeliveryTime(?string $saverDeliveryTime): static
    {
        $this->saverDeliveryTime = $saverDeliveryTime;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $order = $this->resource;
        [$minDelivery, $maxDelivery] = $this->orderDeliveryWindow($order);

        return array_merge(parent::toArray($request), [
            'store' => $this->trackStore($request),
            'min_delivery_time' => $minDelivery,
            'max_delivery_time' => $maxDelivery,
            'saver_delivery_time' => $this->saverDeliveryTime,
            // Filled by EtaService::attach() in the controller — a resource never queries
            // (rule 3). Null is a real answer: no configuration for this (zone, module) means no
            // estimate, and the client shows nothing rather than a made-up time (§11.2).
            'eta' => $order->eta,
            'is_reviewed' => (int) ($order->details_count ?? 0) <= (int) ($order->review_count ?? 0),
            'offline_payment' => $this->offlinePayment(),
            'payments' => $this->payments(),
            'reviews' => $this->reviews(),
            'parcel_cancellation' => $this->parcelCancellation(),
        ]);
    }

    private function trackStore(Request $request): ?array
    {
        $store = $this->resource->store;

        if (! $store) {
            return null;
        }

        return array_merge((new StoreResource($store))->toArray($request), [
            'phone' => $store->phone,
            'store_sub' => $store->store_sub ? ['chat' => $store->store_sub->chat] : null,
            // 4.1 sent the whole store through Helpers::store_data_formatting(), so this was in
            // the tracking payload; StoreResource does not carry it (only StoreShowResource does),
            // so it is merged back here alongside the other two fields restored the same way.
            'store_business_model' => $store->store_business_model,
        ]);
    }

    private function offlinePayment(): ?array
    {
        $payment = $this->resource->offline_payments;

        if (! $payment) {
            return null;
        }

        $stored = $this->decodeJsonColumn($payment->payment_info) ?: [];
        $inputs = [];

        foreach ($stored as $key => $value) {
            if (! in_array($key, ['method_name', 'method_id'], true)) {
                $inputs[] = ['user_input' => $key, 'user_data' => $value];
            }
        }

        return [
            'input' => $inputs,
            'data' => [
                'status' => $payment->status,
                'method_id' => $stored['method_id'] ?? null,
                'method_name' => $stored['method_name'] ?? null,
                'customer_note' => $payment->customer_note,
                'admin_note' => $payment->note,
            ],
            'method_fields' => $this->decodeJsonColumn($payment->method_fields),
        ];
    }

    private function payments(): array
    {
        return collect($this->resource->payments)
            ->map(fn ($payment) => [
                'amount' => (float) $payment->amount,
                'payment_status' => $payment->payment_status,
                'payment_method' => $payment->payment_method,
            ])
            ->values()
            ->all();
    }

    private function reviews(): array
    {
        return collect($this->resource->reviews)
            ->map(fn ($review) => [
                'item_id' => (int) $review->item_id,
                'comment' => $review->comment,
                'rating' => (int) $review->rating,
            ])
            ->values()
            ->all();
    }

    private function parcelCancellation(): ?array
    {
        $cancellation = $this->resource->parcelCancellation;

        if (! $cancellation) {
            return null;
        }

        return [
            'reason' => $this->decodeJsonColumn($cancellation->reason),
            'cancel_by' => $cancellation->cancel_by,
            'note' => $cancellation->note,
            'return_otp' => $cancellation->return_otp,
            'return_fee' => (float) $cancellation->return_fee,
            'return_fee_payment_status' => $cancellation->return_fee_payment_status,
            'return_date' => $cancellation->return_date,
            'before_pickup' => $cancellation->before_pickup,
        ];
    }
}
