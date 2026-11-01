<?php

namespace App\Services\Payment;

use App\Models\DisbursementWithdrawalMethod;
use App\Services\BaseService;
use App\Traits\Payment\WithdrawalMethodFieldsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Payment\WithdrawalMethodService;

class DisbursementWithdrawalMethodService extends BaseService
{
    use WithdrawalMethodFieldsTrait;

    private const OWNER_COLUMNS = ['delivery_man_id', 'store_id'];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = explode(' ', $filters['search'] ?? '');

        return $this->applyOwnerFilter(DisbursementWithdrawalMethod::query(), $filters)
            ->when($filters['active_methods_only'] ?? false,
                fn ($query) => $query->whereHas('withdraw_method', fn ($inner) => $inner->where('is_active', 1)))
            ->when($filters['search'] ?? null, fn ($query) => $query->where(function ($inner) use ($terms) {
                foreach ($terms as $term) {
                    $inner->orWhere('method_name', 'like', "%{$term}%");
                }
            }))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function create(array $data): ?DisbursementWithdrawalMethod
    {
        [$ownerColumn, $ownerId] = $this->owner($data);
        $method = app(WithdrawalMethodService::class)->find($data['withdraw_method_id'] ?? null);

        if (! $method || $ownerId === null) {
            return null;
        }

        $disbursementMethod = DisbursementWithdrawalMethod::firstOrNew(['id' => $data['disbursement_withdrawal_method_id'] ?? null]);
        $disbursementMethod->{$ownerColumn} = $ownerId;
        $disbursementMethod->withdrawal_method_id = $method['id'];
        $disbursementMethod->method_name = $method['method_name'];
        $disbursementMethod->method_fields = json_encode($this->withdrawalMethodFieldValues($method, $data['fields'] ?? []));
        $disbursementMethod->is_default = $disbursementMethod->exists ? $disbursementMethod->is_default : 0;
        $disbursementMethod->save();

        return $disbursementMethod;
    }

    public function makeDefault(mixed $id, array $filters = [], mixed $isDefault = 1): ?DisbursementWithdrawalMethod
    {
        $method = $this->findOwned($id, $filters);

        if (! $method) {
            return null;
        }

        $method->is_default = $isDefault;
        $method->save();

        $this->applyOwnerFilter(DisbursementWithdrawalMethod::query(), $filters)
            ->whereNot('id', $id)
            ->update(['is_default' => 0]);

        return $method;
    }

    public function delete(mixed $id, array $filters = []): bool
    {
        $method = $this->findOwned($id, $filters);

        return $method ? (bool) $method->delete() : false;
    }

    private function findOwned(mixed $id, array $filters): ?DisbursementWithdrawalMethod
    {
        return $this->applyOwnerFilter(DisbursementWithdrawalMethod::query(), $filters)->find($id);
    }

    private function applyOwnerFilter(Builder $query, array $filters): Builder
    {
        [$ownerColumn, $ownerId] = $this->owner($filters);

        return $ownerId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($ownerColumn, $ownerId);
    }

    private function owner(array $filters): array
    {
        foreach (self::OWNER_COLUMNS as $column) {
            if (array_key_exists($column, $filters)) {
                return [$column, $filters[$column]];
            }
        }

        return [self::OWNER_COLUMNS[0], null];
    }
}
