<?php

namespace App\Http\Requests\Admin;

use App\Services\Zone\AdditionalDeliveryChargeService;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Creating an Additional Delivery Charge.
 *
 * Every field the design marks with a red asterisk is required here, and both time fields carry
 * a Min/Hour unit that is multiplied out by the service rather than stored beside the number.
 *
 * The overlap guard ("only one setup per Zone & Module combination") is NOT a rule here: it
 * needs a query, and a request validates and shapes but never queries (rule 2). The controller
 * asks AdditionalDeliveryChargeService for the clashing module names so the message can list
 * them, which a `unique` rule could not do.
 */
class AdditionalDeliveryChargeAddRequest extends FormRequest
{

    /**
     * Rental, ride-share and service can carry no additional delivery charge — none of them
     * reaches the core order pipeline that applies one. The picker omits them; hiding is not
     * enforcing, so a crafted POST is refused here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $incapable = app(AdditionalDeliveryChargeService::class)
                ->incapableModuleNames((array) $this->input('module_ids', []));

            if ($incapable) {
                $validator->errors()->add(
                    'module_ids',
                    implode(', ', $incapable).' '.translate('messages.cannot carry this delivery setup.'),
                );
            }
        });
    }

    public function rules(): array
    {
        return [
            'zone_id' => ['required', 'integer', 'exists:zones,id'],
            'module_ids' => ['required', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'exists:modules,id'],

            // The upper bounds are the COLUMNS' own limits. Without them the database silently
            // rewrote what the admin typed: a charge of 999999999999 became 99999999.99
            // (decimal 10,2) and a time of 99999 became 65535 (smallint unsigned), both with no
            // error anywhere. The time bound follows the chosen unit, because the service
            // multiplies hours out to minutes before storing them.
            'express_extra_charge' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'express_reduce_delivery_time' => ['required', 'integer', 'min:1', 'max:'.$this->maxTimeFor('express_reduce_delivery_time_unit')],
            'express_reduce_delivery_time_unit' => ['required', 'in:min,hour'],

            'delay_reduce_charge' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'delay_add_delivery_time' => ['required', 'integer', 'min:1', 'max:'.$this->maxTimeFor('delay_add_delivery_time_unit')],
            'delay_add_delivery_time_unit' => ['required', 'in:min,hour'],

            // Optional on the design, and empty means no filter at all.
            'vehicle_ids' => ['nullable', 'array'],
            'vehicle_ids.*' => ['integer', 'exists:d_m_vehicles,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('Please select a zone'),
            'module_ids.required' => translate('messages.Please select at least one module'),
            'express_extra_charge.required' => translate('messages.Express_delivery_needs_an_extra_charge'),
            'express_reduce_delivery_time.required' => translate('messages.Express_delivery_needs_a_reduced_delivery_time'),
            'delay_reduce_charge.required' => translate('messages.Slightly_delayed_delivery_needs_a_reduced_charge'),
            'delay_add_delivery_time.required' => translate('messages.Slightly_delayed_delivery_needs_an_added_delivery_time'),
        ];
    }

    /**
     * The largest value the chosen unit can carry.
     *
     * `additional_delivery_charges.*_delivery_time` is a smallint unsigned — 65535 minutes. An
     * hour is stored as 60 of them, so the hour ceiling is 1092 (65520 minutes).
     */
    protected function maxTimeFor(string $unitField): int
    {
        return $this->input($unitField) === 'hour' ? 1092 : 65535;
    }

    /** Field names for the generated messages, so none of them prints a raw column name. */
    public function attributes(): array
    {
        return [
            'express_extra_charge' => translate('Add extra charge'),
            'express_reduce_delivery_time' => translate('Reduce delivery time'),
            'express_reduce_delivery_time_unit' => translate('Reduce delivery time'),
            'delay_reduce_charge' => translate('Reduce charge'),
            'delay_add_delivery_time' => translate('Add extra delivery time'),
            'delay_add_delivery_time_unit' => translate('Add extra delivery time'),
            'zone_id' => translate('messages.Zone'),
            'module_ids' => translate('messages.Module'),
            'module_ids.*' => translate('messages.Module'),
            'vehicle_ids' => translate('Vehicle category'),
            'vehicle_ids.*' => translate('Vehicle category'),
        ];
    }

    /** The shape the service writes, so the controller never hand-builds an array. */
    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'module_ids' => array_map('intval', (array) $this->input('module_ids', [])),
            'vehicle_ids' => array_map('intval', (array) $this->input('vehicle_ids', [])),
            'express_extra_charge' => $this->input('express_extra_charge'),
            'express_reduce_delivery_time' => $this->input('express_reduce_delivery_time'),
            'express_reduce_delivery_time_unit' => $this->input('express_reduce_delivery_time_unit', 'min'),
            'delay_reduce_charge' => $this->input('delay_reduce_charge'),
            'delay_add_delivery_time' => $this->input('delay_add_delivery_time'),
            'delay_add_delivery_time_unit' => $this->input('delay_add_delivery_time_unit', 'min'),
            // No status field on the design's form. A new setup starts active, and the list's
            // toggle is where it gets switched off.
        ];
    }
}
