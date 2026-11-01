<?php

namespace App\Services\System;

use App\Models\AnalyticScript;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;

class AnalyticScriptService extends BaseService
{
    public function getActiveList(
        array $paginate = []
    ): LengthAwarePaginator {
        return AnalyticScript::where('is_active', 1)
            ->select('type', 'script_id')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
}
