<?php

namespace App\Services\Payment;

use Illuminate\Database\Eloquent\Builder;
use App\Models\ProCustomerTransaction;
use App\Services\BaseService;

class ProCustomerTransactionService extends BaseService
{
    public function findLatestForSubscription(mixed $userId, mixed $subscriptionId): ?ProCustomerTransaction
    {
        return $this->latestForSubscriptionQuery($userId, $subscriptionId)->first();
    }
    public function findLatestIdForSubscription(mixed $userId, mixed $subscriptionId): mixed
    {
        return $this->latestForSubscriptionQuery($userId, $subscriptionId)->value('id');
    }
    public function create(array $data): ProCustomerTransaction
    {
        return ProCustomerTransaction::create($data);
    }
    public function usedFreeTrial(mixed $userId): bool
    {
        return ProCustomerTransaction::where('user_id', $userId)
            ->where('plan_type', 'free_trial')
            ->exists();
    }
    public function query(): mixed
    {
        return ProCustomerTransaction::query();
    }

    private function latestForSubscriptionQuery(mixed $userId, mixed $subscriptionId): Builder
    {
        return ProCustomerTransaction::where('user_id', $userId)
            ->where('subscription_id', $subscriptionId)
            ->latest('id');
    }
}
