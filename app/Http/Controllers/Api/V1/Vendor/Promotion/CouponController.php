<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\CouponIdRequest;
use App\Http\Requests\Vendor\Promotion\CouponSearchRequest;
use App\Http\Requests\Vendor\Promotion\CouponStatusRequest;
use App\Http\Requests\Vendor\Promotion\CouponStoreRequest;
use App\Http\Requests\Vendor\Promotion\CouponUpdateRequest;
use App\Http\Resources\Vendor\Promotion\CouponResource;
use App\Services\Marketing\CouponService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly CouponService $couponService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $coupons = $this->couponService->getList(
            filters: ['store_id' => $this->vendorStoreId($request)],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CouponResource::collection($coupons),
            'pagination' => $this->paginateFormatter($coupons),
        ]);
    }

    public function search(CouponSearchRequest $request): JsonResponse
    {
        $coupons = $this->couponService->getSearchList(
            filters: ['store_id' => $this->vendorStoreId($request), 'search' => $request->input('search')],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CouponResource::collection($coupons),
            'pagination' => $this->paginateFormatter($coupons),
        ]);
    }

    public function show(CouponIdRequest $request): JsonResponse
    {
        $coupon = $this->couponService->findWithTranslations(
            $request->input('coupon_id'),
            $this->vendorStoreId($request)
        );

        return $coupon
            ? $this->responseFormatter(config('response.default_200'), new CouponResource($coupon))
            : $this->responseFormatter(config('response.default_404'));
    }

    public function store(CouponStoreRequest $request): JsonResponse
    {
        $this->couponService->create(array_merge($request->payload(), [
            'store_id' => $this->vendorStoreId($request),
            'module_id' => $this->vendorStore($request)?->module_id,
        ]));

        return $this->responseFormatter(config('response.default_store_201'));
    }

    public function update(CouponUpdateRequest $request): JsonResponse
    {
        $coupon = $this->couponService->update(
            $request->input('coupon_id'),
            $request->payload(),
            $this->vendorStoreId($request)
        );

        return $coupon
            ? $this->responseFormatter(config('response.default_update_200'))
            : $this->responseFormatter(config('response.default_404'));
    }

    public function updateStatus(CouponStatusRequest $request): JsonResponse
    {
        $coupon = $this->couponService->updateStatus(
            $request->input('coupon_id'),
            $request->input('status'),
            $this->vendorStoreId($request)
        );

        return $coupon
            ? $this->responseFormatter(config('response.default_update_200'))
            : $this->responseFormatter(config('response.default_404'));
    }

    public function destroy(CouponIdRequest $request): JsonResponse
    {
        $deleted = $this->couponService->delete(
            $request->input('coupon_id'),
            $this->vendorStoreId($request)
        );

        return $deleted
            ? $this->responseFormatter(config('response.default_delete_200'))
            : $this->responseFormatter(config('response.default_404'));
    }
}
