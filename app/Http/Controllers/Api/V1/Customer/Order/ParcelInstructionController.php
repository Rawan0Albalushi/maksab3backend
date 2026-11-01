<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Parcel\DeliveryInstructionResource;
use App\Services\Parcel\ParcelDeliveryInstructionService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelInstructionController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly ParcelDeliveryInstructionService $parcelDeliveryInstructionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->parcelDeliveryInstructionService->getList([], $this->pageParams($request)),
            DeliveryInstructionResource::class
        );
    }
}
