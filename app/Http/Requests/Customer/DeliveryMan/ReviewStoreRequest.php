<?php

namespace App\Http\Requests\Customer\DeliveryMan;

use App\Http\Requests\BaseRequest;

class ReviewStoreRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'delivery_man_id' => 'required|exists:delivery_men,id',
            'order_id' => 'required',
            'comment' => 'required',
            'rating' => 'required|numeric|max:5',
        ];
    }

    public function payload(): array
    {
        return [
            'delivery_man_id' => $this->input('delivery_man_id'),
            'order_id' => $this->input('order_id'),
            'comment' => $this->input('comment'),
            'rating' => $this->input('rating'),
            'attachment' => $this->file('attachment') ?? [],
        ];
    }
}
