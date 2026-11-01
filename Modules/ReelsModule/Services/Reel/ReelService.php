<?php

namespace Modules\ReelsModule\Services\Reel;

use App\CentralLogics\Helpers;
use App\Exceptions\InvalidUploadException;
use App\Models\Item;
use App\Services\BaseService;
use App\Services\Store\StoreService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Entities\ReelEngagement;
use Modules\ReelsModule\Support\ReelModuleConfig;
use Modules\ReelsModule\Support\ReelProductableResolver;
use Modules\Rental\Entities\Vehicle;
use Modules\Service\Entities\Service;
use App\Support\Storage\FileStorage;

class ReelService extends BaseService
{
    public const IMAGE_DIR = 'reels/';
    private const CUSTOMER_COLUMNS = [
        'id', 'description', 'thumbnail', 'video', 'store_id', 'module_id', 'module_type',
        'productable_type', 'productable_id', 'order_now_button', 'order_count', 'total_sale_amount',
        'status', 'is_always_visible', 'start_date', 'end_date',
        'total_views', 'total_likes', 'total_store_visits',
    ];
    private const VENDOR_COLUMNS = [
        'id', 'description', 'thumbnail', 'video', 'store_id', 'module_id', 'module_type',
        'productable_type', 'productable_id', 'order_now_button', 'order_count', 'total_sale_amount',
        'status', 'is_always_visible', 'start_date', 'end_date', 'created_at',
    ];
    private const COUNTER_COLUMNS = ['total_views', 'total_likes', 'total_store_visits'];
    private const STORE_COLUMNS = ['id', 'name', 'logo', 'module_id', 'address', 'phone'];
    private const STORE_CONFIG_COLUMNS = ['id', 'store_id', 'verified_seller'];
    private const ITEM_COLUMNS = [
        'id', 'name', 'price', 'image', 'store_id', 'store_category_id', 'module_id',
        'status', 'is_approved', 'stock', 'maximum_cart_quantity',
    ];
    private const VEHICLE_COLUMNS = [
        'id', 'name', 'thumbnail', 'hourly_price', 'day_wise_price', 'distance_price', 'provider_id', 'status',
    ];
    private const SERVICE_COLUMNS = ['id', 'name', 'base_price', 'thumbnail', 'store_id', 'status', 'is_approved'];
    private const THUMBNAIL_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const VIDEO_FORMATS = ['mp4', 'mov', '3gp', 'gif', 'webm', 'mkv'];
    private const WEBP_SOURCE_FORMATS = ['jpg', 'jpeg', 'png'];
    private const THUMBNAIL_MAX_MB = 2;
    private const SORT_OLDEST = 'oldest';
    private const SORT_MOST_VIEWED = 'most_viewed';
    private const SORT_MOST_LIKED = 'most_liked';
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $query = Reel::select(self::VENDOR_COLUMNS)
            ->with($this->relations())
            ->withCount($this->engagementCounts())
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($filters['search'] ?? null, fn (Builder $builder, $search) => $this->applySearch($builder, $search));

        $this->applyStatuses($query, $filters['statuses'] ?? []);
        $this->applySorting($query, $filters['sort_by'] ?? null);

