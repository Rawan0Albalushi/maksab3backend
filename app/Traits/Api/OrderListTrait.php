<?php

namespace App\Traits\Api;

trait OrderListTrait
{
    private const ORDER_STATUS_SCOPES = [
        'searching_for_deliverymen' => 'SearchingForDeliveryman',
        'pending' => 'Pending',
        'accepted' => 'AccepteByDeliveryman',
        'processing' => 'Preparing',
        'item_on_the_way' => 'ItemOnTheWay',
        'delivered' => 'Delivered',
        'canceled' => 'Canceled',
        'failed' => 'failed',
        'refunded' => 'Refunded',
        'requested' => 'Refund_requested',
        'rejected' => 'Refund_request_canceled',
        'scheduled' => 'Scheduled',
        'on_going' => 'Ongoing',
    ];

    private const ORDER_STATUS_WITHOUT_SCHEDULE_LIMIT = [
        'all',
        'scheduled',
        'canceled',
        'rejected',
        'requested',
        'refund_requested',
        'refunded',
        'delivered',
        'failed',
    ];

    private function applyOrderStatusFilters($query, string $status)
    {
        return $query
            ->when($status == 'scheduled', function ($query) {
                return $query->whereRaw('created_at <> schedule_at');
            })
            ->when(isset(self::ORDER_STATUS_SCOPES[$status]), function ($query) use ($status) {
                return $query->{self::ORDER_STATUS_SCOPES[$status]}();
            })
            ->when(! in_array($status, self::ORDER_STATUS_WITHOUT_SCHEDULE_LIMIT), function ($query) {
                return $query->OrderScheduledIn(30);
            });
    }

    private function applyOrderKeywordSearch($query, $key)
    {
        return $query->search(
            keywords: $key,
            mainCol: ['id', 'order_status', 'transaction_reference'],
            orderByRelevance: false
        );
    }
}
