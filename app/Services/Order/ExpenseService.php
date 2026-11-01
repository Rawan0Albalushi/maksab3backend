<?php

namespace App\Services\Order;

use App\Models\Expense;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ExpenseService extends BaseService
{
    public function create(array $data): bool
    {
        $expense = new Expense;
        $expense->amount = $data['amount'] ?? null;
        $expense->type = $data['type'] ?? null;
        $expense->order_id = $data['order_id'] ?? null;
        $expense->created_by = $data['created_by'] ?? null;
        $expense->store_id = $data['store_id'] ?? null;
        $expense->delivery_man_id = $data['delivery_man_id'] ?? null;
        $expense->user_id = $data['user_id'] ?? null;
        $expense->description = $data['description'] ?? '';
        $expense->ride_id = $data['ride_id'] ?? null;
        $expense->created_at = now();
        $expense->updated_at = now();

        return $expense->save();
    }

    public function getStoreList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = array_filter(explode(' ', $filters['search'] ?? ''));

        return Expense::with(['order:id,user_id,is_guest,delivery_address', 'order.customer:id,f_name,l_name'])
            ->where('created_by', 'vendor')
            ->notRefunded()
            ->where('store_id', $filters['store_id'] ?? null)
            ->where('amount', '>', 0)
            ->when(isset($filters['from'], $filters['to']), fn ($query) => $query
                ->whereBetween('created_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:29']))
            ->when($terms !== [], fn ($query) => $query->where(function ($inner) use ($terms) {
                foreach ($terms as $term) {
                    $inner->orWhere('order_id', 'like', "%{$term}%");
                }
            }))
            ->orderBy('created_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function queryWithoutAddon(): mixed
    {
        return Expense::withoutAddon();
    }
}
