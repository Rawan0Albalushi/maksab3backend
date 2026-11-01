<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use App\Traits\Api\OrderPayloadTrait;
use Illuminate\Http\Request;

class MonthlySubscriptionResource extends BaseResource
{
    use OrderPayloadTrait;

    private const PREVIEW_LIMIT = 4;

    private bool $detailed = false;

    public function detailed(bool $detailed = true): static
    {
        $this->detailed = $detailed;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $reminder = $this->resource;
        $store = $reminder->order?->store;
        $details = $reminder->order?->details ?? collect();
        $rows = $this->itemRows($details);

        $payload = array_merge(parent::toArray($request), [
            'id' => (int) $reminder->id,
            'order_id' => (int) $reminder->order_id,
            'module_id' => (int) $reminder->module_id,
            'module_type' => (string) $reminder->module_type,
            'remind_at' => $reminder->remind_at?->toDateString(),
            'status' => (string) $reminder->status,
            'store' => $store ? [
                'id' => (int) $store->id,
                'name' => $store->name,
                'logo' => $store->logo_full_url,
            ] : null,
            'items_count' => $details->count(),
        ]);

        if (! $this->detailed) {
            $payload['items_preview'] = $rows;

            return $payload;
        }

        $payload['items'] = $rows;
        $payload['total_amount'] = round(array_sum(array_column($rows, 'line_total')), 2);

        return $payload;
    }

    private function itemRows(mixed $details): array
    {
        $rows = $this->detailed ? collect($details) : collect($details)->take(self::PREVIEW_LIMIT);

        return $rows->map(function ($detail) {
            $isCampaign = ! empty($detail->item_campaign_id);
            $catalog = $isCampaign ? $detail->campaign : $detail->item;
            $snapshot = $this->decodeJsonColumn($detail->item_details) ?: [];

            $unitPrice = (float) ($detail->price ?? 0);
            $catalogPrice = $catalog ? (float) ($catalog->price ?? 0) : null;
            $quantity = (int) ($detail->quantity ?? 0);

            $row = [
                'id' => $isCampaign ? (int) $detail->item_campaign_id : (int) $detail->item_id,
                'item_type' => $isCampaign ? 'campaign' : 'item',
                'name' => $catalog?->name ?? ($snapshot['name'] ?? null),
                'image' => $catalog?->image_full_url ?: ($snapshot['image_full_url'] ?? null),
                'price' => $unitPrice,
                'old_price' => ($catalogPrice !== null && $catalogPrice > $unitPrice) ? $catalogPrice : null,
                'quantity' => $quantity,
                'is_available' => (int) ($catalog?->status ?? 0) === 1,
            ];

            if ($this->detailed) {
                $row['variation'] = $this->decodeJsonColumn($detail->variation) ?: [];
                $row['line_total'] = $unitPrice * $quantity;
            }

            return $row;
        })->values()->all();
    }
}
