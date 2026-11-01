<?php

namespace App\Http\Controllers\Api\V1\Common\ProCustomer;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\ProCustomer\PlanResource;
use App\Services\Payment\ProCustomerBenefitSettingService;
use App\Services\Payment\ProCustomerSubscriptionPlanService;
use App\Services\Payment\ProCustomerSubscriptionService;
use App\Services\System\DataSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends BaseApiController
{
    public function __construct(
        private readonly ProCustomerSubscriptionPlanService $planService,
        private readonly ProCustomerSubscriptionService $subscriptionService,
        private readonly ProCustomerBenefitSettingService $benefitService,
        private readonly DataSettingService $dataSettingService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->subscriptionService->memberFeatureEnabled()) {
            return $this->responseFormatter(config('response.default_200'), [
                'pro_member_status' => 0,
                'pro_brand' => null,
                'plans' => [],
                'pagination' => null,
                'benefits' => null,
            ]);
        }

        $plans = $this->planService->getList(
            filters: ['exclude_free_trial' => $this->subscriptionService->freeTrialUsed($request->user()?->id ?? auth('api')->id())],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'pro_member_status' => 1,
            'pro_brand' => $this->subscriptionService->brandName(),
            'plans' => PlanResource::collection($plans),
            'pagination' => $this->paginateFormatter($plans),
            'benefits' => $this->benefitService->normalizedBenefits($this->dataSettingService->proBenefitStatusFlags()),
        ]);
    }
}
