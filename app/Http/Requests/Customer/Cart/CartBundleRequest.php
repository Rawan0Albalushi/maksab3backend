<?php

namespace App\Http\Requests\Customer\Cart;

use App\Http\Requests\BaseRequest;

class CartBundleRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'guest_id' => $this->user ? 'nullable' : 'required',
            'bundle_id' => $this->isAdding() ? 'required' : 'nullable',
            'bundle_group_id' => $this->isAdding() ? 'nullable' : 'required|string',
            'quantity' => 'nullable|integer|min:1',
        ];
    }

    public function isAdding(): bool
    {
        return $this->filled('bundle_id') && ! $this->filled('bundle_group_id');
    }

    public function payload(): array
    {
        return [
            'bundle_id' => $this->input('bundle_id'),
            'bundle_group_id' => $this->input('bundle_group_id'),
            'quantity' => (int) ($this->input('quantity') ?: 1),
        ];
    }
}
