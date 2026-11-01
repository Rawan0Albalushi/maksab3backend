<?php

namespace App\Http\Resources\Customer\Order;

use App\Http\Resources\BaseResource;
use App\Traits\Api\OrderPayloadTrait;
use Illuminate\Http\Request;

class OrderDetailResource extends BaseResource
{
    use OrderPayloadTrait;

    private array $options = [];

    public function withOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public static function renderList(mixed $details, array $options = []): array
    {
        return collect($details)
            ->values()
            ->map(fn ($detail, $index) => (new static($detail))
                ->withOptions($index === 0 ? $options : ['images' => $options['images'] ?? []])
                ->toArray(request()))
            ->all();
    }

    public function toArray(Request $request): array
    {
        $detail = $this->resource;
        $images = $this->options['images'][$detail->id] ?? ['image_full_url' => null, 'images_full_url' => []];

        return array_merge(parent::toArray($request), [
            'id' => (int) $detail->id,
            // Present on every entry and null for an ordinary line. A folded BOGO bundle carries
            // its offer, its members and what the store gave away here, while `price` and
            // `quantity` above are restated as the bundle's -- so a client that ignores this key
            // still totals the order correctly.
            'bogo_details' => $detail->bogo_details ?? null,
            // The product-bundle counterpart, same contract: present on every entry, null for an
            // ordinary line. A folded bundle carries its members in `items` while `price` and
            // `quantity` above are restated as the bundle's, so a client that ignores this key
            // still totals the order correctly. Built by BundleOrderService::groupOrderDetails().
            'bundle_details' => $detail->bundle_details ?? null,
            'item_id' => $detail->item_id,
            'item_campaign_id' => $detail->item_campaign_id,
            'order_id' => (int) $detail->order_id,
            'price' => (float) $detail->price,
            'quantity' => (int) $detail->quantity,
            'item_details' => $this->decodeJsonColumn($detail->item_details),
            'variation' => $this->decodeJsonColumn($detail->variation),
            'add_ons' => $this->decodeJsonColumn($detail->add_ons),
            'total_add_on_price' => (float) $detail->total_add_on_price,
            'addon_discount' => (float) $detail->addon_discount,
            'discount_on_item' => (float) $detail->discount_on_item,
            'discount_type' => $detail->discount_type,
            'discount_percentage' => $detail->discount_percentage,
            'tax_amount' => (float) $detail->tax_amount,
            'tax_status' => $detail->tax_status,
            'category_id' => $detail->category_id,
            'image_full_url' => $images['image_full_url'],
            'images_full_url' => $images['images_full_url'],
            'created_at' => $detail->created_at,
            'updated_at' => $detail->updated_at,
        ], $this->orderContext());
    }

    private function orderContext(): array
    {
        $order = $this->options['order'] ?? null;

        if (! $order) {
            return [];
        }

        return array_merge([
            'is_guest' => (int) $order->is_guest,
            'saver_delivery_time' => $this->options['saver_delivery_time'] ?? null,
            'delivery_type' => $order->delivery_type,
            'delivery_type_charge' => (float) ($order->delivery_type_charge ?? 0),
            'delivery_charge' => (float) ($order->delivery_charge ?? 0),
            'original_delivery_charge' => (float) ($order->original_delivery_charge ?? 0),
            'free_delivery_by' => $order->free_delivery_by,
            'is_happy_hour' => $order->is_happy_hour,
            'is_store_discount' => $order->is_store_discount,
        ], $this->orderProDiscount($order));
    }
}
