<?php

namespace App\Services\DeliveryMan;

use App\Models\DeliveryHistory;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryHistoryService extends BaseService
{
    public function record(mixed $deliveryManId, array $data = []): DeliveryHistory
    {
        return DeliveryHistory::updateOrCreate(['delivery_man_id' => $deliveryManId], [
            'longitude' => $data['longitude'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'time' => now(),
            'location' => $data['location'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getOrderList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return DeliveryHistory::where([
            'order_id' => $filters['order_id'] ?? null,
            'delivery_man_id' => $filters['delivery_man_id'] ?? null,
        ])->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findLastForOrder(mixed $orderId): ?DeliveryHistory
    {
        return DeliveryHistory::whereHas('delivery_man.orders', fn ($query) => $query->where('id', $orderId))
            ->latest()
            ->first();
    }
}
