<?php

namespace App\Repositories;

use App\Contracts\Repositories\UnitRepositoryInterface;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class UnitRepository implements UnitRepositoryInterface
{
    public function __construct(protected Unit $unit)
    {
    }

    public function add(array $data): string|object
    {
        $unit = $this->unit->newInstance();
        foreach ($data as $key => $column) {
            $unit[$key] = $column;
        }
        $unit->save();
        return $unit;
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->unit->with($relations)->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $dataLimit = is_string($dataLimit) ? (int)$dataLimit : $dataLimit;
        return $this->unit->with($relations)->paginate($dataLimit);
    }

    public function getListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');
        return $this->unit->with($relations)->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('unit', 'like', "%{$value}%");
            }
        })->latest('id')->paginate($dataLimit);
    }
    public function getExportList($request): Collection
    {
        $key = explode(' ', $request['search'] ?? '');
        return $this->unit
        ->when($request['search'] , function($q) use($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                       $q->orWhere('unit', 'like', "%{$value}%");
                    }
                });
            })
        ->latest('id')
        ->get();
    }

    public function update(string $id, array $data): bool|string|object
    {
        $unit = $this->unit->find($id);
        foreach ($data as $key => $column) {
            $unit[$key] = $column;
        }
        $unit->save();
        return $unit;
    }

    public function delete(string $id): bool
    {
        $unit = $this->unit->find($id);
        $unit->translations()->delete();
        $unit->delete();

        return true;
    }

    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model
    {
        return $this->unit->with($relations)->withoutGlobalScope('translate')->with('translations')->where($params)->first();
    }
}
