<?php

namespace App\Services\Customer;

use App\Models\VisitorLog;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class VisitorLogService extends BaseService
{
    private const TYPES = [
        'item' => 'App\Models\Item',
        'store' => 'App\Models\Store',
    ];

    public function record(string $model, mixed $userId, mixed $visitorLogId, bool $orderCount = false): void
    {
        VisitorLog::updateOrInsert(
            [
                'visitor_log_type' => self::TYPES[$model] ?? self::TYPES['item'],
                'user_id' => $userId,
                'visitor_log_id' => $visitorLogId,
            ],
            [
                'visit_count' => $orderCount ? DB::raw('visit_count') : DB::raw('visit_count + 1'),
                'order_count' => $orderCount ? DB::raw('order_count + 1') : DB::raw('order_count'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
