<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\CouponApplyRequest;
use App\Http\Resources\Customer\Promotion\CouponResource;
use App\Services\Marketing\CouponService;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends BaseApiController
{
    use ProCustomerSubscriptionTrait;
    use ApiRequestContextTrait;

    private const COUPON_ERROR_CODE = 'coupon';

    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getCustomerList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CouponResource::collection($coupons),
            'pagination' => $this->paginateFormatter($coupons),
        ]);
    }

    public function apply(CouponApplyRequest $request): JsonResponse
    {
        $coupon = $this->couponService->findActiveByCode($request->input('code'));

        if (! $coupon) {
            return $this->couponError(config('response.default_404'), translate('Invalid coupon code.'));
        }

        $status = $this->couponService->validateForCustomer(
            coupon: $coupon,
            customerId: $request->user()->id,
            storeId: $request->input('store_id'),
            orderAmount: $request->input('order_amount')
        );

        return match ($status) {
            200 => $this->responseFormatter(config('response.default_200'), new CouponResource($coupon)),
            406 => $this->couponError(config('response.not_acceptable_406'), translate('messages.Coupon usage limit over')),
            407 => $this->couponError(config('response.expired_407'), translate('messages.Coupon expire')),
            408 => $this->couponError(config('response.forbidden_403'), translate('messages.You are not eligible for this coupon')),
            409 => $this->couponError(config('response.forbidden_403'), translate('messages.Coupon not valid for this zone')),
            410 => $this->couponError(config('response.forbidden_403'), translate('messages.Free delivery already covered by pro')),
            default => $this->couponError(config('response.default_404'), translate('No data found')),
        };
    }

    private function filters(Request $request): array
    {
        $customerId = $request->user()?->id ?? $request->input('customer_id');
        $proOffer = $this->getProCustomerOffer(userId: $customerId);
        $module = config('module.current_module_data');

        return [
            'customer_id' => $customerId,
            'store_id' => $request->input('store_id'),
            'zone_ids' => $this->couponZoneIds($request),
            'module_id' => $module['id'] ?? null,
            'all_zone_service' => $module['all_zone_service'] ?? false,
            'pro_coupon_eligible' => ($proOffer['status'] ?? false)
                && (($proOffer['benefit']['type'] ?? null) === 'coupon'),
        ];
    }

    private function couponZoneIds(Request $request): array
    {
        $zone = $request->input('zone_id') ?? $request->header('zoneId');

        if (is_array($zone)) {
            return $zone;
        }

        $decoded = json_decode((string) $zone, true);

        return is_array($decoded)
            ? $decoded
            : array_values(array_filter([$zone], fn ($value) => $value !== null && $value !== ''));
    }

    private function couponError(array $config, string $message): JsonResponse
    {
        return $this->responseFormatter($config, errors: [
            ['code' => self::COUPON_ERROR_CODE, 'message' => $message],
        ]);
    }
}
