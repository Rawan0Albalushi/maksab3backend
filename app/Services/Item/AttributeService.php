<?php

namespace App\Services\Item;

use App\Models\Attribute;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttributeService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'name', 'created_at', 'updated_at'];

    public function getList(array $paginate = []): LengthAwarePaginator
    {
        return Attribute::query()
            ->select(self::LIST_COLUMNS)
            ->orderBy('name')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getAddData(array $input): array
    {
        return [
            'name' => ($input['name'] ?? null)[array_search('default', ($input['lang'] ?? null))],
        ];
    }

    public function getUsageStats(array $attributeIds): array
    {
        $stats = array_fill_keys($attributeIds, ['items' => 0, 'stores' => 0]);

        if (empty($attributeIds)) {
            return $stats;
        }

        $selects = [];
        $bindings = [];
        foreach ($attributeIds as $id) {
            $match = 'JSON_VALID(`attributes`) AND JSON_CONTAINS(`attributes`, ?)';
            $selects[] = 'SUM(CASE WHEN '.$match.' THEN 1 ELSE 0 END) AS `items_'.(int) $id.'`';
            $bindings[] = json_encode((string) $id);
            $selects[] = 'COUNT(DISTINCT CASE WHEN '.$match.' THEN `store_id` END) AS `stores_'.(int) $id.'`';
            $bindings[] = json_encode((string) $id);
        }

        $row = DB::table('items')
            ->whereNotNull('attributes')
            ->where('attributes', '<>', '[]')
            ->selectRaw(implode(', ', $selects), $bindings)
            ->first();

        foreach ($attributeIds as $id) {
            $stats[$id] = [
                'items' => (int) ($row?->{'items_'.(int) $id} ?? 0),
                'stores' => (int) ($row?->{'stores_'.(int) $id} ?? 0),
            ];
        }

        return $stats;
    }

    public function getTranslatedLocales(array $attributeIds): array
    {
        if (empty($attributeIds)) {
            return [];
        }

        return DB::table('translations')
            ->where('translationable_type', Attribute::class)
            ->whereIn('translationable_id', $attributeIds)
            ->where('key', 'name')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('locale')
            ->get(['translationable_id', 'locale'])
            ->groupBy('translationable_id')
            ->map(fn ($rows) => $rows->pluck('locale')->unique()->values()->all())
            ->all();
    }
}
