<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\BundleStoreRequest;
use App\Http\Resources\Vendor\Promotion\BundleResource;
use App\Models\Bundle;
use App\Services\Promotion\BundleService;
use App\Support\Promotion\AddOnLabels;
use App\Support\Promotion\BundleSettings;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BundleController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly BundleService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        $bundles = $this->service->list(
            search: $request->input('search'),
            moduleId: $store->module_id,
            storeId: $store->id,
            perPage: $this->perPage($request),
        );

        // Once for the page, not once per bundle — every row renders its lines and a per-bundle
        // lookup would be an N+1 across the list (rule 11).
        $addOnLines = AddOnLabels::forParents($bundles->items());

        return $this->responseFormatter(config('response.default_200'), [
            'data' => collect($bundles->items())
                ->map(fn (Bundle $bundle) => (new BundleResource($bundle, withItems: true, addOnLines: $addOnLines))->render())->all(),
            'pagination' => $this->paginateFormatter($bundles),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        $bundle = $this->ownBundle($store, $id, withTranslations: true);

        if (! $bundle) {
            return $this->notFound();
        }

        return $this->responseFormatter(
            config('response.default_200'),
            (new BundleResource($loaded = $bundle->load('items'), withItems: true, addOnLines: AddOnLabels::forParents([$loaded]), withTranslations: true))->render()
        );
    }

    public function items(Request $request): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        return $this->responseFormatter(config('response.default_200'), [
            'items' => $this->service->pickerOptions($store->id, $request->input('search'), $store->module_id),
        ]);
    }

    public function store(BundleStoreRequest $request): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        if ((int) $request->input('store_id') !== (int) $store->id) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Invalid store'), 'store_id');
        }

        $bundle = $this->service->create($request, createdBy: 'vendor');

        return $this->responseFormatter(
            config('response.default_store_201'),
            (new BundleResource($loaded = $bundle->load('items'), withItems: true, addOnLines: AddOnLabels::forParents([$loaded])))->render()
        );
    }

    public function update(BundleStoreRequest $request, string $id): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        $bundle = $this->ownBundle($store, $id);

        if (! $bundle) {
            return $this->notFound();
        }

        $this->service->modify($bundle, $request);

        return $this->responseFormatter(
            config('response.default_update_200'),
            (new BundleResource($fresh = $bundle->fresh('items'), withItems: true, addOnLines: AddOnLabels::forParents([$fresh])))->render()
        );
    }

    public function status(Request $request, string $id): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        $request->validate(['status' => 'required|boolean']);

        $bundle = $this->ownBundle($store, $id);

        if (! $bundle) {
            return $this->notFound();
        }

        $this->service->setStatus($bundle, (int) $request->input('status'));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if (! $store = $this->vendorStore($request)) {
            return $this->storeMissing();
        }

        if ($blocked = $this->refuseWhileDisabled($store->module_id)) {
            return $blocked;
        }

        $bundle = $this->ownBundle($store, $id);

        if (! $bundle) {
            return $this->notFound();
        }

        $this->service->delete($bundle);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function refuseWhileDisabled($moduleId): ?JsonResponse
    {
        return BundleSettings::allowsModule($moduleId)
            ? null
            : $this->errorResponse(config('response.default_404'), translate('No data found'), 'bundle');
    }

    private function ownBundle($store, string $id, bool $withTranslations = false): ?Bundle
    {
        return $this->service->findOwned($id, $store->module_id, $store->id, $withTranslations);
    }

    private function notFound(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'bundle');
    }

    private function storeMissing(): JsonResponse
    {
        return $this->errorResponse(config('response.default_404'), translate('No data found'), 'store');
    }
}
