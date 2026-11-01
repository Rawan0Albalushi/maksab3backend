<?php

namespace App\Http\Requests\Common\Item;

use App\Http\Requests\BaseRequest;

class ReviewStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'item_id' => 'required|exists:items,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|numeric|max:5',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists' => translate('No data found'),
            'item_id.exists' => translate('No data found'),
        ];
    }

    public function payload(): array
    {
        return [
            'item_id' => $this->input('item_id'),
            'order_id' => $this->input('order_id'),
            'rating' => $this->input('rating'),
            'comment' => $this->input('comment'),
            'attachment' => array_values(array_filter((array) $this->file('attachment'))),
        ];
    }
}
