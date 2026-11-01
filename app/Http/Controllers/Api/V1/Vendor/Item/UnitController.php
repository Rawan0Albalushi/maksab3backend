<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Item\UnitResource;
use App\Services\Item\UnitService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly UnitService $unitService) {}

    public function index(Request $request): JsonResponse
    {
        $units = $this->unitService->getList($this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => UnitResource::collection($units),
            'pagination' => $this->paginateFormatter($units),
        ]);
    }
}
