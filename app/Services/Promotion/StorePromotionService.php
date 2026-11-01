<?php

namespace App\Services\Promotion;

use App\Models\Bundle;
use App\Models\Coupon;
use App\Services\BaseService;
use Illuminate\Support\Collection;

/**
 * The promotions a store card advertises: its BOGO offers, its bundles and its coupons.
 *
 * ONE resolver for all three, because a store card is rendered from a dozen different places --
 * StoreListResource and its ten construction sites, StoreResource, the service module's provider
 * list -- and each of them answering the question its own way is how the same store came to show
 * different offers on the listing and on its own page.
 *
 * Everything here is BATCHED by store id. A card is never rendered alone: it is rendered fifty at
 * a time, and a per-store lookup is fifty round trips per listing (rule 11).
 *
 * ## Which module gets which
 *
 * BOGO and bundles need a line-item cart with priced products, which `config/module.php` already
 * states as the `promotions` capability -- the same flag PromotionModuleCheckMiddleware gates the
 * admin screens on. Bundles additionally reach SERVICE, which sells priced, bundleable services
 * but cannot run a buy-one-get-one on them. Coupons reach everything: a parcel or a ride is as
 * couponable as a meal.
 *
 *   module type             bogo    bundle   coupon
 *   grocery/food/
 *   pharmacy/ecommerce      yes     yes      yes
 *   service                 NO      yes      yes
 *   parcel/rental/
 *   ride-share              no      no       yes
 */
class StorePromotionService extends BaseService
{
    /** Coupon rows a card shows, in the order the payload lists them. */
    private const COUPON_COLUMNS = [
        'id', 'title', 'code', 'coupon_type', 'discount', 'discount_type',
        'min_purchase', 'max_discount', 'start_date', 'expire_date', 'limit', 'store_id',
    ];

    /**
     * Every promotion for a page of stores, keyed by store id.
     *
     * @param  iterable  $stores  the store models already fetched for the page
     * @param  array  $context  zone_ids, module_id, module_type
     * @return array{bogo_offers: array, bundles: array, coupons: array}
     */
    public function batchFor(iterable $stores, array $context): array
    {
        $stores = collect($stores);
        $storeIds = $stores->pluck('id')->filter()->map('intval')->unique()->values()->all();

        if (empty($storeIds)) {
            return ['bogo_offers' => [], 'bundles' => [], 'coupons' => []];
        }

        return [
            'bogo_offers' => $this->bogoOffers($stores, $storeIds, $context),
            'bundles' => $this->bundles($storeIds, $context),
            'coupons' => $this->coupons($storeIds, $context),
        ];
    }

    /** Whether this module type may run BOGO offers at all. */
    public function servesBogo(?string $moduleType): bool
    {
        return (bool) config('module.'.$moduleType.'.promotions', false);
    }

    /**
     * Whether this module type may run bundles.
     *
     * Service is the deliberate addition: it carries priced, bundleable services and the bundle
     * tables already hold `service_id` lines for them, but it cannot run a BOGO, which is why
     * this is a separate question from servesBogo() rather than the same flag read twice.
     */
    public function servesBundles(?string $moduleType): bool
    {
        return $this->servesBogo($moduleType) || $moduleType === 'service';
    }

    /**
     * The store's servable BOGO offers.
     *
     * Delegates to BogoOfferCustomerService, which already resolves and formats these for the
     * offer screens -- a second copy of the servability rule here would be a second answer to
     * "can this store actually serve this offer today".
     */
    private function bogoOffers(Collection $stores, array $storeIds, array $context): array
    {
        if (! $this->servesBogo($context['module_type'] ?? null)) {
            return [];
        }

        // Cloned so priming cannot write bogo_offers onto the caller's own models -- this service
        // answers with arrays and leaves the page's stores exactly as it found them.
        $copies = $stores->map(fn ($store) => clone $store);

        app(BogoOfferCustomerService::class)->primeStoreOffers($copies, [
            'zone_ids' => $context['zone_ids'] ?? [],
            'module_id' => $context['module_id'] ?? null,
        ]);

        return $copies
            ->mapWithKeys(fn ($store) => [(int) $store->id => $store->getAttribute('bogo_offers') ?: []])
            ->all();
    }

