<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Support\Promotion\AddOnLabels;
use App\Models\BundleItem;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use Modules\Service\Entities\Service;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use App\Support\Promotion\BundleSettings;
use App\Support\Storage\FileStorage;
use App\Traits\Promotion\ProvidesStoreItemPicker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use App\Services\System\ModuleService;
use App\Services\System\TranslationService;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BundleService
{
    use ProvidesStoreItemPicker;

    public function create(Request $request, string $createdBy): Bundle
    {
        $bundle = $this->store($request, $createdBy);

        $this->saveTranslations($request, $bundle);
        $this->notifyStoreOfNewBundle($bundle, $createdBy);

        return $bundle;
    }

    public function modify(Bundle $bundle, Request $request): Bundle
    {
        $this->update($bundle, $request);
        $this->saveTranslations($request, $bundle);

        return $bundle;
    }

    public function findOwned(mixed $id, ?int $moduleId, ?int $storeId, bool $withTranslations = false): ?Bundle
    {
        return Bundle::when($withTranslations, fn ($query) => $query->withAllTranslations())
            ->where('module_id', $moduleId)
            ->when($storeId, fn ($query, $ownStoreId) => $query->where('store_id', $ownStoreId))
            ->find($id);
    }

    public function findManyForPricing(array $ids): Collection
    {
        return $ids
            ? Bundle::with('items')->whereIn('id', array_values(array_unique(array_filter($ids))))->get()->keyBy('id')
            : collect();
    }

    public function findManyForCart(array $ids, ?Collection $knownItems = null): Collection
    {
        if (! $ids) {
            return collect();
        }

        $bundles = Bundle::with($this->cartRelations($knownItems !== null))
            ->whereIn('id', array_values(array_unique(array_filter($ids))))
            ->get()->keyBy('id');

        if ($knownItems !== null) {
            $this->attachKnownItems($bundles, $knownItems);
        }

        return $bundles;
    }

    private function attachKnownItems(Collection $bundles, Collection $knownItems): void
    {
        $missing = [];

        foreach ($bundles as $bundle) {
            foreach ($bundle->items as $line) {
                if ($line->isService() || ! $line->item_id) {
                    continue;
                }

                if ($item = $knownItems->get($line->item_id)) {
                    $line->setRelation('item', $item);

                    continue;
                }

                $missing[] = $line->item_id;
            }
        }

        if (! $missing) {
            return;
        }

        $fetched = Item::with(['module', 'storage'])
            ->whereIn('id', array_values(array_unique($missing)))
            ->get()->keyBy('id');

        foreach ($bundles as $bundle) {
            foreach ($bundle->items as $line) {
                if (! $line->relationLoaded('item') && $fetched->has($line->item_id)) {
                    $line->setRelation('item', $fetched->get($line->item_id));
                }
            }
        }
    }

    public function findForCart(mixed $bundleId, array $zoneIds = []): ?Bundle
    {
        return Bundle::with($this->cartRelations())
            ->when($zoneIds, fn ($query) => $query->whereHas(
                'store',
                fn ($q) => $q->withoutGlobalScope(ZoneScope::class)->whereIn('zone_id', $zoneIds)
            ))
            ->find($bundleId);
    }

    public function countFor(?int $moduleId, ?int $storeId): int
    {
        return Bundle::when($moduleId, fn ($query, $id) => $query->where('module_id', $id))
            ->when($storeId, fn ($query, $id) => $query->where('store_id', $id))
            ->count();
    }

    public function list(?string $search, ?int $moduleId, ?int $storeId, int $perPage): LengthAwarePaginator
    {
        // items.item is loaded only for its current price, which is what the stale-pricing badge
        // compares against each line's frozen item_price. Two columns, one eager-load for the whole
        // page -- deriving it per bundle would be a query per member row (rule 11).
        //
        // `items.item.storage` is loaded for the member IMAGE, and it is not optional.
        // DescribesFrozenItemLine::sourceImageDisk() takes its fast path only when the item AND
        // its storage are both loaded; with the item alone it fell through to a raw
        // `select value from storages` per member. Nine bundles of two members ran eighteen of
        // them -- a textbook N+1 that the row-count budget in BundleAuditTest was catching.
        return Bundle::with(array_merge(
            $storeId ? [] : ['store:id,name'],
            ['storage', 'items', 'items.item:id,price', 'items.item.storage']
        ))
            ->withCount('items')
            ->when($moduleId, fn ($query) => $query->where('module_id', $moduleId))
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->when($search, fn ($query) => $query->search(
                keywords: $search,
                relations: ['store' => 'name', 'translations' => 'value'],
                mainCol: 'name',
                orderByRelevance: false,
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(Request $request, string $createdBy): Bundle
    {
        return DB::transaction(function () use ($request, $createdBy) {
            $store = Store::withoutGlobalScope(ZoneScope::class)->findOrFail($request->input('store_id'));

            $bundle = Bundle::create([
                'store_id' => $store->id,
                'module_id' => $store->module_id,
                'name' => $this->defaultLangValue($request, 'name'),
                'description' => $this->defaultLangValue($request, 'description'),
                'image' => $request->hasFile('image')
                    ? FileStorage::upload('bundle/', $request->file('image'), MAX_FILE_SIZE, IMAGE_FORMAT_FOR_VALIDATION)
                    : null,
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'discount_percentage' => (float) $request->input('discount_percentage', 0),
                'status' => 1,
                'created_by' => $createdBy,
            ]);

            $this->syncItems($bundle, (array) $request->input('items', []));

            return $bundle;
        });
    }

    public function update(Bundle $bundle, Request $request): Bundle
    {
        return DB::transaction(function () use ($bundle, $request) {
            $bundle->fill([
                'name' => $this->defaultLangValue($request, 'name'),
                'description' => $this->defaultLangValue($request, 'description'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'discount_percentage' => (float) $request->input('discount_percentage', 0),
            ]);

            if ($request->hasFile('image')) {
                $bundle->image = FileStorage::update('bundle/', $bundle->image, $request->file('image'), MAX_FILE_SIZE, IMAGE_FORMAT_FOR_VALIDATION);
            }

            $bundle->save();

            $this->syncItems($bundle, (array) $request->input('items', []));

            app(BundleCartService::class)->strandCarts($bundle);

            return $bundle;
        });
    }

    public function setStatus(Bundle $bundle, int $status): void
    {
        $bundle->status = $status;
        $bundle->save();

        if (! $status) {
            app(BundleCartService::class)->strandCarts($bundle);
        }
    }

    public function delete(Bundle $bundle): void
    {
        DB::transaction(fn () => $bundle->delete());
    }

    public function syncItems(Bundle $bundle, array $lines): void
    {
        $bundle->items()->delete();

        $basePrice = 0.0;

        $isService = $this->isServiceModule((int) $bundle->module_id);

        foreach ($lines as $line) {
            $variations = Helpers::decodeJsonToArray($line['variations'] ?? null);

            if ($isService) {
                $service = Service::withoutGlobalScopes()->find($line['service_id'] ?? $line['item_id'] ?? null);

                if (! $service) {
                    continue;
                }

                $unitPrice = $this->serviceUnitPrice($service, $variations);

                // A physical copy, not just the filename -- the service's own thumbnail file gets
                // deleted out from under us the next time the store replaces it (FileStorage::
                // update() deletes-then-uploads), which would leave this frozen line pointing at
                // a 404. See DescribesFrozenItemLine::sourceImageDisk() for how the copy's disk
                // is looked back up.
                $frozenImage = FileStorage::copyStorageFile('service/', $service->thumbnail, FileStorage::getStorageDiskByKey($service, 'thumbnail', 'public'));

                $bundleItem = BundleItem::create([
                    'bundle_id' => $bundle->id,
                    'service_id' => $service->id,
                    'quantity' => 1,

                    'item_name' => $service->getRawOriginal('name'),
                    'item_image' => $frozenImage ?? $service->thumbnail,
                    'unit_price' => $unitPrice,
                    // The member's own price, so a later price change can be spotted without
                    // re-deriving the whole line. See the stale check on the list.
                    'item_price' => $service->price ?? null,
                    'variations' => $variations,
                ]);

                if ($frozenImage) {
                    FileStorage::updateStorageTable(BundleItem::class, $bundleItem->id, $frozenImage);
                }

                $basePrice += $unitPrice;

                continue;
            }

            $item = Item::withoutGlobalScope(StoreScope::class)
                ->withoutGlobalScope(ZoneScope::class)
                ->with('module:id,module_type')
                ->find($line['item_id'] ?? null);

            if (! $item) {
                continue;
            }

            $addOnIds = Helpers::decodeJsonToArray($line['add_on_ids'] ?? null);
            $addOnQtys = Helpers::decodeJsonToArray($line['add_on_qtys'] ?? null);

            $unitPrice = $this->unitPrice($item, $variations, $addOnIds, $addOnQtys);

            // A physical copy, not just the filename -- the item's own image file gets deleted
            // out from under us the next time the store replaces it (FileStorage::update()
            // deletes-then-uploads), which would leave this frozen line pointing at a 404. See
            // DescribesFrozenItemLine::sourceImageDisk() for how the copy's disk is looked back up.
            $frozenImage = FileStorage::copyStorageFile('product/', $item->image, FileStorage::getStorageDiskByKey($item, 'image', 'public'));

            $bundleItem = BundleItem::create([
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'quantity' => 1,

                'item_name' => $item->getRawOriginal('name'),
                'item_image' => $frozenImage ?? $item->image,
                'unit_price' => $unitPrice,
                'item_price' => $item->price,
                'variations' => $variations,
                'add_on_ids' => $addOnIds,
                'add_on_qtys' => $addOnQtys,
            ]);

            if ($frozenImage) {
                FileStorage::updateStorageTable(BundleItem::class, $bundleItem->id, $frozenImage);
            }

            $basePrice += $unitPrice;
        }

        if ($bundle->items()->count() !== count($lines)) {
            throw new RuntimeException('A bundle item could not be resolved; the bundle was not saved.');
        }

        $bundle->forceFill($this->prices($basePrice, (float) $bundle->discount_percentage))->save();
    }

    public function prices(float $basePrice, float $discountPercentage): array
    {
        $digits = (int) config('round_up_to_digit', 2);

        $basePrice = round($basePrice, $digits);
        $discounted = round($basePrice - ($basePrice * $discountPercentage / 100), $digits);

        return [
            'base_price' => (float) $basePrice,
            'discount_percentage' => (float) $discountPercentage,
            'discounted_price' => (float) max(0, $discounted),
        ];
    }

    public function itemPricingChanged(Item $item): bool
    {
        if ($item->wasRecentlyCreated || ! $item->wasChanged(['price', 'variations', 'food_variations'])) {
            return false;
        }

        return $this->itemPricingSignature(
            $item->getOriginal('price'),
            $item->getOriginal('variations'),
            $item->getOriginal('food_variations'),
        ) !== $this->itemPricingSignature($item->price, $item->variations, $item->food_variations);
    }

    private function itemPricingSignature(mixed $price, mixed $variations, mixed $foodVariations): string
    {
        $rows = [];

        foreach (Helpers::decodeJsonToArray($variations) ?? [] as $combination) {
            $rows[] = ($combination['type'] ?? '').'='.(float) ($combination['price'] ?? 0);
        }

        foreach (Helpers::decodeJsonToArray($foodVariations) ?? [] as $group) {
            foreach ($group['values'] ?? [] as $value) {
                $rows[] = ($group['name'] ?? '').':'.($value['label'] ?? '').'='.(float) ($value['optionPrice'] ?? 0);
            }
        }

        sort($rows);

        return (float) $price.'|'.implode(';', $rows);
    }

    public function repriceLinesForItem(Item $item): void
    {
        $lines = BundleItem::where('item_id', $item->getKey())->get();

        if ($lines->isEmpty()) {
            return;
        }

        $item->loadMissing('module');

        foreach ($lines as $line) {
            $line->forceFill([
                'unit_price' => $this->unitPrice($item, $line->variations, $line->add_on_ids, $line->add_on_qtys),
                'item_price' => (float) $item->price,
            ])->save();
        }

        $this->repriceBundles($lines->pluck('bundle_id')->unique()->all());
    }

    public function repriceForItems(array $itemIds): void
    {
        $itemIds = array_values(array_filter(array_unique(array_map('intval', $itemIds))));

        if (! $itemIds) {
            return;
        }

        $lines = BundleItem::whereIn('item_id', $itemIds)->get();

        if ($lines->isEmpty()) {
            return;
        }

        $items = Item::withoutGlobalScope(StoreScope::class)
            ->withoutGlobalScope(ZoneScope::class)
            ->with('module')
            ->whereIn('id', $lines->pluck('item_id')->unique()->all())
            ->get()->keyBy('id');

        foreach ($lines as $line) {
            if (! $item = $items->get($line->item_id)) {
                continue;
            }

            $line->forceFill([
                'unit_price' => $this->unitPrice($item, $line->variations, $line->add_on_ids, $line->add_on_qtys),
                'item_price' => (float) $item->price,
            ])->save();
        }

        $this->repriceBundles($lines->pluck('bundle_id')->unique()->all());
    }

    public function repriceBundles(array $bundleIds): void
    {
        $bundleIds = array_values(array_filter(array_unique($bundleIds)));

        if (! $bundleIds) {
            return;
        }

        foreach (Bundle::with('items')->whereIn('id', $bundleIds)->get() as $bundle) {
            $base = (float) $bundle->items->sum(fn (BundleItem $line) => (float) $line->unit_price);

            $bundle->forceFill($this->prices($base, (float) $bundle->discount_percentage))->save();
        }
    }

    public function seedItems(?Bundle $bundle): array
    {
        if (! $bundle) {
            return [];
        }

        return $bundle->items->map(fn (BundleItem $line) => [
            'item_id' => $line->item_id,
            // Service lines are stored under service_id, never item_id (see BundleItem's
            // guard: "exactly one of item_id or service_id"). Without this, seedBundleItems()
            // in _picker_scripts.blade.php looks up every service line by service_id, finds
            // nothing, and the edit form for a service-module bundle opens with zero items --
            // an update then fails minItems validation even though the bundle already has items.
            'service_id' => $line->service_id,
            'unit_price' => $line->unit_price,
            'selected_variations' => $line->variations ?? [],
            'add_on_ids' => $line->add_on_ids ?? [],
            'add_on_qtys' => $line->add_on_qtys ?? [],
        ])->all();
    }

    public function translationMap(?Bundle $bundle): array
    {
        $map = [];

        foreach ($bundle?->translations ?? [] as $translation) {
            $map[$translation->locale][$translation->key] = $translation->value;
        }

        return $map;
    }

    public function earliestAllowedDate(?Bundle $bundle): string
    {
        return $bundle?->start_date && $bundle->start_date->isPast()
            ? $bundle->start_date->format('Y-m-d\TH:i')
            : now()->format('Y-m-d\TH:i');
    }

    public function addOnLines(Bundle $bundle): array
    {
        return $this->addOnLinesFor([$bundle]);
    }

    /**
     * "Extra Cheese (2)" for every line of every bundle given, keyed by bundle_item id.
     *
     * Batched across the whole set in ONE query because the customer list endpoints present a
     * page of bundles at a time (rule 11). The lookup itself lives in `AddOnLabels` rather than
     * here: BOGO enrolment lines need the identical thing, and neither of them is a Bundle.
     *
     * @param  iterable<int, Bundle>  $bundles
     * @return array<int, array<int, string>>
     */
    public function addOnLinesFor(iterable $bundles): array
    {
        return AddOnLabels::forParents($bundles);
    }

    public function panelList(?string $search, ?int $moduleId, ?int $storeId, string $routePrefix): array
    {
        $this->ensureModuleAllowed($moduleId);

        $bundles = $this->list(
            search: $search,
            moduleId: $moduleId,
            storeId: $storeId,
            perPage: (int) config('default_pagination'),
        );

        return [
            'bundles' => $bundles,
            'routePrefix' => $routePrefix,
            'showStoreColumn' => $storeId === null,
            'ownerLabel' => $this->ownerLabel($moduleId),
            'bundleCount' => $search ? $this->countFor($moduleId, $storeId) : $bundles->total(),
        ];
    }

    public function formData(
        ?Bundle $bundle,
        Collection $stores,
        ?int $moduleId,
        ?int $storeId,
        string $routePrefix,
        string $action,
        string $heading,
        string $submitLabel,
        string $successMessage,
    ): array {
        $this->ensureModuleAllowed($moduleId);

        return [
            'bundle' => $bundle,
            'language' => getWebConfig('language'),
            'translations' => $this->translationMap($bundle),
            'stores' => $stores,
            'store' => ($storeId ? $stores->first() : null) ?? $bundle?->store,
            'storeLocked' => $storeId !== null,
            'minDateTime' => $this->earliestAllowedDate($bundle),
            'isServiceModule' => $this->isServiceModule($moduleId),
            'ownerLabel' => $this->ownerLabel($moduleId),
            'minItems' => Bundle::MIN_ITEMS,
            'maxDiscount' => Bundle::MAX_DISCOUNT_PERCENTAGE,
            'seedItems' => $this->seedItems($bundle),
            'action' => $action,
            'itemsUrl' => route($routePrefix.'.items'),
            'redirectUrl' => route($routePrefix.'.list'),
            'heading' => $heading,
            'description' => translate('messages.Sell a set of items together at one price'),
            'submitLabel' => $submitLabel,
            'successMessage' => $successMessage,
        ];
    }

    public function detailData(Bundle $bundle, string $routePrefix, bool $showStore): array
    {
        $bundle->load(array_merge(
            ['items.item.category:id,name', 'storage'],
            $showStore ? ['store:id,name'] : []
        ));

        return [
            'bundle' => $bundle,
            'routePrefix' => $routePrefix,
            'ownerLabel' => $this->ownerLabel((int) $bundle->module_id),
            'addOnLines' => $this->addOnLines($bundle),
            'lineMeta' => $this->lineMeta($bundle),
            'summaryRows' => $this->summaryRows($bundle, $showStore, $this->ownerLabel((int) $bundle->module_id)),
            'visibilityBadge' => $this->visibilityBadge($bundle),
        ];
    }

    public function pickerOptions(int $storeId, ?string $search, ?int $moduleId): array
    {
        $this->ensureModuleAllowed($moduleId);

        abort_if($storeId <= 0, 404);

        return $this->isServiceModule($moduleId)
            ? $this->storeServicePickerOptions($storeId, $search, $moduleId)
            : $this->storeItemPickerOptions($storeId, $search, $moduleId);
    }

    public function cartRelations(bool $itemsProvided = false): array
    {
        $relations = $itemsProvided
            ? ['items', 'store']
            : ['items.item.module', 'items.item.storage', 'store'];

        return service_addon_active() && class_exists(Service::class)
            ? array_merge($relations, ['items.service', 'items.service.storage'])
            : $relations;
    }

    public function ownerLabel(?int $moduleId): string
    {
        return $this->isServiceModule($moduleId)
            ? translate('messages.Provider')
            : translate('messages.Store');
    }

    public function isServiceModule(?int $moduleId): bool
    {
        if (! $moduleId || ! service_addon_active() || ! class_exists(Service::class)) {
            return false;
        }

        return app(ModuleService::class)->findTypeById($moduleId) === 'service';
    }

    public function exportRows(?string $search, ?int $moduleId, ?int $storeId): Collection
    {
        $this->ensureModuleAllowed($moduleId);

        // items.item.storage for the same reason as list() above: the export renders member
        // images, and without the storage relation each one costs its own query.
        return Bundle::with(['store:id,name', 'storage', 'items', 'items.item:id,price', 'items.item.storage'])
            ->withCount('items')
            ->where('module_id', $moduleId)
            ->when($storeId, fn ($query, $id) => $query->where('store_id', $id))
            ->when($search, fn ($query) => $query->search(
                keywords: $search,
                relations: ['store' => 'name', 'translations' => 'value'],
                mainCol: 'name',
                orderByRelevance: false,
            ))
            ->latest()
            ->get();
    }

    public function findOwnedOrFail(mixed $id, ?int $moduleId, ?int $storeId, bool $withTranslations = false): Bundle
    {
        $this->ensureModuleAllowed($moduleId);

        return $this->findOwned($id, $moduleId, $storeId, $withTranslations) ?? abort(404);
    }

    public function ensureModuleAllowed(?int $moduleId): void
    {
        abort_unless(BundleSettings::allowsModule($moduleId), 404);
    }

    public function ensureOwnStore(?int $panelStoreId, int $postedStoreId): void
    {
        abort_if($panelStoreId !== null && $postedStoreId !== $panelStoreId, 403);
    }

    private function saveTranslations(Request $request, Bundle $bundle): void
    {
        if (! is_array($request->input('lang'))) {
            return;
        }

        foreach (['name', 'description'] as $attribute) {
            if (! is_array($request->input($attribute))) {
                continue;
            }

            app(TranslationService::class)->addOrUpdate(
                request: $request,
                keyData: $attribute,
                nameField: $attribute,
                modelName: Bundle::class,
                dataId: $bundle->id,
                dataValue: $bundle->getRawOriginal($attribute),
                modelClass: true,
            );
        }
    }

    private function notifyStoreOfNewBundle(Bundle $bundle, string $createdBy): void
    {
        if ($createdBy !== 'admin') {
            return;
        }

        try {
            $vendor = $bundle->loadMissing('store.vendor')->store?->vendor;

            if (! $vendor || ! $vendor->firebase_token || $vendor->firebase_token === '@') {
                return;
            }

            SendNotification::pushToVendorPanel(
                $vendor->id,
                $vendor->firebase_token,
                NotificationMessages::bundleCreatedByAdmin($bundle->name),
            );
        } catch (\Throwable $exception) {
            Log::channel(config('notification.log_channel', 'stack'))
                ->warning('bundle.notify_failed', ['bundle_id' => $bundle->id, 'error' => $exception->getMessage()]);
        }
    }

    private function defaultLangValue(Request $request, string $key): ?string
    {
        $languages = (array) $request->input('lang', []);
        $values = (array) $request->input($key, []);

        foreach ($languages as $index => $language) {
            if ($language === 'default') {
                return $values[$index] ?? null;
            }
        }

        return $values[0] ?? null;
    }

    private function lineMeta(Bundle $bundle): array
    {
        return $bundle->items->mapWithKeys(fn (BundleItem $line) => [
            $line->id => implode(' | ', array_filter([
                $line->item?->category?->name,
                $line->variation_label,
            ])),
        ])->all();
    }

    private function visibilityBadge(Bundle $bundle): array
    {
        return match ($bundle->visibilityStatus()) {
            'running' => ['badge-soft-success', translate('messages.Running')],
            'scheduled' => ['badge-soft-info', translate('messages.Scheduled')],
            'ended' => ['badge-soft-secondary', translate('messages.Expired')],
            default => ['badge-soft-danger', translate('messages.Not Visible')],
        };
    }

    private function summaryRows(Bundle $bundle, bool $showStore, string $ownerLabel): array
    {
        return array_filter([
            $ownerLabel => $showStore
                ? ($bundle->store?->name ?? translate('messages.N/A'))
                : null,
            translate('messages.Validity') => ($bundle->start_date ? Helpers::time_date_format($bundle->start_date) : translate('messages.N/A'))
                .' - '.($bundle->end_date ? Helpers::time_date_format($bundle->end_date) : translate('messages.N/A')),
            translate('Base price') => Helpers::format_currency($bundle->base_price),
            translate('messages.Discount') => ($bundle->discount_percentage + 0).'%  ('
                .Helpers::format_currency($bundle->base_price - $bundle->discounted_price).')',
            translate('After discount') => Helpers::format_currency($bundle->discounted_price),
        ], fn ($value) => $value !== null);
    }
}
