<?php

namespace App\Http\Controllers\Api\V1\Common\Parcel;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Parcel\ParcelCategoryResource;
use App\Services\Parcel\ParcelCategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParcelCategoryController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly ParcelCategoryService $parcelCategoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $categories = $this->parcelCategoryService->getList(
            filters: $this->filters(),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => ParcelCategoryResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }

    private function filters(): array
    {
        return [
            'module_id' => $this->currentModuleId(),
        ];
    }
}