    /**
     * The bundles each store is running right now.
     *
     * `available()` is the same scope the bundle endpoints list by, so a bundle advertised on a
     * card is one the bundle screen will actually show when the customer taps through.
     */
    private function bundles(array $storeIds, array $context): array
    {
        if (! $this->servesBundles($context['module_type'] ?? null)) {
            return [];
        }

        $digits = (int) config('round_up_to_digit', 2);

        return Bundle::query()
            ->with(['storage'])
            ->withCount('items')
            ->available()
            // Same integrity guard the bundle endpoints apply, so a card cannot advertise a
            // bundle the bundle screen refuses to list.
            ->intact()
            ->whereIn('store_id', $storeIds)
            ->when($context['module_id'] ?? null, fn ($query, $moduleId) => $query->where('module_id', $moduleId))
            ->latest()
            ->get()
            ->groupBy('store_id')
            ->map(fn ($bundles) => $bundles->map(fn (Bundle $bundle) => [
                'id' => (int) $bundle->id,
                'slug' => $bundle->slug,
                'name' => $bundle->name,
                'image_full_url' => $bundle->image_full_url,
                'item_count' => (int) ($bundle->items_count ?? 0),
                'base_price' => round((float) $bundle->base_price, $digits),
                'bundle_price' => round((float) $bundle->discounted_price, $digits),
                'discount_percentage' => (float) $bundle->discount_percentage,
                'start_date' => $bundle->start_date?->format('Y-m-d H:i:s'),
                'end_date' => $bundle->end_date?->format('Y-m-d H:i:s'),
            ])->values()->all())
            ->map(fn ($rows) => $rows)
            ->mapWithKeys(fn ($rows, $storeId) => [(int) $storeId => $rows])
            ->all();
    }

    /**
     * The coupons a customer could use at each store.
     *
     * Two kinds reach a card: the store's OWN coupons, and the platform-wide ones that are not
     * tied to any store. Zone-scoped coupons are deliberately absent -- their audience is a zone,
     * not a shop, and a card cannot say whether the customer standing in front of it is inside
     * one without the coordinates the listing does not carry.
     *
     * Only the window and the module are filtered here. Per-customer eligibility -- usage limits,
     * first-order status, a named customer list -- is CouponService's job at redemption, and a
     * card is an advertisement rather than a promise: showing a coupon the customer turns out to
     * have spent is how every marketplace does it, and checking it per store per customer would
     * be a query per card.
     */
    private function coupons(array $storeIds, array $context): array
    {
        $today = date('Y-m-d');

        $coupons = Coupon::query()
            ->active()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('expire_date', '>=', $today)
            ->when($context['module_id'] ?? null, fn ($query, $moduleId) => $query->where(
                fn ($sub) => $sub->where('module_id', $moduleId)->orWhereNull('module_id')
            ))
            ->where(fn ($query) => $query
                ->whereIn('store_id', $storeIds)
                ->orWhere(fn ($sub) => $sub->whereNull('store_id')->where('coupon_type', 'default')))
            ->get(self::COUPON_COLUMNS);

        [$storeScoped, $platformWide] = $coupons->partition(fn (Coupon $coupon) => $coupon->store_id !== null);

        $shared = $platformWide->map(fn (Coupon $coupon) => $this->couponRow($coupon))->values()->all();
        $byStore = $storeScoped->groupBy('store_id');

        $rows = [];

        foreach ($storeIds as $storeId) {
            $own = ($byStore[$storeId] ?? collect())
                ->map(fn (Coupon $coupon) => $this->couponRow($coupon))
                ->values()
                ->all();

            $rows[(int) $storeId] = array_merge($own, $shared);
        }

        return $rows;
    }

    /** One coupon, in the shape every store card prints it. */
    private function couponRow(Coupon $coupon): array
    {
        return [
            'id' => (int) $coupon->id,
            'title' => $coupon->title,
            'code' => $coupon->code,
            'coupon_type' => $coupon->coupon_type,
            'discount' => (float) $coupon->discount,
            'discount_type' => $coupon->discount_type,
            'min_purchase' => (float) $coupon->min_purchase,
            'max_discount' => (float) $coupon->max_discount,
            'start_date' => $this->dateOnly($coupon->start_date),
            'expire_date' => $this->dateOnly($coupon->expire_date),
            'limit' => $coupon->limit === null ? null : (int) $coupon->limit,
        ];
    }

    private function dateOnly(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? $value->format('Y-m-d')
            : substr((string) $value, 0, 10);
    }
}
