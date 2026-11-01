<?php

namespace App\Services\Payment;

use App\Models\DisbursementDetails;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\System\BusinessSettingService;

class DisbursementDetailService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('delivery_man_id', $filters['delivery_man_id'] ?? null, ['delivery_man.storage', 'withdraw_method'], $paginate);
    }
    public function getStoreList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('store_id', $filters['store_id'] ?? null, ['store', 'store.translations', 'store.storage', 'withdraw_method'], $paginate);
    }
    public function storeStatusTotals(array $filters = []): array
    {
        return $this->getTotals('store_id', $filters['store_id'] ?? null);
    }
    public function statusTotals(array $filters = []): array
    {
        return $this->getTotals('delivery_man_id', $filters['delivery_man_id'] ?? null);
    }
    public function storeWaitingDays(): int
    {
        return (int) app(BusinessSettingService::class)->value('store_disbursement_waiting_time', false);
    }
    public function normalizeMethodFields(mixed $rows): mixed
    {
        $rows->each(function ($row) {
            if ($row->withdraw_method?->method_fields && ! is_array($row->withdraw_method->method_fields)) {
                $row->withdraw_method->method_fields = json_decode($row->withdraw_method->method_fields, true);
            }
        });

        return $rows;
    }
    private function ownerList(string $ownerColumn, mixed $ownerId, array $relations, array $paginate): LengthAwarePaginator
    {
        return DisbursementDetails::with($relations)
            ->where($ownerColumn, $ownerId)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    private function getTotals(string $ownerColumn, mixed $ownerId): array
    {
        $row = DisbursementDetails::where($ownerColumn, $ownerId)
            ->toBase()
            ->selectRaw("
                sum(case when status = 'pending' then disbursement_amount else 0 end) as pending_total,
                sum(case when status = 'completed' then disbursement_amount else 0 end) as completed_total,
                sum(case when status = 'canceled' then disbursement_amount else 0 end) as canceled_total
            ")
            ->first();

        return [
            'pending' => (float) ($row->pending_total ?? 0),
            'completed' => (float) ($row->completed_total ?? 0),
            'canceled' => (float) ($row->canceled_total ?? 0),
        ];
    }
}
