<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Services\Order\OrderService;
use App\Http\Resources\Common\Order\OrderDetailResource;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Builder\Contracts\OrderProvider as OrderProviderContract;
use Modules\Builder\ValueObjects\PaginatedResult;
use Modules\Builder\ValueObjects\Storefront\OrderDetailDTO;
use Modules\Builder\ValueObjects\Storefront\OrderSummaryDTO;
use Modules\Builder\ValueObjects\StorefrontScope;

class OrderProvider implements OrderProviderContract
{
    private const STATUS_LABEL = [
        'pending'                 => 'Pending',
        'failed'                  => 'Pending',
        'confirmed'               => 'On the way',
        'accepted'                => 'On the way',
        'processing'              => 'On the way',
        'handover'                => 'On the way',
        'picked_up'               => 'On the way',
        'delivered'               => 'Delivered',
        'canceled'                => 'Canceled',
        'refund_requested'        => 'Refund requested',
        'refund_request_canceled' => 'Refund canceled',
        'refunded'                => 'Refunded',
        'returned'                => 'Returned',
    ];

    private const STATUS_VARIANT = [
        'pending'                 => 'info',
        'failed'                  => 'info',
        'confirmed'               => 'warning',
        'accepted'                => 'warning',
        'processing'              => 'warning',
        'handover'                => 'warning',
        'picked_up'               => 'warning',
        'delivered'               => 'success',
        'canceled'                => 'danger',
        'refund_requested'        => 'warning',
        'refund_request_canceled' => 'warning',
        'refunded'                => 'danger',
        'returned'                => 'danger',
    ];

    private const ROUTE_ACTIVE_STATUSES = ['handover', 'picked_up'];

    public function customerOrderListing(
        ?StorefrontScope $scope,
        ?int $customerId,
        string $bucket,
        array $statuses,
        int $perPage,
        int $page,
        string $pageName,
    ): PaginatedResult {
        if (!$customerId) {
            return PaginatedResult::fromPaginator(
                new LengthAwarePaginator([], 0, $perPage, $page, ['pageName' => $pageName])
            );
        }

        $predicate = $bucket === 'previous'
            ? fn (EloquentBuilder $q) => $q->whereIn('order_status', $statuses)
            : fn (EloquentBuilder $q) => $q->whereNotIn('order_status', $statuses);

        $paginator = $predicate($this->customerOrdersBaseQuery($scope, $customerId))
            ->withCount('details')
            ->orderByDesc('created_at')
            ->paginate(
                perPage: $perPage,
                columns: ['id', 'order_status', 'order_type', 'order_amount', 'payment_status', 'created_at'],
                pageName: $pageName,
                page: $page,
            )
            ->through(fn (Order $order) => OrderSummaryDTO::fromArray([
                'id'            => $order->id,
                'status'        => $order->order_status,
                'statusLabel'   => $this->statusLabel($order->order_status),
                'statusVariant' => $this->statusVariant($order->order_status),
                'amount'        => (float) $order->order_amount,
                'items'         => (int) $order->details_count,
                'paid'          => $order->payment_status === 'paid',
                'reorderable'   => (string) ($order->order_type ?? '') !== 'parcel',
                'reviewable'    => $order->order_status === 'delivered'
                                   && (string) ($order->order_type ?? '') !== 'parcel',
                'date'          => $order->created_at ? Carbon::parse($order->created_at)->format('h:iA, d M y') : null,
            ])->toArray());

        return PaginatedResult::fromPaginator($paginator);
    }

    public function customerOrdersCount(?StorefrontScope $scope, ?int $customerId): int
    {
        if (!$customerId) {
            return 0;
        }
        return $this->customerOrdersBaseQuery($scope, $customerId)->count();
    }

