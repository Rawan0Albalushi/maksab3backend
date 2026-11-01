<?php

namespace App\Services\Payment;

use App\Models\AccountTransaction;
use App\Services\BaseService;
use App\Traits\Payment\CashCollectionTrait;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountTransactionService extends BaseService
{
    use CashCollectionTrait;

    public function getDeliveryManCollectedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->collectedList('deliveryman', $filters['delivery_man_id'] ?? null, $filters, $paginate);
    }

    public function getVendorCollectedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->collectedList('store', $filters['vendor_id'] ?? null, $filters, $paginate);
    }

    private function collectedList(string $owner, mixed $ownerId, array $filters, array $paginate): LengthAwarePaginator
    {
        $terms = isset($filters['search']) ? explode(' ', (string) $filters['search']) : [];

        return AccountTransaction::when($terms !== [], fn ($query) => $query->where(function ($inner) use ($terms) {
            foreach ($terms as $term) {
                $inner->orWhere('ref', 'like', "%{$term}%");
            }
        }))
            ->where('type', 'collected')
            ->where('created_by', $owner)
            ->where('from_id', $ownerId)
            ->where('from_type', $owner)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
