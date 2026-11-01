<?php

namespace App\Http\Resources\Vendor\Report;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class EarningTransactionResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), (array) $this->resource);
    }
}
