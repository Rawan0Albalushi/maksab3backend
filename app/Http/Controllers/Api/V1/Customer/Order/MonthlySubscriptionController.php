<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\MonthlySubscriptionListRequest;
use App\Http\Requests\Customer\Order\MonthlySubscriptionRequest;
use App\Http\Resources\Customer\Order\MonthlySubscriptionResource;
use App\Services\Order\MonthlyOrderReminderService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

class MonthlySubscriptionController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly MonthlyOrderReminderService $monthlyOrderReminderService
    ) {}

    public function index(MonthlySubscriptionListRequest $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->monthlyOrderReminderService->getList(
                ['user_id' => auth('api')->id(), 'module_type' => $request->query('module_type')],
                $this->pageParams($request)
            ),
            MonthlySubscriptionResource::class
        );
    }

    public function show(MonthlySubscriptionRequest $request): JsonResponse
    {
        $reminder = $this->monthlyOrderReminderService->find([
            'id' => $request->input('id'),
            'user_id' => auth('api')->id(),
        ]);

        if (! $reminder) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(
            config('response.default_200'),
            (new MonthlySubscriptionResource($reminder))->detailed()
        );
    }

    public function destroy(MonthlySubscriptionRequest $request): JsonResponse
    {
        $reminder = $this->monthlyOrderReminderService->findOwned([
            'id' => $request->input('id'),
            'user_id' => auth('api')->id(),
        ]);

        if (! $reminder) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $this->monthlyOrderReminderService->cancel($reminder);

        return $this->responseFormatter(config('response.default_delete_200'));
    }
}
