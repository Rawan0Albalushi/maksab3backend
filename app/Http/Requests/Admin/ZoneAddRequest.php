<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\Helpers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use App\Rules\PolygonHasEnoughPoints;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * @property array name
 * @property array coordinates
 * @property string store_wise_topic
 * @property string customer_wise_topic
 * @property string deliveryman_wise_topic
 * @property string|int cash_on_delivery
 * @property string|int digital_payment
 * @property string|int offline_payment
 * @property array lang
 */
class ZoneAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `name` and `display_name` arrive as ARRAYS — one entry per language tab — so a length rule
     * has to be written as `name.*`. Writing `name => max:191` tests the array's COUNT, which is
     * how a 320-character zone name used to pass validation and then get silently cut to 255 by
     * the column (QA case TC_46).
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|array|unique:zones',
            'name.*' => 'nullable|string|max:191',
            'name.0' => 'required|string|max:191',
            // TC_39 — the display name was unvalidated, so a zone saved with display_name null.
            'display_name' => 'required|array',
            'display_name.*' => 'nullable|string|max:191',
            'display_name.0' => 'required|string|max:191',
            // TC_40 — "required" caught an empty map but not a half-drawn one. A polygon needs
            // three distinct points to enclose anything; with two, the LineString built in
            // ZoneService reaches MySQL and throws a 500 instead of telling the admin what is
            // wrong. See PolygonHasEnoughPoints.
            'coordinates' => ['required', 'string', new PolygonHasEnoughPoints],
        ];
    }

    public function messages(): array
    {
        return [
            'name.0.required' => translate('Default name is required'),
            'name.0.max' => translate('The zone name is too long.') . ' ' . translate('Character limit') . ': 191',
            'display_name.0.required' => translate('Default display name is required'),
            'display_name.0.max' => translate('The display name is too long.') . ' ' . translate('Character limit') . ': 191',
        ];
    }

    public function failedValidation(Validator $validator): void
    {
        $response = response()->json(['errors' => Helpers::error_processor($validator)]);
        throw new ValidationException($validator, $response);
    }
}
