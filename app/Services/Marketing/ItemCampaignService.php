<?php

namespace App\Services\Marketing;

use App\Traits\System\PrioritySettingsTrait;
use App\Models\ItemCampaign;
use App\Services\BaseService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Traits\Customer\PersonalizationTrait;
use App\Services\System\BusinessSettingService;

class ItemCampaignService extends BaseService
{
    use PersonalizationTrait, PrioritySettingsTrait;
    private const DEFAULT_SORT = 1;
    private const LIST_COLUMNS = [
        'item_campaigns.id', 'item_campaigns.title', 'item_campaigns.description', 'item_campaigns.slug',
        'item_campaigns.image', 'item_campaigns.price', 'item_campaigns.discount', 'item_campaigns.discount_type',
        'item_campaigns.stock', 'item_campaigns.veg',
        'item_campaigns.maximum_cart_quantity', 'item_campaigns.category_id', 'item_campaigns.category_ids',
        'item_campaigns.unit_id', 'item_campaigns.module_id',
        'item_campaigns.store_id', 'item_campaigns.start_date', 'item_campaigns.end_date',
        'item_campaigns.start_time', 'item_campaigns.end_time',
    ];
    public function getByIdsWithStorage(array $ids): mixed
    {
        return $this->byIdsQuery($ids, ['storage'], withoutTranslate: true)->get()->keyBy('id');
    }
    public function findActiveDetail(mixed $identifier): mixed
    {
        return ItemCampaign::active()
            ->when(config('module.current_module_data'), function ($query) {
                $query->module(config('module.current_module_data')['id']);
            })
            ->when(is_numeric($identifier), fn ($query) => $query->where('id', $identifier))
            ->when(! is_numeric($identifier), fn ($query) => $query->where('slug', $identifier))
            ->first();
    }
    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildQuery($filters)
            ->with($this->relations() + $with)
            ->withCount($withCount)
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }
    public function findActiveWithModule(mixed $id): ?ItemCampaign
    {
        return $this->find($id, ['module'], true);
    }
    public function getByIdsWithModule(array $ids, bool $activeOnly = false): array
    {
        return $this->byIdsQuery($ids, ['module'], $activeOnly)
            ->get()
            ->keyBy('id')
            ->all();
    }
    public function find(mixed $id, array $relations = [], bool $active = false): ?ItemCampaign
    {
        return ItemCampaign::with($relations)
            ->when($active, fn ($query) => $query->active())
            ->find($id);
    }
    public function getRunningForStores(array $storeIds, array $columns): mixed
    {
        return ItemCampaign::whereIn('store_id', $storeIds)->active()->running()->get($columns);
    }
    private function byIdsQuery(array $ids, array $relations = [], bool $activeOnly = false, bool $withoutTranslate = false): mixed
    {
        return ItemCampaign::when($withoutTranslate, fn ($query) => $query->withoutGlobalScope('translate'))
            ->with($relations)
            ->when($activeOnly, fn ($query) => $query->active())
            ->whereIn('id', $ids);
    }
    private function buildQuery(array $filters): Builder
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        $query = ItemCampaign::translateOnly(['title', 'description'])
            ->active()
            ->whereHas('module.zones', fn ($q) => $q->whereIn('zones.id', $zoneIds))
            ->whereHas('store', function ($q) use ($zoneIds, $moduleId) {
                $q->active()
                    ->when($moduleId, function ($q) use ($moduleId) {
                        $q->where('module_id', $moduleId)
                            ->whereHas('zone.modules', fn ($z) => $z->where('modules.id', $moduleId));
                    })
                    ->whereIn('zone_id', $zoneIds);
            })
            ->running();

        return $this->applySort($query, $filters);
    }
    private function applySort(Builder $query, array $filters): Builder
    {
        if ((app(BusinessSettingService::class)->value('item_campaign_default_status') ?? self::DEFAULT_SORT) == self::DEFAULT_SORT) {
            return $this->applyCampaignPersonalization($query, $filters['customer_id'] ?? null)->latest();
        }

        return match ($this->findPrioritySetting(name: 'item_campaign_sort_by_general', type: 'general')) {
            'order_count' => $query->withCount([
                'orderdetails' => fn ($q) => $q->whereHas(
                    'order',
                    fn ($o) => $o->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
                ),
            ])->orderByDesc('orderdetails_count'),
            'a_to_z' => $query->orderBy('title'),
            'z_to_a' => $query->orderByDesc('title'),
            'end_first' => $query->orderBy('end_date'),
            'latest_created' => $query->latest(),
            default => $query,
        };
    }
    private function relations(): array
    {
        return [
            'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
            'unit' => fn ($query) => $query->translateOnly(['unit'])->select('id', 'unit'),
            'module' => fn ($query) => $query->withoutGlobalScope('translate')->select('id', 'module_type'),
            'store' => fn ($query) => $query->translateOnly(['name'])
                ->select('id', 'name', 'slug', 'logo', 'module_id', 'zone_id', 'delivery_time', 'free_delivery', 'schedule_order')
                ->with([
                    'storage' => fn ($q) => $q->select(STORAGE_RELATION_COLUMNS),
                    'storeConfig' => fn ($q) => $q->select('store_id', 'verified_seller', 'halal_tag_status'),
                    'discount' => fn ($q) => $q->validate(),
                ]),
        ];
    }
}
