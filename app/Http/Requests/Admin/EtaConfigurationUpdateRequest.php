<?php

namespace App\Http\Requests\Admin;

use App\Models\EtaConfiguration;
use App\Services\System\ModuleService;
use App\Services\Zone\EtaConfigurationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors.
 *
 * Every timing here is MINUTES, which is why none of them carries a unit: the form fixes "Min"
 * to each input rather than offering a choice.
 *
 * @property array name
 * @property int zone_id
 * @property array module_ids
 * @property string calculation_method
 */
class EtaConfigurationUpdateRequest extends FormRequest
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
            'name' => 'required|array',
            'name.0' => 'required|string|max:100',
            'zone_id' => 'required|exists:zones,id',
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            // Required only when a non-Parcel module is among the picked ones, since the card
            // that carries these lives is hidden otherwise (mirrors the parcel_* fields below,
            // which are required only when Parcel is picked).
            'calculation_method' => [Rule::requiredIf($this->hasNonParcelModule()), 'nullable', Rule::in(EtaConfiguration::METHODS)],
            // The design stars these three and leaves the gap unstarred, so the gap alone is
            // optional. Integers because the column is minutes — there is no half minute here.
            // min:1, not 0. This is the FLOOR of the estimate -- minimumEtaMinutes() is
            // max(buffers, this) -- so a zero here with zero buffers renders the customer an
            // ETA of "0 min". The buffers may legitimately be zero; the floor may not.
            'minimum_delivery_time' => [Rule::requiredIf($this->hasNonParcelModule()), 'nullable', 'integer', 'min:1', 'max:32767'],
            'preparation_buffer' => [Rule::requiredIf($this->hasNonParcelModule()), 'nullable', 'integer', 'min:0', 'max:32767'],
            'transit_buffer' => [Rule::requiredIf($this->hasNonParcelModule()), 'nullable', 'integer', 'min:0', 'max:32767'],
            // Distance based only. The service nulls it for the other method rather than
            // trusting the form to have hidden it.
            'time_gap' => 'nullable|integer|min:0|max:32767',
            // Parcel's own three — required only when Parcel is one of the picked modules, since
            // the section that carries them is hidden otherwise. Parcel has no fixed-delivery-time
            // alternative and no preparation buffer, so there is no fourth/fifth field to mirror.
            'parcel_minimum_delivery_time' => [Rule::requiredIf($this->hasParcel()), 'nullable', 'integer', 'min:1', 'max:32767'],
            'parcel_transit_buffer' => [Rule::requiredIf($this->hasParcel()), 'nullable', 'integer', 'min:0', 'max:32767'],
            'parcel_time_gap' => 'nullable|integer|min:0|max:32767',
        ];
    }

    /** Whether Parcel is among the picked modules — gates the parcel section's own required fields. */
    private function hasParcel(): bool
    {
        $parcelIds = app(ModuleService::class)->moduleIdsOfType('parcel');

        return array_intersect((array) $this->input('module_ids', []), $parcelIds) !== [];
    }

    /** Whether a module OTHER than Parcel is among the picked ones — gates the other card's fields. */
    private function hasNonParcelModule(): bool
    {
        $parcelIds = app(ModuleService::class)->moduleIdsOfType('parcel');

        return array_diff((array) $this->input('module_ids', []), $parcelIds) !== [];
    }

    public function messages(): array
    {
        return [
            'name.0.required' => translate('messages.Default name is required'),
            'zone_id.required' => translate('messages.Please select a zone'),
            'module_ids.required' => translate('messages.Please select at least one module'),
            'calculation_method.required' => translate('messages.Please select an ETA method'),
            'minimum_delivery_time.required' => translate('messages.Minimum delivery time is required'),
            'preparation_buffer.required' => translate('messages.Preparation buffer is required'),
            'transit_buffer.required' => translate('messages.Transit buffer is required'),
            'parcel_minimum_delivery_time.required' => translate('messages.Minimum delivery time is required'),
            'parcel_minimum_delivery_time.min' => translate('messages.Minimum delivery time must be greater than zero'),
            'parcel_transit_buffer.required' => translate('messages.Transit buffer is required'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // DESIGN RULE E1 — "A user can create only one ETA configuration per Zone & Module
        // combination." A configuration claims a SET of modules, so the test is an OVERLAP, and
        // the message names the clashing modules so the admin can deselect them rather than guess.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }

            $clashes = app(EtaConfigurationService::class)->conflictingModuleNames($zoneId, $moduleIds, $this->route('id'));

            if ($clashes) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.An ETA configuration already exists in this zone for').' '.implode(', ', $clashes),
                );
            }
        });

        // D7's rule, applied here too: a module the zone does not serve has nothing to estimate
        // for, so it is refused rather than stored. The picker hides them; hiding is not enforcing.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }


            $incapable = app(EtaConfigurationService::class)->etaIncapableModuleNames($moduleIds);

            if ($incapable) {
                $validator->errors()->add(
                    'module_ids',
                    implode(', ', $incapable).' '.translate('messages.cannot have an ETA configuration.'),
                );
            }

            $unconnected = app(EtaConfigurationService::class)->unconnectedModuleNames($zoneId, $moduleIds);

            if ($unconnected) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.This zone is not connected to').' '.implode(', ', $unconnected)
                        .'. '.translate('Connect it from zone setup first.'),
                );
            }
        });
    }

    /** Shaped here so the controller never hand-builds an array from raw input. */
    public function payload(): array
    {
        return [
            'name' => $this->input('name.0'),
            'zone_id' => (int) $this->input('zone_id'),
            'module_ids' => (array) $this->input('module_ids', []),
            // Parcel-only submits never send this, since its own card has no method choice.
            // EtaConfigurationService::attributes() already defaults a missing value to
            // METHOD_DISTANCE, so there is nothing to default here.
            'calculation_method' => $this->input('calculation_method'),
            'minimum_delivery_time' => $this->input('minimum_delivery_time'),
            'preparation_buffer' => $this->input('preparation_buffer'),
            'transit_buffer' => $this->input('transit_buffer'),
            'time_gap' => $this->input('time_gap'),
            'parcel_minimum_delivery_time' => $this->input('parcel_minimum_delivery_time'),
            'parcel_transit_buffer' => $this->input('parcel_transit_buffer'),
            'parcel_time_gap' => $this->input('parcel_time_gap'),
        ];
    }
}
