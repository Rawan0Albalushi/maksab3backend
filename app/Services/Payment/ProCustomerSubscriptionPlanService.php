<?php

namespace App\Services\Payment;

use App\Models\ProCustomerSubscriptionPlan;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ProCustomerSubscriptionPlanService extends BaseService
{
    private const FREE_TRIAL = 'free_trial';

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return ProCustomerSubscriptionPlan::where('status', 1)
            ->when($filters['exclude_free_trial'] ?? false, fn ($query) => $query->where('plan_type', '!=', self::FREE_TRIAL))
            ->orderBy('duration')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findActive(mixed $id): ?ProCustomerSubscriptionPlan
    {
        return ProCustomerSubscriptionPlan::where('id', $id)->where('status', 1)->first();
    }

    public function findUntranslatedName(mixed $planId): mixed
    {
        return ProCustomerSubscriptionPlan::withoutGlobalScope('translate')
            ->with('translations')
            ->where('id', $planId)
            ->value('plan_name');
    }

}
