<?php

namespace App\Http\Controllers\Api\V1\Vendor\Item;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Item\StoreCategoryIdRequest;
use App\Http\Requests\Vendor\Item\StoreCategoryPriorityRequest;
use App\Http\Requests\Vendor\Item\StoreCategoryStatusRequest;
use App\Http\Requests\Vendor\Item\StoreCategoryStoreRequest;
use App\Http\Requests\Vendor\Item\StoreCategoryUpdateRequest;
use App\Http\Resources\Vendor\Item\StoreCategoryResource;
use App\Services\Store\StoreCategoryService;
use App\Traits\Api\ApiRequestContextTrait;
use App\Traits\Store\VendorStoreCategoryTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCategoryController extends BaseApiController
{
    use ApiRequestContextTrait;
    use VendorStoreCategoryTrait;

    public function __construct(
        private readonly StoreCategoryService $storeCategoryService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $categories = $this->storeCategoryService->getList(
            filters: [
                'store_id' => $this->vendorStoreId($request),
                'search' => $request->query('search'),
                'priority' => $request->query('priority'),
            ],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => StoreCategoryResource::collection($categories),
            'pagination' => $this->paginateFormatter($categories),
        ]);
    }

    public function show(Request $request, mixed $id): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $category = $this->storeCategoryService->findOwned($id, $this->vendorStoreId($request), withTranslations: true);

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $resource = new StoreCategoryResource($category);

        return $this->responseFormatter(
            config('response.default_200'),
            array_merge($resource->resolve(), $resource->withTranslations())
        );
    }

    public function store(StoreCategoryStoreRequest $request): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $translations = $request->translationRows();
        $defaultName = $request->defaultName();

        if (! $defaultName) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'name', 'message' => translate('messages.Default name is required')],
            ]);
        }

        $category = $this->storeCategoryService->create(
            storeId: $this->vendorStoreId($request),
            name: $defaultName,
            priority: $request->filled('priority') ? (int) $request->input('priority') : 0,
            image: $request->file('image')
        );
        $this->storeCategoryService->saveApiTranslations($category, $translations);

        return $this->responseFormatter(config('response.default_store_201'), new StoreCategoryResource($category));
    }

    public function update(StoreCategoryUpdateRequest $request, mixed $id): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $category = $this->storeCategoryService->findOwned($id, $this->vendorStoreId($request));

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $this->storeCategoryService->update(
            category: $category,
            name: $request->defaultName() ?? $category->getRawOriginal('name'),
            priority: $request->filled('priority') ? (int) $request->input('priority') : null,
            image: $request->file('image')
        );
        $this->storeCategoryService->saveApiTranslations($category, $request->translationRows());

        return $this->responseFormatter(config('response.default_update_200'), new StoreCategoryResource($category));
    }

    public function updateStatus(StoreCategoryStatusRequest $request): JsonResponse
    {
        return $this->mutateOwned($request, fn ($category) => $this->storeCategoryService->updateStatus($category, (int) $request->input('status')));
    }

    public function updatePriority(StoreCategoryPriorityRequest $request): JsonResponse
    {
        return $this->mutateOwned($request, fn ($category) => $this->storeCategoryService->updatePriority($category, (int) $request->input('priority')));
    }

    public function destroy(StoreCategoryIdRequest $request): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $category = $this->storeCategoryService->findOwned($request->input('id'), $this->vendorStoreId($request));

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $this->storeCategoryService->delete($category);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function mutateOwned(Request $request, callable $mutation): JsonResponse
    {
        if ($blocked = $this->guardStoreCategory($request)) {
            return $blocked;
        }

        $category = $this->storeCategoryService->findOwned($request->input('id'), $this->vendorStoreId($request));

        if (! $category) {
            return $this->storeCategoryNotFound();
        }

        $mutation($category);

        return $this->responseFormatter(config('response.default_update_200'));
    }
}
