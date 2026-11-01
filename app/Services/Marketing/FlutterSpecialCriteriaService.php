<?php

namespace App\Services\Marketing;

use App\Models\FlutterSpecialCriteria;
use App\Services\BaseService;

class FlutterSpecialCriteriaService extends BaseService
{
    public function getActive(): mixed
    {
        return FlutterSpecialCriteria::withStorage()->where('status', 1)->get();
    }
}
