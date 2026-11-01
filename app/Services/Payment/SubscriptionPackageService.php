<?php

namespace App\Services\Payment;

use App\Models\SubscriptionPackage;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\System\ModuleService;

class SubscriptionPackageService extends BaseService
{
    public function typeFor($store): string
    {
        if ($store?->module_type == 'rental' && addon_published_status('Rental')) {
            return 'rental';
        }
        if ($store?->module_type == 'service' && addon_published_status('Service')) {
            return 'service';
        }

        return 'all';
    }

    public function findWithTranslations(mixed $id): mixed
    {
        return SubscriptionPackage::withoutGlobalScope('translate')->with('translations')->find($id);
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return SubscriptionPackage::where('status', 1)
            ->where('module_type', $this->typeFor(app(ModuleService::class)->find($filters['module_id'] ?? null)))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

}
