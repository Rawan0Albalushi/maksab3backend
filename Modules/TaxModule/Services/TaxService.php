<?php

namespace Modules\TaxModule\Services;

use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\TaxModule\Entities\Tax;

class TaxService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Tax::where('is_active', 1)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getActiveRates(array $taxIds): mixed
    {
        return Tax::whereIn('id', $taxIds)->where('is_active', 1)->select('id', 'name', 'tax_rate')->get();
    }


    public function getByIds(array $ids): mixed
    {
        return Tax::whereIn('id', $ids)->get(['id', 'name', 'tax_rate'])->keyBy('id');
    }

}
