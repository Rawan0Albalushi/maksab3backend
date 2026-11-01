<?php

namespace App\Services\Payment;

use App\Models\SubscriptionTransaction;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class SubscriptionTransactionService extends BaseService
{
    public function countForStore(mixed $storeId): int
    {
        return SubscriptionTransaction::where('store_id', $storeId)->count();
    }

    public function getStoreList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = array_filter(explode(' ', $filters['search'] ?? ''));

        return SubscriptionTransaction::where('store_id', $filters['store_id'] ?? null)
            ->latest()
            ->with(['store:id,name', 'store.translations', 'package:id,package_name', 'package.translations'])
            ->when($terms !== [], fn ($query) => $query->where(function ($outer) use ($terms) {
                foreach ($terms as $term) {
                    $outer->where('id', 'like', "%{$term}%");
                }
                $outer->orWhereHas('store', function ($inner) use ($terms) {
                    foreach ($terms as $term) {
                        $inner->where('name', 'like', "%{$term}%");
                    }
                });
            }))
            ->when(isset($filters['from'], $filters['to']), fn ($query) => $query
                ->whereBetween('created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:29']))
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function query(): mixed
    {
        return SubscriptionTransaction::query();
    }

}
