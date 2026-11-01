<?php

namespace App\Services\Payment;

use App\Models\OfflinePaymentMethod;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class OfflinePaymentMethodService extends BaseService
{
    public function findActive(mixed $id): mixed
    {
        return OfflinePaymentMethod::where(['id' => $id, 'status' => 1])->first();
    }

    public function find(mixed $id): mixed
    {
        return OfflinePaymentMethod::find($id);
    }

    public function getActiveList(
        array $paginate = []
    ): LengthAwarePaginator {
        return OfflinePaymentMethod::where('status', 1)
            ->select('id', 'method_name', 'method_fields', 'method_informations')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
