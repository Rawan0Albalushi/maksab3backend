<?php

namespace App\Http\Requests\Admin;

use App\Models\FreeDelivery;
use App\Services\Zone\FreeDeliveryService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors.
 *
 * @property int zone_id
 * @property array module_ids
 * @property string type
 */
class FreeDeliveryUpdateRequest extends FormRequest
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
            'zone_id' => 'required|exists:zones,id',
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            'type' => ['required', Rule::in(FreeDelivery::TYPES)],
            // Required for a specific_criteria setup — a blank threshold there is
            // indistinguishable from all_store (FreeDelivery::frees() reads a null amount as
            // "always free"), which left an admin unable to tell the two apart on screen
            // (TC_435, TC_436). all_store itself ignores the field either way, so it stays
            // nullable there. The model's own null-tolerance in frees() is left alone — it is
            // what keeps a row saved before this rule existed reading as it always has.
            'minimum_order_amount' => [
                'nullable', 'numeric', 'min:0', 'max:99999999.99',
                Rule::requiredIf(fn () => $this->input('type') === FreeDelivery::TYPE_CRITERIA),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('messages.Please select a zone'),
            'module_ids.required' => translate('messages.Please select at least one module'),
            'type.required' => translate('messages.Please select a free delivery type'),
            'minimum_order_amount.required' => translate('Enter the minimum order amount for specific criteria, or switch the type to all store'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // DESIGN RULE F1 — "A user can create only one Free Delivery per Zone & Module
        // combination." Because a setup claims a SET of modules the test is an OVERLAP, and the
        // message names the clashing modules so the admin can deselect them rather than guess.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }

            $clashes = app(FreeDeliveryService::class)->conflictingModuleNames($zoneId, $moduleIds, $this->route('id'));

            if ($clashes) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.A free delivery setup already exists in this zone for').' '.implode(', ', $clashes),
                );
            }
        });

        // D7's rule, applied here too: a module the zone does not serve could never be freed,
        // so it is refused rather than stored. The picker hides them; hiding is not enforcing.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }

            // Hiding is not enforcing: the picker omits them, this refuses a crafted POST.
            $incapable = app(FreeDeliveryService::class)->incapableModuleNames($moduleIds);

            if ($incapable) {
                $validator->errors()->add(
                    'module_ids',
                    implode(', ', $incapable).' '.translate('messages.cannot carry this delivery setup.'),
                );

                return;
            }

            $unconnected = app(FreeDeliveryService::class)->unconnectedModuleNames($zoneId, $moduleIds);

            if ($unconnected) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.This zone is not connected to').' '.implode(', ', $unconnected)
                        .'. '.translate('Connect it from zone setup first.'),
                );
            }
        });
    }

    /** Field names for the generated messages, so none of them prints a raw column name. */
    public function attributes(): array
    {
        return [
            'zone_id' => translate('messages.Zone'),
            'module_ids' => translate('messages.Module'),
            'module_ids.*' => translate('messages.Module'),
            'type' => translate('Free delivery type'),
            'minimum_order_amount' => translate('Minimum order amount'),
        ];
    }

    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'module_ids' => (array) $this->input('module_ids', []),
            'type' => $this->input('type'),
            'minimum_order_amount' => $this->input('minimum_order_amount'),
        ];
    }
}
