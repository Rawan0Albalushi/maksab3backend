<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\BannerAddRequest;
use App\Http\Requests\Vendor\Promotion\BannerDeleteRequest;
use App\Http\Requests\Vendor\Promotion\BannerStatusRequest;
use App\Http\Requests\Vendor\Promotion\BannerUpdateRequest;
use App\Http\Resources\Vendor\Promotion\BannerResource;
use App\Services\Marketing\BannerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly BannerService $bannerService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $banners = $this->bannerService->getPaginatedList(
            filters: $this->storeFilters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => BannerResource::collection($banners),
            'pagination' => $this->paginateFormatter($banners),
        ]);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $banner = $this->bannerService->find($id, filters: $this->storeFilters($request));

        if (! $banner) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_200'), new BannerResource($banner));
    }

    public function store(BannerAddRequest $request): JsonResponse
    {
        $banner = $this->bannerService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'), new BannerResource($banner));
    }

    public function update(BannerUpdateRequest $request): JsonResponse
    {
        $banner = $this->bannerService->update($request->bannerId(), $request->payload());

        if (! $banner) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_update_200'), new BannerResource($banner));
    }

    public function updateStatus(BannerStatusRequest $request): JsonResponse
    {
        $banner = $this->bannerService->updateStatus(
            $request->bannerId(),
            $request->status(),
            $request->payload()
        );

        if (! $banner) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_update_200'), new BannerResource($banner));
    }

    public function destroy(BannerDeleteRequest $request): JsonResponse
    {
        if (! $this->bannerService->delete($request->bannerId(), $request->filters())) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function storeFilters(Request $request): array
    {
        return [
            'store_id' => $this->vendorStoreId($request),
        ];
    }
}
