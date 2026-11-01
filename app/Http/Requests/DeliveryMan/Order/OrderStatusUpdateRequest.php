<?php

namespace App\Http\Requests\DeliveryMan\Order;

use App\Http\Requests\BaseRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\Config;

class OrderStatusUpdateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'status' => 'required|in:confirmed,canceled,picked_up,delivered,handover',
            'reason' => 'required_if:status,canceled',
            'order_proof' => 'array|max:5',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('otp', 'required', fn ($input) => Config::get('order_delivery_verification') == 1 && $input->status === 'delivered');
    }

    public function payload(): array
    {
        return [
            'status' => $this->input('status'),
            'reason' => $this->input('reason'),
            'note' => $this->input('note'),
            'otp' => $this->input('otp'),
            'order_proof' => $this->file('order_proof') ?? [],
        ];
    }
}
