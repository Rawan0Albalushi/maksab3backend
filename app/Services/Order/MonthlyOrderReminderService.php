<?php

namespace App\Services\Order;

use App\Models\MonthlyOrderReminder;
use App\Models\Order;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use App\Services\System\BusinessSettingService;

class MonthlyOrderReminderService extends BaseService
{
    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return MonthlyOrderReminder::active()
            ->where('user_id', $filters['user_id'] ?? null)
            ->when($filters['module_type'] ?? null, fn ($query) => $query->where('module_type', $filters['module_type']))
            ->with($this->rowRelations())
            ->orderBy('remind_at', 'asc')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function find(array $filters = []): ?MonthlyOrderReminder
    {
        return MonthlyOrderReminder::active()
            ->where('id', $filters['id'] ?? null)
            ->where('user_id', $filters['user_id'] ?? null)
            ->with($this->rowRelations(detailed: true))
            ->first();
    }

    public function findOwned(array $filters = []): ?MonthlyOrderReminder
    {
        return MonthlyOrderReminder::where('id', $filters['id'] ?? null)
            ->where('user_id', $filters['user_id'] ?? null)
            ->first();
    }

    public function cancel(MonthlyOrderReminder $reminder): bool
    {
        if ($reminder->status === 'cancelled') {
            return true;
        }

        return $reminder->update(['status' => 'cancelled']);
    }

    public function scheduleForOrder(Order $order, bool $optIn = false): void
    {
        try {
            if (! $optIn) {
                return;
            }

            if (! app(BusinessSettingService::class)->value('monthly_order_reminder')) {
                return;
            }

            if (! in_array($order->module_type, ['pharmacy', 'grocery'])) {
                return;
            }

            if (! $order->user_id) {
                return;
            }

            if (MonthlyOrderReminder::where('order_id', $order->id)->exists()) {
                return;
            }

            $reminderBefore = (int) (app(BusinessSettingService::class)->value('monthly_order_reminder_days_before') ?? 3);
            $reminderUnit = app(BusinessSettingService::class)->value('monthly_order_reminder_before_unit') ?? 'day';
            $daysBefore = match ($reminderUnit) {
                'week' => $reminderBefore * 7,
                'month' => $reminderBefore * 30,
                default => $reminderBefore,
            };
            $remindAt = now()->addMonth()->subDays($daysBefore)->startOfDay();

            MonthlyOrderReminder::create([
                'user_id' => $order->user_id,
                'order_id' => $order->id,
                'module_id' => $order->module_id,
                'module_type' => $order->module_type,
                'zone_id' => $order->zone_id ?? '',
                'remind_at' => $remindAt->toDateString(),
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            Log::error('MonthlyOrderReminderService scheduleForOrder failed', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function rowRelations(bool $detailed = false): array
    {
        $itemColumns = $detailed
            ? 'id,name,image,price,discount,discount_type,store_id,status,stock,maximum_cart_quantity'
            : 'id,name,image,price,status';

        return [
            'order:id,store_id,module_id',
            'order.store:id,name,logo,module_id',
            'order.store.storage',
            'order.details:id,order_id,item_id,item_campaign_id,item_details,quantity,price,variation,discount_on_item',
            'order.details.item:'.$itemColumns,
            'order.details.item.storage',
            'order.details.campaign:id,name,image,price,discount,discount_type,store_id,status',
            'order.details.campaign.storage',
        ];
    }
}
