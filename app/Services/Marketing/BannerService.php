<?php

namespace App\Services\Marketing;

use App\Http\Resources\Common\Item\ProductResource;
use App\Http\Resources\Common\Marketing\BasicCampaignResource;
use App\Http\Resources\Common\Store\StoreDetailResource;
use App\Models\Banner;
use App\Services\BaseService;
use App\Support\Cache\ApiCache;
use App\Services\Item\ItemService;
use App\Services\Store\StoreService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ProductPayloadTrait;
use App\Traits\Store\StorePayloadTrait;
use App\Traits\System\TranslationsTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Modules\Service\Services\ServiceBannerService;
use Modules\Service\Services\ServiceBasicCampaignService;
use App\Support\Storage\FileStorage;

class BannerService extends BaseService
{
    use PersonalizationTrait;
    use ProductPayloadTrait;
    use StorePayloadTrait;
    use TranslationsTrait;

    private const IMAGE_DIR = 'banner/';

    private const VENDOR_COLUMNS = [
        'id', 'title', 'type', 'image', 'status', 'data', 'zone_id', 'module_id',
        'featured', 'default_link', 'created_by', 'created_at',
    ];

    private const CUSTOMER_COLUMNS = ['id', 'title', 'type', 'image', 'default_link'];

    private const ADMIN_COLUMNS = ['id', 'title', 'type', 'image', 'default_link', 'data', 'zone_id', 'module_id'];

    public function getAddData(array $input): array
    {
        return $this->getBaseData($input, FileStorage::upload('banner/', ($input['image'] ?? null)));
    }

    public function getUpdateData(array $input, object $banner): array
    {
        return $this->getBaseData($input, array_key_exists('image', $input)
            ? FileStorage::update('banner/', $banner->image, ($input['image'] ?? null))
            : $banner->image);
    }

    public function getZoneBanners(array $filters = []): array
    {
        $banners = $this->adminBanners($filters);

        if (empty($filters['customer_id'])) {
            return $banners;
        }

        return $this->reorderByPreference(
            collect($banners),
            $filters['customer_id'],
            'store_id',
            'store'
        )->toArray();
    }

    public function getServiceModuleBanners(array $filters = []): array
    {
        return app(ServiceBannerService::class)->getBanners(
            $filters['zone_ids'] ?? [],
            $filters['module_id'] ?? null,
            $filters['featured'] ?? null
        );
    }

    public function getAllModuleBanners(array $filters = []): array
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $featured = $filters['featured'] ?? null;

        $productBanners = $this->adminBanners($filters, moduleId: null);
        $serviceBanners = service_addon_active()
            ? app(ServiceBannerService::class)->getBanners($zoneIds, null, $featured)
            : [];

        $ids = array_unique(array_merge(
            array_column($productBanners, 'id'),
            array_column($serviceBanners, 'id')
        ));

        $serviceSet = empty($ids) ? [] : array_flip(
            Banner::whereIn('id', $ids)
                ->whereHas('module', fn ($query) => $query->where('module_type', 'service'))
                ->pluck('id')->all()
        );

        $banners = [];

        foreach ($productBanners as $banner) {
            if (! isset($serviceSet[$banner['id']])) {
                $banners[] = $banner;
            }
        }

        foreach ($serviceBanners as $banner) {
            if (isset($serviceSet[$banner['id']])) {
                $banners[] = $banner;
            }
        }

