<?php

namespace App\Services\Parcel;

use App\Models\ParcelDeliveryInstruction;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ParcelDeliveryInstructionService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return ParcelDeliveryInstruction::where('status', 1)
            ->paginate($this->pageSize($paginate), ['id', 'instruction', 'status'], 'page', $this->pageNumber($paginate));
    }
}
