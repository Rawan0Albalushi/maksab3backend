<?php

namespace App\Builder;

use App\Services\Marketing\CashBackService;
use App\Services\Order\DeliveryChargeService;
use App\Services\System\DistanceService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\FreeDeliveryService;
use App\Services\Zone\SurgePriceService;
use App\Services\Zone\ZoneService;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DeliveryRule;
use App\Models\FreeDelivery;
use App\Models\Cart;
use App\Models\CashBackHistory;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\OfflinePaymentMethod;
use App\Models\OfflinePayments;
use App\Models\Store;
use App\Models\User;
use App\Traits\Order\PlaceNewOrderTrait;
use Illuminate\Http\Request;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\DB;
use Modules\Builder\Contracts\CartProvider;
use Modules\Builder\Contracts\CheckoutProvider as CheckoutProviderContract;
use Modules\Builder\Contracts\CouponProvider;
use Modules\Builder\Contracts\LocationProvider;
use Modules\Builder\Contracts\SettingsProvider;
use Modules\Builder\Services\StorefrontContext;
use Modules\Builder\ValueObjects\Storefront\CheckoutQuoteDTO;
use Modules\Builder\ValueObjects\Storefront\CheckoutSnapshotDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

class CheckoutProvider implements CheckoutProviderContract
{
    use PlaceNewOrderTrait;

    public function __construct(
        private StorefrontContext $context,
        private CartProvider $cart,
        private CouponProvider $coupons,
        private LocationProvider $location,
        private SettingsProvider $settings,
    ) {
    }

    /* ─── snapshot ─────────────────────────────────────────── */

    public function snapshot(?StorefrontScope $scope, ?int $customerId): CheckoutSnapshotDTO
    {
        $storeId = $scope?->subTenantId;
        $store   = $storeId ? $this->loadStoreWithOpenFlag($storeId) : null;
        $deliveryTypes = $this->mapDeliveryTypes($store);

        if ($customerId === null) {
            $deliveryTypes['schedule'] = false;
        }

        return CheckoutSnapshotDTO::fromArray([
            'store'          => $this->mapStore($store),
            'deliveryTypes'  => $deliveryTypes,
            'paymentMethods' => $this->mapPaymentMethods($customerId, $store),
            'features'       => $this->mapFeatures($store),
            'tipPresets'     => (array) config('builder.capabilities.checkout.tipPresets', [10, 15, 20, 40]),
            'mostTipped'     => $this->mostTippedAmount(),
            'scheduleSlots'  => ($deliveryTypes['schedule'] && $storeId)
                ? $this->buildScheduleSlots($storeId, $deliveryTypes['scheduleSlotDuration'])
                : [],
            'coverage'       => $this->mapCoverage($store, $scope?->moduleId),
        ]);
    }

    /**
     * The priced areas or ZIP codes the customer must pick from — port doc §15.3.
     *
     * Empty unless the store's (zone, module) is priced by an ACTIVE `area_wise` or
     * `zip_code_wise` rule, which is what hides the picker in distance and fixed zones. Empty
     * too for a self-delivery store, which charges its own rates and has no coverage of its own.
     *
     * @return array{method:string, isZip:bool, options:array<int, array{id:int,name:string}>}|array{}
     */
    private function mapCoverage(mixed $store, ?int $moduleId): array
    {
        if (! $store?->zone_id || ! $moduleId || (int) ($store->sub_self_delivery ?? 0) === 1) {
            return [];
        }

        $payload = app(DeliveryRuleService::class)->coverageForZone($store->zone_id, $moduleId);
        $method = $payload['type'] ?? null;

        if (! in_array($method, [DeliveryRule::METHOD_AREA, DeliveryRule::METHOD_ZIP], true)) {
            return [];
        }

        return [
            'method' => $method,
            'isZip' => $method === DeliveryRule::METHOD_ZIP,
            'options' => array_map(
                fn ($row) => ['id' => (int) $row['id'], 'name' => (string) $row['name']],
                $payload['coverage'] ?? [],
            ),
        ];
    }

    /**
     * The customer's coverage pick, or nulls — port doc §5.4.
     *
     * A customer can post any id. Left unchecked they post the id of a cheaper area in another
     * zone and are charged its rate, so a pick that does not belong to this zone is discarded
     * rather than trusted: the worst case is then the rule's own floor, never someone else's
     * cheaper rate. The engine repeats this check; this is the near line, that is the far one.
     *
     * The storefront posts CAMELCASE (`areaId`, `zipCodeId`) — a host-side
     * `request()->input('area_id')` would find nothing.
     *
     * @return array{0:?int, 1:?int}
     */
    private function validatedCoveragePick(mixed $store, array $state): array
    {
        $areaId = $state['areaId'] ?? null;
        $zipCodeId = $state['zipCodeId'] ?? null;
        $rules = app(DeliveryRuleService::class);

        if ($areaId && ! $rules->coverageBelongsToZone($store->zone_id, (int) $areaId, null)) {
            $areaId = null;
        }

        if ($zipCodeId && ! $rules->coverageBelongsToZone($store->zone_id, null, (int) $zipCodeId)) {
            $zipCodeId = null;
        }

        return [$areaId ? (int) $areaId : null, $zipCodeId ? (int) $zipCodeId : null];
    }

    private function buildScheduleSlots(int $storeId, int $slotDurationMin): array
    {
        $duration = max(5, $slotDurationMin);
        $rows = \DB::table('store_schedule')
            ->where('store_id', $storeId)
            ->get(['day', 'opening_time', 'closing_time']);

        if ($rows->isEmpty()) {
            return [];
        }

        $byDay = [];
        foreach ($rows as $r) {
            $byDay[(int) $r->day][] = [
                'open'  => $r->opening_time,
                'close' => $r->closing_time,
            ];
        }

        $now    = \Carbon\Carbon::now();
        $today  = $now->copy()->startOfDay();
        $result = [];

        for ($i = 0; $i < 7; $i++) {
            $day      = $today->copy()->addDays($i);
            $dow      = (int) $day->dayOfWeek;
            $ranges   = $byDay[$dow] ?? [];
            $slots    = [];

            foreach ($ranges as $range) {
                $start = \Carbon\Carbon::parse(
                    $day->format('Y-m-d') . ' ' . $range['open'],
                );
                $closing = \Carbon\Carbon::parse(
                    $day->format('Y-m-d') . ' ' . $range['close'],
                );

                while ($start->copy()->addMinutes($duration)->lte($closing)) {
                    $end = $start->copy()->addMinutes($duration);
                    if ($i === 0 && $start->lt($now)) {
                        $start = $end;
                        continue;
                    }
                    $slots[] = [
                        'start' => $start->format('H:i'),
                        'end'   => $end->format('H:i'),
                        'iso'   => $start->format('Y-m-d H:i:s'),
                        'label' => $start->format('g:i A') . ' - ' . $end->format('g:i A'),
                    ];
                    $start = $end;
                }
            }

            $result[] = [
                'date'  => $day->format('Y-m-d'),
                'label' => $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : $day->format('D, M j')),
                'slots' => $slots,
            ];
        }

