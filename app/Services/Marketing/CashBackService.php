<?php

namespace App\Services\Marketing;

use App\CentralLogics\Helpers;
use App\Models\CashBack;
use App\Services\BaseService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CashBackService extends BaseService
{
    private const LIST_COLUMNS = [
        'id', 'title', 'cashback_type', 'cashback_amount',
        'min_purchase', 'max_discount', 'start_date', 'end_date',
    ];

    public function getAddData(array $input): array
    {
        $customerId  = ($input['customer_id'] ?? null) ?? ['all'];
        return [
            "title" => ($input['title'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'customer_id' =>  json_encode($customerId),
            "cashback_type" => ($input['cashback_type'] ?? null),
            "same_user_limit" => ($input['same_user_limit'] ?? null),
            "cashback_amount" => ($input['cashback_amount'] ?? null),
            "min_purchase" => ($input['min_purchase'] ?? null) != null ? ($input['min_purchase'] ?? null) : 0,
            "max_discount" => ($input['max_discount'] ?? null) != null ? ($input['max_discount'] ?? null) : 0,
            "start_date" => ($input['start_date'] ?? null),
            "end_date" => ($input['end_date'] ?? null),
        ];
    }

    public function getUpdateData(array $input): array
    {
        return $this->getAddData($input);
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildQuery($filters)
            ->with($with)
            ->withCount($withCount)
            ->orderByDesc('cashback_amount')
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }

    public function calculateForAmount(mixed $amount, mixed $customerId, ?int $moduleId): array
    {
        return $this->calculateForCustomer($amount, $customerId, $this->resolveCashbackType($moduleId));
    }

    public function calculateForCustomer(mixed $amount, mixed $customerId, mixed $type = null): array
    {
        $data = [
            'calculated_amount' => (float) 0,
            'cashback_amount' => 0,
            'cashback_type' => '',
            'min_purchase' => 0,
            'max_discount' => 0,
            'id' => 0,
        ];

        try {
            $percent_bonus = CashBack::active()->when($type, function ($query) use ($type) {
                $type === 'service' ? $query->service() : $query->rental();
            })
                ->where('cashback_type', 'percentage')
                ->Running()
                ->where('min_purchase', '<=', $amount)
                ->where(function ($query) use ($customerId) {
                    $query->whereJsonContains('customer_id', [(string) $customerId])->orWhereJsonContains('customer_id', ['all']);
                })
                ->when(is_numeric($customerId), function ($q) use ($customerId) {
                    $q->where('same_user_limit', '>', function ($query) use ($customerId) {
                        $query->select(DB::raw('COUNT(*)'))
                            ->from('cash_back_histories')
                            ->where('user_id', $customerId)
                            ->whereColumn('cash_back_id', 'cash_backs.id');
                    });
                })
                ->orderBy('cashback_amount', 'desc')
                ->first();

            $amount_bonus = CashBack::active()->where('cashback_type', 'amount')->when($type, function ($query) use ($type) {
                $type === 'service' ? $query->service() : $query->rental();
            })
                ->Running()
                ->where(function ($query) use ($customerId) {
                    $query->whereJsonContains('customer_id', [(string) $customerId])->orWhereJsonContains('customer_id', ['all']);
                })
                ->where('min_purchase', '<=', $amount)
                ->when(is_numeric($customerId), function ($q) use ($customerId) {
                    $q->where('same_user_limit', '>', function ($query) use ($customerId) {
                        $query->select(DB::raw('COUNT(*)'))
                            ->from('cash_back_histories')
                            ->where('user_id', $customerId)
                            ->whereColumn('cash_back_id', 'cash_backs.id');
                    });
                })
                ->orderBy('cashback_amount', 'desc')->first();

            if ($percent_bonus && ($amount >= $percent_bonus->min_purchase)) {
                $p_bonus = ($amount * $percent_bonus->cashback_amount) / 100;
                $p_bonus = $p_bonus > $percent_bonus->max_discount ? $percent_bonus->max_discount : $p_bonus;
                $p_bonus = round($p_bonus, config('round_up_to_digit'));
            } else {
                $p_bonus = 0;
            }

            if ($amount_bonus && ($amount >= $amount_bonus->min_purchase)) {
                $a_bonus = $amount_bonus ? $amount_bonus->cashback_amount : 0;
                $a_bonus = round($a_bonus, config('round_up_to_digit'));
            } else {
                $a_bonus = 0;
            }

            $cashback_amount = max([$p_bonus, $a_bonus]);

            if ($p_bonus == $cashback_amount) {
                $data = [
                    'calculated_amount' => (float) $cashback_amount,
                    'cashback_amount' => $percent_bonus?->cashback_amount ?? 0,
                    'cashback_type' => $percent_bonus?->cashback_type ?? '',
                    'min_purchase' => $percent_bonus?->min_purchase ?? 0,
                    'max_discount' => $percent_bonus?->max_discount ?? 0,
                    'id' => $percent_bonus?->id,
                ];

            } elseif ($a_bonus == $cashback_amount) {
                $data = [
                    'calculated_amount' => (float) $cashback_amount,
                    'cashback_amount' => $amount_bonus?->cashback_amount ?? 0,
                    'cashback_type' => $amount_bonus?->cashback_type ?? '',
                    'min_purchase' => $amount_bonus?->min_purchase ?? 0,
                    'max_discount' => $amount_bonus?->max_discount ?? 0,
                    'id' => $amount_bonus?->id,
                ];
            }

            return $data;
        } catch (\Exception) {
            return $data;
        }

    }

    private function resolveCashbackType(?int $moduleId): int|string|null
    {
        return match (Helpers::moduleTypeById($moduleId)) {
            'rental' => 1,
            'service' => 'service',
            default => null,
        };
    }

    private function buildQuery(array $filters): Builder
    {
        $customerId = $filters['customer_id'] ?? 'all';
        $moduleType = Helpers::moduleTypeById($filters['module_id'] ?? null);

        return CashBack::translateOnly(['title'])
            ->active()
            ->when($moduleType === 'rental', fn ($query) => $query->rental())
            ->when($moduleType === 'service', fn ($query) => $query->service())
            ->running()
            ->where(function ($query) use ($customerId) {
                $query->whereJsonContains('customer_id', [(string) $customerId])
                    ->orWhereJsonContains('customer_id', ['all']);
            })
            ->when(is_numeric($customerId), fn ($query) => $query->where(
                'same_user_limit',
                '>',
                fn ($sub) => $sub->selectRaw('COUNT(*)')
                    ->from('cash_back_histories')
                    ->where('user_id', $customerId)
                    ->whereColumn('cash_back_id', 'cash_backs.id')
            ));
    }
}
