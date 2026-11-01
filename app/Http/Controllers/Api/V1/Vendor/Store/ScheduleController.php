<?php

namespace App\Http\Controllers\Api\V1\Vendor\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Store\ScheduleAddRequest;
use App\Services\Store\StoreScheduleService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly StoreScheduleService $storeScheduleService) {}

    public function store(ScheduleAddRequest $request): JsonResponse
    {
        $payload = $request->payload() + ['store_id' => $this->vendorStoreId($request)];

        if ($this->storeScheduleService->overlaps($payload)) {
            return $this->errorResponse(
                config('response.bad_request_400'),
                translate('messages.schedule_overlapping_warning'),
                'time'
            );
        }

        return $this->responseFormatter(config('response.default_store_201'), [
            'id' => $this->storeScheduleService->create($payload),
        ]);
    }

    public function destroy(Request $request, mixed $storeSchedule): JsonResponse
    {
        $schedule = $this->storeScheduleService->find($storeSchedule, ['store_id' => $this->vendorStoreId($request)]);

        if (! $schedule) {
            return $this->errorResponse(
                config('response.default_404'),
                translate('No data found'),
                'not-fond'
            );
        }

        $this->storeScheduleService->delete($schedule);

        return $this->responseFormatter(config('response.default_delete_200'));
    }
}
