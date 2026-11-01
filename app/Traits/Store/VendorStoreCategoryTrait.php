<?php

namespace App\Traits\Store;

use App\CentralLogics\Helpers;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

trait VendorStoreCategoryTrait
{
    protected function guardStoreCategory(Request $request): ?JsonResponse
    {
        if (! Helpers::vendorCategoryStatus()) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'disabled', 'message' => translate('messages.Store category feature is disabled')],
            ]);
        }

        if (empty($request->input('vendor')?->stores) || ! isset($request->input('vendor')->stores[0])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'no-store', 'message' => translate('messages.no_store_found')],
            ]);
        }

        return null;
    }

    protected function storeCategoryNotFound(): JsonResponse
    {
        return $this->responseFormatter(config('response.default_404'), errors: [
            ['code' => 'store-category-404', 'message' => translate('No data found')],
        ]);
    }

    protected function vendorModuleType(Request $request): ?string
    {
        return $request->input('vendor')?->stores[0]->module_type ?? null;
    }

    protected function isServiceModule(Request $request): bool
    {
        return $this->vendorModuleType($request) === 'service' && addon_published_status('Service');
    }

    protected function bindableModel(Request $request): string
    {
        return $this->isServiceModule($request) ? \Modules\Service\Entities\Service::class : Item::class;
    }
}
