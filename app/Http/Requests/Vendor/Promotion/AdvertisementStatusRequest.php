<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;
use App\Rules\WordValidation;
use App\Traits\Api\ApiRequestContextTrait;

class AdvertisementStatusRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'id' => 'required',
            'status' => 'required|in:paused,approved',
            'pause_note' => ['required_if:status,paused', new WordValidation],
            'cancellation_note' => ['required_if:status,denied', new WordValidation],
        ];
    }

    public function advertisementId(): mixed
    {
        return $this->input('id');
    }

    public function status(): string
    {
        return (string) $this->input('status');
    }

    public function payload(): array
    {
        return [
            'store_id' => $this->vendorStoreId($this),
            'pause_note' => $this->input('pause_note'),
        ];
    }
}
