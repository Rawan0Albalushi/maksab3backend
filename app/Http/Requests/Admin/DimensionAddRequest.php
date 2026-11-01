<?php

namespace App\Http\Requests\Admin;

use App\Services\Parcel\DimensionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors, not the API envelope.
 *
 * No general overlap rule here, unlike WeightAddRequest. Weight bands partition a single axis, so
 * two bands covering 3 kg is ambiguous. Size classes are three-dimensional and deliberately nest —
 * every Small box also fits inside Large — so "overlap" is the normal case, and the class a
 * package takes is the smallest it fits, not the only one. EXACT duplicate measurements are
 * still refused below (TC_220): nothing about "the smallest box it fits in" can break a tie
 * between two classes shaped identically.
 *
 * @property array name
 * @property float max_length
 * @property float max_width
 * @property float max_height
 */
class DimensionAddRequest extends OffcanvasFormRequest
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
                Rule::unique('dimensions', 'name'),
            ],
            // gt:0 — a side of zero gives a box of no volume, which nothing can fit in.
            // Capped at the column's own ceiling. `decimal(8,2)` silently truncates anything
            // larger -- 999999999 was stored as 999999.99 -- so an admin typing a figure too big
            // got a different one back with no warning.
            'max_length' => 'required|numeric|gt:0|max:999999.99',
            'max_width' => 'required|numeric|gt:0|max:999999.99',
            'max_height' => 'required|numeric|gt:0|max:999999.99',
        ];
    }

    public function messages(): array
    {
        return [
            'name.0.required' => translate('messages.Default name is required'),
            'name.0.unique' => translate('messages.A dimension with this name already exists'),
            'max_length.gt' => translate('messages.Maximum length must be greater than zero'),
            'max_width.gt' => translate('messages.Maximum width must be greater than zero'),
            'max_height.gt' => translate('messages.Maximum height must be greater than zero'),
        ];
    }

    /**
     * Refuses only an EXACT match on all three measurements — see the class docblock for why a
     * general overlap rule would be wrong here.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $length = $this->input('max_length');
            $width = $this->input('max_width');
            $height = $this->input('max_height');

            if (! is_numeric($length) || ! is_numeric($width) || ! is_numeric($height)) {
                return;
            }

            $clashes = app(DimensionService::class)->identicalMeasurementNames(
                (float) $length, (float) $width, (float) $height, null,
            );

            if ($clashes) {
                $validator->errors()->add(
                    'max_length',
                    translate('messages.These measurements already match').' '.implode(', ', $clashes),
                );
            }
        });
    }

    /** Shaped here so the controller never hand-builds an array from raw input. */
    public function payload(): array
    {
        return [
            'name' => $this->input('name.0'),
            'max_length' => (float) $this->input('max_length'),
            'max_width' => (float) $this->input('max_width'),
            'max_height' => (float) $this->input('max_height'),
        ];
    }
}
