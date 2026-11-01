<?php

namespace App\Services\Payment;

use App\Models\WalletBonus;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class WalletBonusService extends BaseService
{
    private const RUNNING_COLUMNS = ['id', 'title', 'bonus_type', 'bonus_amount', 'minimum_add_amount', 'end_date'];

    public function getRunningList(array $paginate = []): LengthAwarePaginator
    {
        return WalletBonus::translateOnly(['title'])
            ->active()
            ->running()
            ->latest()
            ->select(self::RUNNING_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getAddData(array $input): array
    {
        return [
            "title" => ($input['title'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            "description" => ($input['description'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            "bonus_type" => ($input['bonus_type'] ?? null),
            "start_date" => ($input['start_date'] ?? null),
            "end_date" => ($input['end_date'] ?? null),
            "minimum_add_amount" => ($input['minimum_add_amount'] ?? null) != null ? ($input['minimum_add_amount'] ?? null) : 0,
            "maximum_bonus_amount" => ($input['maximum_bonus_amount'] ?? null) != null ? ($input['maximum_bonus_amount'] ?? null) : 0,
            "bonus_amount" => ($input['bonus_amount'] ?? null),
            "status" =>  1,
        ];
    }

    public function getUpdateData(array $input): array
    {
        $data = $this->getAddData($input);
        unset($data['status']);

        return $data;
    }


    public function findBestForAmount(mixed $bonusType, mixed $addAmount): mixed
    {
        return WalletBonus::active()
            ->where('bonus_type', $bonusType)
            ->whereDate('end_date', '>=', date('Y-m-d'))
            ->whereDate('start_date', '<=', date('Y-m-d'))
            ->where('minimum_add_amount', '<=', $addAmount)
            ->orderBy('bonus_amount', 'desc')
            ->first();
    }

}
