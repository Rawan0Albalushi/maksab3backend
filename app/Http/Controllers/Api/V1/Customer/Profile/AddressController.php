<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Profile\AddressDeleteRequest;
use App\Http\Requests\Customer\Profile\AddressStoreRequest;
use App\Http\Resources\Customer\Profile\AddressResource;
use App\Services\Customer\CustomerAddressService;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends BaseApiController
{
    private const COORDINATE_ERROR_CODE = 'coordinates';

    public function __construct(
        private readonly CustomerAddressService $customerAddressService,
        private readonly ZoneService $zoneService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->customerAddressService->getList(
            filters: ['user_id' => $request->user()->id],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        $this->zoneService->attachIdsByCoordinates($addresses->getCollection());

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AddressResource::collection($addresses),
            'pagination' => $this->paginateFormatter($addresses),
        ]);
    }

    public function store(AddressStoreRequest $request): JsonResponse
    {
        $zoneIds = $this->zoneService->findIdsByCoordinates($request->input('latitude'), $request->input('longitude'));

        if (! $zoneIds) {
            return $this->outOfServiceArea();
        }

        $this->customerAddressService->create(array_merge($request->payload(), [
            'user_id' => $request->user()->id,
            'zone_id' => $zoneIds[0],
        ]));

        return $this->responseFormatter(config('response.default_store_201'), ['zone_ids' => $zoneIds]);
    }

    public function update(AddressStoreRequest $request, mixed $id): JsonResponse
    {
        $zoneIds = $this->zoneService->findIdsByCoordinates($request->input('latitude'), $request->input('longitude'));

        if (! $zoneIds) {
            return $this->outOfServiceArea();
        }

        $address = $this->customerAddressService->update(
            $id,
            array_merge($request->payload(), ['zone_id' => $zoneIds[0]]),
            $request->user()->id
        );

        return $address
            ? $this->responseFormatter(config('response.default_update_200'), ['zone_id' => $zoneIds[0]])
            : $this->responseFormatter(config('response.default_404'));
    }

    public function destroy(AddressDeleteRequest $request): JsonResponse
    {
        return $this->customerAddressService->delete($request->input('address_id'), $request->user()->id)
            ? $this->responseFormatter(config('response.default_delete_200'))
            : $this->responseFormatter(config('response.default_404'));
    }

    private function outOfServiceArea(): JsonResponse
    {
        return $this->responseFormatter(config('response.forbidden_403'), errors: [
            ['code' => self::COORDINATE_ERROR_CODE, 'message' => translate('messages.Service not available in this area')],
        ]);
    }
}
