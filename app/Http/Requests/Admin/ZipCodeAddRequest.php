<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors, not the API envelope.
 *
 * @property int zone_id
 * @property string zip_code
 */
class ZipCodeAddRequest extends OffcanvasFormRequest
{

    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'zone_id' => 'required|exists:zones,id',
            // Unique WITHIN the zone only. A global unique rule would reject two zones that
            // legitimately share a code where their coverage overlaps (§7).
            'zip_code' => [
                'required',
                'string',
                'max:20',
                // Deliberately PERMISSIVE about shape, strict about characters. Postal codes are
                // numeric in Bangladesh and the US, alphanumeric in the UK and Canada, and carry
                // spaces or hyphens in several ("SW1A 1AA", "K1A-0B1"), so pinning a country
                // format here would reject legitimate codes on an international platform. What is
                // never a postal code is punctuation like "@#!" or a leading "-", both of which
                // this field used to accept and store.
                'regex:/^[A-Za-z0-9]+([ -][A-Za-z0-9]+)*$/',
                Rule::unique('zip_codes', 'zip_code')->where(fn ($query) => $query->where('zone_id', $this->input('zone_id'))),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' => translate('messages.Please select a zone'),
            'zip_code.required' => translate('messages.Zip code is required'),
            'zip_code.regex' => translate('messages.Zip code may contain only letters, numbers, single spaces and hyphens'),
            'zip_code.unique' => translate('messages.This zip code already exists in the selected zone'),
        ];
    }

    public function payload(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'zip_code' => trim((string) $this->input('zip_code')),
            'status' => true,
        ];
    }
}
