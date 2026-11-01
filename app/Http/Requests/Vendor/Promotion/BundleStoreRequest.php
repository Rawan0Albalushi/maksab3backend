<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\CentralLogics\Helpers;
use App\Models\Bundle;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use App\Rules\ImageFile;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BundleStoreRequest extends FormRequest
{
    private ?Bundle $editing = null;

    private bool $editingResolved = false;

    public function authorize(): bool
    {
        // A vendor editing a bundle it does not own gets the 404 here, before validation runs: the
        // rules check items against the *bundle's* store, so the errors came back answering which
        // items that other store's bundle held. The controller's own 404 came too late. Admin edits
        // any bundle and is not affected.
        if (($bundle = $this->editing()) && ($vendorStoreIds = $this->vendorStoreIds()) !== null) {
            abort_unless(in_array((int) $bundle->store_id, $vendorStoreIds, true), 404);
        }

        return true;
    }

    /** The asking vendor's store ids, or null when the request is not a vendor's. */
    private function vendorStoreIds(): ?array
    {
        if ($vendor = $this->input('vendor')) {
            return $vendor->stores->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            return [(int) Helpers::get_store_id()];
        }

        return null;
    }

    public function rules(): array
    {
        $earliest = $this->earliestAllowedDate();
        $storeId = $this->effectiveStoreId();

        return [
            'name' => 'required|array',
            'name.*' => 'nullable|string|max:80',
            'lang' => 'required|array',
            'description' => 'nullable|array',
            'description.*' => 'nullable|string|max:1000',
            'image' => ImageFile::rules($this->editing() ? 'nullable' : 'required'),
            'store_id' => $this->editing()
                ? ['required', 'integer', Rule::in([$this->editing()->store_id])]
                : ['required', 'integer', 'exists:stores,id'],
            'start_date' => 'required|date|after_or_equal:'.$earliest,
            'end_date' => 'required|date|after:start_date|after_or_equal:'.$earliest,
            'discount_percentage' => 'required|numeric|min:0|max:'.Bundle::MAX_DISCOUNT_PERCENTAGE,
            'items' => 'required|array|min:'.Bundle::MIN_ITEMS.'|max:'.Bundle::MAX_ITEMS,
            'items.*.item_id' => $this->forServiceModule()
                ? ['nullable', 'integer']
                : ['required', 'integer', Rule::exists('items', 'id')->where('store_id', $storeId)],
            'items.*.service_id' => $this->forServiceModule()
                ? ['required', 'integer', Rule::exists('services', 'id')->where('store_id', $storeId)]
                : ['nullable', 'integer'],
        ];
    }

    public function forServiceModule(): bool
    {
        return app(BundleService::class)->isServiceModule($this->effectiveModuleId());
    }

    public function messages(): array
    {
        return [
            'items.min' => translate('messages.This bundle needs more items.').' '.translate('messages.Minimum items').': '.Bundle::MIN_ITEMS,
            'items.required' => translate('messages.Add the items this bundle contains'),
            'items.max' => translate('messages.This bundle has too many items.').' '.translate('messages.Maximum items').': '.Bundle::MAX_ITEMS,
            'end_date.after' => translate('messages.The end date must be later than the start date'),
            'discount_percentage.max' => translate('messages.The discount is too high.').' '.translate('messages.Maximum discount').': '.Bundle::MAX_DISCOUNT_PERCENTAGE.'%',
            'items.*.item_id.exists' => translate('messages.An item was chosen that this store does not sell'),
            'items.*.service_id.exists' => translate('messages.A service was chosen that this provider does not offer'),
            'items.*.service_id.required' => translate('messages.Pick the services this bundle contains'),
            'store_id.in' => $this->forServiceModule()
                ? translate('messages.A bundle stays with the provider it was built for')
                : translate('messages.A bundle stays with the store it was built for'),
            'start_date.after_or_equal' => translate('messages.The bundle cannot start in the past'),
            'end_date.after_or_equal' => translate('messages.The bundle cannot end before it starts or in the past'),
        ];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => Helpers::error_processor($validator)], 403)
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $languages = (array) $this->input('lang', []);
            $names = (array) $this->input('name', []);

            foreach ($languages as $index => $language) {
                if ($language === 'default' && blank($names[$index] ?? null)) {
                    $validator->errors()->add('name.'.$index, translate('messages.The bundle name is required'));
                }
            }
        });
    }

    private function editing(): ?Bundle
    {
        if (! $this->editingResolved) {
            $this->editingResolved = true;
            $this->editing = $this->route('id')
                ? Bundle::withoutGlobalScopes()->find($this->route('id'))
                : null;
        }

        return $this->editing;
    }

    private function effectiveStoreId(): int
    {
        return (int) ($this->editing()?->store_id ?? $this->input('store_id'));
    }

    private function effectiveModuleId(): ?int
    {
        if ($editing = $this->editing()) {
            return (int) $editing->module_id;
        }

        return Store::withoutGlobalScopes()->find($this->effectiveStoreId())?->module_id;
    }

    private function earliestAllowedDate(): string
    {
        $stored = $this->editing()?->start_date;
        $today = now()->format('Y-m-d');

        $stored = $stored ? Carbon::parse($stored)->format('Y-m-d') : null;

        return $stored && $stored < $today ? $stored : $today;
    }
}
