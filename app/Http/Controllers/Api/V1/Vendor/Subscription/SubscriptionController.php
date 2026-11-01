<?php

namespace App\Http\Controllers\Api\V1\Vendor\Subscription;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Subscription\BusinessPlanRequest;
use App\Http\Requests\Vendor\Subscription\CancelSubscriptionRequest;
use App\Http\Requests\Vendor\Subscription\ProductLimitRequest;
use App\Models\Store;
use App\Services\Payment\StoreSubscriptionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly StoreSubscriptionService $storeSubscriptionService) {}

    public function businessPlan(BusinessPlanRequest $request): JsonResponse
    {
        $store = Store::find($request->input('store_id'));

        if (! $store || ! $this->mayChoosePlanFor($request, $store)) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $result = $this->storeSubscriptionService->choosePlan($store, [
            'business_plan' => $request->input('business_plan'),
            'package_id' => $request->input('package_id'),
            'payment_gateway' => $request->input('payment_gateway'),
            'payment_platform' => $request->input('payment_platform') ?? 'web',
            'callback' => $request->has('callback') ? $request->input('callback') : session('callback'),
            'type' => $request->input('type'),
        ]);

        if ($result['unresolved'] ?? false) {
            return $this->responseFormatter(config('response.default_404'));
        }

        if ($result['insufficient_balance'] ?? false) {
            return $this->errorResponse(
                config('response.forbidden_403'),
                translate('messages.Insufficient wallet balance'),
                'wallet'
            );
        }

        return $this->responseFormatter(config('response.default_200'), $result);
    }

    /**
     * The route runs without vendor.api, because the app picks a plan right after registering,
     * before the vendor can log in. That left store_id as the only input: anyone could switch any
     * store to commission, start a trial on it, or buy a plan out of its vendor's wallet.
     *
     * So without a login only a store that has not chosen a plan yet ('none', the registration
     * state) is accepted; any other store needs its owner's own token.
     */
    private function mayChoosePlanFor(BusinessPlanRequest $request, Store $store): bool
    {
        if ($store->store_business_model === 'none') {
            return true;
        }

        if ($store->store_business_model === 'commission' && (int) $store->status === 0 && $request->input('business_plan') === 'commission') {
            return true;
        }

        $token = (string) $request->bearerToken();
        $owner = $token !== '' ? \App\Models\Vendor::where('auth_token', $token)->first(['id']) : null;

        return $owner !== null && (int) $owner->id === (int) $store->vendor_id;
    }

    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $store = $this->ownedStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->storeSubscriptionService->cancel([
            'subscription_id' => $request->input('subscription_id'),
            'store_id' => $store->id,
        ]);

        $this->storeSubscriptionService->notifyCancellation($store);

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function checkProductLimits(ProductLimitRequest $request): JsonResponse
    {
        $store = $this->ownedStore($request);

        if (! $store) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(
            config('response.default_200'),
            $this->storeSubscriptionService->productLimitSummary([
                'store_id' => $store->id,
                'package_id' => $request->input('package_id'),
            ])
        );
    }

    private function ownedStore(Request $request): mixed
    {
        $store = $this->vendorStore($request);

        return $store && (string) $store->id === (string) $request->input('store_id') ? $store : null;
    }
}
