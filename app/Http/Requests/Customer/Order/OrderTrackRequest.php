<?php

namespace App\Http\Requests\Customer\Order;

class OrderTrackRequest extends OrderIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), ['contact_number' => $this->user ? 'nullable' : 'required']);
    }

    public function contactNumber(): ?string
    {
        $number = $this->input('contact_number');

        if (! $number) {
            return null;
        }

        return str_starts_with($number, '+') ? $number : '+' . $number;
    }
}
