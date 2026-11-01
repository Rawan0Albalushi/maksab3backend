<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\Helpers;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Turning a delivery rule on or off.
 *
 * Answers the drawer-style JSON the status dialog reads — `{errors: [{code, message}]}` — rather
 * than Laravel's 422 shape, because the handler in `_status-scripts.blade.php` reads `data.errors`
 * as a list of messages and would otherwise report nothing at all.
 *
 * @property int status
 * @property int|null replacement_id
 */
class DeliveryRuleStatusRequest extends FormRequest
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
            'status' => 'required|boolean',
            // Required only when switching OFF: the design makes the admin nominate the rule that
            // takes over, so the zone is never left without one.
            //
            // Except for the LAST rule of the default zone, where no replacement can exist. That
            // case is refused outright by D2 in the controller — but this rule ran first, so the
            // admin was told "Please select the rule that should take over" beside an empty
            // picker, which names neither the reason nor anything they can do about it.
            'replacement_id' => [
                $this->boolean('status') || $this->isLastDefaultZoneRule() ? 'nullable' : 'required',
                'nullable',
                'integer',
                'exists:delivery_rules,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'replacement_id.required' => translate('messages.Please select the rule that should take over.'),
            'replacement_id.exists' => translate('No data found'),
        ];
    }

    /** D2's own test, so the controller's refusal is the one the admin sees. */
    protected function isLastDefaultZoneRule(): bool
    {
        return app(DeliveryRuleService::class)->isLastRuleOfDefaultZone($this->route('id'));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['errors' => Helpers::error_processor($validator)]),
        );
    }

    public function payload(): array
    {
        return [
            'status' => $this->boolean('status'),
            'replacement_id' => $this->input('replacement_id'),
        ];
    }
}
