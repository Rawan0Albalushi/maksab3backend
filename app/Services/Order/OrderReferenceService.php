<?php

namespace App\Services\Order;

use App\Models\OrderReference;
use App\Services\BaseService;
use App\Services\Item\ItemService;

class OrderReferenceService extends BaseService
{
    public function pendingReviewOrder(mixed $userId): mixed
    {
        return app(OrderService::class)->findPendingReview($userId);
    }

    public function reviewImages(mixed $order): array
    {
        if (! $order || ! $order->relationLoaded('details')) {
            return [];
        }

        $itemIds = $order->details
            ->pluck('item_details')
            ->map(fn ($detail) => data_get(json_decode($detail, true), 'id'))
            ->filter()
            ->unique()
            ->all();

        if (! $itemIds) {
            return [];
        }

        $items = app(ItemService::class)->getImagesByIds($itemIds);

        return $order->details
            ->pluck('item_details')
            ->map(fn ($detail) => $items->get(data_get(json_decode($detail, true), 'id'))?->image_full_url)
            ->filter()
            ->values()
            ->all();
    }

    public function cancelReview(mixed $orderId, mixed $userId): bool
    {
        return (bool) OrderReference::where('order_id', $orderId)
            ->whereIn('order_id', app(OrderService::class)->idsForUserQuery($userId))
            ->update(['is_review_canceled' => 1]);
    }
}
