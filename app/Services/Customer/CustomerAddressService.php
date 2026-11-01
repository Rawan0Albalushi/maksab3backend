<?php

namespace App\Services\Customer;

use App\Models\CustomerAddress;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerAddressService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return CustomerAddress::where('user_id', $filters['user_id'] ?? null)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function create(array $data): CustomerAddress
    {
        $address = new CustomerAddress;
        $address->fill($data)->save();

        return $address;
    }

    public function update(mixed $id, array $data, mixed $userId): ?CustomerAddress
    {
        $address = CustomerAddress::where('id', $id)->where('user_id', $userId)->first();

        if (! $address) {
            return null;
        }

        $address->fill($data)->save();

        return $address;
    }

    public function delete(mixed $id, mixed $userId): bool
    {
        return (bool) CustomerAddress::where('id', $id)->where('user_id', $userId)->delete();
    }
}
