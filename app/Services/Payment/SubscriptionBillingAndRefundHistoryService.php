<?php

namespace App\Services\Payment;

use App\Models\SubscriptionBillingAndRefundHistory;
use App\Services\BaseService;

class SubscriptionBillingAndRefundHistoryService extends BaseService
{
    public function pendingBillTotal(mixed $storeId): float
    {
        return (float) SubscriptionBillingAndRefundHistory::where([
            'store_id' => $storeId,
            'transaction_type' => 'pending_bill',
            'is_success' => 0,
        ])->sum('amount');
    }
}
