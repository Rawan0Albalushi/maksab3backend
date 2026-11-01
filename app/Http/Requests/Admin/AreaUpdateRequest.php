<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property int zone_id
 * @property array name
 * @property array display_name
 */
class AreaUpdateRequest extends OffcanvasFormRequest
{

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => 'required|exists:zones,id',
            'name' => 'required|array',
            'name.0' => [
                'required',
                'string',
                'max:191',
                Rule::unique('areas', 'name')
                    ->ignore($this->route('id'))
                    ->where(fn ($query) => $query->where('zone_id', $this->input('zone_id'))),
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
            'name.0.required' => translate('messages.Default name is required'),
            'display_name.0.required' => translate('messages.Default display name is required'),
            'name.0.unique' => translate('messages.This area already exists in the selected zone'),
        ];
    }

    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'name' => $this->input('name.0'),
            'display_name' => $this->input('display_name.0'),
        ];
    }
}
