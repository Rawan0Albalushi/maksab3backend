<?php

namespace App\Http\Requests\Admin;

use App\CentralLogics\Helpers;
use App\Services\System\ModuleService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The Connect Module drawer — payment methods, the modules a zone serves, and the COD ceiling
 * for each of them.
 *
 * Delivery pricing is deliberately absent: a zone prices its deliveries through delivery rules,
 * and surge through its own screen. This drawer connects modules and nothing more.
 *
 * @property array|null $module_id
 * @property string|int|null $cash_on_delivery
 * @property string|int|null $digital_payment
 * @property string|int|null $offline_payment
 * @property string|int|null $max_cod_status
 * @property array|null $max_cod_order_amount
 */
class ZoneConnectModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array|string> */
    public function rules(): array
    {
        $rules = [
            'module_id' => 'required|array|min:1',
            'module_id.*' => 'integer|exists:modules,id',
            'max_cod_status' => 'nullable|in:0,1',
        ];

        // Each connected module names its own ceiling, and only while the toggle is on. Off, the
        // inputs are not even rendered, so requiring them would refuse a perfectly valid save.
        // Rental, RideShare and Service are excluded the same way — the picker never renders a
        // row for them (they price and time themselves; nothing in their booking flow reads
        // maximum_cod_order_amount), so requiring the field here would refuse a save the drawer
        // itself never asked for. deliveryRuleModuleIds() is the same delivery_rule capability
        // flag the delivery-rule picker and the zone readiness rule already gate on -- not a
        // second list to keep in step with theirs.
        if ($this->codLimitEnabled()) {
            $codEligibleIds = app(ModuleService::class)->deliveryRuleModuleIds();

            foreach ((array) $this->input('module_id', []) as $moduleId) {
                if (! in_array((int) $moduleId, $codEligibleIds, true)) {
                    continue;
                }

                // gt:0, not min:0. Zero is the STORED encoding for "no ceiling" -- codLimits()
                // writes 0.0 for every module when the toggle is off, and the placement guard
                // treats a falsy ceiling as unlimited. So a zero typed while the toggle is ON
                // saves as "no limit", which is the opposite of what the field says it does
                // ("A COD order above this amount is refused at checkout") and indistinguishable
                // from having left the toggle off. Refused, with the toggle named as the way to
                // say "no limit" on purpose.
                $rules['max_cod_order_amount.'.$moduleId] = 'required|numeric|gt:0';
            }
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'module_id.required' => translate('messages.Please select at least one module'),
            'module_id.min' => translate('messages.Please select at least one module'),
            'max_cod_order_amount.*.required' => translate('messages.Max COD order amount is required for every connected module'),
            'max_cod_order_amount.*.numeric' => translate('messages.Max COD order amount must be a number'),
            'max_cod_order_amount.*.min' => translate('messages.Max COD order amount cannot be negative'),
            'max_cod_order_amount.*.gt' => translate('Max COD order amount must be greater than zero. Switch the max COD order amount toggle off if this zone should have no COD limit.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // The design states it on the panel itself: "Must select at least one payment method."
            if ($this->payments() === []) {
                $validator->errors()->add('payment_method', translate('messages.Please select at least one payment method'));
            }
        });
    }

    /**
     * The three payment flags, already gated by what the platform allows at all.
     *
     * A method switched off in the third-party settings cannot be switched on for a zone, however
     * the form is posted — the checkbox is not even rendered in that case, so a value arriving
     * here for one is either a stale tab or someone editing the request.
     *
     * @return array<string, int> only the methods that are ON, so an empty array means none
     */
    public function payments(): array
    {
        $on = [];

        foreach (self::platformAllows() as $method => $platformAllows) {
            if ($platformAllows && $this->boolean($method)) {
                $on[$method] = 1;
            }
        }

        return $on;
    }

    /**
     * Which payment methods the platform permits at all, from the third-party settings.
     *
     * Static so the controller can render exactly the checkboxes this will accept — one source
     * for what the form offers and what the save allows, rather than two that can drift.
     *
     * @return array<string, bool>
     */
    public static function platformAllows(): array
    {
        return [
            'cash_on_delivery' => data_get(Helpers::get_business_settings('cash_on_delivery'), 'status') == 1,
            'digital_payment' => data_get(Helpers::get_business_settings('digital_payment'), 'status') == 1,
            'offline_payment' => Helpers::get_business_settings('offline_payment_status') == 1,
        ];
    }

    /** All three flags, zeros included — what the zone row is written with. */
    public function paymentColumns(): array
    {
        return array_merge(
            ['cash_on_delivery' => 0, 'digital_payment' => 0, 'offline_payment' => 0],
            $this->payments(),
        );
    }

    public function codLimitEnabled(): bool
    {
        return (string) $this->input('max_cod_status') === '1';
    }

    /** @return array<int, int> */
    public function moduleIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('module_id', []))));
    }

    /**
     * The ceiling per connected module, keyed by module id.
     *
     * With the toggle off every connected module gets 0, which is how the order path already
     * reads "no ceiling" — `PlaceNewOrderTrait` only enforces a limit that is truthy. So
     * switching the toggle off clears the ceilings rather than leaving stale ones enforced.
     *
     * @return array<int, float>
     */
    public function codLimits(): array
    {
        $enabled = $this->codLimitEnabled();
        $given = (array) $this->input('max_cod_order_amount', []);
        $limits = [];

        foreach ($this->moduleIds() as $moduleId) {
            $limits[$moduleId] = $enabled ? (float) ($given[$moduleId] ?? 0) : 0.0;
        }

        return $limits;
    }
}
