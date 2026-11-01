<?php

namespace App\Http\Requests\Customer\Cart;

use App\CentralLogics\Helpers;
use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Modules\Service\Services\ServiceCartService;

class CartAddRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        if ($this->isServiceModuleContext()) {
            return app(ServiceCartService::class)->cartRules('add', (bool) $this->user);
        }

        return [
            'guest_id' => $this->user ? 'nullable' : 'required',
            'item_id' => 'required|integer',
            'model' => 'required|string|in:Item,ItemCampaign',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'reel_id' => 'nullable|integer',
            'store_id' => 'required',
        ];
    }

    public function filters(): array
    {
        return array_merge($this->cartOwner($this), [
            'store_id' => $this->cartStoreId($this),
        ]);
    }

    public function payload(): array
    {
        return array_merge($this->cartOwner($this), [
            'item_id' => (int) $this->input('item_id'),
            'model' => $this->input('model'),
            'price' => $this->input('price'),
            'quantity' => (int) $this->input('quantity'),
            'variation' => $this->input('variation'),
            'add_on_ids' => $this->input('add_on_ids') ?? [],
            'add_on_qtys' => $this->input('add_on_qtys') ?? [],
            'reel_id' => Helpers::resolve_reel_id(
                $this->filled('reel_id') ? (int) $this->input('reel_id') : null,
                (int) $this->input('item_id')
            ),
        ]);
    }
}
