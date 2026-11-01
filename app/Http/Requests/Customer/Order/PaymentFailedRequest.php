<?php

namespace App\Http\Requests\Customer\Order;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class PaymentFailedRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    private const ORDER_TYPE = 'order';

    private const RENTAL_TYPE = 'rental';

    public function rules(): array
    {
        return [
            'type' => 'nullable|in:order,rental',
            'order_id' => 'nullable',
            'trip_id' => 'nullable',
        ];
    }

    public function isRental(): bool
    {
        return $this->requestedType() === self::RENTAL_TYPE;
    }

    public function isOrder(): bool
    {
        return $this->requestedType() === self::ORDER_TYPE;
    }

    public function filters(): array
    {
        return $this->cartOwner($this) + [
            'order_id' => $this->input('order_id'),
            'trip_id' => $this->isRental() ? ($this->input('trip_id') ?: $this->input('order_id')) : $this->input('trip_id'),
        ];
    }

    private function requestedType(): ?string
    {
        if ($type = $this->input('type')) {
            return $type;
        }

        return match (true) {
            $this->filled('order_id') => self::ORDER_TYPE,
            $this->filled('trip_id') => self::RENTAL_TYPE,
            default => null,
        };
    }
}
