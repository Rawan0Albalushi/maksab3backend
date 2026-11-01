<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Modules\Service\Services\ServiceCartService;

class CartAddMultipleRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        if ($this->isServiceModuleContext()) {
            return app(ServiceCartService::class)->cartRules('add_multiple', (bool) $this->user);
        }

        return [
            'item_list' => 'required|array',
            'store_id' => 'required',
        ];
    }

    public function filters(): array
    {
        return array_merge($this->cartOwner($this), [
            'store_id' => $this->cartStoreId($this),
        ]);
    }

    public function itemList(): array
    {
        return (array) $this->input('item_list', []);
    }
}
