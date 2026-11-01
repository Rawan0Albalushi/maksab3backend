<?php

namespace App\Services\Parcel;

use App\Models\ParcelCancellationReason;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ParcelCancellationReasonService extends BaseService
{
    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return ParcelCancellationReason::where('status', 1)
            ->select('id', 'reason')
            ->when($filters['user_type'] ?? null, fn ($query, $userType) => $query->where('user_type', $userType))
            ->when($filters['cancellation_type'] ?? null, fn ($query, $type) => $query->where('cancellation_type', $type))
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
