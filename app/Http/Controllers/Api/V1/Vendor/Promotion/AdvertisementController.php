<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\AdvertisementAddRequest;
use App\Http\Requests\Vendor\Promotion\AdvertisementStatusRequest;
use App\Http\Requests\Vendor\Promotion\AdvertisementUpdateRequest;
use App\Http\Resources\Vendor\Promotion\AdvertisementResource;
use App\Services\Marketing\AdvertisementService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisementController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly AdvertisementService $advertisementService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->listFilters($request);

        $advertisements = $this->advertisementService->getList(
            filters: $filters,
            paginate: [
                'per_page' => (int) ($request->input('limit') ?? config('default_pagination')),
                'page' => (int) ($request->input('offset') ?? 1),
            ],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => AdvertisementResource::collection($advertisements),
            'pagination' => $this->paginateFormatter($advertisements),
            'statistics' => $this->advertisementService->statusStatistics($filters),
        ]);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        $advertisement = $this->advertisementService->find($id, filters: $this->storeFilters($request));

        if (! $advertisement) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_200'), new AdvertisementResource($advertisement));
    }

    public function store(AdvertisementAddRequest $request): JsonResponse
    {
        $advertisement = $this->advertisementService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'), new AdvertisementResource($advertisement));
    }

    public function update(AdvertisementUpdateRequest $request, mixed $id): JsonResponse
    {
        $payload = $request->payload();

        $errors = $this->advertisementService->attachmentErrors($id, $payload);

        if ($errors) {
            return $this->responseFormatter(config('response.unprocessable_entity_422'), errors: $errors);
        }

        $advertisement = $this->advertisementService->update($id, $payload);

        if (! $advertisement) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_update_200'), new AdvertisementResource($advertisement));
    }

    public function destroy(Request $request, mixed $id): JsonResponse
    {
        if (! $this->advertisementService->delete($id, $this->storeFilters($request))) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    public function updateStatus(AdvertisementStatusRequest $request): JsonResponse
    {
        $advertisement = $this->advertisementService->updateStatus(
            $request->advertisementId(),
            $request->status(),
            $request->payload()
        );

        if (! $advertisement) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_update_200'), new AdvertisementResource($advertisement));
    }

    public function duplicate(AdvertisementUpdateRequest $request): JsonResponse
    {
        $advertisement = $this->advertisementService->copy($request->sourceId(), $request->payload());

        if (! $advertisement) {
            return $this->responseFormatter(config('response.default_404'));
        }

        return $this->responseFormatter(config('response.default_store_201'), new AdvertisementResource($advertisement));
    }

    private function listFilters(Request $request): array
    {
        return [
            'store_id' => $this->vendorStoreId($request),
            'ads_type' => $request->input('ads_type'),
            'search' => $request->input('search'),
        ];
    }

    private function storeFilters(Request $request): array
    {
        return [
            'store_id' => $this->vendorStoreId($request),
        ];
    }
}
