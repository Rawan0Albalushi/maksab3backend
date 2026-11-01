<?php

namespace App\Services\Admin;

use App\Support\Cache\ApiCache;
use App\Services\Item\TempProductService;
use App\Services\Store\StoreService;
use App\Services\Order\OrderService;

class AdminSidebarCountService
{
    private const SCHEDULED_IN = '((created_at <> schedule_at and (schedule_at between ? and ?) or schedule_at < ?) or created_at = schedule_at)';

    private const OFFLINE_EXISTS = 'exists (select * from `offline_payments` where `orders`.`id` = `offline_payments`.`order_id`)';

    public static function get(?int $moduleId, bool $isParcel): array
    {
        return self::remember(
            variant: $isParcel ? 'parcel' : 'store',
            moduleId: $moduleId,
            callback: fn (): array => self::compute(moduleId: $moduleId, isParcel: $isParcel)
        );
    }

    public static function remember(string $variant, ?int $moduleId, callable $callback): array
    {
        return ApiCache::remember(
            group: 'admin_sidebar_counts',
            context: self::context(moduleId: $moduleId, variant: $variant),
            callback: $callback,
            extraTags: self::moduleTags(moduleId: $moduleId)
        );
    }

    public static function flush(?int $moduleId = null): void
    {
        ApiCache::bust($moduleId === null ? 'sidebar_counts' : self::moduleTag(moduleId: $moduleId));
    }

    private static function moduleTag(int $moduleId): string
    {
        return 'sidebar_counts_module_' . $moduleId;
    }

    private static function moduleTags(?int $moduleId): array
    {
        return $moduleId === null ? [] : [self::moduleTag(moduleId: $moduleId)];
    }

    private static function context(?int $moduleId, string $variant): array
    {
        $admin = auth('admin')->user();

        return [
            'module' => $moduleId ?? 'none',
            'variant' => $variant,
            'zone' => ($admin && $admin->role_id != 1 && $admin->zone_id) ? $admin->zone_id : 'all',
        ];
    }

    private static function compute(?int $moduleId, bool $isParcel): array
    {
        return array_merge(self::orderCounts(moduleId: $moduleId, isParcel: $isParcel), [
            'count_new_items' => app(TempProductService::class)->countUnscopedForModule($moduleId),
            'count_new_stores' => app(StoreService::class)->countWithPendingVendor($moduleId),
        ]);
    }

    private static function orderCounts(?int $moduleId, bool $isParcel): array
    {
        $now = now()->toDateTimeString();
        $until = now()->addMinutes(30)->toDateTimeString();
        $window = [$now, $until, $now];

        $select = ['count(*) as count_all'];
        $bindings = [];

        $add = function (string $alias, string $condition, array $binds = []) use (&$select, &$bindings): void {
            $select[] = "sum(case when {$condition} then 1 else 0 end) as {$alias}";
            foreach ($binds as $bind) {
                $bindings[] = $bind;
            }
        };

        $add('count_pending', "order_status = 'pending' and " . self::SCHEDULED_IN, $window);
        $add('count_accepted', "order_status = 'accepted' and " . self::SCHEDULED_IN, $window);
        $add('count_processing', "order_status in ('confirmed', 'processing', 'handover') and " . self::SCHEDULED_IN, $window);
        $add('count_otw', "order_status = 'picked_up' and " . self::SCHEDULED_IN, $window);
        $add('count_delivered', "order_status = 'delivered'");
        $add('count_canceled', "order_status = 'canceled'");
        $add('count_failed', "order_status = 'failed' and not " . self::OFFLINE_EXISTS);
        $add('count_offline', "payment_method = 'offline_payment' and " . self::OFFLINE_EXISTS);

        if ($isParcel) {
            $add('count_unassigned', "delivery_man_id is null and order_type in ('delivery', 'parcel') and order_status not in ('delivered', 'failed', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded') and " . self::SCHEDULED_IN, $window);
            $add('count_ongoing', "order_status in ('accepted', 'confirmed', 'processing', 'handover', 'picked_up') and " . self::SCHEDULED_IN, $window);
        } else {
            $add('count_scheduled', "created_at <> schedule_at and scheduled = '1'");
            $add('count_refunded', "order_status = 'refunded'");
            $add('count_refund_req', "order_status = 'refund_requested'");
        }

        $row = app(OrderService::class)->moduleOrderCounts($isParcel, $moduleId, $select, $bindings);

        $defaults = [
            'count_all' => 0,
            'count_scheduled' => 0,
            'count_pending' => 0,
            'count_accepted' => 0,
            'count_processing' => 0,
            'count_otw' => 0,
            'count_delivered' => 0,
            'count_canceled' => 0,
            'count_failed' => 0,
            'count_refunded' => 0,
            'count_offline' => 0,
            'count_refund_req' => 0,
            'count_unassigned' => 0,
            'count_ongoing' => 0,
        ];

        foreach ($defaults as $alias => $default) {
            $defaults[$alias] = (int) ($row?->{$alias} ?? $default);
        }

        return $defaults;
    }
}
