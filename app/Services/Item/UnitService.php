<?php

namespace App\Services\Item;

use App\Models\Unit;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UnitService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'unit', 'created_at', 'updated_at'];

    public function getList(array $paginate = []): LengthAwarePaginator
    {
        return Unit::query()
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getAddData(array $input): array
    {
        return [
            'unit' => ($input['unit'] ?? null)[array_search('default', ($input['lang'] ?? null))],
        ];
    }

    public function getUsageStats(array $unitIds): array
    {
        $stats = array_fill_keys($unitIds, ['items' => 0, 'stores' => 0]);

        if (empty($unitIds)) {
            return $stats;
        }

        $totals = DB::table('items')
            ->whereIn('unit_id', $unitIds)
            ->select('unit_id', DB::raw('COUNT(*) AS items_total'), DB::raw('COUNT(DISTINCT store_id) AS stores_total'))
            ->groupBy('unit_id')
            ->get();

        foreach ($totals as $total) {
            $stats[$total->unit_id] = [
                'items' => (int) $total->items_total,
                'stores' => (int) $total->stores_total,
            ];
        }

        return $stats;
    }

    public function getModuleUsage(array $unitIds): array
    {
        if (empty($unitIds)) {
            return [];
        }

        return DB::table('items')
            ->join('modules', 'modules.id', '=', 'items.module_id')
            ->whereIn('items.unit_id', $unitIds)
            ->distinct()
            ->orderBy('modules.module_name')
            ->get(['items.unit_id', 'modules.module_name'])
            ->groupBy('unit_id')
            ->map(fn ($rows) => $rows->pluck('module_name')->unique()->values()->all())
            ->all();
    }

    public function getTranslatedLocales(array $unitIds): array
    {
        if (empty($unitIds)) {
            return [];
        }

        return DB::table('translations')
            ->where('translationable_type', Unit::class)
            ->whereIn('translationable_id', $unitIds)
            ->where('key', 'unit')
            ->whereNotNull('value')
            ->where('value', '<>', '')
            ->orderBy('locale')
            ->get(['translationable_id', 'locale'])
            ->groupBy('translationable_id')
            ->map(fn ($rows) => $rows->pluck('locale')->unique()->values()->all())
            ->all();
    }
}
