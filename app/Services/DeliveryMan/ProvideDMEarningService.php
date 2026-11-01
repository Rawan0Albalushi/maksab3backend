<?php

namespace App\Services\DeliveryMan;

use App\Models\ProvideDMEarning;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ProvideDMEarningService extends BaseService
{
    public function recordAdjustment(mixed $deliveryManId, mixed $amount, bool $isPartial): void
    {
        ProvideDMEarning::insert([
            'delivery_man_id' => $deliveryManId,
            'amount' => $amount,
            'ref' => $isPartial ? 'delivery_man_wallet_adjustment_partial' : 'delivery_man_wallet_adjustment_full',
            'method' => 'adjustment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getAdjustmentList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = isset($filters['search']) ? explode(' ', (string) $filters['search']) : [];

        return ProvideDMEarning::when($terms !== [], fn ($query) => $query->where(function ($inner) use ($terms) {
            foreach ($terms as $term) {
                $inner->orWhere('ref', 'like', "%{$term}%");
            }
        }))
            ->where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->where('method', 'adjustment')
            ->whereIn('ref', ['delivery_man_wallet_adjustment_partial', 'delivery_man_wallet_adjustment_full'])
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
