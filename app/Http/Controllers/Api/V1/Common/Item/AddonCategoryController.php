<?php

namespace App\Http\Controllers\Api\V1\Common\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\Item\AddonCategoryListRequest;
use App\Http\Resources\Common\Item\AddonCategoryResource;
use App\Services\Item\AddonCategoryService;
use Illuminate\Http\JsonResponse;

class AddonCategoryController extends BaseApiController
{
    public function __construct(
        private readonly AddonCategoryService $addonCategoryService
    ) {
    }

    public function index(AddonCategoryListRequest $request): JsonResponse
    {
        $addonCategories = $this->addonCategoryService->getList(
            filters: $request->filters(),
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AddonCategoryResource::collection($addonCategories),
            'pagination' => $this->paginateFormatter($addonCategories),
        ]);
    }
}
