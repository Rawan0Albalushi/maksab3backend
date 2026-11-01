<?php

namespace App\Http\Requests\Customer\Wishlist;

use App\Http\Requests\BaseRequest;

class WishlistTargetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'item_id' => 'required_without_all:store_id,service_id',
            'store_id' => 'required_without_all:item_id,service_id',
            'service_id' => 'required_without_all:item_id,store_id',
        ];
    }

    public function target(): array
    {
        return [
            'item_id' => $this->input('item_id'),
            'store_id' => $this->input('store_id'),
            'service_id' => $this->input('service_id'),
        ];
    }

    public function targetCount(): int
    {
        return count(array_filter($this->target(), fn ($value) => ! is_null($value) && $value !== ''));
    }

    public function targetLabel(string $action): string
    {
        return match (true) {
            (bool) $this->input('store_id') => "Store {$action} favorites",
            (bool) $this->input('service_id') => "Service {$action} favorites",
            default => "Item {$action} favorites",
        };
    }
}
