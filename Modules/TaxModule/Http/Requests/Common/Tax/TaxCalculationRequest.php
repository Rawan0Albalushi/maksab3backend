<?php

namespace Modules\TaxModule\Http\Requests\Common\Tax;

use App\Http\Requests\BaseRequest;

class TaxCalculationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'totalProductAmount' => 'required|numeric',
            'productIds' => 'required',
            'categoryIds' => 'required',
            'quantity' => 'required',
            'additionalCharges' => 'nullable',
            'orderId' => 'nullable',
            'countryCode' => 'nullable',
            'taxPayer' => 'nullable',
            'addonIds' => 'nullable',
            'addonQuantity' => 'nullable',
            'addonCategoryIds' => 'nullable',
        ];
    }

    public function payload(): array
    {
        return [
            'amount' => $this->input('totalProductAmount'),
            'productIds' => $this->decodedList('productIds'),
            'addonIds' => $this->decodedList('addonIds'),
            'additionalCharges' => $this->decodedList('additionalCharges'),
            'taxPayer' => $this->input('taxPayer') ?? 'vendor',
            'orderId' => $this->input('orderId'),
            'countryCode' => $this->input('countryCode'),
        ];
    }

    private function decodedList(string $key): array
    {
        $value = $this->input($key);

        return is_array($value) ? $value : (json_decode((string) $value, true) ?? []);
    }
}
