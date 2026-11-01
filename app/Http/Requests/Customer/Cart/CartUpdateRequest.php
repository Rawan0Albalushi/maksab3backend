<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;
use Modules\Service\Services\ServiceCartService;

class CartUpdateRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        if ($this->isServiceModuleContext()) {
            return app(ServiceCartService::class)->cartRules('update', (bool) $this->user);
        }

        return [
            'cart_id' => 'required',
            'guest_id' => $this->user ? 'nullable' : 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'store_id' => 'required',
        ];
    }

    public function cartId(): mixed
    {
        return $this->input('cart_id');
    }

    public function filters(): array
    {
        return array_merge($this->cartOwner($this), [
            'store_id' => $this->cartStoreId($this),
        ]);
    }

    public function variation(): array
    {
        $variation = $this->input('variation');

        return is_array($variation)
            ? array_filter($variation, fn ($value) => $value !== null)
            : [];
    }

    public function payload(): array
    {
        $payload = array_merge($this->cartOwner($this), [
            'price' => $this->input('price'),
            'quantity' => (int) $this->input('quantity'),
            'variation' => $this->variation(),
        ]);

        if ($this->has('add_on_ids')) {
            $payload['add_on_ids'] = $this->input('add_on_ids') ?? [];
        }

        if ($this->has('add_on_qtys')) {
            $payload['add_on_qtys'] = $this->input('add_on_qtys') ?? [];
        }

        return $payload;
    }
}
