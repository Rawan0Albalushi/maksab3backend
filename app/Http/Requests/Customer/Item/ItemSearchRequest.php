<?php

namespace App\Http\Requests\Customer\Item;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ItemListFiltersTrait;

class ItemSearchRequest extends BaseRequest
{
    use ItemListFiltersTrait;

    use \App\Traits\Item\ItemFilterTrait;

    public function rules(): array
    {
        return ['name' => 'required'];
    }

    public function suggestFilters(mixed $module = null): array
    {
        return [
            'zone_id' => $this->header('zoneId'),
            'name' => $this->input('name'),
            'module' => $module,
            'store_category_id' => $this->input('store_category_id'),
            'longitude' => $this->header('longitude'),
            'latitude' => $this->header('latitude'),
            'price' => [
                'min_price' => $this->input('min_price'),
                'max_price' => $this->input('max_price'),
                'price' => $this->input('price'),
            ],
            'scope_input' => $this->all(),
        ];
    }

    public function searchFilters(): array
    {
        return $this->itemSearchFilters($this);
    }
}
