<?php

namespace App\Services\Marketing;

use App\Contracts\Repositories\CouponRepositoryInterface;
use App\Models\Coupon;
use App\Services\BaseService;
use App\Traits\Marketing\CouponDiscountTrait;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Services\Order\OrderService;
use App\Services\Store\StoreService;
use Modules\Rental\Services\Trip\TripsService;

class CouponService extends BaseService
{
    use CouponDiscountTrait;

    use ProCustomerSubscriptionTrait;

    private const TYPE_STORE_WISE = 'store_wise';

    private const TYPE_FREE_DELIVERY = 'free_delivery';

    private const TYPE_ZONE_WISE = 'zone_wise';

    private const TYPE_FIRST_ORDER = 'first_order';

    private const TYPE_PRO_CUSTOMER = 'pro_customer';

    private const BRANCHED_TYPES = [
        self::TYPE_STORE_WISE,
        self::TYPE_ZONE_WISE,
        self::TYPE_FIRST_ORDER,
        self::TYPE_PRO_CUSTOMER,
    ];

    private const CUSTOMER_COLUMNS = [
        'id', 'title', 'code', 'start_date', 'expire_date', 'min_purchase', 'max_discount',
        'discount', 'discount_type', 'coupon_type', 'data', 'customer_id', 'store_id',
    ];

    private const VENDOR_COLUMNS = [
        'id', 'title', 'code', 'start_date', 'expire_date', 'min_purchase', 'max_discount',
        'discount', 'discount_type', 'coupon_type', 'limit', 'status', 'data', 'total_uses',
        'customer_id', 'store_id', 'created_at',
    ];

    private const VENDOR_CREATOR = 'vendor';

    public function findByCode(mixed $code): mixed
    {
        return Coupon::where('code', $code)->first();
    }

    public function __construct(
        protected CouponRepositoryInterface $couponRepo,
    )
    {
    }

