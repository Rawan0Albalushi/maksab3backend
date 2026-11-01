<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\SurgePrice as SurgePriceExportFile;
use App\Exports\SurgePriceExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\SurgePriceAddRequest;
use App\Http\Requests\Admin\SurgePriceUpdateRequest;
use App\Models\SurgePrice;
use App\Services\System\ModuleService;
use App\Services\Zone\SurgePriceService;
use App\Services\Zone\ZoneService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Surge Price — port doc §9, designs G1–G7.
 *
 * Replaces the per-zone screen that hung off the zone list. That one was reached as
 * `surge-price/{zone_id}` and could only ever show one zone's surges; this is a Delivery
 * Management screen of its own, listing every surge, like Delivery Rule and Free Delivery.
 *
 * The views receive finished values — the module label and tooltip, the rate with its unit, the
 * schedule label and the two or three duration lines. No `Helpers::` call and no query lives in
 * a template here.
 */
class SurgePriceController extends BaseController
{
    public function __construct(
        protected SurgePriceService $surgePriceService,
        protected ZoneService $zoneService,
        protected ModuleService $moduleService,
        protected TranslationRepositoryInterface $translationRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $surges = $this->surgePriceService->getList(
            filters: ['search' => $request?->input('search'), 'zone_id' => $request?->input('zone_id')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        $rows = $surges->getCollection();

        return view('admin-views.surge-price.list', [
            'surges' => $surges,
            'moduleLabels' => $rows->mapWithKeys(fn ($surge) => [$surge->id => $this->moduleLabel($surge)])->all(),
            'rateLabels' => $rows->mapWithKeys(fn ($surge) => [$surge->id => $this->rateLabel($surge)])->all(),
            'scheduleLabels' => $rows->mapWithKeys(fn ($surge) => [$surge->id => $this->scheduleLabel($surge)])->all(),
            'durationLines' => $rows->mapWithKeys(fn ($surge) => [$surge->id => $this->durationLines($surge)])->all(),
        ]);
    }

    public function create(): View
    {
        return view('admin-views.surge-price.create', $this->formData());
    }

    public function add(SurgePriceAddRequest $request): RedirectResponse
    {
        $surge = $this->surgePriceService->create($request->payload());

        $this->translationRepo->addByModel(request: $request, model: $surge, modelPath: SurgePrice::class, attribute: 'surge_price_name');
        $this->translationRepo->addByModel(request: $request, model: $surge, modelPath: SurgePrice::class, attribute: 'customer_note');

        Toastr::success(translate('Added successfully'));

        return redirect()->route('admin.business-settings.zone.surge-price.list');
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $surge = $this->surgePriceService->findForEdit($id);

        if (! $surge) {
            Toastr::error(translate('No data found'));

            return back();
        }

        return view('admin-views.surge-price.edit', $this->formData($surge) + [
            'soloModules' => $this->surgePriceService->soloModules($surge),
            'zoneName' => $surge->zone?->name ?? translate('messages.N/A'),
        ]);
    }

    public function update(SurgePriceUpdateRequest $request, $id): RedirectResponse
    {
        $surge = $this->surgePriceService->update($id, $request->payload());

        if (! $surge) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $this->translationRepo->updateByModel(request: $request, model: $surge, modelPath: SurgePrice::class, attribute: 'surge_price_name');
        $this->translationRepo->updateByModel(request: $request, model: $surge, modelPath: SurgePrice::class, attribute: 'customer_note');

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.zone.surge-price.list');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->surgePriceService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('messages.Surge price status updated'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        if (! $this->surgePriceService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $rows = $this->surgePriceService->getListData(filters: ['search' => $request->input('search')])
            ->values()
            ->map(fn ($surge, $index) => [
                'SL' => $index + 1,
                'Title' => $surge->surge_price_name,
                'Zone' => $surge->zone?->name,
                'Module' => $this->moduleNames($surge)->implode(', '),
                'Price Increase Rate' => $this->rateLabel($surge),
                'Surge Price Schedule' => $this->scheduleLabel($surge),
                'Duration' => implode(' / ', $this->durationLines($surge)),
                'Status' => $surge->status ? 'Active' : 'Inactive',
            ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
        ];

        return Excel::download(
            new SurgePriceExport($data),
            $type === 'csv' ? SurgePriceExportFile::EXPORT_CSV : SurgePriceExportFile::EXPORT_XLSX,
        );
    }

    /** Shared by create and edit so the two forms cannot drift apart. */
    private function formData(?SurgePrice $surge = null): array
    {
        $locales = getWebConfig('language') ?: [];
        $zones = $this->zoneService->getSelectOptions();

        return [
            'surge' => $surge,
            'zones' => $zones,
            // Every CONNECTED-or-not module, still: surge is not keyed on the (zone, module) pair
            // the way delivery rules, free delivery and ETA are, and no design annotation narrows
            // it — so the screen keeps the behaviour the one it replaced had.
            //
            // What IS narrowed is capability. Rental, ride-share and service price their own trips
            // and bookings and never reach DeliveryChargeService, so a surge on them would change
            // nothing; offering them let an admin configure a setting with no effect.
            'modules' => $this->surgeCapableModules(),
            'selectedZoneId' => old('zone_id', $surge?->zone_id ?? $zones->first()->id ?? null),
            'selectedModuleIds' => array_map('intval', (array) old('module_ids', $surge?->module_ids ?? [])),
            'selectedType' => old('duration_type', $surge?->duration_type ?? 'daily'),
            'price' => old('price', $surge?->price),
            'priceType' => old('price_type', $surge?->price_type ?? 'percent'),
            'noteEnabled' => (bool) old('customer_note_status', $surge?->customer_note_status ?? 0),
            'isPermanent' => (bool) old('is_permanent', $surge?->is_permanent ?? 0),
            'ranges' => $this->rangeValues($surge),
            'weekdays' => $this->weekdays(),
            // The hidden field posts these as one comma-separated string, so a failed submit hands
            // `old()` back a string where the model hands back an array.
            'selectedWeekdays' => $this->splitList(old('weekly_days', $surge?->weekly_days ?? [])),
            'customDays' => implode(',', (array) old('custom_days', $surge?->custom_days ?? [])),
            'customTimes' => implode(',', (array) old('custom_times', $surge?->custom_times ?? [])),
            // The three summaries the form prints back — built here because the screen this
            // replaces computed them in `@php` blocks inside the Blade (rule 8).
            'weeklySummary' => $this->weeklySummary($surge),
            'customSummary' => $this->customSummary($surge),
            'scheduleCards' => $this->scheduleCards(),
            'currencySymbol' => Helpers::currency_symbol(),
            // `Helpers::get_language_name()` reads business_settings, so the tab label is built
            // here rather than in the template (rule 8).
            'languages' => collect($locales)->map(fn ($locale) => [
                'code' => $locale,
                'label' => Helpers::get_language_name($locale).'('.strtoupper($locale).')',
            ])->all(),
            'translated' => collect($surge?->translations ?? [])
                ->groupBy('locale')
                ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
                ->all(),
        ];
    }

    /**
     * A comma-separated string or an array, either way as a list of trimmed non-empty values.
     *
     * @return array<int, string>
     */
    private function splitList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items), fn ($item) => $item !== ''));
    }

    /** The modules a surge may apply to — see the note in formData(). */
    private function surgeCapableModules(): \Illuminate\Support\Collection
    {
        $capable = $this->moduleService->surgeCapableModuleIds();

        return $this->moduleService->getSelectOptions()
            ->filter(fn ($module) => in_array((int) $module->id, $capable, true))
            ->values();
    }

    /** The range pickers want their value back in the string form they produce. */
    private function rangeValues(?SurgePrice $surge): array
    {
        $dates = $surge?->start_date && $surge?->end_date
            ? date('d M Y', strtotime($surge->start_date)).' - '.date('d M Y', strtotime($surge->end_date))
            : '';
        // 'g:i A' (12-hour, no leading zero) because that is the format the time-range
        // picker itself writes back on apply — see SURGE_TIME_FORMAT in _duration-scripts.
        $times = $surge?->start_time && $surge?->end_time
            ? date('g:i A', strtotime($surge->start_time)).' - '.date('g:i A', strtotime($surge->end_time))
            : '';

        return [
            'daily_date_range' => old('daily_date_range', $surge?->duration_type === 'daily' ? $dates : ''),
            'daily_time_range' => old('daily_time_range', $surge?->duration_type === 'daily' ? $times : ''),
            'weekly_date_range' => old('weekly_date_range', $surge?->duration_type === 'weekly' ? $dates : ''),
            'weekly_time_range' => old('weekly_time_range', $surge?->duration_type === 'weekly' ? $times : ''),
        ];
    }

    /** "Saturday", "Saturday & Sunday", "Friday, Saturday & Sunday" — the form's own phrasing. */
    private function weeklySummary(?SurgePrice $surge): string
    {
        $days = collect((array) ($surge?->weekly_days ?? []))
            ->map(fn ($day) => $this->surgePriceService->weekdayLabel($day))
            ->values();

        return match (true) {
            $days->isEmpty() => '',
            $days->count() === 1 => $days->first(),
            default => $days->slice(0, -1)->implode(', ').' & '.$days->last(),
        };
    }

    /**
     * What the custom-schedule field shows before its modal is opened: how many days are chosen,
     * the span they cover, and one row per day.
     *
     * @return array{placeholder:string,min:?string,max:?string,rows:array<int, array{date:string,time:string}>}
     */
    private function customSummary(?SurgePrice $surge): array
    {
        $blank = ['placeholder' => translate('Select date & time'), 'min' => null, 'max' => null, 'rows' => []];

        if (! $surge || $surge->duration_type !== 'custom') {
            return $blank;
        }

        $days = collect((array) $surge->custom_days)->map(fn ($day) => strtotime(trim($day)))->filter()->values();
        $times = array_values((array) $surge->custom_times);

        if ($days->isEmpty()) {
            return $blank;
        }

        $sorted = $days->sort()->values();

        return [
            'placeholder' => $days->count().' '.translate($days->count() > 1 ? 'messages.days_selected' : 'messages.day_selected'),
            'min' => date('M d, Y', $sorted->first()),
            'max' => date('M d, Y', $sorted->last()),
            'rows' => $days->map(fn ($day, $index) => [
                'date' => date('D, M d', $day),
                'time' => $this->clockRange($times[$index] ?? ''),
            ])->all(),
        ];
    }

    private function scheduleCards(): array
    {
        return [
            'daily' => translate('messages.Daily_Schedule'),
            'weekly' => translate('messages.Weekly_Schedule'),
            'custom' => translate('messages.Custom_Schedule'),
        ];
    }

    /** Sunday first, matching how the stored `weekly_days` are written. */
    private function weekdays(): array
    {
        return collect(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'])
            ->mapWithKeys(fn ($day) => [$day => $this->surgePriceService->weekdayLabel($day)])
            ->all();
    }

    private function moduleNames(SurgePrice $surge): \Illuminate\Support\Collection
    {
        $ids = array_map('strval', (array) ($surge->module_ids ?? []));

        return $this->moduleService->getSelectOptions()
            ->filter(fn ($module) => in_array((string) $module->id, $ids, true))
            ->pluck('module_name')
            ->values();
    }

    /** "Shop Module" for one; "4 Module" for several, with the names carried as a tooltip (G2). */
    private function moduleLabel(SurgePrice $surge): array
    {
        $names = $this->moduleNames($surge);

        return [
            'text' => $names->count() === 1
                ? $names->first().' '.translate('messages.Module')
                : $names->count().' '.translate('messages.Module'),
            'tooltip' => $names->implode(', '),
            'many' => $names->count() > 1,
        ];
    }

    /** "5.00%" or "5.00 ৳" — the rate carries its own unit (G3). */
    private function rateLabel(SurgePrice $surge): string
    {
        $amount = number_format((float) $surge->price, 2);

        return $surge->price_type === 'percent' ? $amount.'%' : $amount.' '.Helpers::currency_symbol();
    }

    private function scheduleLabel(SurgePrice $surge): string
    {
        return match ($surge->duration_type) {
            'weekly' => translate('messages.Weekly'),
            'custom' => translate('messages.Custom'),
            default => translate('messages.Daily'),
        };
    }

    /**
     * The list's Duration cell — up to three lines (G6): the time range, the date range, and the
     * weekdays for a weekly one.
     *
     * A PERMANENT weekly surge has no date range at all, so that line is dropped rather than
     * printed empty. A custom one keeps its own times per day, so the cell states the span its
     * days cover instead of pretending there is one range.
     *
     * @return string[]
     */
    private function durationLines(SurgePrice $surge): array
    {
        $lines = [];

        if ($surge->duration_type === 'custom') {
            $days = collect((array) $surge->custom_days)->map(fn ($day) => strtotime($day))->filter()->sort()->values();
            $times = (array) $surge->custom_times;

            if ($times) {
                $lines[] = $this->clockRange($times[0]);
            }
            if ($days->isNotEmpty()) {
                $lines[] = $days->count() === 1
                    ? date('d M Y', $days->first())
                    : date('d M Y', $days->first()).' '.translate('messages.to').' '.date('d M Y', $days->last());
            }

            return $lines;
        }

        if ($surge->start_time && $surge->end_time) {
            $lines[] = date('g:i a', strtotime($surge->start_time)).' - '.date('g:i a', strtotime($surge->end_time));
        }

        if ($surge->start_date && $surge->end_date) {
            $lines[] = date('d M Y', strtotime($surge->start_date)).' '.translate('messages.to').' '.date('d M Y', strtotime($surge->end_date));
        }

        if ($surge->duration_type === 'weekly' && $surge->weekly_days) {
            $lines[] = collect($surge->weekly_days)->map(fn ($day) => $this->surgePriceService->weekdayLabel($day))->implode(', ');
        }

        return $lines;
    }

    /** "09:00 - 17:00" printed as "9:00 am - 5:00 pm". */
    private function clockRange(string $range): string
    {
        $parts = preg_split('/\s*-\s*/', trim($range), 2);

        return count($parts) === 2
            ? date('g:i a', strtotime($parts[0])).' - '.date('g:i a', strtotime($parts[1]))
            : $range;
    }
}
