<?php

namespace App\Repositories;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Http\Requests\Admin\CategoryBulkExportRequest;
use App\Models\Category;
use App\Observers\CategoryObserver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CategoryRepository implements CategoryRepositoryInterface
{

    public function __construct(protected Category $category)
    {
    }

    public function add(array $data): string|object
    {
        $category = $this->category->newInstance();
        foreach ($data as $key => $column) {
            $category[$key] = $column;
        }
        $category->save();
        return $category;
    }

    public function addByChunk(array $data): void
    {
        $chunkSize = 100;
        $chunkCategories = array_chunk($data, $chunkSize);

        foreach ($chunkCategories as $key => $chunkCategory) {
            foreach ($chunkCategory as $category) {
                $insertedId = DB::table('categories')->insertGetId($category);
                Helpers::updateStorageTable(get_class(new Category), $insertedId, $category['image']);
            }
        }
    }

    public function updateByChunk(array $data): void
    {
        $chunkSize = 100;
        $chunkCategories = array_chunk($data, $chunkSize);

        foreach ($chunkCategories as $key => $chunkCategory) {
            $syncCategoryIds = [];
            foreach ($chunkCategory as $category) {
                if (isset($category['id']) && DB::table('categories')->where('id', $category['id'])->exists()) {
                    DB::table('categories')->where('id', $category['id'])->update($category);
                    $syncCategoryIds[] = $category['id'];
                    Helpers::updateStorageTable(get_class(new Category), $category['id'], $category['image']);
                } else {
                    $insertedId = DB::table('categories')->insertGetId($category);
                    Helpers::updateStorageTable(get_class(new Category), $insertedId, $category['image']);
                }
            }
            // DB::table() writes fire no model events, so CategoryObserver did not run; an
            // imported parent_id change would otherwise leave the items rolled up to the old parent.
            CategoryObserver::syncItemTopCategories($syncCategoryIds);
        }
    }

    public function getFirstWhere(array $params, array $relations = []): ?Model
    {
        return $this->category->with($relations)->where($params)->first();
    }

    public function getFirstWithoutGlobalScopeWhere(array $params, array $relations = []): ?Model
    {
        return $this->category->with($relations)->withoutGlobalScope('translate')->with(['translations', 'storage'])->where($params)->first();
    }

    public function getList(array $orderBy = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null): Collection|LengthAwarePaginator
    {
        return $this->category->with($relations)->get();
    }

    public function getBulkExportList(CategoryBulkExportRequest $request): Collection
    {
        return $this->category->when($request['type'] == 'date_wise', function ($query) use ($request) {
            $query->whereBetween('created_at', [$request->from_date . ' 00:00:00', $request->to_date . ' 23:59:59']);
        })->when($request->type == 'id_wise', function ($query) use ($request) {
            $query->whereBetween('id', [$request->start_id, $request->end_id]);
        })->module(Config::get('module.current_module_id'))->get();
    }

    public function getBulkDataSummary(): array
    {
        // newQuery() first: on the model instance `module` resolves to the
        // belongsTo relation, not scopeModule, and the aggregate would run
        // against the modules table.
        $summary = $this->category->newQuery()
            ->module(Config::get('module.current_module_id'))
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN position = 0 THEN 1 ELSE 0 END) as parent_count')
            ->selectRaw('SUM(CASE WHEN position = 1 THEN 1 ELSE 0 END) as sub_count')
            ->selectRaw('MIN(id) as min_id, MAX(id) as max_id')
            ->selectRaw('MIN(created_at) as first_created_at, MAX(created_at) as last_created_at')
            ->first();

        return [
            'total' => (int) ($summary?->total ?? 0),
            'parent_count' => (int) ($summary?->parent_count ?? 0),
            'sub_count' => (int) ($summary?->sub_count ?? 0),
            'min_id' => $summary?->min_id,
            'max_id' => $summary?->max_id,
            'first_created_at' => $summary?->first_created_at,
            'last_created_at' => $summary?->last_created_at,
        ];
    }

    public function getExportList(Request $request): Collection
    {
        $position=$request->position ?? 0;
        $key = explode(' ', $request['search'] ?? '');
        return $this->category->with(['module', 'taxVats.tax'])->where(['position' => $position])->module(Config::get('module.current_module_id'))
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->latest()
            ->get();
    }

    public function getListWhere(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null, bool $withStorage = true): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');
        return $this->category->with($relations)
            ->when($withStorage, fn ($query) => $query->withStorage())
            ->where($filters)->module(Config::get('module.current_module_id'))
            ->when($searchValue, function ($query) use ($key) {
                $query->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })->latest()->paginate($dataLimit);
    }

    public function getNameList(Request $request, int|string $dataLimit = DEFAULT_DATA_LIMIT): SupportCollection|LengthAwarePaginator
    {
        $search = $request->q ?? $request->searchValue;
        return $this->category->where('name', 'like', '%' . $search. '%')
            ->when($request->module_id, function ($query) use ($request) {
                $query->where('module_id', $request->module_id);
            })
            ->when($request->position == 1, function ($query) {
                $query->where('position', 1);
            })
            ->when($request->position != null && $request->position == 0, function ($query) {
                $query->where('position', 0);
            })
            ->limit($dataLimit)->get()
            ->map(function ($category) {
                $data = $category->position == 0 ? translate('messages.main') : translate('messages.sub');
                return [
                    'id' => $category->id,
                    'text' => $category->name . ' (' . $data . ')',
                ];
            });
    }

    public function getMainList(?string $searchValue = null, array $filters = [], array $relations = [], int|string $dataLimit = DEFAULT_DATA_LIMIT, ?int $offset = null, bool $withStorage = true): Collection|LengthAwarePaginator
    {
        $key = explode(' ', $searchValue ?? '');
        return $this->category->with($relations)
            ->when($withStorage, fn ($query) => $query->withStorage())
            ->where($filters)->module(Config::get('module.current_module_id'))
            ->when($searchValue, function ($query) use ($key) {
                $query->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })->latest()->get();
    }


    public function update(string $id, array $data): string|object
    {
        $category = $this->category->find($id);
        foreach ($data as $key => $column) {
            $category[$key] = $column;
        }
        $category->save();
        return $category;
    }

    public function delete(string $id): bool
    {
        $category = $this->category->find($id);
        if ($category->childes->count() == 0) {
            $category?->taxVats()->delete();
            $category->translations()->delete();
            $category->delete();
        } else {
            return false;
        }
        return true;
    }
}
