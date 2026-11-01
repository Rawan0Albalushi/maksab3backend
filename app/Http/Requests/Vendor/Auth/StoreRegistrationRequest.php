<?php

namespace App\Http\Requests\Vendor\Auth;

use App\Http\Requests\BaseRequest;
use App\Models\BusinessSetting;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRegistrationRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'latitude' => 'required',
            'longitude' => 'required',
            'email' => $this->emailRule('required', 'vendors'),
            'phone' => $this->phoneRule('required', 'vendors'),
            'minimum_delivery_time' => 'required',
            'maximum_delivery_time' => 'required',
            'delivery_time_type' => 'required',
            'password' => $this->passwordRule('required'),
            'zone_id' => 'required',
            'module_id' => 'required',
            'translations' => 'required|array|min:1',
            'translations.*.locale' => 'required|string|max:10',
            'translations.*.key' => 'required|string|max:100',
            'translations.*.value' => 'required|string',
            'logo' => $this->imageRule('required'),
            'cover_photo' => $this->imageRule(),
        ];
    }
    public function messages(): array
    {
        return [
            'translations.required' => translate('messages.Name and description in english is required'),
            'translations.array' => translate('messages.Name and description in english is required'),
            'translations.min' => translate('messages.Name and description in english is required'),
            'password.required' => translate('The password is required'),
        ];
    }
    public function translationRows(): array
    {
        $rows = $this->input('translations');
        $rows = is_array($rows) ? $rows : (json_decode((string) $rows, true) ?? []);

        return collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->map(fn ($row) => [
                'locale' => $row['locale'] ?? null,
                'key' => $row['key'] ?? null,
                'value' => $row['value'] ?? null,
            ])
            ->values()
            ->all();
    }
    protected function prepareForValidation(): void
    {
        $status = BusinessSetting::where('key', 'toggle_store_registration')->first();

        if (isset($status) && $status->value != '0') {
            $this->decodeTranslations();

            return;
        }

        throw new HttpResponseException($this->responseFormatter(
            config('response.forbidden_403'),
            errors: [['code' => 'self-registration', 'message' => translate('messages.Store self registration disabled')]]
        ));
    }
    protected function decodeTranslations(): void
    {
        $this->merge(['translations' => $this->translationRows()]);
    }
}
