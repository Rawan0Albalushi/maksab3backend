<?php

namespace App\Http\Requests\Admin;

use App\Services\Parcel\WeightService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — keeps Laravel's redirect-back-with-errors behaviour. It is NOT the API's
 * envelope; that is deliberate and is what the architecture contract asks of panel forms.
 *
 * @property array name
 * @property float from_weight
 * @property float to_weight
 */
class WeightUpdateRequest extends OffcanvasFormRequest
{

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|array',
            'name.0' => [
                'required',
                'string',
                'max:191',
                Rule::unique('weights', 'name')->ignore($this->route("id")),
            ],
            // Capped at the column's own ceiling. `decimal(8,2)` silently truncates anything
            // larger -- 999999999 was stored as 999999.99 -- so an admin typing a figure too big
            // got a different one back with no warning.
            'from_weight' => 'required|numeric|min:0|max:999999.99',
            // gt, not gte: a band whose ends are equal matches nothing, so saving it is a
            // configuration that can never fire.
            'to_weight' => 'required|numeric|gt:from_weight|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'name.0.required' => translate('messages.Default name is required'),
            'name.0.unique' => translate('messages.A weight class with this name already exists'),
            'from_weight.required' => translate('messages.Starting weight is required'),
            'to_weight.required' => translate('messages.Ending weight is required'),
            'to_weight.gt' => translate('messages.Ending weight must be greater than the starting weight'),
        ];
    }

    /**
     * Bands must not overlap.
     *
     * Two rows covering 3 kg means the charge a package attracts depends on whichever row the
     * query returned first — a silent pricing inconsistency rather than a visible error. Touching
     * endpoints are fine, and are how the design itself writes them ("0 - 2KG", "2 - 4KG"), so the
     * test is strict on both sides. The message names the clashing bands so the admin can see
     * what to change instead of guessing.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $from = $this->input('from_weight');
            $to = $this->input('to_weight');

            if (! is_numeric($from) || ! is_numeric($to) || $to <= $from) {
                return;
            }

            $clashes = app(WeightService::class)->overlappingBandNames((float) $from, (float) $to, $this->route("id"));

            if ($clashes) {
                $validator->errors()->add(
                    'from_weight',
                    translate('messages.This weight range overlaps').' '.implode(', ', $clashes),
                );
            }
        });
    }

    /** Shaped here so the controller never hand-builds an array from raw input. */
    public function payload(): array
    {
        return [
            'name' => $this->input('name.0'),
            'from_weight' => (float) $this->input('from_weight'),
            'to_weight' => (float) $this->input('to_weight'),
        ];
    }
}
