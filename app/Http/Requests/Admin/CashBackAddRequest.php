<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * @property int id
 * @property array|string title
 * @property array translations
 * @property array customer_id
 * @property string cashback_type
 * @property int same_user_limit
 * @property float cashback_amount
 * @property float min_purchase
 * @property float max_discount
 * @property Carbon|null start_date
 * @property Carbon|null end_date
 * @property bool status
 * @property Carbon|null created_at
 * @property Carbon|null updated_at
 * @property array lang
 */
class CashBackAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|max:191',
            'start_date' => 'required',
            'end_date' => 'required',
            'cashback_type' => 'required|in:percentage,amount',
            'cashback_amount' => 'required',
            'min_purchase' => [
                'required',
                function ($attribute, $value, $fail) {
                    $cashbackType = $this->input('cashback_type');
                    $cashbackAmount = $this->input('cashback_amount');

                    if ($cashbackType === 'amount' && $cashbackAmount >= $value) {
                        $fail(translate('The cashback amount should not be greater or equal than the minimum purchase value.'));
                    }
                }
            ],
            'max_discount' => 'required_if:cashback_type,percentage',
            'title.0' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'title.0.required'=>translate('Default title is required'),
        ];
    }
}
