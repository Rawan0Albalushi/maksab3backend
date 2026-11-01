<?php

namespace App\Services\Marketing;

use App\Support\Cache\ApiCache;
use Modules\Service\Services\ServiceAdvertisementService;
use App\Http\Resources\Common\Store\StoreListResource;
use App\Mail\AdminAdversitementMail;
use App\Models\Advertisement;
use App\Services\BaseService;
use App\Traits\Store\StoreDataTrait;
use App\Traits\System\TranslationsTrait;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Traits\Customer\PersonalizationTrait;
use App\Services\Admin\AdminService;
use App\Services\System\ModuleService;
use App\Support\Notification\SendNotification;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\Log;

class AdvertisementService extends BaseService
{
    use PersonalizationTrait;
    use StoreDataTrait;
    use TranslationsTrait;
    private const ATTACHMENT_DIR = 'advertisement/';
    private const CREATED_BY_TYPE = 'App\Models\Vendor';
    private const STORE_PROMOTION = 'store_promotion';
    private const VIDEO_PROMOTION = 'video_promotion';
    private const CUSTOMER_COLUMNS = [
        'id', 'store_id', 'add_type', 'title', 'description', 'priority',
        'is_rating_active', 'is_review_active',
        'cover_image', 'profile_image', 'video_attachment',
    ];
    private const VENDOR_COLUMNS = [
        'id', 'store_id', 'add_type', 'title', 'description', 'start_date', 'end_date',
        'status', 'priority', 'pause_note', 'cancellation_note',
        'is_rating_active', 'is_review_active', 'is_paid', 'is_updated',
        'cover_image', 'profile_image', 'video_attachment', 'created_at',
    ];
    public function validStoreIds(): array
    {
        return ApiCache::remember(
            'advertised_store_ids',
            date('Y-m-d'),
            fn () => Advertisement::valid()->distinct()->pluck('store_id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function getValidStoreIds(array $filters = []): array
    {
        return Advertisement::valid()
            ->when(($filters['store_ids'] ?? null) !== null, fn ($query) => $query->whereIn('store_id', $filters['store_ids']))
            ->when(($filters['module_id'] ?? null) !== null, fn ($query) => $query->where('module_id', $filters['module_id']))
            ->whereHas('store', function ($query) use ($filters) {
                $query->Active();

                if ($filters['apply_zones'] ?? false) {
                    $query->whereIn('zone_id', $filters['zone_ids'] ?? []);
                }
            })
            ->pluck('store_id')
            ->unique()
            ->values()
            ->all();
    }
    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), self::VENDOR_COLUMNS, 'page', $this->pageNumber($paginate));
    }
    public function getRunningPaginatedList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->paginateCollection($this->getRunningList($filters), $paginate);
    }
    public function getServiceModulePaginatedList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->paginateCollection($this->getServiceModuleList($filters), $paginate);
    }
    public function statusStatistics(array $filters = []): array
    {
        $today = date('Y-m-d');

        $counts = Advertisement::withoutGlobalScope('translate')
            ->where('store_id', $filters['store_id'] ?? null)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending")
            ->selectRaw("SUM(CASE WHEN status = 'denied' THEN 1 ELSE 0 END) as denied")
            ->selectRaw("SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused")
            ->selectRaw("SUM(CASE WHEN status = 'approved' AND start_date <= ? AND end_date >= ? THEN 1 ELSE 0 END) as running", [$today, $today])
            ->selectRaw("SUM(CASE WHEN status = 'approved' AND start_date > ? AND end_date >= ? THEN 1 ELSE 0 END) as approved", [$today, $today])
            ->selectRaw("SUM(CASE WHEN status = 'approved' AND end_date < ? THEN 1 ELSE 0 END) as expired", [$today])
            ->first();

        return [
            'all' => (int) ($counts->total ?? 0),
            'running' => (int) ($counts->running ?? 0),
            'pending' => (int) ($counts->pending ?? 0),
            'denied' => (int) ($counts->denied ?? 0),
            'paused' => (int) ($counts->paused ?? 0),
            'approved' => (int) ($counts->approved ?? 0),
            'expired' => (int) ($counts->expired ?? 0),
        ];
    }
    public function find(mixed $id, array $with = [], array $filters = []): ?Advertisement
    {
        return $this->ownedQuery($id, $filters, array_merge([
            'translations' => fn ($query) => $query->select(TRANSLATION_RELATION_COLUMNS),
            'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
        ], $with))->first(self::VENDOR_COLUMNS);
    }
    public function create(array $data): Advertisement
    {
        $advertisement = DB::transaction(function () use ($data) {
            $advertisement = new Advertisement();
            $advertisement->store_id = $data['store_id'];
            $advertisement->add_type = $data['advertisement_type'];
            $advertisement->title = $data['title'];
            $advertisement->description = $data['description'];
            $advertisement->start_date = $data['start_date'];
            $advertisement->end_date = $data['end_date'];
            $advertisement->priority = null;
            $advertisement->is_rating_active = $data['is_rating_active'];
            $advertisement->is_review_active = $data['is_review_active'];
            $advertisement->is_paid = 0;
            $advertisement->created_by_id = $data['vendor_id'];
            $advertisement->created_by_type = self::CREATED_BY_TYPE;
            $advertisement->status = 'pending';
            $advertisement->cover_image = $this->uploadAttachment($data, 'cover_image', self::STORE_PROMOTION);
            $advertisement->profile_image = $this->uploadAttachment($data, 'profile_image', self::STORE_PROMOTION);
            $advertisement->video_attachment = $this->uploadAttachment($data, 'video_attachment', self::VIDEO_PROMOTION);
            $advertisement->save();

            $module = $this->storeModule($data['store_id']);
            $advertisement->module_id = $module?->id;
            $advertisement->module_type = $module?->module_type;
            $advertisement->save();

            $this->insertTranslations($advertisement, $data['translations']);

            return $advertisement;
        });

        $advertisement = $this->find($advertisement->id, filters: ['store_id' => $data['store_id']]);

        $this->notifyAdmin($advertisement, 'new_advertisement', 'advertisement_add', 'new_advertisement_mail_status_admin');

        return $advertisement;
    }
    public function update(mixed $id, array $data): ?Advertisement
    {
        $advertisement = DB::transaction(function () use ($id, $data) {
            $advertisement = $this->ownedQuery($id, $data, withoutTranslate: false)->first();

            if (! $advertisement) {
                return null;
            }

            $advertisement->title = $data['title'];
            $advertisement->description = $data['description'];
            $advertisement->start_date = $data['start_date'];
            $advertisement->end_date = $data['end_date'];
            $advertisement->is_rating_active = $data['is_rating_active'];
            $advertisement->is_review_active = $data['is_review_active'];
            $advertisement->is_updated = $advertisement->status == 'pending' ? 0 : 1;
            $advertisement->status = 'pending';

            if ($advertisement->add_type != $data['advertisement_type']) {
                $this->purgeAttachmentsOnTypeChange($advertisement, $data['advertisement_type']);
            }

            $advertisement->add_type = $data['advertisement_type'];
            $advertisement->cover_image = $this->replaceAttachment($advertisement, $data, 'cover_image', self::STORE_PROMOTION);
            $advertisement->profile_image = $this->replaceAttachment($advertisement, $data, 'profile_image', self::STORE_PROMOTION);
            $advertisement->video_attachment = $this->replaceAttachment($advertisement, $data, 'video_attachment', self::VIDEO_PROMOTION);
            $advertisement->save();

            $this->syncTranslations($advertisement, $data['translations']);

            return $advertisement;
        });

        if ($advertisement) {
            $advertisement = $this->find($id, filters: ['store_id' => $data['store_id'] ?? null]);
            $this->notifyAdmin($advertisement, 'update_advertisement', 'advertisement_update', 'update_advertisement_mail_status_admin');
        }

        return $advertisement;
    }
    public function delete(mixed $id, array $filters = []): bool
    {
        return DB::transaction(function () use ($id, $filters) {
            $advertisement = $this->ownedQuery($id, $filters)
                ->first(['id', 'module_id', 'cover_image', 'profile_image', 'video_attachment']);

            if (! $advertisement) {
                return false;
            }

            foreach (['cover_image', 'profile_image', 'video_attachment'] as $key) {
                if ($advertisement->{$key}) {
                    FileStorage::delete(self::ATTACHMENT_DIR, $advertisement->{$key});
                }
            }

            $advertisement->translations()?->delete();
            $moduleId = $advertisement->module_id;
            $advertisement->delete();

            $this->resequencePriorities($moduleId);

            return true;
        });
    }
    public function updateStatus(mixed $id, string $status, array $data = []): ?Advertisement
    {
        $advertisement = $this->ownedQuery($id, $data)->first(['id', 'status', 'pause_note']);

        if (! $advertisement) {
            return null;
        }

        $advertisement->status = in_array($status, ['paused', 'approved']) ? $status : $advertisement->status;
        $advertisement->pause_note = $data['pause_note'] ?? null;
        $advertisement->save();

        return $this->find($id, filters: ['store_id' => $data['store_id'] ?? null]);
    }
    public function copy(mixed $sourceId, array $data): ?Advertisement
    {
        $source = $this->ownedQuery($sourceId, $data, ['storage', 'store:id,name'])
            ->first(['id', 'store_id', 'cover_image', 'profile_image', 'video_attachment']);

        if (! $source) {
            return null;
        }

        $advertisement = DB::transaction(function () use ($source, $data) {
            $advertisement = new Advertisement();
            $advertisement->store_id = $data['store_id'];
            $advertisement->add_type = $data['advertisement_type'];
            $advertisement->title = $data['title'];
            $advertisement->description = $data['description'];
            $advertisement->start_date = $data['start_date'];
            $advertisement->end_date = $data['end_date'];
            $advertisement->priority = null;
            $advertisement->is_rating_active = $data['is_rating_active'];
            $advertisement->is_review_active = $data['is_review_active'];
            $advertisement->is_paid = 0;
            $advertisement->created_by_id = $data['vendor_id'];
            $advertisement->created_by_type = self::CREATED_BY_TYPE;
            $advertisement->status = 'pending';

            if ($data['advertisement_type'] === self::STORE_PROMOTION) {
                $advertisement->cover_image = $data['cover_image']
                    ? FileStorage::upload(dir: self::ATTACHMENT_DIR, image: $data['cover_image'])
                    : $this->copyAttachment($source, 'cover_image');

                $advertisement->profile_image = $data['profile_image']
                    ? FileStorage::upload(dir: self::ATTACHMENT_DIR, image: $data['profile_image'])
                    : $this->copyAttachment($source, 'profile_image');
            }

            if ($data['advertisement_type'] === self::VIDEO_PROMOTION) {
                $advertisement->video_attachment = $data['video_attachment']
                    ? FileStorage::upload(dir: self::ATTACHMENT_DIR, image: $data['video_attachment'])
                    : $this->copyAttachment($source, 'video_attachment');
            }

            $advertisement->save();

            $module = $this->storeModule($data['store_id']);
            $advertisement->module_id = $module?->id;
            $advertisement->module_type = $module?->module_type;
            $advertisement->save();

            $this->insertTranslations($advertisement, $data['translations']);

            return $advertisement;
        });

        $advertisement = $this->find($advertisement->id, filters: ['store_id' => $data['store_id']]);

        $this->notifyAdmin($source, 'new_advertisement', 'advertisement_add', 'new_advertisement_mail_status_admin');

        return $advertisement;
    }
    public function attachmentErrors(mixed $id, array $data): array
    {
        $advertisement = $this->ownedQuery($id, $data)->first(['id', 'add_type']);

        if (! $advertisement || $advertisement->add_type == $data['advertisement_type']) {
            return [];
        }

        if ($data['advertisement_type'] === self::VIDEO_PROMOTION && ! $data['video_attachment']) {
            return ['file_required' => [translate('messages.You must need to add a promotional video file')]];
        }

        if ($data['advertisement_type'] === self::STORE_PROMOTION && (! $data['cover_image'] || ! $data['profile_image'])) {
            return ['file_required' => [translate('messages.You must need to add cover & profile image')]];
        }

        return [];
    }
    private function ownedQuery(mixed $id, array $filters, array $relations = [], bool $withoutTranslate = true): mixed
    {
        return Advertisement::when($withoutTranslate, fn ($query) => $query->withoutGlobalScope('translate'))
            ->with($relations)
            ->where('id', $id)
            ->where('store_id', $filters['store_id'] ?? null);
    }
    private function getRunningList(array $filters = []): Collection
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        $advertisements = ApiCache::remember(
            'advertisement',
            [$zoneIds, $moduleId ?? 'default', $filters['locale'] ?? null],
            fn () => $this->buildRunningQuery($zoneIds, $moduleId)
        );

        if (! empty($filters['customer_id'])) {
            $advertisements = $this->reorderByPreference(
                $advertisements,
                $filters['customer_id'],
                'store_id',
                'store'
            );
        }

        $this->attachStoreFormatting($advertisements);

        return $advertisements;
    }
    private function getServiceModuleList(array $filters = []): Collection
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        return ApiCache::remember(
            'advertisement_service',
            [$zoneIds, $moduleId ?? 'default', $filters['locale'] ?? app()->getLocale()],
            fn () => app(ServiceAdvertisementService::class)->running($zoneIds, $moduleId)
        );
    }
    private function attachStoreFormatting(Collection $advertisements): void
    {
        if ($advertisements->isEmpty()) {
            return;
        }

        $storeIds = $advertisements->pluck('store.id')->filter()->unique()->values()->all();
        $topItemsByStore = $this->topItemRows($storeIds, 3);
        $itemsCountByStore = $this->itemCountsByStore($storeIds);
        $categoriesByStore = $this->topCategories($storeIds, 5);
        $offersByStore = $this->offersByStore($storeIds);

        $advertisements->each(function ($advertisement) use ($topItemsByStore, $itemsCountByStore, $categoriesByStore, $offersByStore) {
            $store = $advertisement->store;

            if (! $store) {
                return;
            }

            $itemCount = (int) ($itemsCountByStore[(int) $store->id] ?? 0);

            $formattedStore = (new StoreListResource($store))->withOptions([
                'top_items' => $topItemsByStore[(int) $store->id] ?? [],
                'with_items' => true,
                'items_count' => $itemCount,
                'offers' => $offersByStore[(int) $store->id] ?? [],
                'category_data' => $categoriesByStore[(int) $store->id] ?? [],
            ])->render();
            $formattedStore['item_count'] = $itemCount;

            $advertisement->unsetRelation('store');
            $advertisement->setAttribute('store', $formattedStore);
        });
    }
    private function buildQuery(array $filters = [], array $with = [], array $withCount = []): Builder
    {
        return Advertisement::translateOnly(['title', 'description'])
            ->with(array_merge([
                'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
            ], $with))
            ->withCount($withCount)
            ->where('store_id', $filters['store_id'] ?? null)
            ->when(($filters['ads_type'] ?? null) === 'pending', fn ($query) => $query->where('status', 'pending'))
            ->when(($filters['ads_type'] ?? null) === 'denied', fn ($query) => $query->where('status', 'denied'))
            ->when(($filters['ads_type'] ?? null) === 'paused', fn ($query) => $query->where('status', 'paused'))
            ->when(($filters['ads_type'] ?? null) === 'running', fn ($query) => $query->valid())
            ->when(($filters['ads_type'] ?? null) === 'approved', fn ($query) => $query->approved())
            ->when(($filters['ads_type'] ?? null) === 'expired', fn ($query) => $query->expired())
            ->when(! empty($filters['search']), function ($query) use ($filters) {
                foreach (explode(' ', $filters['search']) as $value) {
                    $query->where(fn ($query) => $query->where('id', 'like', "%{$value}%"));
                }
            });
    }
    private function buildRunningQuery(array $zoneIds, mixed $moduleId): Collection
    {
        $advertisements = Advertisement::translateOnly(['title', 'description'])
            ->valid()
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->with([
                'storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS),
                'store' => fn ($query) => $query
                    ->withCount(['reviews_comments as ad_comments_count'])
                    ->withAvg('reviews as ad_rating_avg', 'rating'),
                'store.storage',
                'store.module',
                'store.schedules',
            ])
            ->whereHas('store', function ($query) use ($zoneIds) {
                if (! empty($zoneIds)) {
                    $query->whereIn('zone_id', $zoneIds);
                }
                $query->active();
            })
            ->orderByRaw('ISNULL(priority), priority ASC')
            ->get(self::CUSTOMER_COLUMNS);

        $this->attachStoreRatings($advertisements);

        return $advertisements;
    }
    private function attachStoreRatings(Collection $advertisements): void
    {
        $advertisements->each(function ($advertisement) {
            $store = $advertisement->store;

            $advertisement->reviews_comments_count = (int) ($store?->ad_comments_count ?? 0);
            $advertisement->average_rating = (float) ($store?->ad_rating_avg ?? 0);
        });
    }
    private function uploadAttachment(array $data, string $key, string $requiredType): ?string
    {
        if (! $data[$key] || $data['advertisement_type'] !== $requiredType) {
            return null;
        }

        return FileStorage::upload(dir: self::ATTACHMENT_DIR, image: $data[$key]);
    }
    private function replaceAttachment(Advertisement $advertisement, array $data, string $key, string $requiredType): ?string
    {
        if (! $data[$key] || $data['advertisement_type'] !== $requiredType) {
            return $advertisement->{$key};
        }

        return FileStorage::update(
            dir: self::ATTACHMENT_DIR,
            old_image: $advertisement->{$key},
            image: $data[$key]
        );
    }
    private function purgeAttachmentsOnTypeChange(Advertisement $advertisement, string $newType): void
    {
        if ($newType === self::VIDEO_PROMOTION) {
            foreach (['cover_image', 'profile_image'] as $key) {
                if ($advertisement->{$key}) {
                    FileStorage::delete(self::ATTACHMENT_DIR, $advertisement->{$key});
                }
            }
        }

        if ($newType === self::STORE_PROMOTION && $advertisement->video_attachment) {
            FileStorage::delete(self::ATTACHMENT_DIR, $advertisement->video_attachment);
        }
    }
    private function storeModule(mixed $storeId): mixed
    {
        return app(ModuleService::class)->findForStore($storeId);
    }
    private function resequencePriorities(mixed $moduleId): void
    {
        $ids = Advertisement::withoutGlobalScope('translate')
            ->whereNotNull('priority')
            ->where('module_id', $moduleId)
            ->orderByRaw('ISNULL(priority), priority ASC')
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        $cases = $ids->map(fn ($id, $index) => 'WHEN ' . (int) $id . ' THEN ' . ($index + 1))->implode(' ');

        Advertisement::whereIn('id', $ids)->update([
            'priority' => DB::raw("CASE id {$cases} END"),
        ]);
    }
    private function copyAttachment(Advertisement $source, string $fileKeyName): ?string
    {
        $oldDisk = 'public';

        if ($source->storage && count($source->storage) > 0) {
            foreach ($source->storage as $value) {
                if ($value['key'] == $fileKeyName) {
                    $oldDisk = $value['value'];
                }
            }
        }

        $oldPath = self::ATTACHMENT_DIR . $source->{$fileKeyName};
        $newFileName = Carbon::now()->toDateString() . '-' . uniqid() . '.' . explode('.', (string) $source->{$fileKeyName})[1];
        $newPath = self::ATTACHMENT_DIR . $newFileName;
        $newDisk = FileStorage::getDisk();

        try {
            if (Storage::disk($oldDisk)->exists($oldPath)) {
                if (! Storage::disk($newDisk)->exists(self::ATTACHMENT_DIR)) {
                    Storage::disk($newDisk)->makeDirectory(self::ATTACHMENT_DIR);
                }

                Storage::disk($newDisk)->put($newPath, Storage::disk($oldDisk)->get($oldPath));
            }
        } catch (\Exception $e) {
            Log::warning('marketing.advertisement_service.copy_attachment_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }

        return $newFileName ?? null;
    }
    private function notifyAdmin(?Advertisement $advertisement, string $type, string $notificationKey, string $mailStatusKey): void
    {
        try {
            if (SendNotification::canSendMail($mailStatusKey, 'admin', $notificationKey)) {
                $advertisement?->loadMissing('store:id,name');

                SendNotification::mail(app(AdminService::class)->findSuperAdminEmail(), new AdminAdversitementMail($advertisement?->store?->name, $type, $advertisement->id));
            }
        } catch (\Throwable $th) {
            Log::error('marketing.advertisement_service.notify_admin_failed', [
                'error' => $th->getMessage(),
                'file' => $th->getFile().':'.$th->getLine(),
            ]);
        }
    }
}
