<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — keeps Laravel's redirect-back-with-errors behaviour. It is NOT the API's 400
 * envelope; that is deliberate and is what the architecture contract asks of panel forms.
 *
 * @property int zone_id
 * @property array name
 * @property array display_name
 */
class AreaAddRequest extends OffcanvasFormRequest
{

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|array',
            // Names are unique WITHIN a zone, not globally — two zones may both have a
            // "Downtown", and rejecting that would be wrong.
            'name.0' => [
                'required',
                'string',
                'max:191',
                Rule::unique('areas', 'name')->where(fn ($query) => $query->where('zone_id', $this->input('zone_id'))),
            ],
            // Same defect as the zone form's TC_39: `nullable` let an area save with
            // display_name NULL, and the display name is what the customer side shows. The array
            // must arrive and its DEFAULT entry must carry a value; the per-language entries stay
            // optional, because a missing translation falls back to the default.
            'display_name' => 'required|array',
            'display_name.*' => 'nullable|string|max:191',
            'display_name.0' => 'required|string|max:191',
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('messages.Please select a zone'),
            'name.0.required' => translate('messages.Default name is required'),
            'display_name.0.required' => translate('messages.Default display name is required'),
            'name.0.unique' => translate('messages.This area already exists in the selected zone'),
        ];
    }

    /** Shaped here so the controller never hand-builds an array from raw input. */
    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'name' => $this->input('name.0'),
            'display_name' => $this->input('display_name.0'),
            'status' => true,
        ];
    }
}