    public function getCustomerList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $page = $this->customerQuery($filters)
            ->select(self::CUSTOMER_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        return $this->attachStoreWiseStores($page, $filters);
    }

    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->vendorQuery($filters)
            ->latest()
            ->select(self::VENDOR_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getSearchList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $keywords = explode(' ', (string) ($filters['search'] ?? ''));

        return $this->vendorQuery($filters)
            ->where(function ($query) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $query->orWhere('title', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%");
                }
            })
            ->select(self::VENDOR_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findForStore(mixed $couponId, mixed $storeId): ?Coupon
    {
        return $this->vendorQuery(['store_id' => $storeId])->find($couponId);
    }

    public function findWithTranslations(mixed $couponId, mixed $storeId): ?Coupon
    {
        return Coupon::withoutGlobalScope('translate')
            ->with('translations')
            ->where('created_by', self::VENDOR_CREATOR)
            ->where('store_id', $storeId)
            ->find($couponId);
    }

    public function create(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            $coupon = new Coupon();
            $this->fill($coupon, $data);
            $coupon->status = 1;
            $coupon->created_by = self::VENDOR_CREATOR;
            $coupon->data = array_key_exists('data', $data) ? $data['data'] : json_encode('');
            $coupon->store_id = $data['store_id'];
            $coupon->module_id = $data['module_id'];
            $coupon->save();

            $this->insertTitleTranslations($coupon, $data['translations']);

            return $coupon;
        });
    }

    public function update(mixed $couponId, array $data, mixed $storeId): ?Coupon
    {
        $coupon = $this->findForStore($couponId, $storeId);

        if (! $coupon) {
            return null;
        }

        return DB::transaction(function () use ($coupon, $data) {
            $this->fill($coupon, $data);
            $coupon->save();

            foreach ($data['translations'] as $row) {
                if (! isset($row['locale'], $row['value'])) {
                    continue;
                }

                $coupon->translations()->updateOrCreate(
                    ['locale' => $row['locale'], 'key' => 'title'],
                    ['value' => $row['value']]
                );
            }

            return $coupon;
        });
    }

    public function updateStatus(mixed $couponId, mixed $status, mixed $storeId): ?Coupon
    {
        $coupon = $this->findForStore($couponId, $storeId);

        if (! $coupon) {
            return null;
        }

        $coupon->status = $status;
        $coupon->save();

        return $coupon;
    }

    public function toggleStatus(mixed $couponId, mixed $storeId): ?Coupon
    {
        $coupon = $this->findForStore($couponId, $storeId);

        return $coupon ? $this->updateStatus($couponId, ! $coupon->status, $storeId) : null;
    }

    public function delete(mixed $couponId, mixed $storeId): bool
    {
        $coupon = $this->findForStore($couponId, $storeId);

        return $coupon ? (bool) $coupon->delete() : false;
    }

    public function findActiveByCode(mixed $code, ?string $moduleType = null): ?Coupon
    {
        return Coupon::active()
            ->where('code', $code)
            ->when($moduleType, fn ($query) => $query
                ->whereHas('module', fn ($sub) => $sub->where('module_type', $moduleType)))
            ->first();
    }

    public function validateForCustomer(mixed $coupon, mixed $customerId, mixed $storeId, mixed $moduleId = null, mixed $orderAmount = null): mixed
    {
        $moduleId = $moduleId ?? config('module.current_module_data')['id'];

        $rejection = match (true) {
            isset($moduleId) && $coupon->module_id != $moduleId => 404,
            $this->outsideValidityWindow($coupon) => 407,
            $this->outsideCouponStores($coupon, $storeId) => 404,
            $this->outsideCreatorStore($coupon, $storeId) => 404,
            $coupon->coupon_type == self::TYPE_PRO_CUSTOMER && ! $this->proGrantsCoupon($customerId) => 408,
            ! $this->customerEligible($coupon, $customerId) => 408,
            $coupon->coupon_type == self::TYPE_ZONE_WISE && ! $this->storeInCouponZones($coupon, $storeId) => 409,
            default => null,
        };

        if ($rejection !== null) {
            return $rejection;
        }

        if ($coupon->coupon_type == self::TYPE_FIRST_ORDER) {
            return $this->withinLimit(app(OrderService::class)->countForUser($customerId), $coupon);
        }

        return match (true) {
            $coupon->coupon_type == self::TYPE_FREE_DELIVERY && $this->proCoversDeliveryFee($customerId, $orderAmount, $storeId) => 410,
            $coupon['limit'] == null || $customerId == null => 200,
            $coupon->module?->module_type === 'rental' => app(TripsService::class)->couponLimitReached($customerId, $coupon) ? 406 : 200,
            default => $this->withinLimit(
                app(OrderService::class)->countForUserWithCoupon($customerId, $coupon['code']),
                $coupon
            ),
        };
    }

    public function validateForGuest(mixed $coupon, mixed $storeId, mixed $moduleId = null): mixed
    {
        $moduleId = $moduleId ?? config('module.current_module_data')['id'];

        return match (true) {
            isset($moduleId) && $coupon->module_id != $moduleId => 404,
            $this->outsideValidityWindow($coupon) => 407,
            $this->outsideCouponStores($coupon, $storeId) => 404,
            $this->outsideCreatorStore($coupon, $storeId) => 404,
            $coupon->coupon_type == self::TYPE_PRO_CUSTOMER => 408,
            $coupon->coupon_type == self::TYPE_ZONE_WISE && ! $this->storeInCouponZones($coupon, $storeId) => 409,
            $coupon['limit'] == null => 200,
            default => 404,
        };
    }

    public function getAddData(array $input, int|string $moduleId): array
    {
        $data  = '';
        $customerId  = ($input['customer_ids'] ?? null) ?? ['all'];
        if(($input['coupon_type'] ?? null) == 'zone_wise')
        {
            $data = ($input['zone_ids'] ?? null);
        }
        else if(($input['coupon_type'] ?? null) == 'store_wise')
        {
            $data = ($input['store_ids'] ?? null);
        }
        return [
            'title' => ($input['title'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'code' => ($input['code'] ?? null),
            'limit' => ($input['coupon_type'] ?? null)=='first_order'?1:($input['limit'] ?? null),
            'coupon_type' => ($input['coupon_type'] ?? null),
            'start_date' => ($input['coupon_type'] ?? null) == 'pro_customer' ? null : ($input['start_date'] ?? null),
            'expire_date' => ($input['coupon_type'] ?? null) == 'pro_customer' ? null : ($input['expire_date'] ?? null),
            'min_purchase' => ($input['min_purchase'] ?? null) != null ? ($input['min_purchase'] ?? null) : 0,
            'max_discount' => ($input['max_discount'] ?? null) != null ? ($input['max_discount'] ?? null) : 0,
            // Same 0-when-missing fallback min_purchase/max_discount already use above. A
            // free-delivery coupon disables this field on the form (nothing to discount), so it
            // never reaches the request at all -- ($input['discount'] ?? null) resolved straight
            // to null, and the discount column has no default, so every free-delivery coupon
            // failed on save with "Column 'discount' cannot be null" instead of storing 0.
            'discount' => ($input['discount'] ?? null) != null ? ($input['discount'] ?? null) : 0,
            'discount_type' => ($input['discount_type'] ?? null)??'',
            'status' =>  1,
            'created_by' =>  'admin',
            'data' =>  json_encode($data),
            'customer_id' =>  json_encode($customerId),
            'module_id' => $moduleId,
            'store_id' => is_array($data) && ($input['coupon_type'] ?? null) == 'store_wise' ? $data[0] : null ,
        ];
    }

    public function getUniqueCouponCode(?string $title): string
    {
        if (!$title) {
            $code = strtoupper(bin2hex(random_bytes(4)));
        } else {
            $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $title));
            if (empty($code)) {
                $code = strtoupper(bin2hex(random_bytes(4)));
            }
        }

        $code = substr($code, 0, 12);

        $exists = $this->couponRepo->getFirstWhere(params: ['code' => $code]);
        if (!$exists) {
            return $code;
        }

        $i = 1;
        while (true) {
            $suffix = (string)$i;
            $candidate = substr($code, 0, 12 - strlen($suffix)) . $suffix;
            if (!$this->couponRepo->getFirstWhere(params: ['code' => $candidate])) {
                return $candidate;
            }
            $i++;
        }
    }

    public function findActiveFreeDelivery(mixed $code): ?Coupon
    {
        return Coupon::where('code', $code)
            ->where('coupon_type', 'free_delivery')
            ->where('status', 1)
            ->first();
    }

    private function excludeExhausted(mixed $query, array $usage): void
    {
        $query->where(function ($branch) use ($usage) {
            $branch->whereNull('limit')->orWhereNotIn('code', array_keys($usage));

            foreach ($usage as $code => $used) {
                $branch->orWhere(fn ($sub) => $sub->where('code', $code)->where('limit', '>', (int) $used));
            }
        });
    }

    private function customerQuery(array $filters): Builder
    {
        $today = date('Y-m-d');

        return Coupon::with([
            'store' => fn ($query) => $query->withStorage()
                ->select('id', 'name', 'status', 'zone_id')
                ->with('storeConfig:id,store_id,verified_seller'),
        ])
            ->active()
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->when($filters['module_type'] ?? null, fn ($query, $moduleType) => $query
                ->whereHas('module', fn ($sub) => $sub->where('module_type', $moduleType)))
            ->where(fn ($query) => $query
                ->where('coupon_type', self::TYPE_PRO_CUSTOMER)
                ->orWhere(fn ($window) => $window
                    ->whereDate('expire_date', '>=', $today)
                    ->whereDate('start_date', '<=', $today)))
            ->where(fn ($query) => $this->eligibilityBranches($query, $filters))
            ->when($filters['coupon_usage'] ?? null, fn ($query, $usage) => $this->excludeExhausted($query, $usage));
    }

    private function vendorQuery(array $filters): Builder
    {
        return Coupon::where('created_by', self::VENDOR_CREATOR)
            ->where('store_id', $filters['store_id'] ?? null);
    }

    private function fill(Coupon $coupon, array $data): void
    {
        $coupon->title = $data['translations'][0]['value'] ?? null;
        $coupon->code = $data['code'];
        $coupon->limit = $data['coupon_type'] === self::TYPE_FIRST_ORDER ? 1 : $data['limit'];
        $coupon->coupon_type = $data['coupon_type'];
        $coupon->start_date = $data['start_date'];
        $coupon->expire_date = $data['expire_date'];
        $coupon->min_purchase = $data['min_purchase'] ?? 0;
        $coupon->max_discount = $data['max_discount'] ?? 0;
        $coupon->discount = $data['discount'] ?? 0;
        $coupon->discount_type = $data['discount_type'] ?? '';
        $coupon->customer_id = json_encode($data['customer_ids']);
    }

    private function insertTitleTranslations(Coupon $coupon, array $rows): void
    {
        $payload = [];

        foreach ($rows as $row) {
            if (! isset($row['locale'], $row['value'])) {
                continue;
            }

            $payload[] = array_merge($row, [
                'translationable_type' => $coupon->getMorphClass(),
                'translationable_id' => $coupon->getKey(),
            ]);
        }

        if ($payload) {
            $coupon->translations()->insert($payload);
        }
    }

    private function outsideValidityWindow(mixed $coupon): bool
    {
        $today = Carbon::now()->format('Y-m-d');

        return Carbon::parse($coupon->start_date)->format('Y-m-d') > $today
            || Carbon::parse($coupon->expire_date)->format('Y-m-d') < $today;
    }

    private function outsideCouponStores(mixed $coupon, mixed $storeId): bool
    {
        return $coupon->coupon_type == self::TYPE_STORE_WISE
            && ! in_array($storeId, json_decode($coupon->data, true));
    }

    private function outsideCreatorStore(mixed $coupon, mixed $storeId): bool
    {
        return $coupon->created_by == self::VENDOR_CREATOR && $storeId != $coupon->store_id;
    }

    private function customerEligible(mixed $coupon, mixed $customerId): bool
    {
        $customerIds = json_decode($coupon->customer_id, true);

        return in_array('all', $customerIds) || in_array($customerId, $customerIds);
    }

    private function storeInCouponZones(mixed $coupon, mixed $storeId): bool
    {
        $zoneIds = json_decode($coupon->data, true);

        return $zoneIds
            ? app(StoreService::class)->existsInZones($storeId, $zoneIds)
            : false;
    }

    private function proGrantsCoupon(mixed $customerId): bool
    {
        $proOffer = $this->getProCustomerOffer(userId: $customerId);

        return ($proOffer['status'] ?? false) && ($proOffer['benefit']['type'] ?? null) === 'coupon';
    }

    private function withinLimit(int $used, mixed $coupon): int
    {
        return $used < $coupon['limit'] ? 200 : 406;
    }

    private function proCoversDeliveryFee(mixed $customerId, mixed $orderAmount = null, mixed $storeId = null): bool
    {
        $moduleType = $storeId
            ? app(StoreService::class)->findModuleTypeBasic($storeId)
            : null;

        if (! $moduleType) {
            return false;
        }

        $benefit = $this->getProCustomerOffer(userId: $customerId, moduleType: $moduleType);

        return match (true) {
            ! ($benefit['status'] ?? false) => false,
            ($benefit['benefit']['type'] ?? null) !== 'delivery_fee' => false,
            ($benefit['benefit']['offer_type'] ?? null) !== 'full_free' => false,
            default => (int) ($benefit['benefit']['min_order_status'] ?? 0) !== 1
                || (($benefit['benefit']['min_order_amount'] ?? null) !== null
                    && $orderAmount !== null
                    && $orderAmount >= $benefit['benefit']['min_order_amount']),
        };
    }

    private function eligibilityBranches(mixed $query, array $filters): void
    {
        $query->where(fn ($branch) => $branch
                ->where('coupon_type', self::TYPE_STORE_WISE)
                ->where(fn ($q) => $this->customerAllowed($q, $filters))
                ->whereExists(fn ($sub) => $this->storeWiseStoreExists($sub, $filters)))

            ->orWhere(fn ($branch) => $branch
                ->where('coupon_type', self::TYPE_ZONE_WISE)
                ->where(fn ($q) => $this->dataContainsAnyZone($q, $filters)))

            ->orWhere(fn ($branch) => $branch
                ->where('coupon_type', self::TYPE_FIRST_ORDER)
                ->whereRaw($this->firstOrderAllowed($filters) ? '1 = 1' : '1 = 0')
                ->when(
                    array_key_exists('first_order_eligible', $filters),
                    fn ($query) => $query->where(fn ($sub) => $this->customerAllowed($sub, $filters))
                ))

            ->orWhere(fn ($branch) => $branch
                ->where('coupon_type', self::TYPE_PRO_CUSTOMER)
                ->whereRaw(($filters['pro_coupon_eligible'] ?? false) ? '1 = 1' : '1 = 0'))

            ->orWhere(fn ($branch) => $branch
                ->whereNotIn('coupon_type', self::BRANCHED_TYPES)
                ->whereNotNull('coupon_type')
                ->whereNotNull('store_id')
                ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('store_id', $storeId))
                ->whereExists(fn ($sub) => $this->scopedStoreExists($sub, $filters)))

            ->orWhere(fn ($branch) => $branch
                ->whereNotIn('coupon_type', self::BRANCHED_TYPES)
                ->whereNull('store_id')
                ->where(fn ($q) => $this->customerAllowed($q, $filters)));
    }

    private function customerAllowed(mixed $query, array $filters): void
    {
        $customerId = $filters['customer_id'] ?? null;

        $query->whereRaw("JSON_VALID(customer_id) AND JSON_CONTAINS(customer_id, '\"all\"')");

        if ($customerId) {
            $query->orWhereRaw('JSON_VALID(customer_id) AND JSON_CONTAINS(customer_id, JSON_QUOTE(?))', [(string) $customerId]);
        }
    }

    private function dataContainsAnyZone(mixed $query, array $filters): void
    {
        $zoneIds = $filters['zone_ids'] ?? [];

        if (! $zoneIds) {
            $query->whereRaw('1 = 0');

            return;
        }

        foreach ($zoneIds as $index => $zoneId) {
            $sql = 'JSON_VALID(data) AND JSON_CONTAINS(data, JSON_QUOTE(?))';
            $index === 0
                ? $query->whereRaw($sql, [(string) $zoneId])
                : $query->orWhereRaw($sql, [(string) $zoneId]);
        }
    }

    private function storeWiseStoreExists(mixed $sub, array $filters): void
    {
        $sub->select(DB::raw(1))
            ->from('stores')
            ->whereRaw('JSON_VALID(coupons.data) AND JSON_CONTAINS(coupons.data, JSON_QUOTE(CAST(stores.id AS CHAR)))')
            ->where('stores.status', 1)
            ->when($filters['store_id'] ?? null, fn ($q, $storeId) => $q->where('stores.id', $storeId));

        $this->applyZoneScope($sub, $filters);
    }

    private function scopedStoreExists(mixed $sub, array $filters): void
    {
        $sub->select(DB::raw(1))
            ->from('stores')
            ->whereColumn('stores.id', 'coupons.store_id')
            ->where('stores.status', 1);

        $this->applyZoneScope($sub, $filters);
    }

    private function applyZoneScope(mixed $sub, array $filters): void
    {
        if (($filters['module_id'] ?? null) && ! ($filters['all_zone_service'] ?? false)) {
            $sub->whereIn('stores.zone_id', $filters['zone_ids'] ?? []);
        }
    }

    private function firstOrderAllowed(array $filters): bool
    {
        return array_key_exists('first_order_eligible', $filters)
            ? (bool) $filters['first_order_eligible']
            : $this->firstOrderEligible($filters);
    }

    private function firstOrderEligible(array $filters): bool
    {
        $customerId = $filters['customer_id'] ?? null;

        if (! $customerId) {
            return false;
        }

        return app(OrderService::class)->hasNoPastOrders($customerId);
    }

    private function attachStoreWiseStores(LengthAwarePaginator $page, array $filters): LengthAwarePaginator
    {
        $storeWise = collect($page->items())->filter(fn ($c) => $c->coupon_type === self::TYPE_STORE_WISE);

        if ($storeWise->isEmpty()) {
            return $page;
        }

        $candidateIds = $storeWise
            ->flatMap(fn ($c) => json_decode($c->getRawOriginal('data') ?? '', true) ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        $stores = app(StoreService::class)->getCouponCandidates($candidateIds, $filters);

        foreach ($storeWise as $coupon) {
            foreach (json_decode($coupon->getRawOriginal('data') ?? '', true) ?? [] as $candidate) {
                if ($store = $stores->get((int) $candidate)) {
                    $coupon->setRelation('store', $store);
                    break;
                }
            }
        }

        return $page;
    }

}
