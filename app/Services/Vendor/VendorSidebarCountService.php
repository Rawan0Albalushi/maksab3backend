<?php

namespace App\Services\Vendor;

use App\Models\Order;
use Carbon\Carbon;
use App\Services\Order\OrderService;

/**
 * Order badge counts for the vendor sidebar.
 *
 * These were nine separate COUNT(*) queries embedded in the sidebar Blade
 * partials, running on every vendor page against a large orders table. They are
 * collapsed here into two queries: one set of conditional aggregates over the
 * shared base filter, plus a separate count for the scheduled badge (which
 * deliberately does not apply the NotDigitalOrder scope, so it cannot share the
 * same base).
 */
class VendorSidebarCountService
{
    private const SCHEDULE_WINDOW_MINUTES = 30;

    private array $cache = [];

    /**
     * @return array{count_all:int,count_pending:int,count_confirmed:int,count_processing:int,count_handover:int,count_picked_up:int,count_delivered:int,count_refunded:int,count_scheduled:int}
     */
    public function counts(int|string|null $storeId, bool $storeConfirmsOrders): array
    {
        $key = $storeId.'|'.($storeConfirmsOrders ? '1' : '0');

        return $this->cache[$key] ??= $this->resolve($storeId, $storeConfirmsOrders);
    }

    private function resolve(int|string|null $storeId, bool $storeConfirmsOrders): array
    {
        if ($storeId === null) {
            return array_fill_keys([
                'count_all', 'count_pending', 'count_confirmed', 'count_processing',
                'count_handover', 'count_picked_up', 'count_delivered', 'count_refunded',
                'count_scheduled',
            ], 0);
        }

        $now = Carbon::now()->toDateTimeString();
        $windowEnd = Carbon::now()->addMinutes(self::SCHEDULE_WINDOW_MINUTES)->toDateTimeString();

        // Mirrors Order::scopeOrderScheduledIn(30).
        $scheduledIn = '((((created_at <> schedule_at) and (schedule_at between ? and ?)) or schedule_at < ?) or created_at = schedule_at)';

        // Mirrors the "active orders" condition shared by count_all and count_scheduled.
        $active = $storeConfirmsOrders
            ? "(order_status not in ('failed','canceled','refund_requested','refunded'))"
            : "(order_status not in ('pending','failed','canceled','refund_requested','refunded') or (order_status = 'pending' and order_type = 'take_away'))";

        $pending = $storeConfirmsOrders
            ? "(order_status = 'pending' and {$scheduledIn})"
            : "(order_status = 'pending' and order_type = 'take_away' and {$scheduledIn})";

        $confirmed = "(order_status in ('confirmed','accepted') and confirmed is not null and {$scheduledIn})";

        $select = implode(', ', [
            "coalesce(sum({$active}), 0) as count_all",
            "coalesce(sum({$pending}), 0) as count_pending",
            "coalesce(sum({$confirmed}), 0) as count_confirmed",
            "coalesce(sum(order_status = 'processing'), 0) as count_processing",
            "coalesce(sum(order_status = 'handover'), 0) as count_handover",
            "coalesce(sum(order_status = 'picked_up'), 0) as count_picked_up",
            "coalesce(sum(order_status = 'delivered'), 0) as count_delivered",
            "coalesce(sum(order_status = 'refunded'), 0) as count_refunded",
        ]);

        $bindings = [$now, $windowEnd, $now, $now, $windowEnd, $now];

        $row = app(OrderService::class)->storeOrderCounts($storeId, $select, $bindings);

        // The scheduled badge intentionally omits NotDigitalOrder, so it needs its
        // own query rather than another conditional aggregate over the base above.
        $countScheduled = app(OrderService::class)->countScheduledForStore($storeId, $active);

        return [
            'count_all' => (int) ($row->count_all ?? 0),
            'count_pending' => (int) ($row->count_pending ?? 0),
            'count_confirmed' => (int) ($row->count_confirmed ?? 0),
            'count_processing' => (int) ($row->count_processing ?? 0),
            'count_handover' => (int) ($row->count_handover ?? 0),
            'count_picked_up' => (int) ($row->count_picked_up ?? 0),
            'count_delivered' => (int) ($row->count_delivered ?? 0),
            'count_refunded' => (int) ($row->count_refunded ?? 0),
            'count_scheduled' => (int) $countScheduled,
        ];
    }
}
