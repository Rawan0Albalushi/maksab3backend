<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Order\OrderIdRequest;
use App\Http\Resources\DeliveryMan\Location\DeliveryHistoryResource;
use App\Services\DeliveryMan\DeliveryHistoryService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RideShare\Interface\UserManagement\Service\UserLastLocationServiceInterface;

class LocationController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected DeliveryHistoryService $deliveryHistoryService) {}

    public function store(Request $request): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();

        $this->deliveryHistoryService->record($deliveryMan->id, [
            'longitude' => $request->input('longitude'),
            'latitude' => $request->input('latitude'),
            'location' => $request->input('location'),
        ]);

        $this->syncRideShareLocation($request, $deliveryMan);

        return $this->responseFormatter(['message' => translate('Location recorded')] + config('response.default_store_201'));
    }


    public function orderHistory(OrderIdRequest $request): JsonResponse
    {
        $history = $this->deliveryHistoryService->getOrderList([
            'order_id' => $request->input('order_id'),
            'delivery_man_id' => $this->deliveryManId(),
        ], $this->pageParams($request));

        return $this->pagedResponse($history, DeliveryHistoryResource::class);
    }

    public function lastLocation(OrderIdRequest $request): JsonResponse
    {
        $location = $this->deliveryHistoryService->findLastForOrder($request->input('order_id'));

        return $this->responseFormatter(
            config('response.default_200'),
            $location ? (new DeliveryHistoryResource($location))->toArray($request) : null
        );
    }

    private function syncRideShareLocation(Request $request, mixed $deliveryMan): void
    {
        if (! addon_published_status('RideShare') || $deliveryMan->is_ride != 1 || ! $request->filled('zone_id')) {
            return;
        }

        $data = [
            'type' => 'rider',
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'zone_id' => $request->input('zone_id'),
            'user_id' => $deliveryMan->id,
        ];

        $lastLocation = app(UserLastLocationServiceInterface::class)
            ->findOneBy(criteria: ['user_id' => $deliveryMan->id, 'type' => 'rider']);

        $lastLocation
            ? app(UserLastLocationServiceInterface::class)->update(id: $lastLocation->id, data: $data)
            : app(UserLastLocationServiceInterface::class)->create(data: $data);
    }
}
