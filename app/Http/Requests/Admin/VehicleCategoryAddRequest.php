<?php

namespace App\Http\Requests\Admin;

use App\Services\DeliveryMan\DmVehicleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors.
 *
 * The coverage band and the max weight are SETUP values, stored exactly as typed and re-read in
 * whatever `distance_unit` and `weight_unit` name. Nothing is converted here.
 *
 * @property array type
 * @property array lang
 * @property float starting_coverage_area
 * @property float maximum_coverage_area
 * @property float max_weight
 * @property array dimension_ids
 */
class VehicleCategoryAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|array',
            'type.0' => 'required|string|max:191|unique:d_m_vehicles,type',
            // decimal(10,2) on max_weight; the coverage columns are doubles but the form has no
            // business accepting a number no admin would type.
            'starting_coverage_area' => 'required|numeric|min:0|max:999999.99',
            'maximum_coverage_area' => 'required|numeric|max:999999.99|gt:starting_coverage_area',
            'max_weight' => 'required|numeric|min:0.01|max:99999999.99',
            // Required, as the design stars it — but only once there is something to pick. With
            // no dimension class set up yet the field has no options at all, and requiring it
            // would make the whole screen unusable rather than the field.
            'dimension_ids' => [Rule::requiredIf(fn () => $this->dimensionsExist()), 'array'],
            'dimension_ids.*' => 'integer|exists:dimensions,id',
        ];
    }

    public function messages(): array
    {
        return [
            'type.0.required' => translate('messages.Default vehicle type is required'),
            'type.0.unique' => translate('messages.This vehicle type already exists'),
            'starting_coverage_area.required' => translate('messages.Minimum coverage area is required'),
            'maximum_coverage_area.required' => translate('messages.Maximum coverage area is required'),
            'maximum_coverage_area.gt' => translate('messages.Maximum coverage area must be greater than the minimum coverage area'),
            'max_weight.required' => translate('messages.Max weight is required'),
            'max_weight.min' => translate('messages.Max weight must be greater than zero'),
            'dimension_ids.required' => translate('messages.Please connect at least one dimension'),
        ];
    }

    /** Shaped here so the controller never hand-builds an array from raw input. */
    public function payload(): array
    {
        return [
            'type' => $this->input('type.0'),
            'starting_coverage_area' => $this->input('starting_coverage_area'),
            'maximum_coverage_area' => $this->input('maximum_coverage_area'),
            'max_weight' => $this->input('max_weight'),
            'dimension_ids' => (array) $this->input('dimension_ids', []),
        ];
    }

    protected function dimensionsExist(): bool
    {
        return app(DmVehicleService::class)->dimensionOptions()->isNotEmpty();
    }
}
