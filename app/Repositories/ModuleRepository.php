<?php

namespace App\Repositories;

use App\Contracts\Repositories\ModuleRepositoryInterface;
use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use App\Support\Storage\FileStorage;

class ModuleRepository implements ModuleRepositoryInterface
{
    public function __construct(protected Module $module)
    {
    }

    public function add(array $data): string|object
    {
        $module = $this->module->newInstance();
        foreach ($data as $key => $column) {
            $module[$key] = $column;
        }
        $module->save();
        return $module;
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->module->with($relations)->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        return $this->module->with($relations)->get();
    }

    public function getListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');

        $countRelations = array_unique(array_map(fn ($relation) => explode('.', $relation)[0], $relations));

        return $this->module->with($relations)->withCount($countRelations)->where($filters)
            ->when($searchValue , function($q) use($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('module_name', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->paginate($dataLimit);
    }

    public function update(string $id, array $data): bool|string|object
    {
        $module = $this->module->find($id);
        foreach ($data as $key => $column) {
            $module[$key] = $column;
        }
        $module->save();
        return $module;
    }

    public function delete(string $id): bool
    {
        $module = $this->module->find($id);
        if($module->thumbnail)
        {
       
            FileStorage::delete('module/' , $module['thumbnail']);
            
        }
        $module->translations()->delete();
        $module->delete();

        return true;
    }

    public function getExportList(Request $request): Collection
    {
        $key = explode(' ', $request['search'] ?? '');
        return $this->module->withCount('stores')->
        when($request['search'] , function($q) use($key){
            $q->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('module_name', 'like', "%{$value}%");
                }
            });
        })
        // The list screen hands its query string to the export links, so the file
        // has to honour the pickers the admin is looking at, not just the search.
        ->when($request['module_type'] && $request['module_type'] != 'all', function($q) use($request){
            $q->where('module_type', $request['module_type']);
        })
        ->when($request['status'] !== null && $request['status'] !== '' && $request['status'] != 'all', function($q) use($request){
            $q->where('status', $request['status']);
        })
        ->get();
    }

    /*
     * One grouped row for the list screen's summary strip, so the four tiles
     * cost a single query instead of four counts. `toBase()` is safe here: the
     * only global scope on Module is `translate`, which adds an eager load the
     * base query never runs.
     */
    public function getStatusSummary(array $filters = []): array
    {
        $row = $this->module->where($filters)
            ->selectRaw('COUNT(*) AS total_count, '
                .'SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) AS active_count, '
                .'COUNT(DISTINCT module_type) AS type_count')
            ->toBase()->first();

        $total = (int) ($row->total_count ?? 0);
        $active = (int) ($row->active_count ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'types' => (int) ($row->type_count ?? 0),
        ];
    }

    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model
    {
        return $this->module->with($relations)->withoutGlobalScope('translate')->with(['translations', 'storage'])->where($params)->first();
    }

    public function getSearchListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection
    {
        $key = explode(' ', $searchValue ?? '');

        return $this->module->with($relations)->when($searchValue , function($q) use($key){
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('module_name', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()->limit($dataLimit)->get();
    }
}
