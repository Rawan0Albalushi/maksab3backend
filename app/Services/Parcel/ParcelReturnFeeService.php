<?php

namespace App\Services\Parcel;

use App\CentralLogics\Helpers;
use App\Models\ParcelCancellation;
use App\Models\ParcelReturnFees;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class ParcelReturnFeeService extends BaseService
{
    private const LIST_COLUMNS = ['id', 'transaction_id', 'order_id', 'amount', 'created_at'];

    public function backfillFromPaidCancellations(): void
    {
        if (ParcelReturnFees::query()->exists()) {
            return;
        }

        ParcelCancellation::where('return_fee_payment_status', 'paid')
            ->where('return_fee', '>', 0)
            ->with('order:id,delivery_man_id,user_id')
            ->chunk(500, function ($cancellations) {
                foreach ($cancellations as $cancellation) {
                    $returnFeeLog = ParcelReturnFees::create([
                        'order_id' => $cancellation->order_id,
                        'delivery_man_id' => $cancellation->order->delivery_man_id ?? null,
                        'user_id' => $cancellation->order->user_id,
                        'amount' => $cancellation->return_fee,
                    ]);

                    $returnFeeLog->update([
                        'transaction_id' => Helpers::generate_transaction_id($returnFeeLog),
                    ]);
                }
            });
    }

    public function getDeliveryManList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return ParcelReturnFees::where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->applyDateFilter($filters['date_range'] ?? null, $filters['start_date'] ?? null, $filters['end_date'] ?? null)
            ->select(self::LIST_COLUMNS)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