        return $query->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    public function getActiveList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Reel::active()
            ->select(self::CUSTOMER_COLUMNS)
            ->with($this->relations())
            ->withExists($this->likedByCustomer($filters['customer_id'] ?? null))
            ->when(($filters['module_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('module_id', $filters['module_id']))
            ->when($filters['store_id'] ?? null, fn (Builder $builder, $storeId) => $builder->where('store_id', $storeId))
            ->when($this->zoneScopeApplies($filters), fn (Builder $builder) => $this->applyZoneScope($builder, $filters))
            ->latest('id')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    public function find(mixed $id, array $filters = []): ?Reel
    {
        return $this->storeScopedQuery($filters)->find($id);
    }
    public function findWithTranslations(mixed $id, array $filters = []): ?Reel
    {
        return $this->storeScopedQuery($filters)
            ->withAllTranslations()
            ->select(array_merge(self::VENDOR_COLUMNS, self::COUNTER_COLUMNS))
            ->with($this->relations())
            ->find($id);
    }
    public function findActive(mixed $id, array $filters = []): ?Reel
    {
        return Reel::active()
            ->withAllTranslations()
            ->select(self::CUSTOMER_COLUMNS)
            ->with($this->relations())
            ->withExists($this->likedByCustomer($filters['customer_id'] ?? null))
            ->when(($filters['module_id'] ?? null) !== null, fn (Builder $builder) => $builder->where('module_id', $filters['module_id']))
            ->find($id);
    }
    public function create(array $data): Reel
    {
        return $this->persist(new Reel(), $data);
    }
    public function update(Reel $reel, array $data): Reel
    {
        return $this->persist($reel, $data);
    }
    public function delete(Reel $reel): bool
    {
        if ($reel->thumbnail) {
            FileStorage::delete(dir: self::IMAGE_DIR, old_image: $reel->thumbnail);
        }

        if ($reel->video) {
            FileStorage::delete(dir: self::IMAGE_DIR, old_image: $reel->video);
        }

        $reel->translations()->delete();
        $reel->storage()->delete();

        return (bool) $reel->delete();
    }
    public function updateStatus(Reel $reel, bool $status): bool
    {
        return $reel->update(['status' => $status]);
    }
    public function videoDisk(Reel $reel): string
    {
        foreach ($reel->storage as $storage) {
            if ($storage->key === 'video' && $storage->value) {
                return $storage->value;
            }
        }

        return FileStorage::getDisk();
    }
    public function storeAsset(UploadedFile $file, string $type): string
    {
        return $this->storeReelFile($file, self::IMAGE_DIR, $type);
    }
    public function deleteAsset(?string $file): void
    {
        if (! $file) {
            return;
        }

        FileStorage::delete(dir: self::IMAGE_DIR, old_image: $file);
    }
    public function findLocked(mixed $reelId): mixed
    {
        return $this->lockedQuery()->find($reelId);
    }
    public function findLockedOrFail(mixed $reelId): mixed
    {
        return $this->lockedQuery()->findOrFail($reelId);
    }
    public function incrementCounter(mixed $reelId, string $column): void
    {
        Reel::where('id', $reelId)->increment($column);
    }
    // Always filtered: only the vendor API reads through here, and a missing store id used to drop
    // the filter altogether -- every store's reel, by id. With no store it now matches nothing.
    private function storeScopedQuery(array $filters): Builder
    {
        return Reel::where('store_id', (int) ($filters['store_id'] ?? 0));
    }
    private function lockedQuery(): Builder
    {
        return Reel::lockForUpdate();
    }
    private function relations(): array
    {
        return [
            'storage',
            'store' => fn ($query) => $query
                ->select(self::STORE_COLUMNS)
                ->with(['storage', 'storeConfig' => fn ($config) => $config->select(self::STORE_CONFIG_COLUMNS)]),
            'productable' => fn (MorphTo $morphTo) => $morphTo
                ->morphWith([
                    Item::class => ['storage'],
                    Vehicle::class => ['storage'],
                    Service::class => ['storage'],
                ])
                ->constrain([
                    Item::class => fn ($query) => $query->select(self::ITEM_COLUMNS),
                    Vehicle::class => fn ($query) => $query->select(self::VEHICLE_COLUMNS),
                    Service::class => fn ($query) => $query->select(self::SERVICE_COLUMNS),
                ]),
        ];
    }
    private function engagementCounts(): array
    {
        return [
            'engagements as total_views' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_VIEW),
            'engagements as total_likes' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_LIKE),
            'engagements as total_store_visits' => fn (Builder $builder) => $builder->where('type', ReelEngagement::TYPE_VISIT),
        ];
    }
    private function likedByCustomer(?int $customerId): array
    {
        return [
            'engagements as is_liked' => fn (Builder $builder) => $builder
                ->where('type', ReelEngagement::TYPE_LIKE)
                ->where('user_id', $customerId)
                ->when(! $customerId, fn (Builder $empty) => $empty->whereRaw('1 = 0')),
        ];
    }
    private function zoneScopeApplies(array $filters): bool
    {
        return ! ($filters['all_zone_service'] ?? false) && ! empty($this->zoneIds($filters['zone_header'] ?? null));
    }
    private function applyZoneScope(Builder $query, array $filters): void
    {
        $zoneIds = $this->zoneIds($filters['zone_header'] ?? null);
        $moduleId = $filters['module_id'] ?? null;

        $query->whereHas('store', function (Builder $storeQuery) use ($zoneIds, $moduleId) {
            $storeQuery->whereIn('zone_id', $zoneIds)
                ->whereHas('zone.modules', fn (Builder $moduleQuery) => $moduleQuery
                    ->when($moduleId, fn (Builder $scoped) => $scoped->where('modules.id', $moduleId)));
        });
    }
    private function zoneIds(?string $zoneHeader): array
    {
        if (empty($zoneHeader)) {
            return [];
        }

        $decoded = json_decode($zoneHeader, true);

        if (is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($value) => is_numeric($value)));
        }

