<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;
use App\Traits\Api\ApiRequestContextTrait;

class CashBackCalculateRequest extends BaseRequest
{
    use ApiRequestContextTrait;

    public function rules(): array
    {
        return [
            'amount' => 'required',
        ];
    }

    public function amount(): mixed
    {
        return $this->input('amount');
    }

    public function filters(): array
    {
        return [
            'customer_id' => auth()->id() ?? $this->input('customer_id') ?? 'all',
            'module_id' => getModuleId($this->header('moduleId')),
        ];
    }
}
