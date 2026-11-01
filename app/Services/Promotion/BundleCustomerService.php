<?php

namespace App\Services\Promotion;

use App\Models\Bundle;
use App\Scopes\ZoneScope;
use App\Services\BaseService;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Promotion\HandlesBundleCart;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Services\Store\StoreScheduleService;

class BundleCustomerService extends BaseService
{
    use HandlesBundleCart;
    use PersonalizationTrait;

    public function getList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        $bundles = $this->listQuery($filters)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->primeItemAvailability($bundles->getCollection());

        return $bundles->setCollection($this->preferred($bundles->getCollection(), $filters));
    }

    public function getHomeList(array $filters, int $limit): array
    {
        $bundles = $this->listQuery($filters)->limit($limit)->get();

        $this->primeItemAvailability($bundles);

        return $this->preferred($bundles, $filters)->all();
    }

    public function find($id, array $filters): ?Bundle
    {
        $bundle = $this->listQuery($filters)->find($id);

        if ($bundle) {
            $this->primeItemAvailability(new EloquentCollection([$bundle]));
        }

        return $bundle;
    }

    public function pricingFor(iterable $bundles): array
    {
        $presenter = app(BundleGroupPresenter::class);
        $customerService = app(BogoOfferCustomerService::class);
        $rates = [];
        $promotions = [];
        $pricing = [];

        foreach ($bundles as $bundle) {
            $storeId = $bundle->store_id;

            // Both answers memoised per store, not per bundle: a list routinely carries several
            // bundles from the same store and each question is a discount-window lookup.
            if (! array_key_exists($storeId, $rates)) {
                $rates[$storeId] = $bundle->store
                    ? $customerService->happyHourPercentageFor($bundle->store)
                    : null;
                $promotions[$storeId] = $bundle->store
                    ? $customerService->storeWidePromotionRunning($bundle->store)
                    : false;
            }

            $pricing[$bundle->id] = $presenter->bundlePricing(
                $bundle, $rates[$storeId], $promotions[$storeId]
            );
        }

        return $pricing;
    }

    public function unavailableReason(Bundle $bundle): ?string
    {
        return $this->bundleUnavailableReason($bundle, 1);
    }

    /**
     * The customer's own bundles first, the rest in the order the query left them.
     *
     * Ranked by the store selling the bundle: a bundle has no category of its own, and its id is
     * not an item id, so the store is the only affinity `customer_preferences` already scores
     * that a bundle row can be matched on -- the same call BannerService makes for the same
     * reason. It reads the preference summary, which `recordItemAction()` keeps current from the
     * member items a customer carts, views or orders.
     *
     * No-op for a guest, for a customer with no summary in this module yet, and whenever
     * personalisation is switched off -- the trait returns the collection untouched.
     *
     * On the paginated lists this orders the page, not the whole result set: the ordering lives
     * in the collection rather than in SQL, which is how every other bundle-shaped surface
     * (banners, advertisements, flash sale) does it.
     */
    private function preferred(EloquentCollection $bundles, array $filters): EloquentCollection
    {
        if (empty($filters['customer_id'])) {
            return $bundles;
        }

        return $this->reorderByPreference($bundles, (int) $filters['customer_id'], 'store_id', 'store');
    }

    private function primeItemAvailability(EloquentCollection $bundles): void
    {
        $this->primeActiveItems(
            $bundles->flatMap(fn (Bundle $bundle) => $bundle->items->pluck('item_id'))->all()
        );
    }

    private function listQuery(array $filters): Builder
    {
        return Bundle::with(array_merge(
            ['items.item.storage', 'items.item.module', 'store:id,name,logo,zone_id,module_id,status,active', 'store.storage', 'storage'],
            service_addon_active() ? ['items.service.storage'] : [],
        ))
            ->withCount('items')
            ->available()
            // A member deleted out from under the bundle takes its line with it (ON DELETE
            // CASCADE) and leaves the bundle priced for something it no longer contains.
            ->intact()
            ->whereHas('store', fn ($s) => app(StoreScheduleService::class)->scopeOpenNow(
                $s->withoutGlobalScope(ZoneScope::class)->where('status', 1)
            ))
            ->when($filters['module_id'] ?? null, fn ($q, $moduleId) => $q->where('module_id', $moduleId))
            ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($filters['zone_ids'] ?? null, fn ($q, $zoneIds) => $q->whereHas(
                'store',
                fn ($s) => $s->withoutGlobalScope(ZoneScope::class)->whereIn('zone_id', $zoneIds)
            ))
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->search(
                keywords: $search,
                relations: ['store' => 'name', 'translations' => 'value'],
                mainCol: 'name',
                orderByRelevance: false,
            ))
            ->latest();
    }
}