        return $banners;
    }

    public function getRunningCampaigns(array $filters = []): array
    {
        if (! empty($filters['featured'])) {
            return [];
        }

        $zoneId = $filters['zone_id'] ?? null;
        $moduleId = $filters['module_id'] ?? 'default';

        $campaigns = ApiCache::remember(
            'campaigns',
            [$zoneId, $moduleId, $filters['locale'] ?? null],
            fn () => $this->buildCampaignQuery($filters)->get()
        );

        return BasicCampaignResource::renderCollection($campaigns);
    }

    public function getServiceModuleCampaigns(array $filters = []): array
    {
        if (! empty($filters['featured'])) {
            return [];
        }

        return app(ServiceBasicCampaignService::class)->customerList(
            $filters['zone_ids'] ?? [],
            $filters['module_id'] ?? null
        );
    }

    public function getAllModuleCampaigns(array $filters = []): array
    {
        if (! empty($filters['featured'])) {
            return [];
        }

        $productCampaigns = $this->getRunningCampaigns($filters);
        $serviceCampaigns = service_addon_active()
            ? app(ServiceBasicCampaignService::class)->customerList($filters['zone_ids'] ?? [], null)
            : [];

        return array_merge($productCampaigns, is_array($serviceCampaigns) ? $serviceCampaigns : []);
    }

    public function getStoreBannerList(
        mixed $storeId,
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->paginateCollection($this->getStoreBanners($storeId, $filters), $paginate);
    }

    public function getPaginatedList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->paginateCollection($this->getList($filters, $with, $withCount), $paginate);
    }

    public function find(mixed $id, array $with = [], array $filters = []): ?Banner
    {
        return $this->scopedBanner($id, self::VENDOR_COLUMNS, $filters, array_merge([
            'translations' => fn ($query) => $query->select(TRANSLATION_RELATION_COLUMNS),
            'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
        ], $with));
    }

    public function create(array $data): Banner
    {
        $banner = DB::transaction(function () use ($data) {
            $banner = new Banner;
            $banner->title = $data['title'];
            $banner->type = 'store_wise';
            $banner->zone_id = $data['zone_id'];
            $banner->image = FileStorage::upload(self::IMAGE_DIR, $data['image']);
            $banner->data = $data['store_id'];
            $banner->module_id = $data['module_id'];
            $banner->default_link = $data['default_link'];
            $banner->created_by = 'store';
            $banner->save();

            $this->syncTranslations($banner, $data['translations']);

            return $banner;
        });

        return $this->find($banner->id, filters: ['store_id' => $data['store_id']]);
    }

    public function update(mixed $id, array $data): ?Banner
    {
        $updated = DB::transaction(function () use ($id, $data) {
            $banner = $this->scopedBanner($id, ['id', 'image'], $data);

            if (! $banner) {
                return null;
            }

            $banner->title = $data['title'];
            $banner->image = $data['image']
                ? FileStorage::update(self::IMAGE_DIR, $banner->image, $data['image'])
                : $banner->image;
            $banner->default_link = $data['default_link'];
            $banner->save();

            $this->syncTranslations($banner, $data['translations']);

            return $banner;
        });

        return $updated ? $this->find($id, filters: ['store_id' => $data['store_id'] ?? null]) : null;
    }

    public function delete(mixed $id, array $filters = []): bool
    {
        return DB::transaction(function () use ($id, $filters) {
            $banner = $this->scopedBanner($id, ['id', 'image'], $filters);

            if (! $banner) {
                return false;
            }

            if ($banner->image) {
                FileStorage::delete(self::IMAGE_DIR, $banner->image);
            }

            $banner->translations()->delete();
            $banner->delete();

            return true;
        });
    }

    public function updateStatus(mixed $id, string $status, array $data = []): ?Banner
    {
        $banner = $this->scopedBanner($id, ['id', 'status'], $data);

        if (! $banner) {
            return null;
        }

        $banner->status = $status;
        $banner->save();

        return $this->find($id, filters: ['store_id' => $data['store_id'] ?? null]);
    }

    public function getModuleTypeBanners(array $filters = []): array
    {
        return $this->formatProviderBanners(
            $this->cachedAdminBanners($filters['zone_id'] ?? null, null, $filters['featured'] ?? null, $filters),
            $filters['module_id'] ?? null
        );
    }

    public function toggleStatus(mixed $id, array $filters = []): ?Banner
    {
        return $this->toggleFlag($id, 'status', $filters);
    }

    public function toggleFeatured(mixed $id, array $filters = []): ?Banner
    {
        return $this->toggleFlag($id, 'featured', $filters);
    }

    private function getBaseData(array $input, mixed $image): array
    {
        return [
            'title' => ($input['title'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'type' => ($input['banner_type'] ?? null),
            'zone_id' => ($input['zone_id'] ?? null),
            'image' => $image,
            'data' => (($input['banner_type'] ?? null) == 'store_wise') ? ($input['store_id'] ?? null) : ((($input['banner_type'] ?? null) == 'item_wise') ? ($input['item_id'] ?? null) : ''),
            'module_id' => Config::get('module.current_module_id'),
            'default_link' => ($input['default_link'] ?? null),
        ];
    }

    private function getList(array $filters = [], array $with = [], array $withCount = [], bool $withTrashed = false): Collection
    {
        return Banner::translateOnly(['title'])
            ->with(array_merge([
                'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
            ], $with))
            ->withCount($withCount)
            ->where('created_by', 'store')
            ->where('data', $filters['store_id'] ?? null)
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->when($filters['search'] ?? null, fn ($query, $search) => $this->applyTitleSearch($query, $search))
            ->latest()
            ->get(self::VENDOR_COLUMNS);
    }

    private function scopedBanner(mixed $id, array $columns, array $filters, array $with = []): ?Banner
    {
        return Banner::withoutGlobalScope('translate')
            ->with($with)
            ->where('id', $id)
            ->where('created_by', 'store')
            ->where('data', $filters['store_id'] ?? null)
            ->first($columns);
    }

    private function adminBanners(array $filters, mixed $moduleId = false): array
    {
        $zoneId = $filters['zone_id'] ?? null;
        $featured = $filters['featured'] ?? null;
        $moduleId = $moduleId === false ? ($filters['module_id'] ?? null) : $moduleId;

        return ApiCache::remember(
            'banners_formatted',
            [
                $zoneId,
                $featured ? 'featured' : 'non_featured',
                $moduleId ?? 'default',
                $filters['module_type'] ?? 'any_module_type',
                $filters['locale'] ?? null,
            ],
            fn () => $this->formatAdminBanners(
                $this->cachedAdminBanners($zoneId, $moduleId, $featured, $filters),
                $zoneId,
                $moduleId
            )
        );
    }

    private function cachedAdminBanners(mixed $zoneId, mixed $moduleId, mixed $featured, array $filters): Collection
    {
        $moduleType = $filters['module_type'] ?? null;

        return ApiCache::remember(
            'banners',
            [
                $zoneId,
                $featured ? 'featured' : 'non_featured',
                $moduleId ?? 'default',
                $moduleType ?? 'any_module_type',
                $filters['locale'] ?? null,
            ],
            fn () => $this->adminBannerQuery($zoneId, $moduleId, $featured, $moduleType)->get(self::ADMIN_COLUMNS)
        );
    }

    private function adminBannerQuery(mixed $zoneId, mixed $moduleId, mixed $featured, ?string $moduleType = null): Builder
    {
        $zoneIds = $this->decodeZoneIds($zoneId);

        return Banner::translateOnly(['title'])
            ->active()
            ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->when($featured, fn ($query) => $query->featured())
            ->when($moduleType, fn ($query) => $query->whereHas('module', fn ($sub) => $sub->where('module_type', $moduleType)))
            ->whereHas('module', fn ($query) => $query->active())
            ->where('created_by', 'admin')
            ->where(function ($query) use ($zoneIds) {
                $query->where(function ($sub) use ($zoneIds) {
                    $sub->whereIn('type', ['store_wise', 'item_wise'])->whereIn('zone_id', $zoneIds);
                })->orWhere('type', 'default');
            })
            ->when($moduleId, function ($query) use ($moduleId) {
                $query->where(function ($sub) use ($moduleId) {
                    $sub->whereNull('zone_id')
                        ->orWhereHas('zone.modules', fn ($z) => $z->where('modules.id', $moduleId));
                })->module($moduleId);
            });
    }

    private function formatAdminBanners(Collection $banners, mixed $zoneId, mixed $moduleId): array
    {
        $stores = $this->bannerStores($this->targetIds($banners, 'store_wise'), $moduleId);
        $items = $this->bannerItems($this->targetIds($banners, 'item_wise'), $zoneId, $moduleId);

        $data = [];

        foreach ($banners as $banner) {
            $row = [
                'id' => $banner->id,
                'title' => $banner->title,
                'type' => $banner->type,
                'image' => $banner->image,
                'link' => null,
                'store' => null,
                'item' => null,
                'image_full_url' => $banner->image_full_url,
            ];

            if ($banner->type === 'store_wise') {
                $store = $stores[$banner->data] ?? null;

                if (! $store) {
                    continue;
                }

                $row['store'] = (new StoreDetailResource($this->loadStoreRelations($store)))->render();
            } elseif ($banner->type === 'item_wise') {
                $item = $items[$banner->data] ?? null;

                if (! $item) {
                    continue;
                }

                $row['item'] = (new ProductResource($this->loadItemRelations($item)))->render();
            } elseif ($banner->type === 'default') {
                $row['link'] = $banner->default_link;
            } elseif (filled($banner->type)) {
                continue;
            }

            $data[] = $row;
        }

        return $data;
    }

    private function targetIds(Collection $banners, string $type): array
    {
        return $banners->where('type', $type)->pluck('data')->filter()->unique()->values()->all();
    }

    private function bannerStores(array $ids, mixed $moduleId): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        return app(StoreService::class)->getBannerStores($ids, $moduleId);
    }

    private function bannerItems(array $ids, mixed $zoneId, mixed $moduleId): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        $zoneIds = $this->decodeZoneIds($zoneId);

        return app(ItemService::class)->getBannerItems($ids, $zoneIds, $moduleId);
    }

    private function decodeZoneIds(mixed $zoneId): array
    {
        $decoded = json_decode((string) $zoneId, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function getStoreBanners(mixed $storeId, array $filters = []): Collection
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? 'default';

        return ApiCache::remember(
            'banners_store',
            [
                $filters['zone_id'] ?? null,
                $moduleId,
                $storeId,
                $filters['module_type'] ?? 'any_module_type',
                ($filters['featured_first'] ?? false) ? 'featured_first' : 'natural',
                $filters['locale'] ?? null,
            ],
            fn () => $this->buildStoreBannerQuery($storeId, $zoneIds, $filters)->get(self::CUSTOMER_COLUMNS)
        );
    }

    private function buildStoreBannerQuery(mixed $storeId, array $zoneIds, array $filters)
    {
        $moduleId = $filters['module_id'] ?? null;

        return Banner::translateOnly(['title'])
            ->active()
            ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->when($moduleId, function ($query) use ($moduleId, $zoneIds, $filters) {
                $query->whereHas('zone.modules', fn ($sub) => $sub->where('modules.id', $moduleId))
                    ->module($moduleId)
                    ->when(! ($filters['all_zone_service'] ?? false), fn ($sub) => $sub->whereIn('zone_id', $zoneIds));
            })
            ->when($filters['module_type'] ?? null, fn ($query, $moduleType) => $query
                ->whereHas('module', fn ($sub) => $sub->where('module_type', $moduleType)))
            ->whereIn('zone_id', $zoneIds)
            ->whereHas('module', fn ($query) => $query->active())
            ->where('data', $storeId)
            ->where('created_by', 'store')
            ->when($filters['featured_first'] ?? false, fn ($query) => $query->orderByDesc('featured'));
    }

    private function buildCampaignQuery(array $filters)
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        return app(CampaignService::class)->runningQuery($zoneIds, $moduleId, $filters);
    }

    private function applyTitleSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($builder) use ($search) {
            foreach (array_filter(explode(' ', $search)) as $keyword) {
                $builder->orWhere('title', 'LIKE', '%'.$keyword.'%');
            }
        });
    }

    private function toggleFlag(mixed $id, string $column, array $filters): ?Banner
    {
        $banner = $this->scopedBanner($id, ['id', $column], $filters);

        if (! $banner) {
            return null;
        }

        $banner->{$column} = ! $banner->{$column};
        $banner->save();

        return $this->find($id, filters: $filters);
    }

    private function formatProviderBanners(Collection $banners, mixed $moduleId): array
    {
        $stores = $this->bannerStores($this->targetIds($banners, 'store_wise'), $moduleId);
        $data = [];

        foreach ($banners as $banner) {
            if ($banner->type === 'store_wise' && ! isset($stores[$banner->data])) {
                continue;
            }

            $data[] = [
                'type' => $banner->type,
                'link' => $banner->type === 'default' ? $banner->default_link : null,
                'provider_id' => $banner->type === 'store_wise' ? $stores[$banner->data]->id : null,
                'image_full_url' => $banner->image_full_url,
            ];
        }

        return $data;
    }

    private function loadItemRelations(mixed $item): mixed
    {
        $this->loadProductRelations(new EloquentCollection([$item]));

        return $item;
    }
}
