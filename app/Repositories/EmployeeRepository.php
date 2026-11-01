<?php

namespace App\Repositories;

use App\Contracts\Repositories\EmployeeRepositoryInterface;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(protected Admin $employee)
    {
    }

    public function add(array $data): string|object
    {
        $employee = $this->employee->newInstance();
        foreach ($data as $key => $column) {
            $employee[$key] = $column;
        }
        $employee->save();
        return $employee;
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->employee->with($relations)->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        return $this->employee->with($relations)->get();
    }

    public function getListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');

        return $this->employee->with($relations)->zone()->where('role_id', '!=','1')
        ->where($filters)
            ->when($searchValue, function ($query) use ($key) {
                $query->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('f_name', 'like', "%{$value}%");
                        $query->orWhere('l_name', 'like', "%{$value}%");
                        $query->orWhere('phone', 'like', "%{$value}%");
                        $query->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })->latest()->paginate($dataLimit);
    }

    public function getZoneWiseListWhere(string $zoneId = 'all', ?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');
        return $this->employee->zone()->where('role_id', '!=','1')->with($relations)
        ->where($filters)
        ->when(is_numeric($zoneId), function($query) use($zoneId){
            return $query->where('zone_id', $zoneId);
        })
            ->when($searchValue, function ($query) use ($key) {
                $query->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('f_name', 'like', "%{$value}%");
                        $query->orWhere('l_name', 'like', "%{$value}%");
                        $query->orWhere('phone', 'like', "%{$value}%");
                        $query->orWhere('email', 'like', "%{$value}%");
                    }
                });
            })->latest()->paginate($dataLimit);
    }

    public function update(string $id, array $data): bool|string|object
    {
        $employee = $this->employee->find($id);
        foreach ($data as $key => $column) {
            $employee[$key] = $column;
        }
        $employee->save();
        return $employee;
    }

    public function delete(string $id): bool
    {
        $employee = $this->employee->zone()->where('role_id', '!=','1')->find($id);
        $employee->delete();

        return true;
    }

    public function getSearchList(Request $request): Collection
    {
        $key = explode(' ', $request['search'] ?? '');
        return $this->employee->zone()->where('role_id', '!=','1')->with(['role', 'zones:id,name', 'storage'])
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%");
                    $q->orWhere('l_name', 'like', "%{$value}%");
                    $q->orWhere('phone', 'like', "%{$value}%");
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            })->limit(50)->get();
    }

    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model
    {
        // Was a verbatim copy of CustomRoleRepository's version of this method, which is correct
        // for AdminRole and wrong for Admin in three ways: `admin_roles` has `name` and `modules`
        // columns and a `translations` relation, `admins` has none of them. It threw on the
        // relation and, past that, on `Unknown column 'name'` — it could never have returned a
        // row. No caller today, which is the only reason nobody noticed.
        //
        // The column list is dropped rather than guessed at: every other read on this repository
        // returns the whole model, and inventing a subset for a method with no caller would be
        // picking an intent that was never expressed.
        return $this->employee->with($relations)->where($params)->first();
    }

    public function getFirstWhereExceptAdmin(array $params, array $relations = []): ?Model
    {
        return $this->employee->with($relations)->zone()->where('role_id', '!=','1')->where($params)->first();
    }

    public function getExportList(Request $request): Collection
    {
        $key = explode(' ', $request['search'] ?? '');
        return $this->employee->with(['role', 'zones'])->zone()->where('role_id', '!=','1')
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%");
                    $q->orWhere('l_name', 'like', "%{$value}%");
                    $q->orWhere('phone', 'like', "%{$value}%");
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            })->latest()->get();
    }
}
