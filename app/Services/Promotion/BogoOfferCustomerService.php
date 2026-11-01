<?php

namespace App\Services\Promotion;

use App\CentralLogics\Helpers;
use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Store;
use App\Services\BaseService;
use App\Traits\Promotion\HandlesBogoBundles;
use App\Traits\Promotion\HandlesBogoCartInspection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The customer's side of BOGO: the home card, the offer list, an offer's participating stores,
 * and the bundles one store runs.
 *
 * Read-only. Everything about what may be shown -- the window, the caps, whether every member of
 * a bundle can actually be served -- is decided by BogoOfferCatalog, which the cart and checkout
 * also read, so what is advertised and what the cart will accept cannot drift apart.
 *
 * Identifiers accept an id or a slug throughout, matching how campaigns are addressed here.
 */
class BogoOfferCustomerService extends BaseService
{
    use HandlesBogoBundles;
    // bogoRemainingUses() comes from here, so what the offer screens promise and what the cart
    // will let a customer add are counted the same way.
    use HandlesBogoCartInspection;

    /**
     * How many more times this customer may use each offer on the page.
     *
     * Attached to the model rather than resolved in the resource, because it counts rows. A null
     * means the offer carries no per-customer cap and the client should show no counter; a guest
     * has no redemption history to read, so the full allowance is reported.
     */
    public function primeRemainingUses($offers, array $owner): void
    {
        foreach ($offers as $offer) {
            $offer->setAttribute('remaining_uses', $this->bogoRemainingUses(
                $offer,
                $owner['user_id'] ?? null,
                (int) ($owner['is_guest'] ?? 0),
                $owner['phone'] ?? null
            ));
        }
    }

    /**
     * The home "BOGO is live" card: whether anything is on, how much, and a short preview.
     *
     * The count is taken before the preview is sliced, so `total_offers` is the real number
     * rather than the size of the page.
     */
    public function homeSummary(array $filters, int $limit = 6): array
    {
        if (! $this->hasContext($filters)) {
            return ['is_live' => false, 'total_offers' => 0, 'offers' => collect()];
        }

        $query = $this->availableQuery($filters);
        $total = (clone $query)->count();

        return [
            'is_live' => $total > 0,
            'total_offers' => $total,
            'offers' => $query->latest()->take($limit)->get(),
        ];
    }

    /** The offer list screen. */
    public function getList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        if (! $this->hasContext($filters)) {
            return $this->emptyPage($paginate);
        }

        return $this->availableQuery($filters)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    /** One offer, by id or slug, if a customer here may see it at all. */
    public function findAvailable(array $filters, string $key): ?BogoOffer
    {
        if (! $this->hasContext($filters)) {
            return null;
        }

        return $this->availableQuery($filters)
            ->where(fn ($q) => $q->where('id', $key)->orWhere('slug', $key))
            ->first();
    }

    /**
     * Every store's bundle for one offer.
     *
     * One store contributes exactly one bundle per offer -- the pivot is unique on the pair -- so
     * this pages over stores, not over bundles.
     */
    public function getBundleList(BogoOffer $offer, array $filters, array $paginate = []): LengthAwarePaginator
    {
        $paginator = app(BogoOfferCatalog::class)
            ->bundleQuery(
                $offer,
                $filters['zone_ids'] ?? [],
                (int) ($filters['module_id'] ?? 0),
                $filters['longitude'] ?? null,
                $filters['latitude'] ?? null
            )
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->primeBundleRows($paginator->getCollection());

        return $paginator;
    }

    /**
     * The offers one store runs, for the store detail screen.
     *
     * This store's own bundle has to be servable, not merely somebody's in the zone: a closed
     * store's page shows no offers rather than offers nobody can order.
     */
    public function getStoreOfferList(Store $store, array $filters, array $paginate = []): LengthAwarePaginator
    {
        if (! $this->hasContext($filters)) {
            return $this->emptyPage($paginate);
        }

        $catalog = app(BogoOfferCatalog::class);

        $servableHere = $catalog->orderableEnrollments(
            $catalog->atServableStore(
                BogoOfferStore::approved()->where('store_id', $store->id),
                $filters['zone_ids'] ?? [],
                (int) ($filters['module_id'] ?? 0)
            )
        )->pluck('bogo_offer_id')->unique()->values()->all();

        $paginator = $this->availableQuery($filters)
            ->whereIn('id', $servableHere)
            ->whereHas('enrollments', fn ($q) => $q->approved()->where('store_id', $store->id))
            // items.item because a free member is valued from its own row and the stock rule
            // reads the live item; store because pricing resolves the window from it. Without
            // these, every bundle on every offer would fetch them one at a time.
            ->with(['enrollments' => fn ($q) => $q->approved()
                ->where('store_id', $store->id)
                ->with(['items.item.module', 'store', 'bogoOffer'])])
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        foreach ($paginator->getCollection() as $offer) {
            $this->primeBundleRows($offer->enrollments);
        }

        return $paginator;
    }