    private function customerOrdersBaseQuery(?StorefrontScope $scope, int $customerId): EloquentBuilder
    {
        return Order::query()
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn (EloquentBuilder $q) => $q->where('store_id', $scope->subTenantId),
            );
    }

    public function latestUnpaidDigitalOrder(?StorefrontScope $scope, ?int $customerId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $order = $this->customerOrdersBaseQuery($scope, $customerId)
            ->where('payment_status', 'unpaid')
            ->where('order_status', 'failed')
            ->orderByDesc('created_at')
            ->first(['id', 'order_amount', 'partially_paid_amount']);

        if (!$order) {
            return null;
        }

        return [
            'orderId'   => (int) $order->id,
            'dueAmount' => (float) ($order->order_amount - ($order->partially_paid_amount ?? 0)),
        ];
    }

    public function paymentReturnInfo(int $orderId): ?array
    {
        $order = Order::query()
            ->select(['id', 'is_guest', 'user_id', 'delivery_address', 'payment_status'])
            ->where('id', $orderId)
            ->first();

        if (!$order) {
            return null;
        }

        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);

        return [
            'isGuest'       => (int) $order->is_guest === 1,
            'storedPhone'   => $stored['contact_person_number'] ?? null,
            'paymentStatus' => $order->payment_status !== null ? (string) $order->payment_status : null,
        ];
    }

    public function customerOrderDetails(?StorefrontScope $scope, ?int $customerId, int $orderId): ?array
    {
        if (!$customerId) {
            return null;
        }

        $order = Order::query()
            ->with([
                'details', 'store', 'customer', 'module:id,module_type',
                'delivery_man.rating',
                'delivery_man.last_location',
            ])
            ->where('id', $orderId)
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();

        return $order ? $this->formatOrder($order) : null;
    }

    public function formatOrder(Order $order): array
    {
        $detailsHydrated = OrderDetailResource::renderList($order->details, app(OrderService::class)->detailImages($order->details));

        $rawStatus = (string) $order->order_status;
        $paid      = $order->payment_status === 'paid';

        $cancellable = in_array($rawStatus, ['pending', 'failed'], true);
        $refundable  = $rawStatus === 'delivered'
            && $paid
            && (bool) \config('builder.wallet_features_enabled', true);
        $reorderable = (string) ($order->order_type ?? '') !== 'parcel';
        $reviewable = $rawStatus === 'delivered'
            && (string) ($order->order_type ?? '') !== 'parcel';

        $verificationCode = (int) (Helpers::get_business_settings('order_delivery_verification') ?? 0) === 1
            && ! in_array($rawStatus, ['delivered', 'canceled', 'failed', 'refunded', 'returned'], true)
            && ! empty($order->otp)
                ? (string) $order->otp
                : null;

        return OrderDetailDTO::fromArray([
            'id'             => (string) $order->id,
            'date'           => $order->created_at
                ? Carbon::parse($order->created_at)->format('h:iA, d M y')
                : null,
            'scheduled'      => (bool) $order->scheduled,
            'scheduleAt'     => ((bool) $order->scheduled) && $order->schedule_at
                ? Carbon::parse($order->schedule_at)->format('h:iA, d M y')
                : null,
            'status'         => $this->statusLabel($rawStatus),
            'statusRaw'      => $rawStatus,
            'statusVariant'  => $this->statusVariant($rawStatus),
            'paid'           => $paid,
            'cancellable'    => $cancellable,
            'refundable'     => $refundable,
            'reorderable'    => $reorderable,
            'reviewable'     => $reviewable,
            'paymentMethod'  => $this->paymentLabel($order->payment_method),
            'paymentIcon'    => $this->paymentIcon($order->payment_method),
            'orderType'      => (string) ($order->order_type ?? 'delivery'),
            'moduleType'     => $order->module?->module_type,
            'items'          => $this->mapItems($detailsHydrated),
            'pricing'        => $this->mapPricing($order, $detailsHydrated),
            'delivery'       => $this->mapDelivery($order),
            'seller'         => $this->mapSeller($order->store),
            'deliveryMan'    => $this->mapDeliveryMan($order->delivery_man),
            'tracking'       => $this->mapTracking($order),
            'offlinePayment' => $this->mapOfflinePayment($order),
            'changeAmount'   => (int) ($order->bring_change_amount ?? 0) > 0 ? (float) $order->bring_change_amount : null,
            'cancellationNote' => $order->cancellation_note ?: null,
            'verificationCode' => $verificationCode,
        ])->toArray();
    }

    private function mapOfflinePayment(Order $order): ?array
    {
        if ($order->payment_method !== 'offline_payment') {
            return null;
        }

        $record = $order->offline_payments;
        if (! $record) {
            return null;
        }

        $info = \json_decode($record->payment_info ?? '[]', true);
        if (! \is_array($info)) {
            $info = [];
        }

        $labels = [
            'method_name'    => translate('Payment method'),
            'name'           => translate('Payment by'),
            'date'           => translate('Date'),
            'transaction_id' => translate('Transaction ID'),
        ];

        $fields = [];
        foreach ($info as $key => $value) {
            if ($key === 'method_id' || $value === null || $value === '') {
                continue;
            }
            $fields[] = [
                'label' => $labels[$key] ?? \ucwords(\str_replace('_', ' ', (string) $key)),
                'value' => (string) $value,
            ];
        }

        return [
            'methodName' => $info['method_name'] ?? null,
            'status'     => (string) ($record->status ?? ''),
            'fields'     => $fields,
        ];
    }

    private function mapItems(array $details): array
    {
        $items = [];
        foreach ($details as $row) {
            $unitPrice = (float) ($row['price'] ?? 0);
            $qty       = (int) ($row['quantity'] ?? 0);
            $name      = $row['item_details']['name'] ?? null;
            if (!$name) {
                $name = 'Item';
            }

            $items[] = [
                'id'        => (int) ($row['id'] ?? 0),
                'name'      => (string) $name,
                'variant'   => (string) ($this->variantLabel($row['variation'] ?? null)
                    ?? $this->cleanScalar($row['variant'] ?? null)
                    ?? ''),
                'addons'    => (string) ($this->addOnsLabel($row['add_ons'] ?? null) ?? ''),
                'unitPrice' => $unitPrice,
                'qty'       => $qty,
                'total'     => max(0.0, ($unitPrice * $qty)),
                'image'     => $row['image_full_url'] ?? null,
            ];
        }
        return $items;
    }

    private function cleanScalar(mixed $value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim((string) $value, " \t\n\r\0\x0B\"'");
        if ($trimmed === '' || strcasecmp($trimmed, 'null') === 0) {
            return null;
        }
        return $trimmed;
    }

    private function variantLabel(mixed $variation): ?string
    {
        if (!is_array($variation) || empty($variation)) {
            return null;
        }

        $parts = [];
        foreach ($variation as $key => $value) {
            if (!is_array($value)) {
                if ($value !== null && $value !== '') {
                    $parts[] = is_string($key) ? "$key: $value" : (string) $value;
                }
                continue;
            }

            if (isset($value['values']) && is_array($value['values'])) {
                $labels = [];
                foreach ($value['values'] as $v) {
                    if (is_array($v)) {
                        $label = $v['label'] ?? $v['value'] ?? '';
                        $price = isset($v['optionPrice']) ? (float) $v['optionPrice'] : 0.0;
                        if ($label === '') continue;
                        $labels[] = $price > 0
                            ? $label . ' (+$' . number_format($price, 2) . ')'
                            : (string) $label;
                    } elseif ((string) $v !== '') {
                        $labels[] = (string) $v;
                    }
                }
                if (!empty($labels)) {
                    $name = $value['name'] ?? (is_string($key) ? $key : null);
                    $parts[] = $name
                        ? $name . ': ' . implode(', ', $labels)
                        : implode(', ', $labels);
                }
                continue;
            }

            if (isset($value['type']) && $value['type'] !== '') {
                $parts[] = (string) $value['type'];
                continue;
            }

            if (isset($value['name']) && isset($value['value'])) {
                $parts[] = $value['name'] . ': ' . $value['value'];
            }
        }

        return $parts ? implode(' • ', $parts) : null;
    }

    private function addOnsLabel(mixed $addOns): ?string
    {
        if (is_string($addOns)) {
            $addOns = json_decode($addOns, true);
        }
        if (!is_array($addOns) && !is_object($addOns)) {
            return null;
        }
        $addOns = (array) $addOns;
        if (empty($addOns)) {
            return null;
        }

        $labels = [];
        foreach ($addOns as $addOn) {
            $addOn = is_object($addOn) ? (array) $addOn : $addOn;
            if (!is_array($addOn)) continue;
            $name = $addOn['name'] ?? null;
            if (!$name) continue;
            $qty   = max(1, (int) ($addOn['quantity'] ?? 1));
            $price = (float) ($addOn['price'] ?? 0);
            $line  = "$name × $qty";
            if ($price > 0) {
                $line .= ' ($' . number_format($price * $qty, 2) . ')';
            }
            $labels[] = $line;
        }

        return $labels ? implode(', ', $labels) : null;
    }

    private function mapPricing(Order $order, array $details): array
    {
        $itemPrice   = 0.0;
        $addonsPrice = 0.0;

        foreach ($details as $row) {
            $itemPrice   += (float) ($row['price'] ?? 0) * (int) ($row['quantity'] ?? 0);
            $addonsPrice += (float) ($row['total_add_on_price'] ?? 0);
        }

        $subtotal = $itemPrice + $addonsPrice;
        $discount = (float) ($order->store_discount_amount ?? 0)
                  + (float) ($order->flash_admin_discount_amount ?? 0)
                  + (float) ($order->flash_store_discount_amount ?? 0);

        // Same two sources CheckoutProvider::quote() labels as storeWideSource, read back off
        // the order rather than re-resolved live: a happy hour or standing discount can end or
        // change after the order is placed, and the badge has to keep naming what was ACTUALLY
        // applied then, not what would apply now. happy_hour_id is set alongside
        // store_discount_amount only when that amount came from a happy hour (see
        // PlaceNewOrderTrait, every order-building path). A flash-sale discount is neither —
        // it is a different promotion StoreDiscountResolver does not resolve at all — so it gets
        // no source label and no tooltip.
        $discountSource = $order->happy_hour_id
            ? 'happy_hour'
            : ((float) ($order->store_discount_amount ?? 0) > 0 ? 'store_discount' : null);
        $vatTax = $order->tax_status === 'included'
            ? 0.0
            : (float) ($order->total_tax_amount ?? 0);

        $additionalChargeLabel = (string) (Helpers::get_business_settings('additional_charge_name')
            ?: 'Additional Charge');

        return [
            'itemPrice'             => $itemPrice,
            'addonsPrice'           => $addonsPrice,
            'subtotal'              => $subtotal,
            'discount'              => $discount,
            'discountSource'        => $discountSource,
            'couponDiscount'        => (float) ($order->coupon_discount_amount ?? 0),
            'vatTax'                => $vatTax,
            'taxIncluded'           => $order->tax_status === 'included',
            'dmTips'                => (float) ($order->dm_tips ?? 0),
            'deliveryCharge'        => (float) ($order->delivery_charge ?? 0),
            // TC_15/TC_19 — surfaced so the order-details screen can show the same free-delivery
            // and self-delivery status the admin panel and invoice already show, instead of
            // silently omitting it.
            'freeDelivery'          => (bool) $order->free_delivery_by,
            'freeDeliveryBy'        => $order->free_delivery_by,
            'isSelfDelivery'        => $order->wasSelfDelivery(),
            'additionalCharge'      => (float) ($order->additional_charge ?? 0),
            'additionalChargeLabel' => $additionalChargeLabel,
            'extraPackaging'        => (float) ($order->extra_packaging_amount ?? 0),
            'total'                 => (float) ($order->order_amount ?? 0),
        ];
    }

    private function mapDelivery(Order $order): array
    {
        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);

        $fallback = $order->delivery_address_id
            ? CustomerAddress::query()->find($order->delivery_address_id)
            : null;

        $field = function (string $primaryKey, ?string $fallbackAttr = null) use ($stored, $fallback) {
            if (!empty($stored[$primaryKey])) {
                return $stored[$primaryKey];
            }
            if ($fallback && $fallbackAttr && !empty($fallback->{$fallbackAttr})) {
                return $fallback->{$fallbackAttr};
            }
            return null;
        };

        return [
            'label'   => $field('address_type', 'address_type'),
            'address' => $field('address', 'address'),
            'floor'   => $field('floor', 'floor'),
            'house'   => $field('house', 'house'),
            'road'    => $field('road', 'road'),
            'name'    => $field('contact_person_name', 'contact_person_name'),
            'phone'   => $field('contact_person_number', 'contact_person_number'),
            'email'   => $stored['contact_person_email'] ?? $order->customer?->email ?? null,
            'instruction'         => $order->delivery_instruction ?: null,
            'unavailableItemNote' => $order->unavailable_item_note ?: null,
        ];
    }

    private function mapSeller(?Store $store): array
    {
        if (!$store) {
            return [
                'name'    => null,
                'rating'  => 0.0,
                'reviews' => 0,
                'image'   => null,
            ];
        }

        [$avg, $count] = $this->ratingFromBuckets($store->getRawOriginal('rating'));

        return [
            'name'    => $store->name,
            'rating'  => round($avg, 1),
            'reviews' => $count,
            'image'   => $store->logo_full_url ?? null,
        ];
    }

    private function mapDeliveryMan(mixed $dm): ?array
    {
        if (!$dm) {
            return null;
        }

        $ratingRow = $dm->relationLoaded('rating')
            ? $dm->getRelation('rating')->first()
            : $dm->rating()->first();
        $average = (float) ($ratingRow->average ?? 0);
        $count   = (int)   ($ratingRow->rating_count ?? 0);

        $name = trim(($dm->f_name ?? '') . ' ' . ($dm->l_name ?? ''));
        if ($name === '') {
            $name = 'Delivery Partner';
        }

        return [
            'id'      => (int) ($dm->id ?? 0),
            'name'    => $name,
            'phone'   => $dm->phone ?? null,
            'rating'  => round($average, 1),
            'reviews' => $count,
            'image'   => $dm->image_full_url ?? null,
        ];
    }

    private function mapTracking(Order $order): ?array
    {
        $orderType = (string) ($order->order_type ?? 'delivery');
        if ($orderType !== 'delivery') {
            return null;
        }

        $raw = (string) $order->order_status;
        if (in_array($raw, ['canceled', 'refunded', 'refund_requested', 'refund_request_canceled', 'failed'], true)) {
            return null;
        }

        $bucket = match ($raw) {
            'pending', 'confirmed', 'accepted' => 0,
            'processing'                       => 1,
            'handover', 'picked_up'            => 2,
            'delivered'                        => 3,
            default                            => 0,
        };

        $stepDefs = [
            ['key' => 'confirmed',  'label' => translate('Order confirmed')],
            ['key' => 'preparing',  'label' => translate('Preparing items')],
            ['key' => 'on_the_way', 'label' => translate('Items on the way')],
            ['key' => 'delivered',  'label' => translate('Delivered')],
        ];

        $steps = [];
        foreach ($stepDefs as $i => $def) {
            $steps[] = [
                'key'     => $def['key'],
                'label'   => $def['label'],
                'done'    => $i < $bucket,
                'current' => $i === $bucket,
            ];
        }

        $storeLoc = null;
        if ($order->store && $order->store->latitude && $order->store->longitude) {
            $storeLoc = [
                'lat' => (float) $order->store->latitude,
                'lng' => (float) $order->store->longitude,
            ];
        }

        $customerLoc = null;
        $stored = is_array($order->delivery_address)
            ? $order->delivery_address
            : (json_decode((string) $order->delivery_address, true) ?: []);
        if (!empty($stored['latitude']) && !empty($stored['longitude'])) {
            $customerLoc = [
                'lat' => (float) $stored['latitude'],
                'lng' => (float) $stored['longitude'],
            ];
        } elseif ($order->delivery_address_id) {
            $row = CustomerAddress::query()->find($order->delivery_address_id);
            if ($row && $row->latitude && $row->longitude) {
                $customerLoc = [
                    'lat' => (float) $row->latitude,
                    'lng' => (float) $row->longitude,
                ];
            }
        }

        $dmLoc = null;
        $dm = $order->delivery_man;
        if ($dm) {
            $last = $dm->relationLoaded('last_location')
                ? $dm->getRelation('last_location')
                : $dm->last_location()->first();
            if ($last && $last->latitude && $last->longitude) {
                $dmLoc = [
                    'lat' => (float) $last->latitude,
                    'lng' => (float) $last->longitude,
                ];
            }
        }

        return [
            'steps'               => $steps,
            'storeLocation'       => $storeLoc,
            'customerLocation'    => $customerLoc,
            'deliveryManLocation' => $dmLoc,
            'routeActive'         => in_array($raw, self::ROUTE_ACTIVE_STATUSES, true) && $dmLoc !== null,
            'isLive'              => $raw !== 'delivered',
        ];
    }

    /**
     * stores.rating is JSON like {"1":0,"2":1,"3":1,"4":4,"5":3}.
     * @return array{0: float, 1: int}
     */
    private function ratingFromBuckets(mixed $raw): array
    {
        $buckets = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $count   = 0;
        $sum     = 0.0;
        foreach ($buckets as $stars => $n) {
            $stars = (int) $stars;
            $n     = (int) $n;
            $count += $n;
            $sum   += $stars * $n;
        }
        return [$count > 0 ? $sum / $count : 0.0, $count];
    }

    private function statusLabel(?string $status): string
    {
        $label = self::STATUS_LABEL[(string) $status] ?? null;

        return $label !== null
            ? translate($label)
            : Str::title(str_replace('_', ' ', (string) $status));
    }

    private function statusVariant(?string $status): string
    {
        return self::STATUS_VARIANT[(string) $status] ?? 'info';
    }

    private function paymentLabel(?string $key): ?string
    {
        if (!$key) return null;
        $row = $this->paymentConfig($key);
        $title = $row?->additional_data?->gateway_title ?? null;
        return $title ?: Str::title(str_replace('_', ' ', $key));
    }

    private function paymentIcon(?string $key): ?string
    {
        if (!$key) return null;
        $row = $this->paymentConfig($key);
        $filename = $row?->additional_data?->gateway_image ?? null;
        if (!$filename) return null;

        return Helpers::get_full_url(
            'payment_modules/gateway_image',
            $filename,
            $row?->additional_data?->storage ?? 'public',
        );
    }

    private function paymentConfig(string $key): ?object
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $row = DB::table('addon_settings')
            ->where('key_name', $key)
            ->where('settings_type', 'payment_config')
            ->value('additional_data');

        if (!$row) {
            return $cache[$key] = null;
        }

        $decoded = json_decode((string) $row);
        return $cache[$key] = $decoded ? (object) ['additional_data' => $decoded] : null;
    }
}
