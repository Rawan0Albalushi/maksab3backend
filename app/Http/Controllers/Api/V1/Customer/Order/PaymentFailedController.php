<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\PaymentFailedRequest;
use App\Http\Resources\Customer\Order\PaymentFailedResource;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Modules\Rental\Http\Resources\Customer\Trip\TripPaymentFailedResource;
use Modules\Rental\Services\Trip\TripService;

class PaymentFailedController extends BaseApiController
{
    public function __construct(
        private readonly OrderService $orderService
    ) {
    }

    public function show(PaymentFailedRequest $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->resolve($request, $request->filters())
        );
    }

    private function resolve(PaymentFailedRequest $request, array $filters): mixed
    {
        if ($request->isRental()) {
            return $this->failedTrip($filters);
        }

        if ($request->isOrder()) {
            return $this->failedOrder($filters);
        }

        return $this->failedOrder($filters) ?? $this->failedTrip($filters);
    }

    private function failedOrder(array $filters): ?PaymentFailedResource
    {
        $order = $this->orderService->findUnpaid($filters);

        return $order ? new PaymentFailedResource($order) : null;
    }

    private function failedTrip(array $filters): ?TripPaymentFailedResource
    {
        if (! addon_published_status('Rental')) {
            return null;
        }

        $trip = app(TripService::class)->findUnpaid($filters);

        return $trip ? new TripPaymentFailedResource($trip) : null;
    }
}
