<?php

namespace App\Http\Requests\Admin;

use App\Services\Zone\SurgePriceService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Panel request — redirect-back-with-errors.
 *
 * The form posts the two ranges as single strings ("01 Jun 2026 - 30 Jun 2026", "09:00 - 17:00")
 * because that is what the date/time range pickers produce. They are split here so the controller
 * and the service only ever see four plain columns.
 *
 * @property array surge_price_name
 * @property int zone_id
 * @property array module_ids
 * @property string duration_type
 */
class SurgePriceUpdateRequest extends FormRequest
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
            'surge_price_name' => 'array',
            'surge_price_name.0' => 'required|string|max:191',
            'customer_note' => 'nullable|array',
            // 50, because the design prints a 0/50 counter under the field.
            'customer_note.0' => [
                Rule::requiredIf(fn () => $this->boolean('customer_note_status')),
                'nullable', 'string', 'max:50',
            ],
            'zone_id' => 'required|exists:zones,id',
            'module_ids' => 'required|array|min:1',
            'module_ids.*' => 'required|integer|exists:modules,id',
            'price' => [
                'required', 'numeric', 'min:0',
                Rule::when($this->input('price_type') === 'percent', ['max:100']),
                // A fixed amount had no ceiling, so anything past decimal(10,2) was accepted and
                // SILENTLY rewritten to 99999999.99 by the column.
                Rule::when($this->input('price_type') === 'amount', ['max:99999999.99']),
            ],
            'price_type' => 'required|in:percent,amount',
            'duration_type' => 'required|in:daily,weekly,custom',
            'daily_date_range' => 'required_if:duration_type,daily',
            'daily_time_range' => 'required_if:duration_type,daily',
            'weekly_time_range' => 'required_if:duration_type,weekly',
            'weekly_days' => 'required_if:duration_type,weekly',
            // A permanent weekly surge never ends, so it carries no date range.
            'weekly_date_range' => [Rule::requiredIf(
                fn () => $this->input('duration_type') === 'weekly' && ! $this->boolean('is_permanent'),
            )],
            'custom_days' => 'required_if:duration_type,custom',
            'custom_times' => 'required_if:duration_type,custom',
        ];
    }

    public function messages(): array
    {
        return [
            'surge_price_name.0.required' => translate('messages.Default surge price name required'),
            'price.max' => translate('messages.Maximum price increase rate') . ': ' . ($this->input('price_type') === 'percent'
                ? '100%'
                : \App\CentralLogics\Helpers::format_currency(99999999.99)),
            'customer_note.0.required' => translate('messages.Note for customer is required'),
            'customer_note.0.max' => translate('messages.The note for customer is too long'),
            'zone_id.required' => translate('messages.Please select a zone'),
            'module_ids.required' => translate('messages.Please select at least one module'),
            'daily_date_range.required_if' => translate('messages.Daily date range required'),
            'daily_time_range.required_if' => translate('messages.Daily time range required'),
            'weekly_date_range.required' => translate('messages.Weekly date range required'),
            'weekly_time_range.required_if' => translate('messages.Weekly time range required'),
            'weekly_days.required_if' => translate('messages.Please select at least one weekday'),
            'custom_days.required_if' => translate('messages.Custom days required'),
            'custom_times.required_if' => translate('messages.Custom times required'),
        ];
    }

    /**
     * DESIGN RULE G1 — a surge may share a zone and module with another one, but not a duration.
     *
     * Unlike D1, F1 and E1 this is not a uniqueness test on the pair: a zone can be surged at
     * breakfast and again at dinner. It is a test on TIME, and the message names the surge it
     * collides with and the first date it collides on, so the admin can see what to move.
     */
    public function withValidator(Validator $validator): void
    {
        // Hiding is not enforcing: the picker omits them, this refuses a crafted POST. A surge on
        // a module that never reaches DeliveryChargeService would be stored and never applied.
        $validator->after(function (Validator $validator) {
            $incapable = app(SurgePriceService::class)->surgeIncapableModuleNames(
                (array) $this->input('module_ids', [])
            );

            if ($incapable) {
                $validator->errors()->add(
                    'module_ids',
                    implode(', ', $incapable).' '.translate('messages.cannot have a surge price.'),
                );
            }
        });

        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $clashes = app(SurgePriceService::class)->conflictingWindows($this->payload(), $this->route('id'));

            foreach ($clashes as $clash) {
                $validator->errors()->add('duration_type', $clash);
            }
        });
    }

    /** Shaped here so neither the controller nor the service ever sees a range string. */
    public function payload(): array
    {
        $type = (string) $this->input('duration_type');
        $permanent = $type === 'weekly' && $this->boolean('is_permanent');

        [$startDate, $endDate] = match (true) {
            $type === 'daily' => $this->splitDates($this->input('daily_date_range')),
            $type === 'weekly' && ! $permanent => $this->splitDates($this->input('weekly_date_range')),
            default => [null, null],
        };

        [$startTime, $endTime] = match ($type) {
            'daily' => $this->splitTimes($this->input('daily_time_range')),
            'weekly' => $this->splitTimes($this->input('weekly_time_range')),
            default => [null, null],
        };

        return [
            'name' => $this->input('surge_price_name.0'),
            'customer_note' => $this->input('customer_note.0'),
            'customer_note_status' => $this->boolean('customer_note_status'),
            'zone_id' => (int) $this->input('zone_id'),
            'module_ids' => (array) $this->input('module_ids', []),
            'price' => (float) $this->input('price'),
            'price_type' => (string) $this->input('price_type'),
            'duration_type' => $type,
            'is_permanent' => (int) $permanent,
            'weekly_days' => $this->splitList($this->input('weekly_days')),
            'custom_days' => $this->splitList($this->input('custom_days')),
            'custom_times' => $this->splitList($this->input('custom_times')),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ];
    }

    /** "01 Jun 2026 - 30 Jun 2026" as two Y-m-d strings. */
    private function splitDates(mixed $range): array
    {
        $parts = preg_split('/\s+-\s+/', trim((string) $range), 2);

        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== ''
            ? [date('Y-m-d', strtotime($parts[0])), date('Y-m-d', strtotime($parts[1]))]
            : [null, null];
    }

    /** "09:00 - 17:00" as two H:i:s strings. */
    private function splitTimes(mixed $range): array
    {
        $parts = preg_split('/\s*-\s*/', trim((string) $range), 2);

        return count($parts) === 2 && $parts[0] !== '' && $parts[1] !== ''
            ? [date('H:i:s', strtotime($parts[0])), date('H:i:s', strtotime($parts[1]))]
            : [null, null];
    }

    /** The pickers post these as one comma-separated string; an array is accepted too. */
    private function splitList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), fn ($item) => $item !== ''));
    }
}