        return is_numeric($zoneHeader) ? [(int) $zoneHeader] : [];
    }
    private function applySearch(Builder $query, string $search): void
    {
        foreach (array_filter(explode(' ', $search)) as $keyword) {
            $query->where(fn (Builder $builder) => $builder
                ->where('id', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%")
                ->orWhereHas('translations', fn (Builder $translation) => $translation
                    ->where('key', 'description')
                    ->where('value', 'like', "%{$keyword}%")));
        }
    }
    private function applyStatuses(Builder $query, array $statuses): void
    {
        $statuses = array_values(array_diff($statuses, ['all']));

        if (empty($statuses)) {
            return;
        }

        $today = now()->toDateString();

        $query->where(function (Builder $builder) use ($statuses, $today) {
            foreach ($statuses as $status) {
                match ($status) {
                    'deactivated' => $builder->orWhere('status', 0),
                    'live' => $builder->orWhere(fn (Builder $live) => $live
                        ->where('status', 1)
                        ->where(fn (Builder $window) => $window
                            ->where('is_always_visible', 1)
                            ->orWhere(fn (Builder $dated) => $dated
                                ->where('is_always_visible', 0)
                                ->whereDate('start_date', '<=', $today)
                                ->whereDate('end_date', '>=', $today)))),
                    'upcoming' => $builder->orWhere(fn (Builder $upcoming) => $upcoming
                        ->where('status', 1)
                        ->where('is_always_visible', 0)
                        ->whereDate('start_date', '>', $today)),
                    'expired' => $builder->orWhere(fn (Builder $expired) => $expired
                        ->where('status', 1)
                        ->where('is_always_visible', 0)
                        ->whereDate('end_date', '<', $today)),
                    default => null,
                };
            }
        });
    }
    private function applySorting(Builder $query, ?string $sortBy): void
    {
        match ($sortBy) {
            self::SORT_OLDEST => $query->orderBy('created_at'),
            self::SORT_MOST_VIEWED => $query->orderByDesc('total_views')->orderByDesc('created_at'),
            self::SORT_MOST_LIKED => $query->orderByDesc('total_likes')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }
    private function persist(Reel $reel, array $data): Reel
    {
        $store = app(StoreService::class)->findUnscopedWithModule($data['store_id'] ?? null);
        $translations = $data['translations'] ?? [];
        $alwaysVisible = (bool) ($data['is_always_visible'] ?? false);
        $product = ReelProductableResolver::resolve($store, $data['product_id'] ?? null);

        $reel->store_id = $store?->id;
        $reel->module_id = ReelModuleConfig::isMultiModule() ? (int) $store?->module_id : ReelModuleConfig::defaultModuleId();
        $reel->module_type = ReelModuleConfig::isMultiModule()
            ? (string) ($store?->module?->module_type ?? ReelModuleConfig::defaultModuleType())
            : ReelModuleConfig::defaultModuleType();
        $reel->productable_type = $product['type'];
        $reel->productable_id = $product['id'];
        $reel->order_now_button = (bool) ($data['order_now_button'] ?? false);
        $reel->description = $translations[0]['value'] ?? ($data['description'] ?? null);
        $reel->is_always_visible = $alwaysVisible;
        $reel->start_date = $alwaysVisible ? null : ($data['start_date'] ?? null);
        $reel->end_date = $alwaysVisible ? null : ($data['end_date'] ?? null);

        if (! $reel->exists) {
            $reel->created_by_id = $data['created_by_id'] ?? null;
            $reel->created_by_type = $data['created_by_type'] ?? null;
        }

        $this->attachUpload($reel, 'thumbnail', $data['thumbnail'] ?? null);
        $this->attachUpload($reel, 'video', $data['video'] ?? null);

        $reel->save();
        $this->syncTranslations($reel, $translations);

        return $reel;
    }
    private function attachUpload(Reel $reel, string $type, ?UploadedFile $file): void
    {
        if (! $file) {
            return;
        }

        if ($reel->$type) {
            FileStorage::delete(dir: self::IMAGE_DIR, old_image: $reel->$type);
        }

        $reel->$type = $this->storeReelFile($file, self::IMAGE_DIR, $type);
    }
    private function syncTranslations(Reel $reel, array $translations): void
    {
        if (empty($translations)) {
            return;
        }

        $reel->translations()->delete();

        foreach ($translations as $key => $translation) {
            $translations[$key]['translationable_type'] = Reel::class;
            $translations[$key]['translationable_id'] = $reel->id;
        }

        $reel->translations()->insert($translations);
    }
    private function storeReelFile(UploadedFile $file, string $dir, string $type): string
    {
        $this->guardUpload($file, $type);

        $format = $this->extension($file);
        $fileToStore = $file;

        if ($type === 'thumbnail' && in_array($format, self::WEBP_SOURCE_FORMATS, true)) {
            $fileToStore = (new ImageManager(Driver::class))->read($file)->encode(new WebpEncoder(quality: 80))->toString();
            $format = 'webp';
        }

        $fileName = now()->toDateString() . '-' . uniqid() . '.' . $format;
        $disk = FileStorage::getDisk();

        if (! Storage::disk($disk)->exists($dir)) {
            Storage::disk($disk)->makeDirectory($dir);
        }

        $fileToStore instanceof UploadedFile
            ? Storage::disk($disk)->putFileAs($dir, $fileToStore, $fileName)
            : Storage::disk($disk)->put($dir . '/' . $fileName, $fileToStore);

        return $fileName;
    }
    private function guardUpload(UploadedFile $file, string $type): void
    {
        $maxSizeMb = $type === 'video' ? $this->maxVideoSizeMb() : self::THUMBNAIL_MAX_MB;
        $extension = $this->extension($file);

        if (! $extension || ! in_array($extension, $type === 'video' ? self::VIDEO_FORMATS : self::THUMBNAIL_FORMATS, true)) {
            throw new InvalidUploadException($type === 'video'
                ? translate('messages.Reel video format is invalid')
                : translate('messages.Reel thumbnail format is invalid'));
        }

        if ($file->getSize() > ($maxSizeMb * 1024 * 1024)) {
            throw new InvalidUploadException($type === 'video'
                ? translate('messages.Reel video is too large.').' '.translate('messages.Maximum size').': '.$maxSizeMb.' MB'
                : translate('messages.Reel thumbnail is too large.').' '.translate('messages.Maximum size').': '.MAX_FILE_SIZE.' MB');
        }
    }
    private function maxVideoSizeMb(): int
    {
        return max(1, (int) (Helpers::get_business_settings('reels_max_upload_size_mb') ?: 15));
    }
    private function extension(UploadedFile $file): string
    {
        return strtolower($file->getClientOriginalExtension() ?: FileStorage::extensionFromMimeType($file->getMimeType()));
    }
}
