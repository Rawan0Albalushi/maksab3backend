<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Item\AttributeResource;
use App\Services\Item\AttributeService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttributeController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly AttributeService $attributeService) {}

    public function index(Request $request): JsonResponse
    {
        $attributes = $this->attributeService->getList($this->pageParams($request));

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AttributeResource::collection($attributes),
            'pagination' => $this->paginateFormatter($attributes),
        ]);
    }
}