        return $result;
    }

    private function loadStoreWithOpenFlag(int $storeId): ?Store
    {
        $loc  = $this->location->current();
        $lat  = $loc['lat'] ?? null;
        $lng  = $loc['lng'] ?? null;

        $base = Store::query()->where('id', $storeId);
        if ($lat !== null && $lng !== null) {
            $base->withOpen($lng, $lat);
        }
        return $base->first();
    }

    private function mapStore(?Store $store): ?array
    {
        if (!$store) {
            return null;
        }

        $open = isset($store->open) ? (bool) $store->open : true;

        // TC_15 — this used to only ever look at the vendor's own free-delivery flag, so a store
        // whose free delivery came from the admin's zone/module setup (the source checkout's own
        // fee computation already honours) reported no free-delivery status here at all. Checked
        // in the same precedence order effectiveFee() applies at checkout: vendor flag first, then
        // the admin setup.
        $adminFreeDelivery = app(FreeDeliveryService::class)->activeSetup($store->zone_id, $store->module_id);
        $freeDeliveryOver = match (true) {
            (int) ($store->free_delivery ?? 0) === 1 => 0.0,
            $adminFreeDelivery?->type === FreeDelivery::TYPE_ALL => 0.0,
            $adminFreeDelivery?->type === FreeDelivery::TYPE_CRITERIA => (float) ($adminFreeDelivery->minimum_order_amount ?? 0),
            default => null,
        };

        return [
            'id'               => (int) $store->id,
            'name'             => (string) $store->name,
            'open'             => $open,
            'minOrder'         => (float) ($store->minimum_order ?? 0),
            'freeDeliveryOver' => $freeDeliveryOver,
            // Storefront-only: lets the checkout page ask Google's Distance Matrix for a real
            // routed distance to this store, the same way the customer app's own client does
            // before it posts `distance` to /order/checkout-summary. Without this the page has
            // no way to compute one at all and computeDelivery() falls back to a straight-line
            // estimate, which undercharges every distance-priced zone (see haversineKm()).
            'latitude'         => $store->latitude !== null ? (float) $store->latitude : null,
            'longitude'        => $store->longitude !== null ? (float) $store->longitude : null,
        ];
    }

    private function mapDeliveryTypes(?Store $store = null): array
    {
        $delivery = (int) Helpers::get_business_settings('home_delivery_status') === 1;
        $pickup   = (int) Helpers::get_business_settings('takeaway_status') === 1;
        $schedule = (bool) Helpers::get_business_settings('schedule_order');

        if ($store) {
            $delivery = $delivery && (bool) $store->delivery;
            $pickup   = $pickup   && (bool) $store->take_away;
            $schedule = $schedule && (bool) $store->schedule_order;
        }

        return [
            'delivery'             => $delivery,
            'pickup'               => $pickup,
            'schedule'             => $schedule,
            'scheduleSlotDuration' => $this->slotDurationMinutes(),
        ];
    }

    private function slotDurationMinutes(): int
    {
        $raw = (int) (Helpers::get_business_settings('schedule_order_slot_duration') ?? 0);
        if ($raw < 5 || $raw > 240) {
            return 30;
        }
        return $raw;
    }

    private function mapPaymentMethods(?int $customerId, ?Store $store = null): array
    {
        // Zone only narrows the platform setting, never widens it — same rule
        // ConfigService.php applies for the legacy checkout's /config payload — so a method the
        // admin unchecked in "Connect module with zone" (see the screenshot) is hidden here too,
        // instead of being offerable and only rejected once the order is placed.
        $zoneAllowed = app(ZoneService::class)->allowedPaymentMethodsForZoneIds($store?->zone_id);

        $cod             = Helpers::get_business_settings('cash_on_delivery');
        $digital         = Helpers::get_business_settings('digital_payment');
        $offlineEnabled  = $zoneAllowed['offline_payment'] && (int) Helpers::get_business_settings('offline_payment_status') === 1;

        $walletFeaturesEnabled = (bool) \config('builder.wallet_features_enabled', true);
        $partialEnabled  = $walletFeaturesEnabled
            && (int) Helpers::get_business_settings('partial_payment_status') === 1;
        $partialMethod   = Helpers::get_business_settings('partial_payment_method');
        $walletEnabled   = $walletFeaturesEnabled
            && (int) Helpers::get_business_settings('wallet_status') === 1;

        $walletBalance = 0.0;
        if ($walletEnabled && $customerId) {
            $walletBalance = (float) (User::query()->where('id', $customerId)->value('wallet_balance') ?? 0);
        }

        $offlineMethods = $offlineEnabled
            ? OfflinePaymentMethod::query()
                ->where('status', 1)
                ->get(['id', 'method_name', 'method_fields', 'method_informations'])
                ->map(fn ($m) => [
                    'id'           => (int) $m->id,
                    'name'         => (string) $m->method_name,
                    'fields'       => is_array($m->method_fields) ? $m->method_fields
                        : (json_decode((string) $m->method_fields, true) ?: []),
                    'informations' => is_array($m->method_informations) ? $m->method_informations
                        : (json_decode((string) $m->method_informations, true) ?: []),
                ])
                ->all()
            : [];

        $gateways = [];
        if ($zoneAllowed['digital_payment'] && is_array($digital) && (int) ($digital['status'] ?? 0) === 1) {
            foreach (Helpers::getActivePaymentGateways() as $g) {
                $gateways[] = [
                    'key'      => (string) ($g['gateway'] ?? ''),
                    'title'    => (string) ($g['gateway_title'] ?? $g['gateway']),
                    'imageUrl' => $g['gateway_image_full_url'] ?? null,
                ];
            }
        }

        return [
            'cod' => [
                'enabled'           => $zoneAllowed['cash_on_delivery'] && is_array($cod) && (int) ($cod['status'] ?? 0) === 1,
                'allowChangeAmount' => true,
            ],
            'offline' => [
                'enabled' => $offlineEnabled,
                'methods' => $offlineMethods,
            ],
            'gateways' => $gateways,
            'partial' => [
                'enabled' => $partialEnabled,
                'method'  => $partialMethod ?: null,
            ],
            'wallet' => [
                'enabled' => $walletEnabled,
                'balance' => $walletBalance,
            ],
        ];
    }

    private function mapFeatures(?Store $store): array
    {
        $additionalChargeEnabled = (int) Helpers::get_business_settings('additional_charge_status') === 1;
        $additionalChargeAmount  = (float) Helpers::get_business_settings('additional_charge');
        $additionalChargeName    = (string) (Helpers::get_business_settings('additional_charge_name') ?: 'Service Charge');

        $extraPackagingFee = 0.0;
        $extraPackagingEnabled = false;
        if ($store && $this->packagingAllowedForModule($store) && ($cfg = $store->storeConfig ?? null)) {
            $extraPackagingEnabled = (int) ($cfg->extra_packaging_status ?? 0) === 1;
            $extraPackagingFee     = $extraPackagingEnabled ? (float) ($cfg->extra_packaging_amount ?? 0) : 0.0;
        }

        $taxIncluded = false;
        if (\addon_published_status('TaxModule')) {
            $sys = \Modules\TaxModule\Entities\SystemTaxSetup::query()
                ->where('is_active', 1)->where('is_default', 1)->first();
            $taxIncluded = (int) ($sys?->is_included ?? 0) === 1;
        }

        return [
            'tipsEnabled' => (int) Helpers::get_business_settings('dm_tips_status') === 1,
            'additionalCharge' => [
                'enabled' => $additionalChargeEnabled,
                'name'    => $additionalChargeName,
                'amount'  => $additionalChargeAmount,
            ],
            'extraPackaging' => [
                'enabled'  => $extraPackagingEnabled,
                'required' => false,
                'fee'      => $extraPackagingFee,
            ],
            'taxIncluded' => $taxIncluded,
        ];
    }

    private function mostTippedAmount(): ?float
    {
        try {
            return ApiCache::remember('builder_checkout', 'mostTipped', function () {
                $val = DB::table('orders')
                    ->where('dm_tips', '>', 0)
                    ->select('dm_tips', DB::raw('count(*) as n'))
                    ->groupBy('dm_tips')
                    ->orderByDesc('n')
                    ->limit(1)
                    ->value('dm_tips');
                return $val !== null ? (float) $val : null;
            });
        } catch (\Throwable) {
            return null;
        }
    }

    /* ─── quote ────────────────────────────────────────────── */

    /**
     * A saved address id is honoured only for its owner. Everything downstream looked it up with a
     * bare find(), so posting another customer's id copied their address and coordinates into this
     * order -- and the delivery quote doubled as a way to locate them. A guest has no saved ones.
     */
    private function ownAddressOnly(array $state, ?int $customerId): array
    {
        if (!empty($state['addressId']) && (!$customerId || !\App\Models\CustomerAddress::query()
            ->whereKey((int) $state['addressId'])->where('user_id', $customerId)->exists())) {
            unset($state['addressId']);
        }

        return $state;
    }

    public function quote(?StorefrontScope $scope, ?int $customerId, array $state): CheckoutQuoteDTO
    {
        $state = $this->ownAddressOnly($state, $customerId);
        $cart = $this->cart->list();
        $items = $cart['items'] ?? [];

        $itemDiscount   = $this->sumItemLevelDiscount($items);
        $discountedLine = (float) ($cart['totals']['subtotal'] ?? 0);
        $itemPrice      = $discountedLine + $itemDiscount;

        // The store-wide rate -- a happy hour, or the vendor's own standing discount -- takes the
        // WHOLE basket over from the items' own discounts, never both. Same rule
        // PlaceNewOrderTrait::makeOrderDetails() applies at order placement and ItemPricing::compute()
        // applies to a single item's display price, and the one StackFood's own
        // CheckoutProvider::cartPricing() has always followed: a configured rate suppresses the
        // items' own discounts even when it computes to nothing (basket under the minimum spend) --
        // it does not fall back to them, or the customer would see the promotion silently swap
        // depending on basket size.
        //
        // Computed on the GROSS basket ($itemPrice, before $itemDiscount comes off) rather than the
        // item-discounted one: that is what checkAdminDiscount() is charged against everywhere else,
        // and charging it on a smaller, already-discounted base would quietly shrink the discount.
        $basketDiscount = $this->storeWideVerdict($scope, $itemPrice);
        $storeWideDiscount = $basketDiscount['discount'];
        $storeWideSource   = $basketDiscount['source'];

        if ($basketDiscount['rateExists']) {
            $itemDiscount = 0.0;
        }

        $couponCode      = $state['couponCode'] ?? null;
        $couponDiscount  = 0.0;
        $couponTitle     = null;
        $couponError     = null;
        $couponFreeDeliv = false;
        if ($couponCode) {
            // Net of the store-wide reduction too, matching placement: getCalculatedTax() and
            // placeNewOrder() both hand the coupon a basket that already had the store rate taken
            // off. Validating against the gross basket here would let a coupon with a minimum
            // spend pass on the quote and then be refused at placement.
            $base = max(0.0, $itemPrice - $itemDiscount - $storeWideDiscount);
            $r = $this->coupons->validate((string) $couponCode, $customerId, $scope, $base);
            if ($r['ok'] ?? false) {
                $couponCode      = $r['code'] ?? $couponCode;
                $couponDiscount  = (float) ($r['discount'] ?? 0);
                $couponTitle     = $r['title'] ?? null;
                $couponFreeDeliv = (bool) ($r['freeDelivery'] ?? false);
            } else {
                $couponError = (string) ($r['error'] ?? 'Invalid coupon');
                $couponCode  = null;
            }
        }

        $discountedSubtotal = max(0.0, $itemPrice - $itemDiscount - $storeWideDiscount - $couponDiscount);
        $state['couponCode'] = $couponCode;

        $deliveryType = $state['deliveryType'] ?? 'delivery';
        $delivery = $deliveryType === 'pickup'
            ? $this->deliveryQuote(0.0, null, null, ['active' => false, 'reason' => 'pickup'])
            : $this->computeDelivery($scope, $state, $discountedSubtotal, $couponFreeDeliv);

        $deliveryFee = $delivery['fee'];
        $deliveryFeeNote = $delivery['note'];
        $distanceKm = $delivery['distanceKm'];
        $freeDelivery = $delivery['freeDelivery'];

        $tax = $this->computeTax($scope, $customerId, $state, $discountedSubtotal);

        $additionalCharge = (int) Helpers::get_business_settings('additional_charge_status') === 1
            ? (float) Helpers::get_business_settings('additional_charge')
            : 0.0;
        if ($deliveryType === 'pickup') {
            $additionalCharge = 0.0;
        }

        $tipsEnabled = (int) Helpers::get_business_settings('dm_tips_status') === 1;
        $dmTip = ($tipsEnabled && $deliveryType !== 'pickup') ? (float) ($state['tip'] ?? 0) : 0.0;

        $packagingFee = $this->extraPackagingFee($scope, $state);

        $taxIncluded = $this->isTaxIncluded();
        $total = $discountedSubtotal + ($taxIncluded ? 0 : $tax) + $deliveryFee + $additionalCharge + $dmTip + $packagingFee;

        $cashback = $this->computeCashback($customerId, $total);

        return CheckoutQuoteDTO::fromArray([
            'itemPrice'        => $this->roundMoney($itemPrice),
            'itemDiscount'     => $this->roundMoney($itemDiscount),
            'storeWideDiscount' => $this->roundMoney($storeWideDiscount),
            'storeWideSource'  => $storeWideSource,
            'couponCode'       => $couponCode,
            'couponTitle'      => $couponTitle,
            'couponDiscount'   => $this->roundMoney($couponDiscount),
            'couponError'      => $couponError,
            'tax'              => $this->roundMoney($tax),
            'taxEnabled'       => true,
            'taxIncluded'      => $taxIncluded,
            'deliveryFee'      => $this->roundMoney($deliveryFee),
            'deliveryFeeNote'  => $deliveryFeeNote,
            // §12.1 — equal to `deliveryFee` when nothing was taken off, so the storefront can
            // render it unconditionally and compare the two.
            'deliveryFeeBeforeDiscount' => $this->roundMoney($delivery['beforeDiscount']),
            'additionalCharge' => $this->roundMoney($additionalCharge),
            'dmTip'            => $this->roundMoney($dmTip),
            'extraPackaging'   => $this->roundMoney($packagingFee),
            'distance'         => $distanceKm,
            'total'            => $this->roundMoney($total),
            'cashback'         => $cashback,
            'freeDelivery'     => $freeDelivery,
        ]);
    }


    /**
     * The shape computeDelivery() answers in.
     *
     * A named array rather than the four-element list it used to return: S11 added two more
     * values, and a six-element list is where a caller starts destructuring the wrong slot.
     *
     * @return array{fee:float, note:?string, distanceKm:?float, freeDelivery:array, beforeDiscount:float}
     */
    private function deliveryQuote(
        float $fee,
        ?string $note,
        ?float $distanceKm,
        array $freeDelivery,
        ?float $beforeDiscount = null,
    ): array {
        return [
            'fee' => $fee,
            'note' => $note,
            'distanceKm' => $distanceKm,
            'freeDelivery' => $freeDelivery,
            // Defaults to the fee itself, so "nothing was taken off" needs no special case.
            'beforeDiscount' => $beforeDiscount ?? $fee,
        ];
    }

    public function shippingMethods(?StorefrontScope $scope, ?int $customerId): array
    {
        return ['enabled' => false, 'groups' => []];
    }

    public function selectShippingMethod(?StorefrontScope $scope, ?int $customerId, string $cartGroupId, int $shippingMethodId): array
    {
        return ['success' => false, 'error' => 'Order-wise shipping selection is not available.'];
    }

    public function resolveDigitalPaymentReturn(array $query): array
    {
        return ['orderId' => null, 'phone' => null];
    }

    /**
     * The store-wide rate's verdict on this basket -- a happy hour or the vendor's own standing
     * discount -- charged through the same helper placement uses (Helpers::checkAdminDiscount()),
     * so the quote and the bill apply the minimum spend and the cap identically.
     *
     * `rateExists` is true whenever the store has a live rate configured at all, independent of
     * whether it produced a discount here -- callers use it to decide whether the items' own
     * discounts are suppressed (see the call site in quote()).
     *
     * @return array{discount: float, source: ?string, rateExists: bool}
     */
    private function storeWideVerdict(?StorefrontScope $scope, float $grossBasket): array
    {
        $empty = ['discount' => 0.0, 'source' => null, 'rateExists' => false];

        $storeId = $scope?->subTenantId;

        if (! $storeId) {
            return $empty;
        }

        $store = Store::query()->with('discount')->find($storeId);
        $rate = Helpers::get_store_discount($store);

        if (! $rate || ($rate['discount'] ?? 0) <= 0) {
            return $empty;
        }

        $discount = (float) Helpers::checkAdminDiscount(
            price: $grossBasket,
            discount: $rate['discount'],
            max_discount: $rate['max_discount'],
            min_purchase: $rate['min_purchase'],
        );

        return [
            'discount' => $discount,
            // Below the minimum purchase nothing came off, so there is no rate to name.
            'source' => $discount > 0 ? ($rate['source'] ?? null) : null,
            'rateExists' => true,
        ];
    }

    private function sumItemLevelDiscount(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $row) {
            $catalog = $row['item']['price'] ?? null;
            $qty     = (int) ($row['quantity'] ?? 0);
            $line    = (float) ($row['price'] ?? 0);
            if ($catalog === null || $qty === 0) {
                continue;
            }
            $gross = (float) $catalog * $qty;
            if ($gross > $line) {
                $sum += $gross - $line;
            }
        }
        return $sum;
    }

    private function computeDelivery(?StorefrontScope $scope, array $state, float $eligibleAmount, bool $couponFreeDelivery): array
    {
        $storeId = $scope?->subTenantId;
        if (!$storeId) {
            return $this->deliveryQuote(0.0, __('messages.service_not_available_in_this_area'), null, ['active' => false, 'reason' => null]);
        }

        [$destLat, $destLng] = $this->resolveDestinationCoords($state);
        if ($destLat === null || $destLng === null) {
            return $this->deliveryQuote(0.0, 'Select a delivery address to see the delivery fee.', null, ['active' => false, 'reason' => null]);
        }

        // `store_business_model` and the `store_sub` relation are loaded because
        // sub_self_delivery (below) is an accessor that defers to the subscription for
        // subscription-model stores. Without them it either lazy-loads — which strict mode
        // forbids — or silently answers from the raw column.
        $store = Store::query()
            ->select(['id', 'latitude', 'longitude', 'self_delivery_system', 'free_delivery',
                      'per_km_shipping_charge', 'minimum_shipping_charge', 'maximum_shipping_charge',
                      // `module_id` is not decoration: step 7a of the fee pipeline reads the
                      // store's (zone, module) to find the admin's free-delivery setup, and an
                      // unselected column answers NULL — so the storefront skipped that step
                      // entirely and never gave admin free delivery. Found in S11.
                      'minimum_order', 'zone_id', 'module_id', 'store_business_model'])
            ->with('store_sub')
            ->where('id', $storeId)
            ->first();
        if (!$store) {
            return $this->deliveryQuote(0.0, null, null, ['active' => false, 'reason' => null]);
        }

        // A real routed distance, when the storefront managed to get one from Google's Distance
        // Matrix (see mapStore()'s latitude/longitude and CheckoutController::normalizeState()).
        // Straight-line haversine is the fallback only — before the client-side lookup resolves,
        // if it errors, or for a customer whose browser blocked the Maps script. It is always a
        // shorter, cheaper UNDER-estimate of the real route, so a zone priced per km/mile must
        // never treat it as anything but a stopgap: place_order and the REST API's own
        // checkout-summary both price off a client-supplied routed distance, never haversine.
        $clientDistanceKm = isset($state['distance']) ? (float) $state['distance'] : null;
        $distanceKm = $clientDistanceKm !== null && $clientDistanceKm > 0
            ? $clientDistanceKm
            : $this->haversineKm((float) $store->latitude, (float) $store->longitude, $destLat, $destLng);

        // The storefront asks the same question place_order asks. It used to read the raw
        // `self_delivery_system` column instead, which disagrees with the subscription on a
        // subscription-model store — and for four stores on this install that meant the app
        // charged the zone rate while the storefront charged nothing at all, because those
        // stores have no self-delivery rates of their own to fall back on.
        $isSelfDelivery = (int) ($store->sub_self_delivery ?? 0) === 1;

        // TC_16 — the admin's own zone/module threshold (when the setup is amount-based), so the
        // storefront can show "add $X more for free delivery" progress toward it. This is
        // independent of whether the order already qualifies through some OTHER free-delivery
        // source (the vendor's own flag, a coupon, or an "every order" setup with no threshold at
        // all) — those still zero the fee via effectiveFee() below, they just have nothing to show
        // a progress bar against.
        $freeDeliverySetup = app(FreeDeliveryService::class)->activeSetup($store->zone_id, $scope?->moduleId);
        $freeDeliveryThreshold = ($freeDeliverySetup && $freeDeliverySetup->type === FreeDelivery::TYPE_CRITERIA)
            ? (float) ($freeDeliverySetup->minimum_order_amount ?? 0)
            : 0.0;
        $freeDeliveryProgress = function (bool $isFree, ?string $reason) use ($freeDeliveryThreshold, $eligibleAmount): array {
            return [
                'active'       => $isFree,
                'reason'       => $reason,
                'enabled'      => $freeDeliveryThreshold > 0,
                'threshold'    => $freeDeliveryThreshold,
                'qualified'    => $isFree || ($freeDeliveryThreshold > 0 && $eligibleAmount >= $freeDeliveryThreshold),
                'amountNeeded' => $freeDeliveryThreshold > 0 ? max(0.0, $freeDeliveryThreshold - $eligibleAmount) : 0.0,
                'progress'     => $freeDeliveryThreshold > 0 ? min(100.0, ($eligibleAmount / $freeDeliveryThreshold) * 100) : 0.0,
            ];
        };

        $pivot = null;
        if (! $isSelfDelivery) {
            $pivot = \DB::table('module_zone')
                ->where('zone_id', $store->zone_id)
                ->where('module_id', $scope?->moduleId)
                ->first();

            // S19 — a pivot row is not availability. The pair also has to carry both setups, the
            // same test the API applies before it will accept an order: the storefront must not
            // quote a fee for a checkout place_order would refuse, which is what the pivot
            // fallback let it do. Same zero-quote answer as an unconnected module, because to the
            // shopper the two are the same thing.
            $available = $pivot && in_array(
                (int) $scope?->moduleId,
                \App\Models\Zone::withoutGlobalScopes()->find($store->zone_id)?->completeModuleIds() ?? [],
                true
            );

            if (! $available) {
                return $this->deliveryQuote(0.0, 'No delivery pricing rule configured for this zone.', $distanceKm, $freeDeliveryProgress(false, null));
            }
        }

        // Steps 1-6 belong to the one engine (N1) — the storefront never reimplements the
        // arithmetic place_order runs (§15.1). One Builder-only behaviour is still preserved
        // as a compat flag: it clamps to the maximum under a guard the other engines do not
        // share. That one is latent on current data (no zone has a positive maximum below its
        // minimum) and is retired in S5.
        // §15.1 — the pick is passed EXPLICITLY. The engine keys on `area_id` / `zip_code_id`;
        // the storefront posts `areaId` / `zipCodeId`, and nothing in between translates them.
        [$areaId, $zipCodeId] = $this->validatedCoveragePick($store, $state);
        $coverage = $this->mapCoverage($store, $scope?->moduleId);

        $quote = app(DeliveryChargeService::class)->quote([
            'order_type' => 'delivery',
            'distance' => $distanceKm,
            'store' => $store,
            'module_zone_pivot' => $pivot,
            'zone_id' => $store->zone_id,
            'module_id' => $scope?->moduleId,
            'area_id' => $areaId,
            'zip_code_id' => $zipCodeId,
            'surge' => $surge = $this->resolveSurgePriceValue($store->zone_id, $scope?->moduleId, $state['scheduleAt'] ?? null),
        ]);

        $fee = $quote['delivery_charge'];
        // Always 0.00 since A8; kept so the subtraction below reads as the sum it always was.
        $vehicleExtra = $quote['vehicle_extra'];
        $surgeExtra = $quote['surge_amount'];

        // §3.4 — the note states a distance, so it states it in the unit the rate is quoted in.
        // `format()` converts and labels in one call; the hardcoded "%.2f km" this replaces would
        // have said "km" while the fee was being computed per mile.
        $distanceLabel = app(DistanceService::class)->format($distanceKm);

        if ($isSelfDelivery) {
            $note = $fee > 0
                ? sprintf('Based on %s delivery distance', $distanceLabel)
                : 'Store has no per-unit delivery rate set.';
        } else {
            $priced = $fee - $vehicleExtra - $surgeExtra;

            // The note has to describe the SOURCE the engine actually priced from, not the
            // pivot's column: an active delivery rule outranks the pivot (step 2b over 2c), and
            // an area-wise rule priced by the customer's area was still being explained as
            // "7.04 km × zone rate".
            $isRulePriced = ($quote['pricing_source'] ?? null) === DeliveryChargeService::SOURCE_DELIVERY_RULE;
            $needsPick = $isRulePriced && $coverage !== [] && $areaId === null && $zipCodeId === null;
            $isDistancePriced = $isRulePriced
                ? ! $needsPick && $areaId === null && $zipCodeId === null
                : ($pivot->delivery_charge_type ?? 'fixed') === 'distance';

            $note = match (true) {
                // No full stops: a vehicle or surge suffix may be appended below.
                $areaId !== null => 'Delivery rate for the selected area',
                $zipCodeId !== null => 'Delivery rate for the selected zip code',
                // An area or ZIP rule with nothing picked prices at the zone's minimum. Saying
                // so is what tells the customer why the picker above matters.
                $needsPick => 'Minimum delivery charge for this zone — select your '
                    .(($coverage['isZip'] ?? false) ? 'zip code' : 'area').' for the exact rate',
                $isDistancePriced => $priced > 0
                    ? sprintf('%s × zone rate', $distanceLabel)
                    : 'Distance pricing configured but the per-unit rate is zero.',
                default => $priced > 0 ? 'Flat zone delivery fee.' : 'Flat fee for this zone is zero.',
            };

            // The "+ vehicle (…)" clause that stood here is gone with the charge itself (A8):
            // `vehicle_extra` is always 0.00 now, so the branch was dead. Vehicle categories
            // survive as dispatch (A10) and are not part of what the customer pays.

            // §9.3 / POS parity — gated the same way PlaceNewOrderTrait::posSurgeNote() gates
            // POS's tooltip: on the surge amount actually charged, not on whether the admin
            // wrote a note (that used to be the ONLY way this appeared at all, hiding a real
            // surge whenever no note was set). Content differs from POS by design: when the
            // admin's customer-facing note is on and filled in, it stands ALONE — the amount
            // prefix would repeat what the note already says in the admin's own words. Only
            // when no note is configured does it fall back to the plain "Surge price {amount}".
            // REPLACES the breakdown note rather than appending to it: once a surge applies,
            // that is the more useful explanation for the fee. Folded into the one tooltip —
            // showing it as its own line too (Checkout/CheckoutOrderSummary.jsx) duplicated the
            // exact same text a second time whenever an admin note was set, and said nothing at
            // all otherwise.
            $surgeAmount = round($surgeExtra, config('round_up_to_digit'));

            if ($surgeAmount > 0) {
                $adminNote = trim((string) (app(SurgePriceService::class)->customerNote($surge ?? [], (float) $fee) ?? ''));

                $note = $adminNote !== ''
                    ? $adminNote
                    : translate('messages.surge_price') . ' ' . Helpers::format_currency($surgeAmount);
            }
        }

        $effective = $this->effectiveFee(
            (float) $fee,
            $store,
            max(0.0, $eligibleAmount),
            null,
        );
        // §12.1 — what the fee was immediately BEFORE steps 7 to 9 (free delivery, post-engine
        // discounts, saver). The storefront prints it beside the net one so a customer sees the
        // fee and its discount as two lines rather than one already-netted number.
        $beforeDiscount = (float) $fee;

        if ($effective['is_free']) {
            return $this->deliveryQuote(0.0, 'Free delivery (' . $effective['free_by'] . ').', $distanceKm, $freeDeliveryProgress(true, $effective['free_by']), $beforeDiscount);
        }

        if ($couponFreeDelivery && $fee > 0) {
            return $this->deliveryQuote(0.0, 'Coupon includes free delivery.', $distanceKm, $freeDeliveryProgress(true, 'coupon'), $beforeDiscount);
        }

        return $this->deliveryQuote($fee, $note, $distanceKm, $freeDeliveryProgress(false, null), $beforeDiscount);
    }

    /**
     * Surge in the shape DeliveryChargeService expects. A storefront quote must not fail
     * outright because a surge lookup did, so a failure prices as no surge and is logged.
     */
    private function resolveSurgePriceValue($zoneId, $moduleId, ?string $scheduleAt): ?array
    {
        if (!$zoneId || !$moduleId) {
            return null;
        }
        try {
            $when = $scheduleAt ? \Carbon\Carbon::parse($scheduleAt) : \Carbon\Carbon::now();

            return $this->getSurgePriceValue((int) $zoneId, (int) $moduleId, $when);
        } catch (\Throwable $e) {
            \info('Builder quote: surge lookup failed — ' . $e->getMessage());
            return null;
        }
    }

    private function resolveDestinationCoords(array $state): array
    {
        if (!empty($state['lat']) && !empty($state['lng'])) {
            return [(float) $state['lat'], (float) $state['lng']];
        }
        if (!empty($state['addressId'])) {
            $row = \App\Models\CustomerAddress::query()->find((int) $state['addressId']);
            if ($row) {
                return [(float) $row->latitude, (float) $row->longitude];
            }
        }
        $loc = $this->location->current();
        return [$loc['lat'] ?? null, $loc['lng'] ?? null];
    }

    private function resolvePickupOverride(?StorefrontScope $scope): ?array
    {
        if (!$scope?->subTenantId) {
            return null;
        }
        $store = Store::query()
            ->select('latitude', 'longitude', 'address')
            ->find((int) $scope->subTenantId);
        if (!$store || !$store->latitude || !$store->longitude) {
            return null;
        }
        return [
            'lat'     => (float) $store->latitude,
            'lng'     => (float) $store->longitude,
            'address' => (string) ($store->address ?? ''),
        ];
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusM = 6_378_137.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return ($earthRadiusM * 2 * asin(sqrt($a))) / 1000.0;
    }

    private function ensureModuleConfig(?StorefrontScope $scope): void
    {
        if (!$scope?->moduleId) {
            return;
        }
        $current = config('module.current_module_data');
        if ($current && (int) ($current['id'] ?? 0) === (int) $scope->moduleId) {
            return;
        }
        $module = \App\Models\Module::find((int) $scope->moduleId);
        if ($module) {
            config(['module.current_module_data' => $module]);
        }
    }

    private function packagingAllowedForModule(?Store $store): bool
    {
        $moduleType = $store?->module?->module_type;
        if (!$moduleType) {
            return false;
        }

        $raw = Helpers::get_business_settings('extra_packaging_data', false);
        $map = json_decode((string) $raw, true);

        return is_array($map) && (string) ($map[$moduleType] ?? '0') === '1';
    }

    private function extraPackagingFee(?StorefrontScope $scope, array $state): float
    {
        if (!$scope?->subTenantId || empty($state['extraPackaging'])) {
            return 0.0;
        }

        $store = Store::find($scope->subTenantId);
        if (!$store || !$this->packagingAllowedForModule($store)) {
            return 0.0;
        }

        $cfg = $store->storeConfig ?? null;
        if (!$cfg || (int) ($cfg->extra_packaging_status ?? 0) !== 1) {
            return 0.0;
        }

        return (float) ($cfg->extra_packaging_amount ?? 0);
    }

    private function computeTax(?StorefrontScope $scope, ?int $customerId, array $state, float $discountedSubtotal): float
    {
        if (!$scope?->subTenantId || $discountedSubtotal <= 0) {
            return 0.0;
        }

        $this->ensureModuleConfig($scope);

        $user    = $customerId ? User::query()->find($customerId) : null;
        $guestId = $user ? null : $this->context->getGuestId();

        if (!$user && !$guestId) {
            return 0.0;
        }

        try {
            $request = Request::create('', 'POST', [
                'store_id'              => $scope->subTenantId,
                'order_type'            => $state['deliveryType'] === 'pickup' ? 'take_away' : 'delivery',
                'order_amount'          => $discountedSubtotal,
                'coupon_code'           => $state['couponCode'] ?? null,
                'extra_packaging_amount' => $this->extraPackagingFee($scope, $state) > 0 ? 1 : 0,
                'is_prescription'       => false,
                'is_buy_now'            => 0,
                'guest_id'              => $guestId,
            ]);
            $request->headers->set('moduleId', (string) $scope->moduleId);

            if ($user) {
                $request->setUserResolver(fn () => $user);

                $request->merge(['user' => $user]);
            }

            $response = $this->getCalculatedTax($request);
            $payload  = $response->getData(true);
            if (isset($payload['tax_amount'])) {
                $tax = (float) $payload['tax_amount'];
                if ($tax <= 0 && $discountedSubtotal > 0) {
                    $cartRows = Cart::where('user_id', $user?->id ?? (int) $guestId)
                        ->where('module_id', \getModuleId((string) $scope->moduleId))
                        ->selectRaw('is_guest, count(*) as n, sum(price) as total_price')
                        ->groupBy('is_guest')
                        ->get();
                    \info('Builder quote tax: trait returned tax_amount=0 despite payable subtotal', [
                        'store_id'    => $scope->subTenantId,
                        'module_id'   => $scope->moduleId,
                        'user_id'     => $customerId,
                        'guest_id'    => $guestId,
                        'subtotal'    => $discountedSubtotal,
                        'cart_rows'   => $cartRows->toArray(),
                        'tax_status'  => $payload['tax_status'] ?? null,
                        'tax_included' => $payload['tax_included'] ?? null,
                        'taxmodule_published' => \addon_published_status('TaxModule'),
                        'system_tax_active'   => \addon_published_status('TaxModule')
                            ? \Modules\TaxModule\Entities\SystemTaxSetup::query()
                                ->where('is_active', 1)->where('tax_payer', 'vendor')->exists()
                            : null,
                    ]);
                }
                return $tax;
            }

            \info('Builder quote tax: trait returned no tax_amount', [
                'store_id'  => $scope->subTenantId,
                'module_id' => $scope->moduleId,
                'user_id'   => $customerId,
                'guest_id'  => $guestId,
                'payload'   => $payload,
            ]);
        } catch (\Throwable $e) {
            \info('Builder quote tax: trait threw — quoting zero tax. ' . $e->getMessage());
        }

        return 0.0;
    }

    private function isTaxIncluded(): bool
    {
        if (!\addon_published_status('TaxModule')) {
            return false;
        }
        $sys = \Modules\TaxModule\Entities\SystemTaxSetup::query()
            ->where('is_active', 1)->where('is_default', 1)->first();
        return (int) ($sys?->is_included ?? 0) === 1;
    }

    private function computeCashback(?int $customerId, float $orderTotal): ?array
    {
        if (!$customerId || $orderTotal <= 0) {
            return null;
        }
        if (! \config('builder.wallet_features_enabled', true)) {
            return null;
        }
        try {
            $r = app(CashBackService::class)->calculateForCustomer($orderTotal, $customerId);
            if (!is_array($r) || empty($r)) {
                return null;
            }
            return [
                'eligible'    => (float) ($r['cashback_amount'] ?? 0) > 0,
                'amount'      => (float) ($r['cashback_amount'] ?? 0),
                'minPurchase' => (float) ($r['min_purchase'] ?? 0),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function roundMoney(float $value): float
    {
        return round($value, (int) (config('round_up_to_digit') ?? 2));
    }

    /* ─── placeOrder ───────────────────────────────────────── */

    public function placeOrder(?StorefrontScope $scope, ?int $customerId, array $state): array
    {
        $state = $this->ownAddressOnly($state, $customerId);
        $this->ensureModuleConfig($scope);

        if (! \config('builder.wallet_features_enabled', true)) {
            $rawMethod = (string) ($state['paymentMethod'] ?? '');
            $partial   = !empty($state['partialPayment']);
            if ($rawMethod === 'wallet' || $partial) {
                return ['success' => false, 'errors' => [['code' => 'wallet_disabled', 'message' => 'Wallet and partial payment are not available. Please pick another payment method.']]];
            }
        }

        $user    = $customerId ? User::query()->find($customerId) : null;
        $guestId = $user ? null : $this->context->getGuestId();

        if (!$user && !$guestId) {
            return ['success' => false, 'errors' => [['code' => 'auth', 'message' => 'Could not identify the current shopper. Please refresh and try again.']]];
        }

        if (!$user) {
            if (!Helpers::get_business_settings('guest_checkout_status')) {
                return ['success' => false, 'errors' => [['code' => 'is_guest', 'message' => 'Guest checkout is currently disabled. Please sign in to place an order.']]];
            }
            $missing = [];
            if (empty($state['contactName']))  $missing[] = 'name';
            if (empty($state['contactPhone'])) $missing[] = 'phone';
            if (empty($state['contactEmail'])) $missing[] = 'email';
            if ($missing) {
                return ['success' => false, 'errors' => [['code' => 'contact', 'message' => 'Please provide your ' . implode(', ', $missing) . ' to continue.']]];
            }
        }

        $quote = $this->quote($scope, $customerId, $state)->toArray();

        $request = $user
            ? $this->buildPlaceOrderRequest($scope, $user, $state, $quote)
            : $this->buildGuestPlaceOrderRequest($scope, $guestId, $state, $quote);

        $response = $this->placeNewOrder($request);
        $payload  = $response->getData(true);
        $status   = $response->getStatusCode();

        if ($status === 200) {
            $orderId = (int) ($payload['order_id'] ?? 0);

            $this->clearStoreScopedCart(
                $user ? $user->id : (int) $guestId,
                (int) $scope?->moduleId,
                (int) $scope?->subTenantId,
                $user ? 0 : 1,
            );

            if (($state['paymentMethod'] ?? null) === 'offline_payment') {
                $regError = $this->registerOfflinePayment($orderId, $state);
                if ($regError) {
                    return [
                        'success' => false,
                        'errors'  => [['code' => 'offline_payment', 'message' => $regError]],
                    ];
                }
            }

            return [
                'success'         => true,
                'orderId'         => $orderId,
                'paymentRedirect' => $this->paymentRedirectFor(
                    $state['paymentMethod'] ?? null,
                    $orderId,
                    $user?->id ?? (int) $guestId,
                    !$user,
                    $user ? null : $this->normalizePhone($state['contactPhone'] ?? null),
                ),
                'message'         => (string) ($payload['message'] ?? 'Order placed successfully'),
                'total'           => (float) ($payload['total_ammount'] ?? $quote['total']),
                'contactPhone'    => $this->normalizePhone($state['contactPhone'] ?? null),
            ];
        }

        $errors = $payload['errors'] ?? [['code' => 'unknown', 'message' => 'Failed to place order.']];
        if (!is_array($errors[0] ?? null)) {
            $errors = [['code' => 'unknown', 'message' => is_string($errors) ? $errors : 'Failed to place order.']];
        }
        return ['success' => false, 'errors' => $errors];
    }

    private function normalizePhone(?string $phone): ?string
    {
        $trimmed = $phone === null ? null : trim($phone);
        if ($trimmed === null || $trimmed === '') {
            return null;
        }
        return str_starts_with($trimmed, '+') ? $trimmed : '+' . ltrim($trimmed, '+ ');
    }

    private const DIGITAL_GATEWAYS = [
        'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paystack',
        'flutterwave', 'ssl_commerz', 'paytabs', 'paytm', 'paymob_accept',
        'liqpay', 'bkash', 'mercadopago',
    ];

    private function isDigitalGateway(?string $key): bool
    {
        return $key && in_array($key, self::DIGITAL_GATEWAYS, true);
    }

    private function buildPlaceOrderRequest(?StorefrontScope $scope, User $user, array $state, array $quote): Request
    {
        $deliveryType = $state['deliveryType'] ?? 'delivery';
        $orderType    = $deliveryType === 'pickup' ? 'take_away' : 'delivery';
        $scheduleAt   = $deliveryType === 'schedule' ? ($state['scheduleAt'] ?? null) : null;

        [$lat, $lng] = $this->resolveDestinationCoords($state);
        $address = (string) ($state['address'] ?? '');
        if (!$address && !empty($state['addressId'])) {
            $row = \App\Models\CustomerAddress::query()->find((int) $state['addressId']);
            if ($row) {
                $address = (string) $row->address;
            }
        }
        if ($deliveryType === 'pickup') {
            $pickup = $this->resolvePickupOverride($scope);
            if ($pickup) {
                $lat = $pickup['lat'];
                $lng = $pickup['lng'];
                if (!$address) {
                    $address = $pickup['address'];
                }
            }
        }

        $cartRows = $this->loadStoreScopedCart($user->id, (int) $scope?->moduleId, (int) $scope?->subTenantId);
        $cart     = $cartRows->map(fn (Cart $row) => $this->cartRowForOrderDetails($row))->all();

        $rawMethod = $state['paymentMethod'] ?? 'cash_on_delivery';
        $hostMethod = $this->isDigitalGateway($rawMethod) ? 'digital_payment' : $rawMethod;

        $body = [
            'cart'                   => json_encode($cart),
            'order_amount'           => $quote['total'],
            'discount_amount'        => $quote['itemDiscount'],
            'coupon_code'            => $quote['couponCode'],
            'coupon_discount_amount' => $quote['couponDiscount'],
            'coupon_discount_title'  => $quote['couponTitle'],
            'distance'               => $quote['distance'] ?? 0,
            // §15.1 — the priced-coverage pick, sent on the ORDER as well as on the quote, so
            // the fee place_order computes is the fee the customer was shown. The storefront
            // posts camelCase; place_order keys on snake_case.
            'area_id'                => $state['areaId'] ?? null,
            'zip_code_id'            => $state['zipCodeId'] ?? null,
            'order_type'             => $orderType,
            'payment_method'         => $hostMethod,
            'store_id'               => $scope?->subTenantId,
            'address'                => $address,
            'address_type'           => $state['addressType'] ?? 'Delivery',
            'latitude'               => $lat,
            'longitude'              => $lng,
            'house'                  => $state['house'] ?? null,
            'floor'                  => $state['floor'] ?? null,
            'road'                   => $state['road'] ?? null,
            'contact_person_name'    => $state['contactName']  ?? trim(($user->f_name ?? '') . ' ' . ($user->l_name ?? '')),
            'contact_person_number'  => $this->normalizePhone($state['contactPhone'] ?? $user->phone),
            'contact_person_email'   => $state['contactEmail'] ?? $user->email,
            'dm_tips'                => $quote['dmTip'],
            'extra_packaging_amount' => $quote['extraPackaging'],
            'unavailable_item_note'  => $state['unavailableAction'] ?? null,
            'delivery_instruction'   => $state['instructions'] ?? null,
            'order_note'             => $state['orderNote'] ?? null,
            'bring_change_amount'    => $state['bringChange'] ?? 0,
            'schedule_at'            => $scheduleAt,
            'partial_payment'        => !empty($state['partialPayment']) ? 1 : 0,
            'is_buy_now'             => 1,
            'guest_id'               => null,
            // Read only by PlaceNewOrderTrait to skip the Pro-customer delivery-fee benefit for
            // Builder orders (TC_14) — never persisted onto the order itself.
            'order_source'           => 'builder',
        ];

        $request = Request::create('', 'POST', $body);
        $request->headers->set('moduleId', (string) ($scope?->moduleId ?? ''));
        $request->headers->set('zoneId',   json_encode($scope?->regionId ? [$scope->regionId] : []));
        $request->setUserResolver(fn () => $user);

        $request->merge(['user' => $user]);

        return $request;
    }

    private function buildGuestPlaceOrderRequest(?StorefrontScope $scope, int $guestId, array $state, array $quote): Request
    {
        $deliveryType = $state['deliveryType'] ?? 'delivery';
        if ($deliveryType === 'schedule') {
            $deliveryType = 'delivery';
        }
        $orderType    = $deliveryType === 'pickup' ? 'take_away' : 'delivery';
        $scheduleAt   = null;

        [$lat, $lng] = $this->resolveDestinationCoords($state);
        $address = (string) ($state['address'] ?? '');
        if ($deliveryType === 'pickup') {
            $pickup = $this->resolvePickupOverride($scope);
            if ($pickup) {
                $lat = $pickup['lat'];
                $lng = $pickup['lng'];
                if (!$address) {
                    $address = $pickup['address'];
                }
            }
        }

        $cartRows = $this->loadStoreScopedCart($guestId, (int) $scope?->moduleId, (int) $scope?->subTenantId, 1);
        $cart     = $cartRows->map(fn (Cart $row) => $this->cartRowForOrderDetails($row))->all();

        $rawMethod  = $state['paymentMethod'] ?? 'cash_on_delivery';
        $hostMethod = $this->isDigitalGateway($rawMethod) ? 'digital_payment' : $rawMethod;

        $body = [
            'cart'                   => json_encode($cart),
            'order_amount'           => $quote['total'],
            'discount_amount'        => $quote['itemDiscount'],
            'coupon_code'            => $quote['couponCode'],
            'coupon_discount_amount' => $quote['couponDiscount'],
            'coupon_discount_title'  => $quote['couponTitle'],
            'distance'               => $quote['distance'] ?? 0,
            // §15.1 — the priced-coverage pick, sent on the ORDER as well as on the quote, so
            // the fee place_order computes is the fee the customer was shown. The storefront
            // posts camelCase; place_order keys on snake_case.
            'area_id'                => $state['areaId'] ?? null,
            'zip_code_id'            => $state['zipCodeId'] ?? null,
            'order_type'             => $orderType,
            'payment_method'         => $hostMethod,
            'store_id'               => $scope?->subTenantId,
            'address'                => $address,
            'address_type'           => $state['addressType'] ?? 'Delivery',
            'latitude'               => $lat,
            'longitude'              => $lng,
            'house'                  => $state['house'] ?? null,
            'floor'                  => $state['floor'] ?? null,
            'road'                   => $state['road'] ?? null,
            'contact_person_name'    => $state['contactName']  ?? null,
            'contact_person_number'  => $this->normalizePhone($state['contactPhone'] ?? null),
            'contact_person_email'   => $state['contactEmail'] ?? null,
            'dm_tips'                => $quote['dmTip'],
            'extra_packaging_amount' => $quote['extraPackaging'],
            'unavailable_item_note'  => $state['unavailableAction'] ?? null,
            'delivery_instruction'   => $state['instructions'] ?? null,
            'order_note'             => $state['orderNote'] ?? null,
            'bring_change_amount'    => $state['bringChange'] ?? 0,
            'schedule_at'            => $scheduleAt,
            'partial_payment'        => !empty($state['partialPayment']) ? 1 : 0,
            'is_buy_now'             => 1,
            'guest_id'               => $guestId,
            // Read only by PlaceNewOrderTrait to skip the Pro-customer delivery-fee benefit for
            // Builder orders (TC_14) — never persisted onto the order itself.
            'order_source'           => 'builder',
        ];

        $request = Request::create('', 'POST', $body);
        $request->headers->set('moduleId', (string) ($scope?->moduleId ?? ''));
        $request->headers->set('zoneId',   json_encode($scope?->regionId ? [$scope->regionId] : []));

        $request->merge(['user' => null]);

        return $request;
    }

    private function loadStoreScopedCart(int $userId, int $moduleId, int $storeId, int $isGuest = 0)
    {
        return Cart::query()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->get();
    }

    private function cartRowForOrderDetails(Cart $row): array
    {
        return [
            'id'         => (int) $row->id,
            'item_id'    => (int) $row->item_id,
            'item_type'  => $row->item_type,
            'price'      => (float) $row->price,
            'quantity'   => (int) $row->quantity,
            'variation'  => $this->decodeJson($row->variation),
            'variant'    => '',
            'add_on_ids' => $this->decodeJson($row->add_on_ids),
            'add_on_qtys'=> $this->decodeJson($row->add_on_qtys),
        ];
    }

    private function decodeJson($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function createCashBackHistory($order_amount, $user_id, $order_id)
    {
        $cashBack = app(CashBackService::class)->calculateForCustomer(amount: $order_amount, customerId: $user_id);
        if (data_get($cashBack, 'calculated_amount') > 0) {
            $row = new CashBackHistory();
            $row->user_id           = $user_id;
            $row->order_id          = $order_id;
            $row->calculated_amount = data_get($cashBack, 'calculated_amount');
            $row->cashback_amount   = data_get($cashBack, 'cashback_amount');
            $row->cash_back_id      = data_get($cashBack, 'id');
            $row->cashback_type     = data_get($cashBack, 'cashback_type');
            $row->min_purchase      = data_get($cashBack, 'min_purchase');
            $row->max_discount      = data_get($cashBack, 'max_discount');
            $row->save();

            $row?->order()->update(['cash_back_id' => $row->id]);
        }
        return true;
    }

    private function clearStoreScopedCart(int $userId, int $moduleId, int $storeId, int $isGuest = 0): void
    {
        Cart::query()
            ->where('user_id', $userId)
            ->where('is_guest', $isGuest)
            ->where('module_id', $moduleId)
            ->whereHasMorph(
                'item',
                [Item::class, ItemCampaign::class],
                fn ($q) => $q->where('store_id', $storeId),
            )
            ->delete();
    }

    private function registerOfflinePayment(int $orderId, array $state): ?string
    {
        $methodId = (int) ($state['offlinePayment']['methodId'] ?? 0);
        if (!$methodId) {
            return 'Offline payment method is required.';
        }

        $method = OfflinePaymentMethod::query()->where(['id' => $methodId, 'status' => 1])->first();
        if (!$method) {
            return 'Selected offline payment method is no longer available.';
        }

        $offlineFields = (array) ($state['offlinePayment']['fields'] ?? []);
        $customerNote  = (string) ($state['offlinePayment']['customerNote'] ?? '');

        $declared = array_column($method->method_informations ?? [], 'customer_input');
        $info = ['method_id' => $methodId, 'method_name' => $method->method_name];
        foreach ($declared as $key) {
            if (array_key_exists($key, $offlineFields)) {
                $info[$key] = $offlineFields[$key];
            }
        }

        try {
            $row = OfflinePayments::firstOrNew(['order_id' => $orderId]);
            $row->payment_info   = json_encode($info);
            $row->customer_note  = $customerNote;
            $row->method_fields  = json_encode($method->method_fields);
            $row->save();

            \App\Models\Order::query()->where('id', $orderId)->update([
                'order_status'   => 'pending',
                'payment_method' => 'offline_payment',
            ]);
        } catch (\Throwable $e) {
            return 'Could not register offline payment: ' . $e->getMessage();
        }

        return null;
    }

    private function paymentRedirectFor(
        ?string $paymentMethod,
        int $orderId,
        int $payerId,
        bool $isGuest,
        ?string $guestPhone = null,
    ): ?string {
        if (!$this->isDigitalGateway($paymentMethod) || $orderId === 0 || $payerId === 0) {
            return null;
        }

        $callback = url(route(
            'storefront.payment_callback',
            array_filter([
                'orderId' => $orderId,
                'phone'   => $isGuest ? ($guestPhone ?: null) : null,
            ]),
            false,
        ));

        try {
            return route('payment-mobile', [
                'order_id'         => $orderId,
                'customer_id'      => $payerId,
                'payment_method'   => $paymentMethod,
                'payment_platform' => 'web',
                'callback'         => $callback,
            ]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