    /**
     * Attach every servable bundle to each store on a page, as one query set for the whole page.
     *
     * Opt-in rather than resolved inside StoreResource: a store card is rendered by dozens of
     * listing endpoints, and making all of them pay for an offer lookup they do not show would be
     * an N+1 introduced everywhere to benefit two screens. A caller that wants the key calls this;
     * the rest omit it and cost nothing.
     */
    public function primeStoreOffers($stores, array $filters): void
    {
        $stores = collect($stores);
        $storeIds = $stores->pluck('id')->filter()->map('intval')->unique()->values()->all();

        if (empty($storeIds) || ! $this->hasContext($filters)) {
            $stores->each(fn ($store) => $store->setAttribute('bogo_offers', []));

            return;
        }

        $catalog = app(BogoOfferCatalog::class);

        // Every enrolment across every store on the page, filtered by the same servability rule
        // the offer screens use, in ONE pass.
        $servable = $catalog->orderableEnrollments(
            $catalog->atServableStore(
                BogoOfferStore::approved()->whereIn('store_id', $storeIds),
                $filters['zone_ids'],
                (int) $filters['module_id']
            ),
            // The caller already fetched these stores WITH their config. Handing them over stops
            // the enrolment query re-fetching the same rows, which put a second identical
            // store_configs select on every store listing that showed offers.
            $stores->keyBy('id')->all()
        )->groupBy('store_id');

        foreach ($stores as $store) {
            $store->setAttribute('bogo_offers', ($servable[$store->id] ?? collect())
                ->map(fn (BogoOfferStore $enrollment) => [
                    'id' => (int) $enrollment->bogo_offer_id,
                    'slug' => $enrollment->bogoOffer?->slug,
                    // Names the offer AT THIS STORE, which an offer id alone cannot when several
                    // stores run the same one. Every bundle endpoint takes this.
                    'bundle_id' => (int) $enrollment->id,
                    'title' => $enrollment->bogoOffer?->title,
                    'image_full_url' => $enrollment->bogoOffer?->image_full_url,
                    'buy_qty' => (int) ($enrollment->bogoOffer?->buy_qty ?? 0),
                    'get_qty' => (int) ($enrollment->bogoOffer?->get_qty ?? 0),
                    'offer_label' => translate('messages.Buy').' '.($enrollment->bogoOffer?->buy_qty ?? 0)
                        .' '.translate('messages.Get').' '.($enrollment->bogoOffer?->get_qty ?? 0),
                    'start_date' => $enrollment->bogoOffer?->start_date?->format('Y-m-d H:i:s'),
                    'end_date' => $enrollment->bogoOffer?->end_date?->format('Y-m-d H:i:s'),
                ])->values()->all());
        }
    }

    /**
     * The store-wide percentage that reaches a bundle right now, or null.
     *
     * A happy hour is the one promotion that does: an ordinary store discount is not taken off a
     * bundle, because the bundle is already the promotion. Resolved through the same helper every
     * item price goes through so the two cannot disagree.
     */
    public function happyHourPercentageFor(?Store $store): ?float
    {
        $discount = Helpers::get_store_discount($store);

        return ($discount['source'] ?? null) === 'happy_hour' ? (float) $discount['discount'] : null;
    }

    /**
     * Whether ANY store-wide promotion is open right now -- a happy hour, or the vendor's own
     * standing store discount.
     *
     * Not the same question as happyHourPercentageFor(), which answers what rate REACHES a bundle
     * and so ignores a plain store discount by design. This one answers whether the bundle may
     * still advertise its own saving, and both promotions silence it: while one is running the
     * store-wide rate is what the customer is getting, so quoting the bundle's percentage next to
     * it reads as two discounts on one line. Same thing ItemResource does to a plain item, which
     * drops its own `discount` to 0 for the window and lets checkout reveal the real cut.
     *
     * Resolved through the same helper every item price goes through, so the two cannot disagree.
     */
    public function storeWidePromotionRunning(?Store $store): bool
    {
        return (float) (Helpers::get_store_discount($store)['discount'] ?? 0) > 0;
    }

    /**
     * Attach the window rate each bundle is priced against.
     *
     * Resolved per store rather than per bundle, and memoised across the page: several bundles in
     * one list routinely belong to the same store, and the lookup is not free. The rate is real —
     * bogoBundlePricing() is responsible for flagging is_happy_hour without cutting the displayed
     * price, the same way ItemResource keeps happy_hour_discount non-zero for its banner while
     * zeroing the item's own applied discount.
     */
    private function primeBundleRows($enrollments): void
    {
        $rates = [];

        foreach ($enrollments as $enrollment) {
            $storeId = (int) $enrollment->store_id;

            if (! array_key_exists($storeId, $rates)) {
                $rates[$storeId] = $this->happyHourPercentageFor($enrollment->store);
            }

            $enrollment->setAttribute('happy_hour_percentage', $rates[$storeId]);
        }
    }

    private function availableQuery(array $filters)
    {
        return app(BogoOfferCatalog::class)->availableOffers(
            $filters['zone_ids'] ?? [],
            (int) ($filters['module_id'] ?? 0),
            $filters['order_type'] ?? null
        );
    }

    /** Zone and module both have to be known before any offer can be scoped to the caller. */
    private function hasContext(array $filters): bool
    {
        return ! empty($filters['zone_ids']) && ! empty($filters['module_id']);
    }

    private function emptyPage(array $paginate): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $this->pageSize($paginate), $this->pageNumber($paginate));
    }
}
