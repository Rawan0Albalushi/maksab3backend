<?php

namespace App\Services\Promotion;

use App\Models\HappyHour;
use App\Models\Store;
use App\Services\BaseService;
use App\Traits\Store\StoreDataTrait;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The customer's side of Happy Hour: which stores are in one, and which window is open now.
 *
 * Read-only. The discount itself is never computed here -- it comes from
 * Helpers::get_store_discount(), the same resolver every item price goes through, so a store's
 * happy hour price cannot disagree with the price on its own menu.
 *
 * The catalog is resolved inline rather than constructor-injected: HappyHourCatalog is another
 * service, and a service holding a service is what Rule 4 forbids.
 */
class HappyHourCustomerService extends BaseService
{
    use StoreDataTrait;


    /**
     * Stores here taking part in a happy hour.
     *
     * `running_only` narrows to the stores whose window is open at this moment. That cannot be a
     * WHERE clause -- a window is a schedule, not a column -- so the catalog reduces to a set of
     * ids first and this pages over those, which is what keeps the paginator's count honest.
     *
     * Every row is primed with the full set of windows it is enrolled on -- the one thing this
     * screen shows that an ordinary store card does not.
     */
    public function getStoreList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        $catalog = app(HappyHourCatalog::class);

        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = (int) ($filters['module_id'] ?? 0);

        if (empty($zoneIds) || ! $moduleId) {
            return new LengthAwarePaginator([], 0, $this->pageSize($paginate), $this->pageNumber($paginate));
        }

        $query = $catalog->storesQuery($zoneIds, $moduleId, $filters['longitude'] ?? null, $filters['latitude'] ?? null);

        if (! empty($filters['running_only'])) {
            $query = $catalog->restrictToLive($query, $zoneIds, $moduleId, $filters['longitude'] ?? null, $filters['latitude'] ?? null);
        }

        // discount and storeConfig are read by the resource; module by the packaging rule inside
        // it. Loaded here so a page of stores costs one query each instead of one per row.
        $paginator = $query->with(['discount', 'storeConfig', 'module'])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->primeStoreRows($paginator->getCollection());

        return $paginator;
    }

    /**
     * The happy hour a customer here is under right now, with its window, or null.
     *
     * Exactly one, never a list -- see HappyHourCatalog::runningIn(). Returned as an array pairing
     * the record with its window because the window is computed (a countdown and a store count)
     * and a resource is not allowed to compute it.
     */
    public function findRunning(array $filters): ?array
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = (int) ($filters['module_id'] ?? 0);

        if (empty($zoneIds) || ! $moduleId) {
            return null;
        }

        $catalog = app(HappyHourCatalog::class);
        $happyHour = $catalog->runningIn($zoneIds, $moduleId);

        if (! $happyHour) {
            return null;
        }

        return [
            'happy_hour' => $happyHour,
            'window' => $catalog->windowPayload($happyHour, $zoneIds, $moduleId),
        ];
    }

    /**
     * Every happy hour a store is approved on, running or not.
     *
     * The store screen shows tonight's window beside the ones still to come, so this is the whole
     * enrolled set rather than only the live one.
     */
    public function enrolledWindows(Store $store): array
    {
        if (! $store->relationLoaded('happyHourEnrollments')) {
            return [];
        }

        return $store->happyHourEnrollments
            ->pluck('happyHour')
            ->filter(fn (?HappyHour $happyHour) => $happyHour !== null)
            ->values()
            ->all();
    }

    /**
     * Attach the one thing this screen needs that the store card does not already carry.
     *
     * `is_happy_hour_running`, `happy_hour` and `active_discount` all come from StoreResource,
     * which every store card goes through. Only the full enrolled set is particular here, and it
     * is read off relations the caller already loaded, so this adds no queries.
     */
    private function primeStoreRows($stores): void
    {
        $storeIds = collect($stores)->pluck('id')->filter()->map('intval')->unique()->values()->all();
        $categories = empty($storeIds) ? collect() : $this->topCategories($storeIds, 5);

        foreach ($stores as $store) {
            $store->setAttribute('category_data', $categories[(int) $store->id] ?? []);
            $store->setAttribute('enrolled_happy_hours', $this->enrolledWindows($store));
        }
    }
}
