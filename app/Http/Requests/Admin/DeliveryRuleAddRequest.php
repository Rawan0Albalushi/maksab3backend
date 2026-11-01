<?php

namespace App\Http\Requests\Admin;

use App\Models\DeliveryRule;
use App\Services\Zone\AreaService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\ZipCodeService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors.
 *
 * Carries three validations §5.5 asks for beyond the obvious types:
 *  - maximum_delivery_charge >= minimum_delivery_charge
 *  - a coverage method refuses to save when the zone has no areas / ZIP codes to price
 *  - the method's own fields are required only when that method is chosen, so a hidden pane
 *    cannot block a save with a value the admin cannot see
 *
 * @property int zone_id
 * @property int module_id
 * @property string pricing_method
 */
class DeliveryRuleAddRequest extends FormRequest
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
        $method = $this->input('pricing_method');

        return [
            'zone_id' => 'required|exists:zones,id',
            // Multi-select: the design's "Choose Module To Connect" renders chips.
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            'name' => 'required|string|max:191',
            'minimum_delivery_charge' => 'required|numeric|min:0|max:99999999.99',
            'pricing_method' => ['required', Rule::in(DeliveryRule::PRICING_METHODS)],

            'per_km_charge' => $method === DeliveryRule::METHOD_DISTANCE ? 'required|numeric|min:0|max:99999999.99' : 'nullable',
            'maximum_delivery_charge' => $method === DeliveryRule::METHOD_DISTANCE
                ? 'nullable|numeric|min:0|max:99999999.99|gte:minimum_delivery_charge'
                : 'nullable',
            'fixed_charge' => $method === DeliveryRule::METHOD_FIXED ? 'required|numeric|min:0|max:99999999.99' : 'nullable',

            // The coverage tables post under the name of the METHOD they belong to, so a hidden
            // pane's values never reach the save. `payload()` folds whichever one applies into a
            // single `charges` map keyed by coverage id.
            'area_charges' => 'nullable|array',
            'area_charges.*' => 'nullable|numeric|min:0|max:99999999.99',
            'zip_charges' => 'nullable|array',
            'zip_charges.*' => 'nullable|numeric|min:0|max:99999999.99',

            // The wizard's two parcel steps. Nullable throughout: the steps only exist when a
            // parcel-capable module is connected, and a step whose Status is off submits nothing.
            // A blank charge means "no extra for this band", which the service stores as 0 —
            // the design's own note says to enter 0 when nothing should be added.
            'weight_charge_status' => 'nullable|boolean',
            'dimension_charge_status' => 'nullable|boolean',
            'weight_charges' => 'nullable|array',
            'weight_charges.*' => 'nullable|numeric|min:0|max:99999999.99',
            'dimension_charges' => 'nullable|array',
            'dimension_charges.*' => 'nullable|numeric|min:0|max:99999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('messages.Please select a zone'),
            'module_id.required' => translate('messages.Please select a module'),
            'maximum_delivery_charge.gte' => translate('messages.Maximum delivery charge cannot be less than the minimum delivery charge'),
            'per_km_charge.required' => translate('messages.Per unit delivery charge is required for distance wise pricing'),
            'fixed_charge.required' => translate('messages.Fixed delivery charge is required for fixed amount pricing'),
        ];
    }

    /**
     * Field names for the generated messages.
     *
     * Without this, `per_km_charge` humanises to "per km charge" — it leaks the column name, and
     * it names KILOMETRES on a platform whose `distance_unit` may say miles. The screen calls it
     * "Per Unit Delivery Charge"; so does the error.
     */
    public function attributes(): array
    {
        return [
            'per_km_charge' => translate('Per unit delivery charge'),
            'minimum_delivery_charge' => translate('messages.Minimum Delivery Charge'),
            'maximum_delivery_charge' => translate('Maximum delivery charge'),
            'fixed_charge' => translate('Delivery charge'),
            // Wildcards, so a rejected row reads "The weight charge must be at least 0" rather
            // than "The weight charges.48 must be at least 0" — which named an internal id the
            // admin has no way to match to a row in the table.
            'area_charges.*' => translate('Delivery charge'),
            'zip_charges.*' => translate('Delivery charge'),
            'weight_charges.*' => translate('Weight charge'),
            'dimension_charges.*' => translate('Dimension charge'),
        ];
    }

    /**
     * A coverage method with nothing to cover would save a rule that prices every order at the
     * floor and shows the customer an empty picker. Refuse it and say why (§5.5).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = $this->input('pricing_method');
            $zoneId = $this->input('zone_id');

            if (! $zoneId || ! in_array($method, DeliveryRule::COVERAGE_METHODS, true)) {
                return;
            }

            $hasCoverage = $method === DeliveryRule::METHOD_AREA
                ? app(AreaService::class)->activeForZone($zoneId)->isNotEmpty()
                : app(ZipCodeService::class)->activeForZone($zoneId)->isNotEmpty();

            if (! $hasCoverage) {
                $validator->errors()->add('pricing_method', translate('messages.No coverage has been created for this zone'));
            }
        });

        // DESIGN RULE D1 — "A user can create only one Delivery Rule per Zone & Module
        // combination. If a rule already exists (e.g. Dhaka Zone - Food Module), another rule
        // cannot be created for the same combination. A new rule can be created for a different
        // Zone."
        //
        // Stronger than the one-ACTIVE-rule invariant the model enforces: a second rule for the
        // combination cannot be created at all, active or not. Because a rule claims a SET of
        // modules the test is OVERLAP, and the message names the modules that clash so the admin
        // can deselect them rather than guess. Scoped to the zone, so the same module in another
        // zone is fine.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }

            $clashes = app(DeliveryRuleService::class)->conflictingModuleNames($zoneId, $moduleIds, null);

            if ($clashes) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.A delivery rule already exists in this zone for').' '.implode(', ', $clashes),
                );
            }
        });

        // DESIGN RULE — "in the module dropdown only those modules will show which are connected
        // to the zone in Zone Setup → Connect Module."
        //
        // The dropdown hides the rest, but hiding is not enforcing: a stale form, a module
        // disconnected between render and submit, or a crafted POST all reach here. A rule for a
        // module the zone does not serve could never fire, so it is refused rather than stored.
        $validator->after(function (Validator $validator) {
            $zoneId = $this->input('zone_id');
            $moduleIds = (array) $this->input('module_ids', []);

            if (! $zoneId || ! $moduleIds) {
                return;
            }

            // Hiding is not enforcing: the picker omits them, this refuses a crafted POST. A rule
            // the edit form already holds keeps its modules — see incapableModuleNames().
            $incapable = app(DeliveryRuleService::class)->incapableModuleNames($moduleIds, null);

            if ($incapable) {
                $validator->errors()->add(
                    'module_ids',
                    implode(', ', $incapable).' '.translate('messages.cannot carry a delivery rule.'),
                );

                return;
            }

            $unconnected = app(DeliveryRuleService::class)->unconnectedModuleNames($zoneId, $moduleIds);

            if ($unconnected) {
                $validator->errors()->add(
                    'module_ids',
                    translate('messages.This zone is not connected to').' '.implode(', ', $unconnected)
                        .'. '.translate('Connect it from zone setup first.'),
                );
            }
        });
    }

    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'module_ids' => (array) $this->input('module_ids', []),
            'name' => $this->input('name'),
            'minimum_delivery_charge' => (float) $this->input('minimum_delivery_charge', 0),
            'pricing_method' => $this->input('pricing_method'),
            'per_km_charge' => $this->input('per_km_charge'),
            'maximum_delivery_charge' => $this->input('maximum_delivery_charge'),
            'fixed_charge' => $this->input('fixed_charge'),
            'charges' => $this->coverageCharges(),
            'weight_charge_status' => (bool) $this->input('weight_charge_status'),
            'dimension_charge_status' => (bool) $this->input('dimension_charge_status'),
            'weight_charges' => (array) $this->input('weight_charges', []),
            'dimension_charges' => (array) $this->input('dimension_charges', []),
            // Deliberately ABSENT, so the service decides: the first rule a (zone, module) has
            // ever had is created active, a later one is not. It used to be hardcoded false here
            // on the reasoning that a rule "would quote before its charges were checked" — but
            // every charge map above is posted in this same request and synced inside create()'s
            // transaction, so a rule is complete the moment it commits.
        ];
    }

    /**
     * The charge map for the chosen method, keyed by coverage id.
     *
     * Area-wise and ZIP-wise render separate tables and post under separate names, so reading a
     * bare `charges` key silently collected nothing — every coverage charge was dropped on the way
     * to the service and the rule saved with an empty table. Mistake M5, payload key mismatch.
     *
     * A non-coverage method contributes nothing: DeliveryRuleChargeService::syncForRule() already
     * refuses to write rows for one, and returning the other pane's values here would leave them
     * waiting to reappear if the method were switched back.
     */
    private function coverageCharges(): array
    {
        return match ($this->input('pricing_method')) {
            DeliveryRule::METHOD_AREA => (array) $this->input('area_charges', []),
            DeliveryRule::METHOD_ZIP => (array) $this->input('zip_charges', []),
            default => [],
        };
    }

}
