<?php

namespace App\Services\Order;

use App\Support\Settings\BusinessRules;
use App\CentralLogics\Helpers;
use App\Models\DeliveryMan;
use App\Models\ModuleZoneDeliveryOption;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Scopes\StoreScope;
use App\Services\BaseService;
use App\Services\Customer\UserService;
use App\Services\Item\ItemService;
use App\Services\Marketing\CouponService;
use App\Services\Promotion\BogoOrderService;
use App\Services\Promotion\BundleOrderService;
use App\Services\Marketing\ItemCampaignService;
use App\Services\Store\StoreService;
use App\Services\Zone\AdditionalDeliveryChargeService;
use App\Services\System\ModuleService;
use App\Services\System\UserNotificationService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Services\Zone\ZoneService;
use App\Traits\DeliveryMan\DeliverymanLoyaltyPointTrait;
use App\Traits\Item\ItemStockTrait;
use App\Traits\Marketing\CouponDiscountTrait;
use App\Traits\Order\DeliveryFeeTrait;
use App\Traits\Order\OrderPaymentsTrait;
use App\Traits\Order\OrderTransactionsTrait;
use App\Traits\Order\PlaceNewOrderTrait;
use App\Traits\Parcel\ParcelOrderCancellationTrait;
use App\Traits\Payment\CustomerTransactionsTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Fluent;
use Modules\Rental\Services\Trip\TripsService;
use Modules\RideShare\Entities\TripManagement\RideRequest;
use Modules\TaxModule\Services\CalculateTaxService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use App\Services\Zone\FreeDeliveryService;
use App\Services\System\CurrencyService;
use App\Support\Notification\NotificationText;
use App\Services\System\NotificationMessageService;
use App\Support\Storage\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderService extends BaseService
{
    use CouponDiscountTrait;
    use DeliverymanLoyaltyPointTrait;
    use CustomerTransactionsTrait;
    use DeliveryFeeTrait;
    use ItemStockTrait;
    use OrderPaymentsTrait;
    use OrderTransactionsTrait;
    use ParcelOrderCancellationTrait;
    use PlaceNewOrderTrait;

    private const TAXABLE_STATUSES = ['delivered', 'refund_requested', 'refund_request_canceled'];

    private const UNPAID_STATUSES = ['pending', 'failed'];

    private const SETTLED_METHODS = ['cash_on_delivery', 'wallet'];

    private const UNPAID_WINDOW_MONTHS = 1;

    public const CLOSED_STATUSES = [
        'delivered', 'canceled', 'refund_requested',
        'refund_request_canceled', 'refunded', 'failed', 'returned',
    ];

    private const DM_CURRENT_STATUSES = ['accepted', 'confirmed', 'pending', 'processing', 'picked_up', 'handover'];

    private const DM_HISTORY_STATUSES = ['delivered', 'canceled', 'returned', 'refund_requested', 'refunded', 'failed'];

    private const RIDE_CLOSED_STATUSES = ['completed', 'cancelled'];

    private const TRIP_CLOSED_STATUSES = ['completed', 'canceled'];

    private const LIST_COLUMNS = [
        'id', 'user_id', 'store_id', 'module_id', 'zone_id', 'parcel_category_id', 'delivery_man_id',
        'order_amount', 'coupon_discount_amount', 'store_discount_amount', 'total_tax_amount',
        'delivery_charge', 'delivery_type', 'delivery_type_charge', 'dm_tips', 'additional_charge',
        'flash_admin_discount_amount', 'flash_store_discount_amount', 'extra_packaging_amount',
        'ref_bonus_amount', 'bring_change_amount',
        'payment_status', 'order_status', 'payment_method', 'order_note', 'order_type',
        'schedule_at', 'otp', 'scheduled', 'prescription_order', 'tax_status', 'charge_payer',
        'cancellation_reason', 'cancellation_note', 'processing_time', 'unavailable_item_note',
        'cutlery', 'delivery_instruction', 'delivery_address', 'receiver_details',
        'order_attachment', 'order_proof',
        'pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up', 'delivered',
        'canceled', 'refund_requested', 'refunded', 'failed',
        'created_at', 'updated_at',
    ];

    public function findPendingReview(mixed $userId): mixed
    {
        return Order::has('details')
            ->whereHas('OrderReference', fn ($query) => $query->where('is_reviewed', 0)->where('is_review_canceled', 0))
            ->where('user_id', $userId)
            ->where('order_status', 'delivered')
            ->where('is_guest', 0)
            ->latest()
            ->with('details:id,order_id,item_details')
            ->first();
    }

    public function idsForUserQuery(mixed $userId): mixed
    {
        return Order::where('user_id', $userId)->select('id');
    }

    public function findRefundable(mixed $userId, mixed $orderId): mixed
    {
        return $this->customerOrderQuery($userId, $orderId)->where('is_guest', 0)->Notpos()->first();
    }

    public function countByStatuses(array $filters, array $statuses): int
    {
        return Order::when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['store_id'] ?? null, fn ($q, $v) => $q->where('store_id', $v))
            ->when($filters['delivery_man_id'] ?? null, fn ($q, $v) => $q->where('delivery_man_id', $v))
            ->whereIn('order_status', $statuses)
            ->count();
    }

    public function hasOngoingForUser(mixed $userId, array $statuses): bool
    {
        return Order::where('user_id', $userId)->where('is_guest', 0)->whereIn('order_status', $statuses)->exists();
    }

    public function countForUser(mixed $userId): int
    {
        return Order::where(['user_id' => $userId])->count();
    }

    public function countForUserWithCoupon(mixed $userId, mixed $code): int
    {
        return Order::where(['user_id' => $userId, 'coupon_code' => $code])->count();
    }

    public function hasNoPastOrders(mixed $userId): bool
    {
        return Order::where('user_id', $userId)->where('is_guest', '0')->doesntExist();
    }

    public function storeTaxSummary(array $filters = []): array
    {
        $terms = $this->searchTerms($filters);
        [$startDate, $endDate] = [$filters['start_date'] ?? null, $filters['end_date'] ?? null];

        $summary = DB::table('orders')
            ->where('store_id', $filters['store_id'] ?? null)
            ->whereIn('order_status', self::TAXABLE_STATUSES)
            ->when($startDate && $endDate, fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]))
            ->when($terms !== [], fn ($query) => $query->where(fn ($inner) => $this->orLikeAny($inner, 'id', $terms)))
            ->selectRaw('COUNT(*) as total_orders, SUM(order_amount) as total_order_amount, SUM(total_tax_amount) as total_tax')
            ->first();

        return [
            'total_orders' => (int) ($summary->total_orders ?? 0),
            'total_order_amount' => (float) ($summary->total_order_amount ?? 0),
            'total_tax' => (float) ($summary->total_tax ?? 0),
        ];
    }

    public function getStoreTaxOrderList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->storeTaxOrderQuery($filters)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function storeTaxBreakdown(array $filters = []): mixed
    {
        $terms = $this->searchTerms($filters);
        [$startDate, $endDate] = [$filters['start_date'] ?? null, $filters['end_date'] ?? null];

        return DB::table('order_taxes')
            ->select('tax_name', DB::raw('SUM(tax_amount) as total_tax'), DB::raw('CONCAT(tax_rate) as tax_label'))
            ->where('order_type', Order::class)
            ->when($terms !== [], fn ($query) => $query->where(fn ($inner) => $this->orLikeAny($inner, 'order_id', $terms)))
            ->whereIn('order_id', $this->storeTaxOrderQuery($filters)->pluck('id')->toArray())
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($startDate && $endDate, fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]))
            ->groupBy('tax_name', 'tax_rate')
            ->get();
    }

    public function markReviewed(mixed $orderId): ?int
    {
        $order = $this->findWithRelations($orderId);
        $order?->OrderReference?->update(['is_reviewed' => 1]);

        return $order?->module_id;
    }

    public function hasRunningOrdersInZone(mixed $zoneId): bool
    {
        return Order::where('zone_id', $zoneId)
            ->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
            ->exists();
    }

    public function findUnpaid(array $filters = []): ?Order
    {
        $order = $filters['order_id']
            ? $this->byIdQuery($filters['order_id'])->where('user_id', $filters['user_id'] ?? null)->first()
            : $this->findLatestUnpaid($filters['user_id'] ?? null);

        return $order ? $this->loadPaymentContext($order) : null;
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownedQuery($filters)
            ->with($this->listRelations())
            ->withCount('details')
            ->when(($filters['type'] ?? null) === 'previous', fn ($query) => $query->whereIn('order_status', self::CLOSED_STATUSES))
            ->when(($filters['type'] ?? null) === 'running', fn ($query) => $query->whereNotIn('order_status', self::CLOSED_STATUSES))
            ->latest()
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }

    public function statusCounts(array $filters = []): array
    {
        return $this->moduleStatusCounts($this->ownedQuery($filters), 'order_status', self::CLOSED_STATUSES);
    }

    public function getRunningList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownedQuery($filters)
            ->with($this->listRelations(fullList: false))
            ->withCount('details')
            ->whereNotIn('order_status', self::CLOSED_STATUSES)
            ->latest()
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }

    public function getActivityFeed(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $feed = Order::when(($filters['is_guest'] ?? 1) === 0, fn ($query) => $query->where('is_guest', 0))
            ->where('user_id', $filters['user_id'] ?? null)
            ->whereNotIn('order_status', array_diff(self::CLOSED_STATUSES, ['returned']))
            ->NotHiddenForCustomer()
            ->Notpos()
            ->selectRaw("id, 'order' as order_type, order_status as status, created_at");

        if (addon_published_status('RideShare')) {
            $feed->union($this->runningRideQuery($filters['user_id'] ?? null));
        }

        if (addon_published_status('Rental')) {
            $feed->union($this->runningTripQuery($filters['user_id'] ?? null));
        }

        return DB::query()
            ->fromSub($feed, 'activity')
            ->orderByDesc('created_at')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getOrderType(?int $moduleId): string
    {
        $moduleType = $moduleId ? app(ModuleService::class)->findTypeById($moduleId) : null;

        if ($moduleType === 'ride-share' && addon_published_status('RideShare')) {
            return 'ride';
        }

        return $moduleType === 'rental' && addon_published_status('Rental') ? 'trip' : 'order';
    }

    public function getRideList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->moduleFeedQuery(
            RideRequest::where('customer_id', $filters['user_id'] ?? null)->where('module_id', $filters['module_id'] ?? null),
            'current_status',
            self::RIDE_CLOSED_STATUSES,
            $filters['type'] ?? null
        )
            ->with(['driver', 'vehicle.model', 'vehicleCategory', 'time', 'coordinate', 'fee'])
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function rideStatusCounts(array $filters = []): array
    {
        return $this->moduleStatusCounts(
            RideRequest::where('customer_id', $filters['user_id'] ?? null)->where('module_id', $filters['module_id'] ?? null),
            'current_status',
            self::RIDE_CLOSED_STATUSES
        );
    }

    public function getTripList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->moduleFeedQuery(
            $this->tripBaseQuery($filters),
            'trip_status',
            self::TRIP_CLOSED_STATUSES,
            $filters['type'] ?? null
        )
            ->with('provider:id,name,logo,cover_photo,phone')
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function tripStatusCounts(array $filters = []): array
    {
        return $this->moduleStatusCounts($this->tripBaseQuery($filters), 'trip_status', self::TRIP_CLOSED_STATUSES);
    }

    public function findForTracking(array $filters = []): ?Order
    {
        return Order::with([
            'store' => fn ($query) => $query->with(['store_sub', 'storage', 'discount', 'module.storage']),
            'module',
            'delivery_man' => fn ($query) => $query->with(['rating', 'last_location', 'storage']),
            'parcel_category' => fn ($query) => $query->with('storage'),
            // The other two parcel tiers order details reports. Cheap — one row each, and only
            // for parcel orders, which is where the FK is set.
            'weight',
            'dimension',
            'refund',
            'payments',
            'parcelCancellation',
            'reviews',
            'orderProDiscount',
            'offline_payments',
        ])
            ->withCount(['details', 'reviews as review_count'])
            ->where('id', $filters['order_id'] ?? null)
            ->when($filters['user_id'] ?? null, fn ($query) => $query->where('user_id', $filters['user_id'])->where('is_guest', 0))
            ->when(! ($filters['user_id'] ?? null), fn ($query) => $query
                ->whereJsonContains('delivery_address->contact_person_number', $filters['contact_number'] ?? null)
                ->where('is_guest', 1))
            ->NotHiddenForCustomer()
            ->Notpos()
            ->first();
    }

    public function findDetail(array $filters = []): ?Order
    {
        return Order::with([
            'details',
            'offline_payments',
            'parcel_category' => fn ($query) => $query->with('storage'),
            'weight',
            'dimension',
            'parcelCancellation',
            'orderProDiscount',
            'store' => fn ($query) => $query->select('id', 'delivery_time', 'module_id'),
        ])
            ->when(($filters['is_guest'] ?? 1) === 0, fn ($query) => $query->where('is_guest', 0))
            ->when($filters['user_id'] ?? null, fn ($query) => $query->where('user_id', $filters['user_id']))
            ->NotHiddenForCustomer()
            ->where('id', $filters['order_id'] ?? null)
            ->first();
    }

    public function detailImages(mixed $details): array
    {
        $itemIds = [];
        $campaignIds = [];
        $sources = [];

        foreach ($details as $detail) {
            $stored = $detail->item_details;
            $stored = is_array($stored) ? $stored : json_decode((string) $stored, true);
            $sourceId = $stored['id'] ?? null;

            if (! $sourceId) {
                continue;
            }

            $sources[$detail->id] = [$detail->item_id ? 'item' : 'campaign', $sourceId];
            $detail->item_id ? $itemIds[] = $sourceId : $campaignIds[] = $sourceId;
        }

        $items = app(ItemService::class)->getByIdsWithStorage(array_unique($itemIds));
        $campaigns = app(ItemCampaignService::class)->getByIdsWithStorage(array_unique($campaignIds));

        $images = [];

        foreach ($sources as $detailId => [$type, $sourceId]) {
            $source = $type === 'item' ? $items->get($sourceId) : $campaigns->get($sourceId);

            $images[$detailId] = [
                'image_full_url' => $source?->image_full_url,
                'images_full_url' => $type === 'item' ? $source?->images_full_url : [],
            ];
        }

        return $images;
    }

    public function getRecentDeliveredList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Order::where('user_id', $filters['user_id'] ?? null)
            ->where('is_guest', 0)
            ->where('order_status', 'delivered')
            ->when($filters['module_id'] ?? null, fn ($query) => $query->where('module_id', $filters['module_id']))
            ->when($filters['store_id'] ?? null, fn ($query) => $query->where('store_id', $filters['store_id']))
            ->with([
                // store_business_model is in the order payload's store card, so it has to be
                // selected here too -- an unselected column reads back as null rather than erroring.
                'store:id,name,logo,module_id,zone_id,slug,store_business_model',
                'store.storage',
                // The promo group ids sit alongside item_campaign_id because Order::can_reorder
                // reads all three off the details: a BOGO or bundle order cannot be reordered.
                // Unselected they come back null, so the check found nothing and every such
                // order reported can_reorder true.
                'details:id,order_id,item_id,quantity,item_campaign_id,bogo_group_id,bundle_group_id',
                'details.item:id,name,image,store_id',
                'details.item.storage',
            ])
            ->orderByDesc('delivered')
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function hide(array $filters = []): int
    {
        $ids = Order::whereIn('id', $filters['order_ids'] ?? [])
            ->where('user_id', $filters['user_id'] ?? null)
            ->where('is_guest', $filters['is_guest'] ?? 1)
            ->whereIn('order_status', self::CLOSED_STATUSES)
            ->Notpos()
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return 0;
        }

        return Order::whereIn('id', $ids)->update(['is_hidden' => 1]);
    }

    public function findReorderable(array $filters = []): ?Order
    {
        return Order::with(['details.item'])
            ->where('id', $filters['order_id'] ?? null)
            ->when($filters['user_id'] ?? null, fn ($query) => $query->where('user_id', $filters['user_id'])->where('is_guest', 0))
            ->first();
    }

    public function findCancellable(array $filters = []): ?Order
    {
        return $this->customerOrderQuery($filters['user_id'] ?? null, $filters['order_id'] ?? null)
            ->with(['module', 'details.item', 'details.campaign', 'store'])
            ->when(($filters['is_guest'] ?? 1) === 0, fn ($query) => $query->where('is_guest', 0))
            ->Notpos()
            ->first();
    }

    public function cancelParcel(Order $order, array $data = []): array
    {
        return (array) $this->cancelParcelOrder($order, 'customer', new Fluent([
            'note' => $data['note'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]));
    }

    public function cancel(Order $order, array $data = []): bool
    {
        $this->restoreStock($order);

        if ($order->is_guest == 0) {
            $this->refundBeforeDelivered($order);
        }

        $order->order_status = 'canceled';
        $order->canceled = now();
        $order->cancellation_reason = $data['reason'] ?? null;
        $order->cancellation_note = $data['note'] ?? null;
        $order->canceled_by = 'customer';
        $order->save();

        if ($order->store) {
            Helpers::increment_order_count($order->store);
        }

        SendNotification::sendOrderNotifications($order);

        return true;
    }

    public function findForPayment(array $filters = []): ?Order
    {
        return $this->customerOrderQuery($filters['user_id'] ?? null, $filters['order_id'] ?? null)
            ->Notpos()
            ->first();
    }

    public function switchToCashOnDelivery(Order $order): Order
    {
        if ($order->payment_method !== 'partial_payment') {
            $order->forceFill(['payment_method' => 'cash_on_delivery', 'order_status' => 'pending', 'pending' => now()])->save();
        } else {
            $order->forceFill(['order_status' => 'pending', 'pending' => now()])->save();

            app(OrderPaymentService::class)->markUnpaidAsCashOnDelivery($order->id);
        }

        return $order->fresh(['customer']);
    }

    public function payFromWallet(Order $order): bool
    {
        if (! $this->recordWalletTransaction($order->user_id, $order->order_amount, 'order_place', $order->id)) {
            return false;
        }

        $order->order_status = 'confirmed';
        $order->payment_status = 'paid';
        $order->payment_method = 'wallet';
        $order->update();

        return true;
    }

    public function findReturnableParcel(mixed $orderId, array $filters = []): ?Order
    {
        return $this->byIdQuery($orderId)
            ->when(isset($filters['delivery_man_id']), fn ($query) => $query->where('delivery_man_id', $filters['delivery_man_id']))
            ->with('parcelCancellation')
            ->first();
    }

    public function validateParcelReturn(?Order $order, array $data = []): ?array
    {
        return $this->validateParcelReturnRequest(new Fluent(['return_otp' => $data['return_otp'] ?? null]), $order);
    }

    public function returnParcel(Order $order): bool
    {
        if (in_array($order->parcelCancellation->cancel_by, ['deliveryman', 'admin_for_deliveryman'], true)) {
            $this->settleDeliveryManParcelCancellation($order);

            return true;
        }

        $this->createParcelCancelTransaction($order, $order->payment_status === 'paid' ? 'admin' : 'deliveryman');

        return true;
    }

    public function setParcelReturnDate(mixed $orderId, array $filters = [], mixed $returnDate = null): bool
    {
        $cancellation = $this->findReturnableParcel($orderId, $filters)?->parcelCancellation;

        if (! $cancellation) {
            return false;
        }

        $cancellation->return_date = $returnDate;
        $cancellation->set_return_date = 1;

        return $cancellation->save();
    }

    public function mostTippedAmount(): mixed
    {
        return Order::whereNot('dm_tips', 0)
            ->groupBy('dm_tips')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('dm_tips')
            ->first(['dm_tips'])?->dm_tips;
    }

    public function saverDeliveryWindow(?Order $order): ?string
    {
        $type = $order?->delivery_type;

        if (! in_array($type, ['express', 'slightly_delay'], true)) {
            return null;
        }

        if ((int) ($order->store?->sub_self_delivery ?? 0) === 1) {
            return null;
        }

        if (! $order->store?->delivery_time || $order->order_type !== 'delivery') {
            return null;
        }

        $option = app(ModuleZoneDeliveryOptionService::class)->findForModuleZoneType($order->module_id, $order->zone_id, $type);

        if (! $option) {
            return null;
        }

        $raw = (string) $order->store->delivery_time;
        $parts = preg_split('/[-\s]+/', trim($raw));
        $numeric = array_values(array_filter($parts, 'is_numeric'));

        if (count($numeric) < 2 || str_contains(strtolower(end($parts) ?: 'min'), 'hour')) {
            return $raw;
        }

        $floorMin = app(EtaConfigurationService::class)->minimumDeliveryTimeFloor($order->zone_id, $order->module_id);

        $shift = $type === 'express'
            ? -(int) ($option->getRawOriginal('reduce_delivery_time') ?? 0)
            : (int) ($option->getRawOriginal('add_delivery_time') ?? 0);

        return max($floorMin, (int) $numeric[0] + $shift).'-'.max($floorMin, (int) $numeric[1] + $shift).' min';
    }

    public function resolveDeliveryType(mixed $value): ?string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return app(ModuleZoneDeliveryOptionService::class)->findDeliveryType($value);
    }

    public function getDeliveryManList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->deliveryManQuery($filters, self::DM_CURRENT_STATUSES)
            ->orderBy('accepted')
            ->orderBy('schedule_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getDeliveryManHistoryList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->deliveryManQuery($filters, self::DM_HISTORY_STATUSES)
            ->orderBy('schedule_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function deliveryManStatusCounts(array $filters = []): array
    {
        $statuses = ($filters['type'] ?? 'current') === 'history' ? self::DM_HISTORY_STATUSES : self::DM_CURRENT_STATUSES;

        $counts = Order::where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->whereIn('order_status', $statuses)
            ->selectRaw('order_status, COUNT(*) as total')
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        $rows = [['key' => 'all', 'count' => $counts->sum()]];

        foreach ($statuses as $status) {
            $rows[] = ['key' => $status, 'count' => $counts[$status] ?? 0];
        }

        return $rows;
    }

    public function getDeliveryManAssignableList(DeliveryMan $deliveryMan, array $paginate = []): LengthAwarePaginator
    {
        $query = Order::withStorage()->with($this->deliveryManRelations());

        if ($deliveryMan->type === 'zone_wise') {
            $query->where('zone_id', $deliveryMan->zone_id)
                ->where(fn ($outer) => $outer->whereNull('store_id')
                    ->orWhere(fn ($inner) => $inner
                        ->whereHas('store', fn ($store) => $store->where('store_business_model', 'subscription')
                            ->whereHas('store_sub', fn ($sub) => $sub->where('self_delivery', 0)))
                        ->orWhereHas('store', fn ($store) => $store->where('store_business_model', 'commission')->where('self_delivery_system', 0))));
        } else {
            $query->where('store_id', $deliveryMan->store_id);
        }

        if (BusinessRules::deliverymanConfirmsOrder() && $deliveryMan->type === 'zone_wise') {
            $query->whereIn('order_status', ['pending', 'confirmed', 'processing', 'handover']);
        } else {
            $query->where(fn ($outer) => $outer->whereIn('order_status', ['confirmed', 'processing', 'handover'])
                ->orWhere(fn ($inner) => $inner->where('order_type', 'parcel')
                    ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'handover'])));
        }

        if (isset($deliveryMan->vehicle_id)) {
            $query->where('dm_vehicle_id', $deliveryMan->vehicle_id);
        }

        // The express vehicle filter (decided 2026-09-08). A setup may name the vehicle categories
        // allowed to take its express orders; naming none means everyone may. Where it does name
        // some and this deliveryman's vehicle is not among them, that pair's EXPRESS orders are
        // hidden from them — standard and slightly-delayed orders are untouched.
        //
        // Express is still offered at checkout regardless, so an order nobody is eligible for
        // simply waits unassigned rather than the option disappearing for the customer.
        $barred = app(AdditionalDeliveryChargeService::class)->pairsBarredForExpress($deliveryMan->vehicle_id);

        if ($barred !== []) {
            $query->where(function ($outer) use ($barred) {
                $outer->where('delivery_type', '!=', ModuleZoneDeliveryOption::TYPE_EXPRESS)
                    ->orWhereNull('delivery_type')
                    ->orWhere(function ($inner) use ($barred) {
                        foreach ($barred as $pair) {
                            $inner->where(fn ($q) => $q->where('zone_id', '!=', $pair['zone_id'])
                                ->orWhere('module_id', '!=', $pair['module_id']));
                        }
                    });
            });
        }

        return $query->dmOrder()
            ->Notpos()
            ->NotDigitalOrder()
            ->OrderScheduledIn(30)
            ->whereNull('delivery_man_id')
            ->orderBy('schedule_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findForDeliveryMan(array $filters = []): ?Order
    {
        return $this->deliveryManOrderQuery($filters)
            ->with($this->deliveryManRelations(true))
            ->Notpos()
            ->first();
    }

    public function findDetailForDeliveryMan(array $filters = []): ?Order
    {
        // `weight` / `dimension` because a parcel order falls through to ParcelOrderResource,
        // which reports the tiers the fee was built from.
        return Order::with(['details', 'store:id,vendor_id', 'module', 'weight', 'dimension'])
            ->where('id', $filters['order_id'] ?? null)
            ->where(fn ($query) => $query->whereNull('delivery_man_id')->orWhere('delivery_man_id', $filters['delivery_man_id'] ?? null))
            ->Notpos()
            ->first();
    }

    public function findForOtp(array $filters = [], bool $deliveryManConfirms = false): ?Order
    {
        return $this->deliveryManOrderQuery($filters)
            ->when(
                $deliveryManConfirms,
                fn ($query) => $query->whereIn('order_status', ['pending', 'confirmed', 'processing', 'handover', 'picked_up']),
                fn ($query) => $query->where(fn ($inner) => $inner
                    ->whereIn('order_status', ['confirmed', 'processing', 'handover', 'picked_up'])
                    ->orWhere('order_type', 'parcel'))
            )
            ->dmOrder()
            ->first();
    }

    public function sendOtpToCustomer(Order $order): bool
    {
        $fcmToken = $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;

        try {
            if ($fcmToken && SendNotification::channelEnabled('customer', 'customer_delivery_verification', 'push_notification_status')) {
                $data = NotificationMessages::orderReadyOtp($order);

                SendNotification::sendToDevice($fcmToken, $data);

                app(UserNotificationService::class)->record($order->user_id, $data);
            }
        } catch (\Exception $exception) {
            return false;
        }

        return true;
    }

    public function findStatusChangeable(array $filters = []): ?Order
    {
        return $this->deliveryManOrderQuery($filters)->dmOrder()->first();
    }

    public function markPaymentPaid(array $filters = [], mixed $status = 'paid'): bool
    {
        $scope = Order::where(['delivery_man_id' => $filters['delivery_man_id'] ?? null, 'id' => $filters['order_id'] ?? null]);

        if (! (clone $scope)->dmOrder()->exists()) {
            return false;
        }

        return (bool) $scope->update(['payment_status' => $status]);
    }

    public function acceptForDeliveryMan(DeliveryMan $deliveryMan, array $data = []): array
    {
        if (addon_published_status('RideShare') && $deliveryMan->is_ride == 1
            && RideRequest::whereIn('current_status', ['accepted', 'ongoing'])->where('driver_id', $deliveryMan->id)->exists()) {
            return $this->failure(409, 'running_trip', translate('You already have a ongoing ride. Please complete it before accepting a new order.'));
        }

        $order = $this->findAssignableForDeliveryMan($data['order_id'] ?? null);

        if (! $order) {
            return $this->failure(404, 'order', translate('messages.Can not accept'));
        }

        if (isset($data['lat'], $data['lng']) && $deliveryMan->earning && $order->order_type !== 'parcel') {
            try {
                $zoneIds = app(ZoneService::class)->findIdsByCoordinates($data['lat'], $data['lng']);

                if ($deliveryMan->zone_id && ! in_array($deliveryMan->zone_id, $zoneIds)) {
                    return $this->failure(403, 'dm_out_of_zone', translate('messages.You are outside the service area. Move closer to accept this order.'));
                }
            } catch (\Throwable $throwable) {
                Log::warning('order.order_service.accept_for_delivery_man_failed', [
                    'error' => $throwable->getMessage(),
                    'file' => $throwable->getFile().':'.$throwable->getLine(),
                ]);
            }
        }

        if ($deliveryMan->active != 1) {
            return $this->failure(404, 'active_status', translate('messages.You can not accept order on offline'));
        }

        if ($deliveryMan->current_orders >= BusinessRules::dmMaximumOrders()) {
            return $this->failure(405, 'dm_maximum_order_exceed', translate('messages.Dm maximum order exceed warning'));
        }

        $maxCash = app(BusinessSettingService::class)->value('dm_max_cash_in_hand');
        $isCashOrder = $order->payment_method === 'cash_on_delivery'
            || $order->payments()->where('payment_method', 'cash_on_delivery')->exists();

        if (app(BusinessSettingService::class)->value('cash_in_hand_overflow_delivery_man') == 1 && $isCashOrder
            && (($deliveryMan?->wallet?->collected_cash ?? 0) + $order->order_amount) >= $maxCash) {
            return $this->failure(405, 'dm_maximum_hand_in_cash', Helpers::format_currency($maxCash).' '.translate('Max cash in hand exceeds'));
        }

        if ($order->order_type === 'parcel' && $order->order_status === 'confirmed') {
            $order->order_status = 'handover';
            $order->handover = now();
            $order->processing = now();
        } else {
            $order->order_status = in_array($order->order_status, ['pending', 'confirmed'], true) ? 'accepted' : $order->order_status;
        }

        $order->delivery_man_id = $deliveryMan->id;
        $order->accepted = now();
        $order->save();

        $deliveryMan->current_orders = $deliveryMan->current_orders + 1;
        $deliveryMan->save();
        $deliveryMan->increment('assigned_order_count');

        $this->notifyCustomerOfAcceptance($order);

        return ['status_code' => 200];
    }

    public function updateStatusForDeliveryMan(DeliveryMan $deliveryMan, Order $order, array $data = []): array
    {
        $status = $data['status'] ?? null;

        if ($order->order_type === 'parcel' && $status === 'canceled') {
            $result = (array) $this->cancelParcelOrder($order, 'deliveryman', new Fluent([
                'reason' => $data['reason'] ?? null,
                'note' => $data['note'] ?? null,
            ]));

            return data_get($result, 'status_code') == 200
                ? ['status_code' => 200, 'message' => translate('messages.Parcel canceled successfully')]
                : $result;
        }

        if ($guard = $this->statusChangeGuard($order, $data)) {
            return $guard;
        }

        if ($status === 'delivered') {
            if ($failure = $this->completeDelivery($deliveryMan, $order, $data)) {
                return $failure;
            }
        } elseif ($status === 'canceled') {
            $this->cancelForDeliveryMan($order, $data);
        } elseif ($order->order_type === 'parcel' && $status === 'handover') {
            $order->confirmed = now();
            $order->processing = now();
        } elseif ($order->order_type !== 'parcel' && $status === 'picked_up') {
            Helpers::sendOrderDeliveryVerificationOtp($order);
        }

        $order->order_status = $status;
        $order[$status] = now();
        $order->save();

        SendNotification::sendOrderNotifications($order);

        return ['status_code' => 200];
    }

    public function updateFromCart(Order $order, array $payload, string $editedBy): array
    {
        $carts = $payload['carts'] ?? null;
        $request = $this->cartRequest($payload);
        if (! is_array($carts) || count($carts) === 0) {
            return ['status' => 403, 'code' => 'cart', 'message' => translate('messages.cart_is_empty')];
        }

        $store = $order->store;
        $originalDetailQtys = $order->details->pluck('quantity', 'id')->all();
        $originalIds = array_map('intval', array_keys($originalDetailQtys));

        $cart = collect([]);
        $keptIds = [];
        $preservedIds = [];

        foreach ($carts as $line) {
            $detailId = isset($line['order_details_id']) ? (int) $line['order_details_id'] : null;
            $isKept = $detailId && in_array($detailId, $originalIds, true);
            if ($isKept) {
                $keptIds[] = $detailId;
            }
            if ($isKept && ! empty($line['unavailable'])) {
                $preservedIds[] = $detailId;

                continue;
            }

            $detail = app(OrderDetailService::class)->make();
            if ($isKept) {
                $detail->id = $detailId;
            }
            $detail->item_id = $line['item_id'] ?? null;
            $detail->item_campaign_id = null;
            $detail->item_type = 'App\Models\Item';
            $detail->order_id = $order->id;
            $detail->quantity = max(1, (int) ($line['quantity'] ?? 1));
            $detail->variation = json_encode($line['variation'] ?? []);
            $detail->variant = json_encode($line['variant'] ?? []);
            $detail->add_ons = json_encode($line['add_on_ids'] ?? []);
            $detail->add_on_qtys = $line['add_on_qtys'] ?? [];
            $detail->status = true;

            $storedLine = $isKept ? $order->details->firstWhere('id', $detailId) : null;
            $detail->bogo_offer_id = $storedLine?->bogo_offer_id;
            $detail->bogo_group_id = $storedLine?->bogo_group_id;
            $detail->is_free_item = $storedLine?->is_free_item ?? 0;
            $detail->bundle_id = $storedLine?->bundle_id;
            $detail->bundle_group_id = $storedLine?->bundle_group_id;

            $cart->push($detail);
        }

        $deletedIds = array_values(array_diff($originalIds, $keptIds));

        foreach ($order->details as $existing) {
            if (in_array((int) $existing->id, $keptIds, true)) {
                continue;
            }
            $removed = app(OrderDetailService::class)->make();
            $removed->id = $existing->id;
            $removed->item_id = $existing->item_id;
            $removed->item_campaign_id = $existing->item_campaign_id;
            $removed->variation = $existing->variation;
            $removed->quantity = $existing->quantity;
            $removed->status = false;
            $cart->push($removed);
        }

        $editLogs = $this->collectEditLogs($cart, $originalDetailQtys);

        $bundlesBefore = app(BogoOrderService::class)->bundlesFor($order->details, (int) $store->id);
        $bundleCopiesBefore = app(BundleOrderService::class)->copiesFor($order->details);

        $computed = $this->makeEditOrderDetails($cart, $request, $store, $originalDetailQtys);
        if (data_get($computed, 'status_code') === 403) {
            return ['status' => 403, 'code' => data_get($computed, 'code', 'cart'), 'message' => data_get($computed, 'message')];
        }

        DB::beginTransaction();
        try {
            $coupon = $order->coupon_code ? app(CouponService::class)->findByCode($order->coupon_code) : null;

            $order_details = $computed['order_details'];
            $total_addon_price = $computed['total_addon_price'];
            $product_price = $computed['product_price'];
            $store_discount_amount = $computed['store_discount_amount'];
            $flash_sale_admin_discount_amount = $computed['flash_sale_admin_discount_amount'];
            $flash_sale_vendor_discount_amount = $computed['flash_sale_vendor_discount_amount'];

            foreach ($order->details->whereIn('id', $preservedIds) as $pd) {
                $order_details[] = app(OrderDetailService::class)->buildPreservedRow($pd);
                $product_price += (float) $pd->price * (int) $pd->quantity;
                $total_addon_price += (float) ($pd->total_add_on_price ?? 0);
                if (($pd->discount_type ?? null) != 'flash_sale') {
                    $store_discount_amount += (float) ($pd->discount_on_item ?? 0) * (int) $pd->quantity;
                }
            }

            if ($bogoRefusal = app(BogoOrderService::class)->editRefusalReason($order, $order_details, $bundlesBefore, (int) $store->id)) {
                DB::rollBack();

                return ['status' => 403, 'code' => 'bogo_offer', 'message' => $bogoRefusal];
            }

            if ($bundleRefusal = app(BundleOrderService::class)
                ->editRefusalReason($order, $order_details, $bundleCopiesBefore, (int) $store->id)) {
                DB::rollBack();

                return ['status' => 403, 'code' => 'bundle', 'message' => $bundleRefusal];
            }

            $store_discount = Helpers::get_store_discount($store);
            if (isset($store_discount)) {
                if ($product_price + $total_addon_price < $store_discount['min_purchase']) {
                    $store_discount_amount = 0;
                }

                if ($store_discount['max_discount'] !== null && $store_discount_amount > $store_discount['max_discount']) {
                    $store_discount_amount = $store_discount['max_discount'];
                }
            }

            $order->delivery_charge = $order->original_delivery_charge;
            if ($coupon && $coupon->coupon_type == 'free_delivery') {
                $order->delivery_charge = 0;
                $coupon = null;
            }
            if ($order->store->free_delivery || $order->order_type == 'take_away') {
                $order->delivery_charge = 0;
            }

            $additionalCharges = [];
            $settings = app(BusinessSettingService::class)->valuesFor(['additional_charge_status', 'additional_charge']);
            $order->additional_charge = 0;
            if (($settings['additional_charge_status'] ?? null) == 1) {
                $order->additional_charge = $settings['additional_charge'] ?? 0;
            }

            $coupon_discount_amount = $coupon ? $this->calculateDiscount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
            $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount - $coupon_discount_amount;
            $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $coupon_discount_amount + $order->ref_bonus_amount;

            $isProCustomer = $order->user_id && app(UserService::class)->isProCustomer($order->user_id);
            if ($isProCustomer) {
                if (app(FreeDeliveryService::class)->frees(
                    [$store?->zone_id],
                    $store?->module_id,
                    $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount,
                )) {
                    $order->delivery_charge = 0;
                    $order->free_delivery_by = 'admin';
                }
                $proRecompute = $this->recomputeOrderProDiscountOnEdit(
                    order: $order,
                    subtotal: $product_price + $total_addon_price,
                    totalPrice: $total_price,
                    moduleType: $store?->module?->module_type,
                    deliveryCharge: (float) $order->delivery_charge,
                );
                $total_price = (float) $proRecompute['total_price'];
                $order->delivery_charge = (float) $proRecompute['delivery_charge'];
                if ($proRecompute['delivery_savings'] > 0) {
                    $order->free_delivery_by = $proRecompute['free_delivery_by'];
                }
                $totalDiscount += (float) $proRecompute['discount'];
            }

            $finalCalculatedTax = Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);
            $tax_amount = $finalCalculatedTax['tax_amount'];
            $tax_status = $finalCalculatedTax['tax_status'];
            $taxMap = $finalCalculatedTax['taxMap'];
            $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);
            $taxType = data_get($finalCalculatedTax, 'taxType');
            $order->tax_type = $taxType;
            $order->tax_status = $tax_status;
            $total_tax_amount = $order->tax_status == 'included' ? 0 : $tax_amount;

            if ($store->minimum_order > $product_price + $total_addon_price) {
                DB::rollBack();

                return ['status' => 403, 'code' => 'minimum_order', 'message' => translate('messages.Your order is below the store minimum.').' '.translate('messages.Minimum order amount').': '.Helpers::format_currency($store->minimum_order)];
            }

            if (! $isProCustomer) {
                if (app(FreeDeliveryService::class)->frees(
                    [$store?->zone_id],
                    $store?->module_id,
                    $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount,
                )) {
                    $order->delivery_charge = 0;
                    $order->free_delivery_by = 'admin';
                }
            }

            $total_order_ammount = $total_price + $total_tax_amount + $order->delivery_charge + $order->additional_charge;
            $total_order_ammount = $this->applyDeliveryTypeToAmount($order, (float) $total_order_ammount);
            $adjustment = $order->order_amount - $total_order_ammount;

            $order->coupon_discount_amount = $coupon_discount_amount;
            $order->store_discount_amount = $store_discount_amount;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $total_order_ammount;
            $order->adjusment = $adjustment;

            $order->bogo_discount_amount = round(
                collect($order_details)->sum(fn ($line) => (float) ($line['bogo_free_value'] ?? 0) * (int) ($line['quantity'] ?? 0)),
                config('round_up_to_digit')
            );
            $order->bundle_discount_amount = round(
                collect($order_details)->sum(
                    fn ($line) => data_get($line, 'bundle_group_id')
                        ? (float) ($line['discount_on_item'] ?? 0) * (int) ($line['quantity'] ?? 0)
                        : 0
                ),
                config('round_up_to_digit')
            );
            $order->happy_hour_id = $computed['happy_hour_id'] ?? null;
            $order->discount_on_product_by = $computed['discount_on_product_by'] ?? $order->discount_on_product_by;
            $order->edited = true;
            $order->save();

            foreach ($editLogs as $log) {
                $this->makeEditOrderLogs($order->id, $log, $editedBy);
            }

            if (! empty($deletedIds)) {
                app(OrderDetailService::class)->deleteByIds($deletedIds, $order->id);
            }

            if ($order->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $item_id = $item['item_id'] ?: $item['item_campaign_id'];
                    $index = $taxMapCollection->search(fn ($tax) => $tax['product_id'] == $item_id);
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                foreach ($order_details as $detail) {
                    $cartId = $detail['cart_id'] ?? null;
                    unset($detail['cart_id']);
                    $detail['order_id'] = $order->id;
                    if ($cartId && isset($originalDetailQtys[$cartId])) {
                        unset($detail['created_at']);
                        $order->details()->where('id', $cartId)->update($detail);
                    } else {
                        $order->details()->insert($detail);
                    }
                }

                $order?->orderTaxes()?->delete();
                if (count($orderTaxIds)) {
                    CalculateTaxService::updateOrderTaxData(
                        orderId: $order->id,
                        orderTaxIds: $orderTaxIds,
                    );
                }

                app(BogoOrderService::class)->syncUsage($order, $order_details);

                $this->adjustEditedStockFromCart($cart, $originalDetailQtys);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());

            return ['status' => 403, 'code' => 'order_update', 'message' => translate('messages.order_update_failed')];
        }

        return ['status' => 200, 'message' => translate('Updated successfully')];
    }

    public function findVendorEditable(mixed $orderId, array $filters = [], bool $withDetails = false): ?Order
    {
        return $this->findForVendor(
            $orderId,
            $filters,
            $withDetails ? ['details', 'store.module', 'store.vendor', 'payments'] : [],
            true
        );
    }

    public function findForEditResponse(mixed $orderId): ?Order
    {
        return $this->findWithRelations($orderId, [
            'customer', 'details', 'delivery_man', 'payments', 'orderProDiscount',
            'storage', 'store.storage', 'store.store_sub', 'module.storage',
        ]);
    }

    public function getVendorCurrentList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->vendorOrderQuery($filters)
            ->where(fn ($query) => $this->constrainVendorRunning($query, $filters))
            ->notPos()
            ->notDigitalOrder()
            ->orderBy('schedule_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVendorAllList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->vendorOrderQuery($filters)
            ->notPos()
            ->notDigitalOrder()
            ->orderBy('schedule_at', 'desc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVendorCompletedList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $status = $filters['status'] ?? 'all';

        return $this->vendorOrderQuery($filters)
            ->when($status === 'all', fn ($query) => $query->whereIn('order_status', ['refunded', 'delivered']))
            ->when($status !== 'all', fn ($query) => $query->where('order_status', $status))
            ->notPos()
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVendorCanceledList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->vendorOrderQuery($filters)
            ->where('order_status', 'canceled')
            ->notPos()
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findForVendor(mixed $orderId, array $filters = [], array $with = [], bool $notPos = false): ?Order
    {
        return Order::whereHas('store.vendor', fn ($query) => $query->where('id', $filters['vendor_id'] ?? null))
            ->with($with)
            ->where('id', $orderId)
            ->when($notPos, fn ($query) => $query->notPos())
            ->first();
    }

    public function findVendorOrderDetails(mixed $orderId, array $filters = []): array
    {
        $order = $this->findForVendor($orderId, $filters, [
            'customer', 'details', 'orderProDiscount', 'store.storage', 'store.store_sub', 'payments', 'module',
            // A parcel order answers through ParcelOrderResource, which reports both tiers.
            'weight', 'dimension',
        ], notPos: true);

        if (! $order) {
            return ['status_code' => 404, 'code' => 'order_id', 'message' => translate('No data found')];
        }

        if ($order->details?->count()) {
            return ['status_code' => 200, 'order' => $order, 'details' => $order->details];
        }

        if ($order->order_type === 'parcel' || $order->prescription_order == 1) {
            return ['status_code' => 200, 'order' => $order, 'details' => null];
        }

        return ['status_code' => 404, 'code' => 'order', 'message' => translate('No data found')];
    }

    public function updateStatusForVendor(array $data, mixed $vendor): array
    {
        $store = $vendor->stores[0];
        $order = $this->findForVendor($data['order_id'], ['vendor_id' => $vendor->id], ['details.item', 'customer', 'delivery_man', 'store'], notPos: true);

        if (! $order) {
            return ['status_code' => 404, 'code' => 'order_id', 'message' => translate('No data found')];
        }

        $rejection = $this->vendorStatusRejection($order, $store, $data);

        if ($rejection) {
            return $rejection;
        }

        if ($data['status'] === 'delivered' && $order->transaction === null) {
            $this->settleVendorDeliveredOrder($order);
        }

        if ($data['status'] === 'delivered') {
            $this->countVendorDeliveredOrder($order);
            $proof = $this->uploadOrderProof($data['order_proof'] ?? []);

            if ($proof !== []) {
                $order->order_proof = json_encode($proof);
            }
        }

        if (in_array($data['status'], ['canceled', 'delivered'], true)) {
            if ($order->delivery_man) {
                $order->delivery_man->current_orders = $order->delivery_man->current_orders > 1 ? $order->delivery_man->current_orders - 1 : 0;
                $order->delivery_man->save();
            }

            $order->cancellation_reason = $data['reason'] ?? null;
            $order->canceled_by = 'store';
        } elseif ($order->order_type !== 'parcel' && $data['status'] === 'picked_up') {
            Helpers::sendOrderDeliveryVerificationOtp($order);
        }

        $order->order_status = $data['status'];

        if ($order->order_status === 'processing') {
            $order->processing_time = $data['processing_time'] ?? explode('-', $order['store']['delivery_time'])[0];
        }

        $order[$data['status']] = now();
        $order->save();
        SendNotification::sendOrderNotifications($order);

        return ['status_code' => 200, 'message' => 'Status updated'];
    }

    public function sendOtpForVendor(mixed $orderId, mixed $vendor): array
    {
        $store = $vendor->stores[0];

        $order = $this->byIdQuery($orderId)
            ->whereHas('store.vendor', fn ($query) => $query->where('id', $vendor->id))
            ->with(['customer.storage', 'guest'])
            ->where(fn ($query) => $this->constrainVendorOtp($query, $store))
            ->notPos()
            ->notDigitalOrder()
            ->first();

        if (! $order) {
            return ['status_code' => 404, 'code' => 'order', 'message' => translate('No data found')];
        }

        $firebaseToken = $order->is_guest == 0 ? $order->customer?->cm_firebase_token : $order->guest?->fcm_token;

        if (! $firebaseToken || ! SendNotification::channelEnabled('customer', 'customer_delivery_verification', 'push_notification_status')) {
            return ['status_code' => 200];
        }

        try {
            $data = $this->otpNotification($order);
            SendNotification::sendToDevice($firebaseToken, $data);
            app(UserNotificationService::class)->record($order->user_id, $data);
        } catch (\Exception) {
            return ['status_code' => 403, 'message' => translate('messages.push_notification_failed')];
        }

        return ['status_code' => 200];
    }

    public function updateAmountForVendor(array $data, mixed $vendor): array
    {
        $storeId = $vendor->stores[0]->id;
        $orderTaxIds = [];
        $order = null;

        if ($data['order_amount'] ?? null) {
            $result = $this->rebuildOrderFromAmount($data, $storeId);

            if (isset($result['status_code'])) {
                return $result;
            }

            [$order, $orderTaxIds] = [$result['order'], $result['order_tax_ids']];
        }

        if ($data['discount_amount'] ?? null) {
            $result = $this->rebuildOrderFromDiscount($data, $storeId);

            if (isset($result['status_code'])) {
                return $result;
            }

            [$order, $orderTaxIds] = [$result['order'], $result['order_tax_ids']];
        }

        if (! $order) {
            return ['status_code' => 200, 'message' => translate('Updated successfully')];
        }

        $order->orderTaxes()?->delete();

        if (count($orderTaxIds)) {
            CalculateTaxService::updateOrderTaxData(orderId: $order->id, orderTaxIds: $orderTaxIds);
        }

        return ['status_code' => 200, 'message' => translate('Updated successfully')];
    }

    public function findWithDetails(mixed $orderId): mixed
    {
        return $this->findWithRelations($orderId, ['details']);
    }

    public function findWithRelations(mixed $orderId, array $relations = []): ?Order
    {
        return Order::with($relations)->find($orderId);
    }

    public function maxId(): mixed
    {
        return Order::max('id');
    }

    public function moduleOrderCounts(bool $isParcel, mixed $moduleId, array $select, array $bindings): mixed
    {
        return $this->rawCounts(
            Order::when($isParcel, fn ($query) => $query->ParcelOrder(), fn ($query) => $query->StoreOrder())->module($moduleId),
            implode(', ', $select),
            $bindings
        );
    }

    public function storeOrderCounts(mixed $storeId, string $select, array $bindings): mixed
    {
        return $this->rawCounts(
            Order::where('store_id', $storeId)->StoreOrder()->NotDigitalOrder(),
            $select,
            $bindings
        );
    }

    public function countScheduledForStore(mixed $storeId, string $activeRaw): int
    {
        return Order::where('store_id', $storeId)
            ->StoreOrder()
            ->Scheduled()
            ->whereRaw($activeRaw)
            ->count();
    }

    protected function primeEditCartRelations($cart)
    {
        $models = collect($cart)->filter(fn ($row) => $row instanceof OrderDetail);

        if ($models->isEmpty()) {
            return $cart;
        }

        (new EloquentCollection($models->all()))->loadMissing([
            'item' => fn ($query) => $query->withoutGlobalScope(StoreScope::class)->withStorage(),
            'campaign' => fn ($query) => $query->withoutGlobalScope(StoreScope::class)->withStorage(),
        ]);

        foreach (['item', 'campaign'] as $relation) {
            $related = $models
                ->filter(fn ($row) => $row->relationLoaded($relation) && $row->getRelation($relation) !== null)
                ->map(fn ($row) => $row->getRelation($relation))
                ->values();

            if ($related->isNotEmpty()) {
                (new EloquentCollection($related->all()))->loadMissing('storage');
            }
        }

        return $cart;
    }

    private function customerOrderQuery(mixed $userId, mixed $orderId): Builder
    {
        return Order::where('user_id', $userId)->where('id', $orderId);
    }

    private function byIdQuery(mixed $orderId): Builder
    {
        return Order::where('id', $orderId);
    }

    private function deliveryManOrderQuery(array $filters): Builder
    {
        return Order::where(['id' => $filters['order_id'] ?? null, 'delivery_man_id' => $filters['delivery_man_id'] ?? null]);
    }

    private function rawCounts(mixed $query, string $select, array $bindings): mixed
    {
        return $query->toBase()->selectRaw($select, $bindings)->first();
    }

    private function findAssignableForDeliveryMan(mixed $orderId): ?Order
    {
        return $this->byIdQuery($orderId)->whereNull('delivery_man_id')->dmOrder()->first();
    }

    private function storeTaxOrderQuery(array $filters): Builder
    {
        $terms = $this->searchTerms($filters);
        [$startDate, $endDate] = [$filters['start_date'] ?? null, $filters['end_date'] ?? null];

        return Order::with([
            'orderTaxes' => fn (MorphMany $query) => $query
                ->where('order_type', Order::class)
                ->select('id', 'order_id', 'tax_name', 'tax_amount', 'tax_type'),
            'module:id,module_type',
            'storage',
        ])
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($terms !== [], fn ($query) => $query->where(fn ($inner) => $this->orLikeAny($inner, 'id', $terms)))
            ->whereIn('order_status', self::TAXABLE_STATUSES)
            ->when($startDate && $endDate, fn ($query) => $query->whereBetween('created_at', [$startDate, $endDate]))
            ->select(['id', 'module_id', 'order_amount', 'total_tax_amount', 'order_type', 'created_at', 'order_status', 'payment_status'])
            ->latest('created_at');
    }

    private function searchTerms(array $filters): array
    {
        return array_filter(explode(' ', $filters['search'] ?? ''));
    }

    private function orLikeAny(mixed $query, string $column, array $terms): mixed
    {
        foreach ($terms as $term) {
            $query->orWhere($column, 'like', "%{$term}%");
        }

        return $query;
    }

    private function deliveryManQuery(array $filters, array $allowedStatuses): Builder
    {
        $requested = $filters['order_status'] ?? null;

        return Order::with($this->deliveryManRelations())
            ->where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->dmOrder()
            ->when(
                $requested && $requested !== 'all',
                fn ($query) => $query->when(
                    in_array($requested, $allowedStatuses, true),
                    fn ($inner) => $inner->where('order_status', $requested)
                ),
                fn ($query) => $query->whereIn('order_status', $allowedStatuses)
            );
    }

    private function notifyCustomerOfAcceptance(Order $order): void
    {
        $fcmToken = $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;

        $message = NotificationText::format(
            value: app(NotificationMessageService::class)->forOrderStatus('accepted', $order->module->module_type),
            store_name: $order->store?->name,
            order_id: $order->id,
            user_name: "{$order?->customer?->f_name} {$order?->customer?->l_name}",
            delivery_man_name: "{$order->delivery_man?->f_name} {$order->delivery_man?->l_name}"
        );

        try {
            if ($message && $fcmToken && SendNotification::channelEnabled('customer', 'customer_order_notification', 'push_notification_status')) {
                SendNotification::sendToDevice($fcmToken, [
                    'title' => translate('Order notification'),
                    'description' => $message,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'order_status',
                ]);
            }
        } catch (\Exception $exception) {
            Log::error('order.order_service.notify_customer_of_acceptance_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    private function statusChangeGuard(Order $order, array $data): ?array
    {
        $status = $data['status'] ?? null;

        if ($status === 'confirmed' && BusinessRules::storeConfirmsOrder()) {
            return $this->failure(403, 'order-confirmation-model', translate('messages.Order confirmation warning'));
        }

        if ($status === 'canceled' && ! BusinessRules::canceledByDeliveryman()) {
            return $this->failure(403, 'status', translate('messages.You can not cancel a order'));
        }

        if ($order->confirmed && $status === 'canceled') {
            return $this->failure(403, 'delivery-man', translate('messages.Order can not cancle after confirm'));
        }

        if (BusinessRules::deliveryVerificationEnabled() && $status === 'delivered' && $order->otp != ($data['otp'] ?? null)) {
            return $this->failure(406, 'otp', translate('Otp Not matched'));
        }

        return null;
    }

    private function completeDelivery(DeliveryMan $deliveryMan, Order $order, array $data): ?array
    {
        if ($order->transaction === null) {
            $unpaid = app(OrderPaymentService::class)->findUnpaidForOrder($order->id);
            $isCash = $order->payment_method === 'cash_on_delivery' || $unpaid?->payment_method === 'cash_on_delivery';
            $receivedBy = $isCash ? ($deliveryMan->type !== 'zone_wise' ? 'store' : 'deliveryman') : 'admin';

            if (! $this->createOrderTransaction($order, $receivedBy, null)) {
                return $this->failure(406, 'error', translate('messages.Faield to create order transaction'));
            }

            $order->payment_status = 'paid';
            $this->recordDeliverymanLoyaltyPoint(
                deliveryManId: $deliveryMan->id,
                amount: $order->order_amount,
                transactionType: 'earn_on_order_completion',
                pointConversionType: 'credit',
                reference: $order->id
            );
        }

        $order->transaction?->update(['delivery_man_id' => $deliveryMan->id]);

        $order->details->each(fn ($detail) => $detail->food?->increment('order_count'));
        $order?->customer?->increment('order_count');

        $deliveryMan->current_orders = $deliveryMan->current_orders > 1 ? $deliveryMan->current_orders - 1 : 0;
        $deliveryMan->save();
        $deliveryMan->increment('order_count');

        $order->store?->increment('order_count');
        $order->parcel_category?->increment('orders_count');

        if (! empty($data['order_proof'])) {
            $order->order_proof = json_encode(array_map(
                fn ($image) => ['img' => FileStorage::upload('order/', $image), 'storage' => FileStorage::getDisk()],
                $data['order_proof']
            ));
        }

        $this->markUnpaidOrderPaymentPaid(orderId: $order->id, paymentMethod: $order->payment_method);

        return null;
    }

    private function cancelForDeliveryMan(Order $order, array $data): void
    {
        if ($order->delivery_man) {
            $order->delivery_man->current_orders = $order->delivery_man->current_orders > 1 ? $order->delivery_man->current_orders - 1 : 0;
            $order->delivery_man->save();
        }

        $hasStock = config('module.'.$order->module->module_type)['stock'];
        $hasFlashDiscount = $order->flash_admin_discount_amount > 0 && $order->flash_store_discount_amount > 0;

        if ($hasStock || $hasFlashDiscount) {
            foreach ($order->details as $detail) {
                if ($hasStock) {
                    $variation = json_decode($detail->variation, true);
                    self::updateItemStock($detail->campaign ?? $detail->item, -$detail->quantity, $variation[0]['type'] ?? null)?->save();
                }

                if ($hasFlashDiscount) {
                    self::updateFlashSaleStock($detail->item, $detail->quantity, true)?->save();
                }
            }
        }

        if ($order->is_guest == 0) {
            $this->refundBeforeDelivered($order);
        }

        $order->cancellation_reason = $data['reason'] ?? null;
        $order->canceled_by = 'deliveryman';
    }

    private function failure(int $statusCode, string $code, mixed $message): array
    {
        return ['status_code' => $statusCode, 'code' => $code, 'message' => $message];
    }

    private function deliveryManRelations(bool $detail = false): array
    {
        $relations = [
            'customer.storage',
            'store.storage',
            'store.store_sub',
            'parcel_category.storage',
            'weight',
            'dimension',
            'storage',
            'details',
            'orderProDiscount',
            'module.storage',
        ];

        return $detail ? array_merge($relations, ['payments', 'parcelCancellation']) : $relations;
    }

    private function ownedQuery(array $filters = []): Builder
    {
        return Order::where('user_id', $filters['user_id'] ?? null)
            ->NotHiddenForCustomer()
            ->when(is_numeric($filters['module_id'] ?? null), fn ($query) => $query->where('module_id', $filters['module_id']))
            ->when(($filters['is_guest'] ?? 1) === 0, fn ($query) => $query->where('is_guest', 0))
            ->Notpos();
    }

    private function listRelations(bool $fullList = true): array
    {
        $relations = [
            // store_business_model: see the note on the same column in lastOrders() above.
            'store:id,name,slug,logo,phone,module_id,store_business_model',
            'store.storage',
            'module:id,module_type',
            'delivery_man:id,f_name,l_name,phone,image',
            'delivery_man.rating',
            'delivery_man.last_location',
            'delivery_man.storage',
            'parcel_category',
            'parcel_category.storage',
            'weight',
            'dimension',
            'orderProDiscount',
        ];

        if (! $fullList) {
            return $relations;
        }

        return array_merge($relations, [
            'refund:order_id,admin_note,customer_note,image,customer_reason',
            // bogo_group_id/bundle_group_id: see the note on the same select in
            // getRecentDeliveredList() -- Order::can_reorder reads them off the details.
            'details:id,order_id,item_id,quantity,item_campaign_id,bogo_group_id,bundle_group_id',
            'details.item:id,name,image,store_id,unit_id',
            'details.item.storage',
        ]);
    }

    private function moduleFeedQuery(Builder $base, string $column, array $closed, ?string $type): Builder
    {
        return $base
            ->NotHiddenForCustomer()
            ->when($type === 'previous', fn ($query) => $query->whereIn($column, $closed))
            ->when($type === 'running', fn ($query) => $query->whereNotIn($column, $closed));
    }

    private function moduleStatusCounts(Builder $base, string $column, array $closed): array
    {
        $placeholders = implode(',', array_fill(0, count($closed), '?'));

        $row = $base->NotHiddenForCustomer()
            ->selectRaw('COUNT(*) as all_count')
            ->selectRaw("SUM(CASE WHEN $column IN ($placeholders) THEN 1 ELSE 0 END) as previous_count", $closed)
            ->selectRaw("SUM(CASE WHEN $column NOT IN ($placeholders) THEN 1 ELSE 0 END) as running_count", $closed)
            ->first();

        return [
            'all_count' => (int) ($row->all_count ?? 0),
            'previous_count' => (int) ($row->previous_count ?? 0),
            'running_count' => (int) ($row->running_count ?? 0),
        ];
    }

    private function tripBaseQuery(array $filters = []): Builder
    {
        return app(TripsService::class)->baseQuery($filters);
    }

    private function runningRideQuery(mixed $userId): Builder
    {
        return RideRequest::where('customer_id', $userId)
            ->where(fn ($query) => $query
                ->whereNotIn('current_status', self::RIDE_CLOSED_STATUSES)
                ->orWhere(fn ($unpaid) => $unpaid
                    ->whereNotNull('driver_id')
                    ->whereHas('fee', fn ($fee) => $fee
                        ->where(fn ($cancelled) => $cancelled->where('cancelled_by', '!=', 'driver')->orWhereNull('cancelled_by')))
                    ->whereIn('current_status', self::RIDE_CLOSED_STATUSES)
                    ->where('payment_status', 'unpaid')))
            ->selectRaw("ref_id as id, 'ride' as order_type, current_status as status, created_at");
    }

    private function runningTripQuery(mixed $userId): Builder
    {
        return app(TripsService::class)->runningQuery($userId, self::RIDE_CLOSED_STATUSES);
    }

    private function restoreStock(Order $order): void
    {
        $hasStock = config('module.'.$order->module->module_type)['stock'] ?? false;
        $hasFlashDiscount = $order->flash_admin_discount_amount > 0 && $order->flash_store_discount_amount > 0;

        if (! $hasStock && ! $hasFlashDiscount) {
            return;
        }

        foreach ($order->details as $detail) {
            if ($hasStock) {
                $variation = json_decode($detail->variation, true);
                self::updateItemStock(
                    $detail->campaign ?? $detail->item,
                    -$detail->quantity,
                    empty($variation) ? null : $variation[0]['type']
                )?->save();
            }

            if ($hasFlashDiscount) {
                self::updateFlashSaleStock($detail->item, $detail->quantity, true)?->save();
            }
        }
    }

    private function findLatestUnpaid(mixed $userId): ?Order
    {
        return Order::where('user_id', $userId)
            ->where('created_at', '>=', now()->subMonths(self::UNPAID_WINDOW_MONTHS))
            ->whereIn('order_status', self::UNPAID_STATUSES)
            ->whereNotIn('payment_method', self::SETTLED_METHODS)
            ->where(function ($query) {
                $query->where(function ($partial) {
                    $partial->where('payment_method', 'partial_payment')
                        ->whereHas('payments', fn ($payment) => $payment->where('payment_status', 'unpaid')
                            ->whereNotIn('payment_method', self::SETTLED_METHODS));
                })
                    ->orWhere(function ($offline) {
                        $offline->where('payment_method', 'offline_payment')->whereDoesntHave('offline_payments');
                    })
                    ->orWhere(function ($digital) {
                        $digital->whereNotIn('payment_method', [...self::SETTLED_METHODS, 'partial_payment', 'offline_payment']);
                    });
            })
            ->first();
    }

    private function loadPaymentContext(Order $order): Order
    {
        $order->load([
            'zone' => fn ($query) => $query->select('id', 'cash_on_delivery', 'digital_payment', 'offline_payment'),
            'module' => fn ($query) => $query->withoutGlobalScope('translate')->select('id', 'module_type')
                ->with(['zones' => fn ($zone) => $zone->select('zones.id')->where('zones.id', $order->zone_id)]),
        ]);

        return $order;
    }

    private function adjustEditedStockFromCart($cart, array $originalDetailQtys): void
    {
        $stockProducts = app(ItemService::class)->getStockProductsByIds(collect($cart)->pluck('item_id')->filter()->unique()->values()->all());

        foreach ($cart as $c) {
            if (empty($c['item_id'])) {
                continue;
            }

            $stockProduct = $stockProducts[$c['item_id']] ?? null;
            if (! $stockProduct || ! $stockProduct->module) {
                continue;
            }
            if (! data_get(config('module.'.$stockProduct->module->module_type), 'stock', false)) {
                continue;
            }

            $variationDecoded = is_string($c['variation']) ? (json_decode($c['variation'], true) ?: []) : (is_array($c['variation']) ? $c['variation'] : []);
            $variantType = (isset($variationDecoded[0]['type']) && $variationDecoded[0]['type'] !== '') ? $variationDecoded[0]['type'] : null;

            $wasKept = ! empty($c['status']);
            $originalQty = (isset($c->id) && isset($originalDetailQtys[$c->id])) ? (int) $originalDetailQtys[$c->id] : 0;

            $delta = $wasKept ? (int) $c['quantity'] - $originalQty : -$originalQty;
            if ($delta === 0) {
                continue;
            }

            self::updateItemStock($stockProduct, $delta, $variantType)?->save();
            if ($delta > 0) {
                self::updateFlashSaleStock($stockProduct, $delta)?->save();
            } else {
                self::updateFlashSaleStock($stockProduct, abs($delta), true)?->save();
            }
        }
    }

    private function vendorOrderQuery(array $filters): Builder
    {
        return Order::whereHas('store.vendor', fn ($query) => $query->where('id', $filters['vendor_id'] ?? null))
            ->with([
                'customer.storage', 'storage', 'module.storage', 'store.storage', 'store.store_sub', 'orderProDiscount',
                'details' => fn ($query) => $query->select('id', 'order_id', 'item_campaign_id'),
            ]);
    }

    private function constrainVendorRunning($query, array $filters): void
    {
        if (BusinessRules::storeConfirmsOrder() || ($filters['sub_self_delivery'] ?? false)) {
            $query->whereIn('order_status', ['accepted', 'pending', 'confirmed', 'processing', 'handover', 'picked_up']);

            return;
        }

        $query->whereIn('order_status', ['confirmed', 'processing', 'handover', 'picked_up'])
            ->orWhere(fn ($inner) => $inner->whereNotNull('confirmed')->where('order_status', 'accepted'))
            ->orWhere(fn ($inner) => $inner->where('payment_status', 'paid')->where('order_status', 'accepted'))
            ->orWhere(fn ($inner) => $inner->where('order_status', 'pending')->where('order_type', 'take_away'));
    }

    private function vendorStatusRejection(Order $order, mixed $store, array $data): ?array
    {
        if (($data['order_status'] ?? null) === 'canceled') {
            if (! BusinessRules::canceledByStore()) {
                return ['status_code' => 403, 'code' => 'status', 'message' => translate('messages.You can not cancel a order')];
            }

            if ($order->confirmed) {
                return ['status_code' => 403, 'code' => 'status', 'message' => translate('messages.You can not cancel after confirm')];
            }
        }

        if ($data['status'] === 'confirmed' && ! $store->sub_self_delivery
            && BusinessRules::deliverymanConfirmsOrder()
            && $order->order_type !== 'take_away' && ! $order->is_pos) {
            return ['status_code' => 403, 'code' => 'order-confirmation-model', 'message' => translate('messages.Order confirmation warning')];
        }

        if ($order->picked_up !== null) {
            return ['status_code' => 403, 'code' => 'status', 'message' => translate('messages.You can not change status after picked up by delivery man')];
        }

        if ($data['status'] === 'delivered' && $order->order_type !== 'take_away' && ! $order->is_pos && ! $store->sub_self_delivery) {
            return ['status_code' => 403, 'code' => 'status', 'message' => translate('messages.You can not delivered delivery order')];
        }

        if (BusinessRules::deliveryVerificationEnabled() && $data['status'] === 'delivered' && $order->otp != ($data['otp'] ?? null)) {
            return ['status_code' => 403, 'code' => 'otp', 'message' => 'Not matched'];
        }

        return null;
    }

    private function settleVendorDeliveredOrder(Order $order): void
    {
        $unpaidMethod = app(OrderPaymentService::class)->findUnpaidForOrder($order->id)?->payment_method ?? 'digital_payment';
        $receiver = $order->payment_method === 'cash_on_delivery' || $unpaidMethod === 'cash_on_delivery' ? 'store' : 'admin';

        $this->createOrderTransaction($order, $receiver, null);

        if ($order->delivery_man_id) {
            $this->recordDeliverymanLoyaltyPoint(
                deliveryManId: $order->delivery_man_id,
                amount: $order->order_amount,
                transactionType: 'earn_on_order_completion',
                pointConversionType: 'credit',
                reference: $order->id
            );
        }

        $order->payment_status = 'paid';
        $this->markUnpaidOrderPaymentPaid(orderId: $order->id, paymentMethod: $order->payment_method);
    }

    private function countVendorDeliveredOrder(Order $order): void
    {
        $order->details->each(fn ($detail) => $detail->item?->increment('order_count'));

        if ($order->is_guest == 0) {
            $order->customer?->increment('order_count');
        }

        $order->store?->increment('order_count');
    }

    private function uploadOrderProof(array $files): array
    {
        $images = [];

        foreach ($files as $file) {
            $images[] = ['img' => FileStorage::upload('order/', $file), 'storage' => FileStorage::getDisk()];
        }

        return $images;
    }

    private function constrainVendorOtp($query, mixed $store): void
    {
        if (BusinessRules::storeConfirmsOrder() || $store->sub_self_delivery) {
            $query->whereIn('order_status', ['accepted', 'pending', 'confirmed', 'processing', 'handover', 'picked_up']);

            return;
        }

        $query->whereIn('order_status', ['confirmed', 'processing', 'handover', 'picked_up'])
            ->orWhere(fn ($inner) => $inner->where('payment_status', 'paid')->where('order_status', 'accepted'))
            ->orWhere(fn ($inner) => $inner->where('order_status', 'pending')->where('order_type', 'take_away'));
    }

    private function otpNotification(Order $order): array
    {
        return NotificationMessages::orderReadyOtp($order);
    }

    private function rebuildOrderFromAmount(array $data, mixed $storeId): array
    {
        $order = $this->findWithRelations($data['order_id']);

        if (! $order || $order->store_id != $storeId) {
            return ['status_code' => 403, 'code' => 'order', 'message' => translate('No data found')];
        }

        $store = app(StoreService::class)->find($order->store_id);
        $coupon = null;
        $freeDeliveryBy = null;

        if ($order->coupon_code) {
            $coupon = app(CouponService::class)->findActiveByCode($order->coupon_code);

            if (! $coupon) {
                return ['status_code' => 404, 'code' => 'coupon', 'message' => translate('No data found')];
            }

            $rejection = $this->couponRejection(app(CouponService::class)->validateForCustomer($coupon, $order->user_id, $order->store_id));

            if ($rejection) {
                return $rejection;
            }
        }

        $productPrice = $data['order_amount'];
        $storeDiscount = Helpers::get_store_discount($store) ?: ['discount' => 0, 'max_discount' => 0, 'min_purchase' => 0];
        $storeDiscountAmount = Helpers::checkAdminDiscount(
            price: $productPrice,
            discount: $storeDiscount['discount'],
            max_discount: $storeDiscount['max_discount'],
            min_purchase: $storeDiscount['min_purchase']
        );

        $order->discount_on_product_by = $storeDiscountAmount > 0 ? 'admin' : ($order->discount_on_product_by ?? 'vendor');

        $couponDiscountAmount = $coupon ? $this->calculateDiscount($coupon, $productPrice - $storeDiscountAmount) : 0;
        $totalPrice = max($productPrice - $storeDiscountAmount - $couponDiscountAmount, 0);

        $this->applyVendorEditCharges($order, $data);

        if (app(FreeDeliveryService::class)->frees(
            [$store?->zone_id],
            $store?->module_id,
            $productPrice - $couponDiscountAmount - $storeDiscountAmount,
        )) {
            $order->delivery_charge = 0;
            $freeDeliveryBy = 'admin';
        }

        if ($store->free_delivery) {
            $order->delivery_charge = 0;
            $freeDeliveryBy = 'vendor';
        }

        if ($coupon) {
            if ($coupon->coupon_type === 'free_delivery' && $coupon->min_purchase <= $productPrice - $storeDiscountAmount) {
                $order->delivery_charge = 0;
                $freeDeliveryBy = 'admin';
            }

            $coupon->increment('total_uses');
        }

        $proRecompute = $this->recomputeOrderProDiscountOnEdit(
            order: $order,
            subtotal: (float) $productPrice,
            totalPrice: (float) $totalPrice,
            moduleType: app(StoreService::class)->findModuleType($order->store_id),
            deliveryCharge: (float) $order->delivery_charge,
        );

        $totalPrice = (float) $proRecompute['total_price'];
        $order->delivery_charge = (float) $proRecompute['delivery_charge'];

        if ($proRecompute['delivery_savings'] > 0) {
            $freeDeliveryBy = $proRecompute['free_delivery_by'];
        }

        $orderTaxIds = $this->applyVendorEditTax($order, $totalPrice, $store->id);

        $order->coupon_discount_amount = round($couponDiscountAmount, config('round_up_to_digit'));
        $order->coupon_discount_title = $coupon ? $coupon->title : '';
        $order->store_discount_amount = round($storeDiscountAmount, config('round_up_to_digit'));
        $order->order_amount = round($totalPrice + $order->total_tax_amount + $order->delivery_charge, config('round_up_to_digit'));
        $order->free_delivery_by = $freeDeliveryBy;
        $order->order_amount = $this->applyDeliveryTypeToAmount($order, (float) $order->order_amount);
        $order->order_amount = $order->order_amount + $order->dm_tips + $order->additional_charge;
        $order->save();

        return ['order' => $order, 'order_tax_ids' => $orderTaxIds];
    }

    private function rebuildOrderFromDiscount(array $data, mixed $storeId): array
    {
        $order = $this->findWithRelations($data['order_id']);

        if (! $order || $order->store_id != $storeId) {
            return ['status_code' => 403, 'code' => 'order', 'message' => translate('No data found')];
        }

        $productPrice = $order['order_amount'] + $order->store_discount_amount - $order['delivery_charge']
            - $order['total_tax_amount'] - $order['dm_tips'] - $order->additional_charge
            + (float) ($order->orderProDiscount?->amount_saved ?? 0);

        if ($data['discount_amount'] > $productPrice) {
            return ['status_code' => 403, 'code' => 'order', 'message' => translate('messages.Discount amount is greater then product amount')];
        }

        $this->applyVendorEditCharges($order, $data);

        $proRecompute = $this->recomputeOrderProDiscountOnEdit(
            order: $order,
            subtotal: (float) $productPrice,
            totalPrice: max((float) ($productPrice - $data['discount_amount']), 0),
            moduleType: app(StoreService::class)->findModuleType($order->store_id),
            deliveryCharge: (float) $order->delivery_charge,
        );

        $proDiscountAmount = (float) $proRecompute['discount'];
        $order->delivery_charge = (float) $proRecompute['delivery_charge'];

        if ($proRecompute['delivery_savings'] > 0) {
            $order->free_delivery_by = $proRecompute['free_delivery_by'];
        }

        $orderTaxIds = $this->applyVendorEditTax($order, $productPrice - $data['discount_amount'] - $proDiscountAmount, $order->store_id);

        $order->discount_on_product_by = 'vendor';
        $order->store_discount_amount = round($data['discount_amount'], config('round_up_to_digit'));
        $order->order_amount = $productPrice + $order['delivery_charge'] + $order['total_tax_amount'] + $order['dm_tips']
            - $order->store_discount_amount + $order->additional_charge - $proDiscountAmount;
        $order->save();

        return ['order' => $order, 'order_tax_ids' => $orderTaxIds];
    }

    private function applyVendorEditCharges(Order $order, array $data): void
    {
        $settings = app(BusinessSettingService::class)->valuesFor(['dm_tips_status', 'additional_charge_status', 'additional_charge']);

        $order->dm_tips = ($settings['dm_tips_status'] ?? null) == 1 ? ($order->dm_tips ?? $data['dm_tips'] ?? 0) : 0;

        if (($settings['additional_charge_status'] ?? null) == 1) {
            $order->additional_charge = $settings['additional_charge'] ?? 0;
        }
    }

    private function applyVendorEditTax(Order $order, mixed $amount, mixed $storeId): array
    {
        $taxData = CalculateTaxService::getCalculatedTax(
            amount: $amount,
            productIds: [],
            taxPayer: 'prescription',
            storeData: true,
            additionalCharges: [],
            addonIds: [],
            orderId: null,
            storeId: $storeId
        );

        $order->total_tax_amount = round($taxData['totalTaxamount'], config('round_up_to_digit'));
        $order->tax_status = $taxData['include'] ? 'included' : 'excluded';

        return $taxData['orderTaxIds'] ?? [];
    }

    private function couponRejection(mixed $status): ?array
    {
        return match ((int) $status) {
            407 => ['status_code' => 407, 'code' => 'coupon', 'message' => translate('messages.Coupon expire')],
            406 => ['status_code' => 406, 'code' => 'coupon', 'message' => translate('messages.Coupon usage limit over')],
            409 => ['status_code' => 403, 'code' => 'coupon', 'message' => translate('messages.Coupon not valid for this zone')],
            404 => ['status_code' => 404, 'code' => 'coupon', 'message' => translate('No data found')],
            default => null,
        };
    }

    private function cartRequest(array $payload): object
    {
        return new class($payload)
        {
            public function __construct(private array $payload) {}

            public function input(string $key, mixed $default = null): mixed
            {
                return $this->payload[$key] ?? $default;
            }

            public function file(string $key): mixed
            {
                return $this->payload[$key] ?? null;
            }
        };
    }

    public function promotionLockedRefusal(Request $request): ?JsonResponse
    {
        if (! isset($request->cart_item_key)) {
            return null;
        }

        $existing = $request->session()->get('order_cart', collect([]))[$request->cart_item_key] ?? null;

        if (! $existing) {
            return null;
        }

        if (data_get($existing, 'bogo_group_id')) {
            return response()->json([
                'data' => 'bogo_locked',
                'message' => translate('A BOGO offer can only have its quantity changed'),
            ]);
        }

        if (data_get($existing, 'bundle_group_id')) {
            return response()->json([
                'data' => 'bundle_locked',
                'message' => translate('messages.a bundle can only have its quantity changed'),
            ]);
        }

        return null;
    }
}
