<?php

namespace App\Services\Item;

use App\Models\EcommerceItemDetails;
use App\Services\BaseService;

class EcommerceItemDetailsService extends BaseService
{
    public function getBrandItemQuery(): mixed
    {
        return EcommerceItemDetails::query()->select('brand_id', 'item_id')->distinct();
    }
}
