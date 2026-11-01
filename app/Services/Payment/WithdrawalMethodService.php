<?php

namespace App\Services\Payment;

use App\Models\WithdrawalMethod;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class WithdrawalMethodService extends BaseService
{
    public function find(mixed $id): ?WithdrawalMethod
    {
        return WithdrawalMethod::find($id);
    }

    public function getActiveList(array $paginate = []): LengthAwarePaginator
    {
        return WithdrawalMethod::where('is_active', 1)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
