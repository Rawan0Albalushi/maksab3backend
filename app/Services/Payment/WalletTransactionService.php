<?php

namespace App\Services\Payment;

use App\Models\WalletTransaction;
use App\Services\BaseService;
use App\Traits\Payment\CustomerTransactionsTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletTransactionService extends BaseService
{
    use CustomerTransactionsTrait;

    private const LIST_COLUMNS = [
        'id', 'user_id', 'credit', 'debit', 'admin_bonus', 'transaction_type', 'reference', 'created_at',
    ];

    private const TYPE_GROUPS = [
        'order' => ['order_place', 'order_refund', 'partial_payment', 'service_booking'],
        'loyalty_point' => ['loyalty_point'],
        'add_fund' => ['add_fund'],
        'referrer' => ['referrer'],
        'CashBack' => ['CashBack'],
        'pro_subscription' => ['pro_subscription'],
    ];

    public function referenceExists(mixed $reference): bool
    {
        return WalletTransaction::where('reference', $reference)->exists();
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;

        return WalletTransaction::where('user_id', $filters['user_id'] ?? null)
            ->when($type && isset(self::TYPE_GROUPS[$type]), fn ($query) => $query->whereIn('transaction_type', self::TYPE_GROUPS[$type]))
            ->latest()
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
