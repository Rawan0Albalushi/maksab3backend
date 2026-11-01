<?php

namespace App\Services\Promotion;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Traits\Promotion\HandlesBogoBundles;
use App\Traits\Promotion\HandlesBogoPricing;

/**
 * The read surface for BOGO: what a customer may be shown, and how a bundle is described.
 *
 * One set of rules for every caller -- the customer API, the store page and the AI chat
 * assistant -- because a bundle that appears on one surface and not another, or costs one thing
 * on the offer screen and another in the cart, is the bug this exists to prevent.
 *
 * Every method here is public on purpose. The source left atServableStore() private while a
 * controller called it, which 500'd a live endpoint; a structural test now asserts every call
 * site resolves to a public method, but the simpler fix is not to have private methods that
 * callers plainly need.
 */
class BogoOfferCatalog
{
    use HandlesBogoBundles;
    use HandlesBogoPricing;

    /**
     * Offers a customer here may see: switched on, inside its window, whole-offer cap not
     * reached, and with at least one approved store they can order from.
     */
    public function availableOffers(array $zoneIds, int $moduleId, ?string $orderType = null)
    {
        return BogoOffer::active()->running()->notExhausted()
            ->forModule($moduleId)
            ->whereHas('enrollments', fn ($q) => $this->atServableStore($q->approved(), $zoneIds))
            // A bundle is all or nothing: one member switched off, deleted or out of stock and
            // the whole thing cannot be served, so the offer must not be advertised at all. The
            // cart already refuses such a bundle -- this stops the customer being shown it.
            ->whereIn('id', $this->orderableOfferIds($zoneIds, $moduleId))
            ->when(BogoOffer::orderTypesEnabled() && $orderType, function ($q) use ($orderType) {
                $q->where(function ($inner) use ($orderType) {
                    foreach (['delivery', 'home_delivery'] as $alias) {
                        if ($orderType === $alias || $orderType === 'delivery') {
                            $inner->orWhereJsonContains('order_types', $alias);
                        }
                    }

                    $inner->orWhereJsonContains('order_types', $orderType);
                });
            });
    }

    /**
     * The store side of "may a customer be shown this bundle": in their zones and module,
     * switched on, and open at this moment.
     *
     * A closed store is hidden rather than greyed out. An offer is a reason to visit a store
     * now, so advertising one nobody can order from is worse than not advertising it.
     */
    public function atServableStore($enrollmentQuery, array $zoneIds, ?int $moduleId = null)
    {
        return $enrollmentQuery->whereHas('store', function ($s) use ($zoneIds, $moduleId) {
            $s->active()->whereIn('zone_id', $zoneIds);

            if ($moduleId) {
                $s->where('module_id', $moduleId);
            }
        });
    }

    /**
     * Offers with at least one bundle that can actually be served.
     *
     * Availability is a stock and status question spread across a JSON column and the platform's
     * own listability rule, so it cannot be asked in SQL without reimplementing it. It is
     * resolved through the same trait the cart and checkout use, then applied as an id
     * constraint so the paginator still counts correctly.
     */
    public function orderableOfferIds(array $zoneIds, ?int $moduleId = null): array
    {
        return $this->orderableEnrollments(
            $this->atServableStore(BogoOfferStore::approved(), $zoneIds, $moduleId)
        )->pluck('bogo_offer_id')->unique()->values()->all();
    }

    /**
     * The enrolments in the given query a customer could order from right now.
     *
     * Every item across every bundle is resolved in one query before the rule is asked about any
     * of them -- asked one at a time this listing would pay a query per member, and lazy loading
     * throws outside production anyway.
     */
    /**
     * @param  array  $loadedStores  stores the caller already has, keyed by id. Supplying them
     *                               skips the `store` eager load and attaches these instead --
     *                               a store listing has already fetched them with their config,
     *                               and re-fetching hit store_configs a second time on every such
     *                               request. The availability rule reads the store, so it has to
     *                               be present either way; this only decides who pays for it.
     */
    public function orderableEnrollments($query, array $loadedStores = [])
    {
        $relations = ['items.item.module', 'bogoOffer'];

        if (empty($loadedStores)) {
            $relations[] = 'store';
        }

        $enrollments = $query->with($relations)->get();

        foreach ($loadedStores ? $enrollments : [] as $enrollment) {
            if ($store = $loadedStores[$enrollment->store_id] ?? null) {
                $enrollment->setRelation('store', $store);
            }
        }

        $this->primeActiveItems(
            $enrollments->flatMap(fn ($enrollment) => $enrollment->items->pluck('item_id'))->all()
        );

        return $enrollments->filter(
            fn (BogoOfferStore $enrollment) => $this->enrollmentIsIntact($enrollment)
                && $this->bogoItemsUnavailableReason($enrollment, 1) === null
        );
    }

    /**
     * Whether an enrolment still holds every line it was approved and priced with.
     *
     * `bogo_offer_items.item_id` is declared ON DELETE CASCADE, so deleting a product takes its
     * line out of every enrolment that named it. What is left is not an offer the store agreed
     * to: a "buy 1 get 1" whose free half has vanished, or -- when the same product filled both
     * halves -- an enrolment with NO lines at all.
     *
     * The availability rule cannot catch either, and that is not a bug in it: it walks the lines
     * it is given and reports what is wrong with them, so a line that is gone raises nothing and
     * an empty enrolment passes every check by having nothing to fail. It has to be asked here,
     * before that rule, as a question about the enrolment's own shape.
     *
     * Two things must hold. A BOGO needs BOTH halves -- an offer with nothing to give away is not
     * one. And `bundle_price` is exactly what the buy lines summed to when the enrolment was
     * saved, so a total that no longer adds up proves a buy line has gone, which the halves test
     * alone would miss on an offer that bought two products and lost one.
     *
     * Costs nothing: the lines are already loaded by the caller.
     */
    private function enrollmentIsIntact(BogoOfferStore $enrollment): bool
    {
        $buy = $enrollment->items->where('type', 'buy');
        $get = $enrollment->items->where('type', 'get');

        if ($buy->isEmpty() || $get->isEmpty()) {
            return false;
        }

        $charged = $buy->sum(fn ($line) => (float) $line->price * max(1, (int) $line->quantity));

        return abs($charged - (float) $enrollment->bundle_price) < 0.01;
    }

