<?php

namespace App\Services\Item;

use App\Models\AddOn;
use App\Scopes\StoreScope;
use App\Traits\Item\HasProductTaxablesTrait;
use App\Services\BaseService;
use App\Traits\System\MemoizesLookupsTrait;
use App\Traits\System\TranslationsTrait;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\DB;
use Rap2hpoutre\FastExcel\FastExcel;

class AddonService extends BaseService
{
    use HasProductTaxablesTrait;
    use MemoizesLookupsTrait;
    use TranslationsTrait;

    private const LIST_COLUMNS = [
        'id', 'name', 'price', 'store_id', 'status', 'addon_category_id', 'created_at', 'updated_at',
    ];

    public function getMemoizedActiveByIds(mixed $ids): mixed
    {
        $ids = array_values(array_filter((array) $ids, fn ($id) => is_numeric($id)));

        if (! $ids) {
            return collect();
        }

        $map = $this->memoize('active', fn () => AddOn::withoutGlobalScope(StoreScope::class)->active()->get()->keyBy('id'));

        return $map->only($ids)->values();
    }

    public function getCachedForStore(mixed $storeId = null): mixed
    {
        $admin = auth('admin')->user();
        $zoneKey = ($admin && $admin->role_id != 1 && $admin->zone_id) ? $admin->zone_id : 'all';

        return ApiCache::remember('reference_list', ['store_addons', $storeId ?? 'all', $zoneKey, app()->getLocale()], function () use ($storeId) {
            return AddOn::withoutGlobalScope(StoreScope::class)
                ->when($storeId, function ($query) use ($storeId) {
                    $query->where('store_id', $storeId);
                })
                ->orderBy('name')
                ->get();
        });
    }

    public function getMemoizedNameAndPriceByIds(mixed $ids): array
    {
        $map = $this->memoize('name_and_price', fn () => AddOn::get(['id', 'name', 'price'])->keyBy('id'));

        return $map->only($ids)->values()
            ->map(fn ($row) => ['name' => $row->name, 'price' => $row->price])
            ->all();
    }

    public function getByIdsWithTaxes(array $ids): mixed
    {
        return AddOn::with(['taxVats' => fn ($q) => $q->select('id', 'taxable_id', 'taxable_type', 'tax_id')])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownedQuery($filters)
            ->withoutGlobalScope('translate')
            ->with(['translations', 'taxVats'])
            ->select(self::LIST_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function find(mixed $id, array $filters = []): ?AddOn
    {
        return $this->ownedQuery($filters)->find($id);
    }

    public function create(array $data): AddOn
    {
        return DB::transaction(function () use ($data) {
            $addon = new AddOn;
            $this->fill($addon, $data);
            $addon->store_id = $data['store_id'];
            $addon->save();

            $this->createTaxables($addon, $data['tax_ids'] ?? []);
            $this->insertTranslations($addon, $data['translations'] ?? []);

            return $addon;
        });
    }

    public function update(AddOn $addon, array $data): AddOn
    {
        return DB::transaction(function () use ($addon, $data) {
            $this->fill($addon, $data);
            $addon->save();

            $this->resyncTaxes($addon, $data['tax_ids'] ?? []);
            $this->syncTranslations($addon, $data['translations'] ?? []);

            return $addon;
        });
    }

    public function updateStatus(AddOn $addon, mixed $status): bool
    {
        $addon->status = $status;

        return $addon->save();
    }

    public function delete(AddOn $addon): bool
    {
        return (bool) DB::transaction(function () use ($addon) {
            $addon->translations()->delete();
            $addon->taxVats()->delete();

            return $addon->delete();
        });
    }

    public function getAddData(array $input): array
    {
        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'price' => ($input['price'] ?? null),
            'store_id' => ($input['store_id'] ?? null),
            'addon_category_id' => ($input['category_id'] ?? null),
        ];
    }

    public function getImportData(mixed $file, bool $toAdd = true): array
    {
        try {
            $collections = (new FastExcel)->import($file);
        } catch (Exception) {
            return ['flag' => 'wrong_format'];
        }

        $data = [];
        foreach ($collections as $collection) {
            if ($collection['Name'] === '' || ! is_numeric($collection['StoreId'])) {
                return ['flag' => 'required_fields'];
            }
            if (isset($collection['Price']) && ($collection['Price'] < 0)) {
                return ['flag' => 'price_range'];
            }
            $array = [
                'name' => $collection['Name'],
                'price' => $collection['Price'],
                'store_id' => $collection['StoreId'],
                'status' => $collection['Status'] == 'active' ? 1 : 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! $toAdd) {
                $array['id'] = $collection['Id'];
            }

            $data[] = $array;
        }

        return $data;
    }

    public function getBulkExportData(object $collection): array
    {
        $data = [];
        foreach ($collection as $key => $item) {
            $data[] = [
                'Id' => $item->id,
                'Name' => $item->name,
                'Price' => $item->price,
                'StoreId' => $item->store_id,
                'Status' => $item->status == 1 ? 'active' : 'inactive',
            ];
        }

        return $data;
    }

    public function existsInStore(int|string $storeId, string $addonName): bool
    {
        return AddOn::where('store_id', $storeId)->where('name', $addonName)->exists();
    }

    public function getByIds(array $ids): mixed
    {
        return $this->byIdsQuery($ids)->get();
    }

    // For a store editing its own order: another store's add-ons must not be priced onto it.
    public function getByIdsForStore(array $ids, mixed $storeId): mixed
    {
        return AddOn::withoutGlobalScope(StoreScope::class)->where('store_id', $storeId)->whereIn('id', $ids)->get();
    }

    public function getActiveByIdsWithTaxes(array $ids): mixed
    {
        return $this->byIdsQuery($ids)->active()->with('taxVats')->get()->keyBy('id');
    }

    private function fill(AddOn $addon, array $data): void
    {
        $addon->name = $data['name'];
        $addon->price = $data['price'];
        $addon->addon_category_id = $data['addon_category_id'];
    }

    private function byIdsQuery(array $ids): mixed
    {
        return AddOn::whereIn('id', $ids);
    }

    private function ownedQuery(array $filters): Builder
    {
        return AddOn::withoutGlobalScope(StoreScope::class)
            ->when(isset($filters['store_id']), fn ($query) => $query->where('store_id', $filters['store_id']));
    }

    private function resyncTaxes(AddOn $addon, array $taxIds): void
    {
        if (! addon_published_status('TaxModule') || ! $taxIds) {
            return;
        }

        $existing = $addon->taxVats()->pluck('tax_id')->toArray() ?? [];
        $incoming = array_map('intval', $taxIds);
        sort($incoming);
        sort($existing);

        if ($incoming === $existing) {
            return;
        }

        $addon->taxVats()->delete();
        $this->createTaxables($addon, $taxIds);
    }
}