    /**
     * Approved enrolments for this offer that a customer can order from, with the items needed
     * to price the bundle and draw its thumbnails.
     */
    public function bundleQuery(BogoOffer $offer, array $zoneIds, ?int $moduleId = null, $longitude = null, $latitude = null)
    {
        // One store's bundle can be unservable while another's is fine, so this is filtered per
        // bundle rather than per offer.
        $servableIds = $this->orderableEnrollments(
            BogoOfferStore::where('bogo_offer_id', $offer->id)->approved()
        )->pluck('id')->all();

        return BogoOfferStore::where('bogo_offer_id', $offer->id)
            ->approved()
            ->whereIn('id', $servableIds)
            ->with(['items.item.module', 'store' => function ($q) use ($longitude, $latitude) {
                // withOpen adds selectRaw columns, so it can only go on the eager load -- inside
                // a whereHas it lands in an EXISTS subquery and the SQL is invalid.
                $q->withOpen($longitude, $latitude);
            }])
            ->whereHas('store', function ($q) use ($zoneIds, $moduleId) {
                $q->active()->whereIn('zone_id', $zoneIds);

                if ($moduleId) {
                    $q->where('module_id', $moduleId);
                }
            });
    }

    /** How the offer itself is described, independent of any one store's bundle. */
    public function offerCard(BogoOffer $offer, $userId = null, int $isGuest = 0, ?string $phone = null): array
    {
        return [
            'id' => $offer->id,
            'title' => $offer->title,
            'slug' => $offer->slug,
            'description' => $offer->description,
            'image_full_url' => $offer->image_full_url,
            'buy_qty' => $offer->buy_qty,
            'get_qty' => $offer->get_qty,
            // Derived rather than stored: the same "Buy N Get M" phrasing everywhere a headline
            // is needed (this card, the AI assistant) instead of each caller composing its own.
            'offer_label' => 'Buy '.$offer->buy_qty.' Get '.$offer->get_qty,
            'start_date' => $offer->start_date?->format('Y-m-d H:i:s'),
            'end_date' => $offer->end_date?->format('Y-m-d H:i:s'),
            'valid_until' => $offer->end_date?->format('d M Y'),
            'order_types' => BogoOffer::orderTypesEnabled() ? ($offer->order_types ?: []) : null,
            'remaining_uses' => $this->bogoRemainingUses($offer, $userId, $isGuest, $phone),
        ];
    }

    /**
     * One store's bundle, priced and explained.
     *
     * bundle_id is the enrolment's own id and is what every write endpoint expects back. It is
     * not the offer id, and the two are never interchangeable -- several stores run one offer.
     */
    public function bundleCard(BogoOfferStore $enrollment, ?float $happyHourPercentage = null, bool $withStore = true): array
    {
        $pricing = $this->bundlePricingFor($enrollment, $happyHourPercentage);

        $card = [
            'bundle_id' => $enrollment->id,
            'bogo_offer_id' => $enrollment->bogo_offer_id,
            'offer_title' => $enrollment->bogoOffer?->title,
            'offer_slug' => $enrollment->bogoOffer?->slug,
            'is_available' => $this->bogoItemsUnavailableReason($enrollment, 1) === null,
            'unavailable_reason' => $this->bogoItemsUnavailableReason($enrollment, 1),
            'buy_items' => $enrollment->items->where('type', 'buy')->map(fn ($l) => $this->linePayload($l))->values()->all(),
            'free_items' => $enrollment->items->where('type', 'get')->map(fn ($l) => $this->linePayload($l))->values()->all(),
        ] + $pricing;

        if ($withStore) {
            $card['store'] = $enrollment->store ? [
                'id' => $enrollment->store->id,
                'name' => $enrollment->store->name,
                'slug' => $enrollment->store->slug,
            ] : null;
        }

        return $card;
    }

    /**
     * The bundle's price block, exposed so the API resources can build it too.
     *
     * A thin public door onto HandlesBogoPricing::bogoBundlePricing(), which is protected and so
     * unreachable from a Resource. Routing the offer screens, the cart and the checkout through
     * one method is the point: the cart was the surface that once forgot to mention a running
     * happy hour, and quoting a bundle at two prices is the bug that produced.
     */
    public function bundlePricingFor(BogoOfferStore $enrollment, ?float $happyHourPercentage = null): array
    {
        return $this->bogoBundlePricing($enrollment, $happyHourPercentage);
    }

    /**
     * One frozen line, described from the snapshot rather than the live menu.
     *
     * The name and image are the enrolment's copies on purpose: they still read correctly after
     * the item is renamed or deleted, which is the whole reason they were frozen.
     */
    private function linePayload($line): array
    {
        return [
            'item_id' => $line->item_id,
            'service_id' => $line->service_id,
            'name' => $line->item_name,
            'image' => $line->item_image,
            'quantity' => $line->quantity,
            'price' => (float) $line->price,
            'original_price' => (float) $line->original_price,
            'variations' => $line->variations ?: [],
            'variation_summary' => implode(', ', $line->variationLabels()),
            'add_on_ids' => $line->add_on_ids ?: [],
            'add_on_qtys' => $line->add_on_qtys ?: [],
        ];
    }
}
