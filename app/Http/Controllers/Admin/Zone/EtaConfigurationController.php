<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\EtaConfiguration as EtaConfigurationExportFile;
use App\Exports\EtaConfigurationExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\EtaConfigurationAddRequest;
use App\Http\Requests\Admin\EtaConfigurationUpdateRequest;
use App\Exceptions\DuplicateEtaConfigurationException;
use App\Models\EtaConfiguration;
use App\Services\System\ModuleService;
use App\Services\Zone\EtaConfigurationService;
use App\Services\Zone\ZoneService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ETA Configuration — per (zone, module), §11.
 *
 * Same shape as Free Delivery Setup, which is deliberate: both are one setup per zone claiming a
 * set of modules, so the list, the picker and the overlap guard read the same on both screens.
 *
 * The views receive finished values — the module column's label and tooltip, the method label,
 * both two-line timing cells, the language tabs with their display names. No `Helpers::` call,
 * no query and no `@php` block lives in a template here.
 */
class EtaConfigurationController extends BaseController
{
    public function __construct(
        protected EtaConfigurationService $etaConfigurationService,
        protected ZoneService $zoneService,
        protected TranslationRepositoryInterface $translationRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $configurations = $this->etaConfigurationService->getList(
            filters: ['search' => $request?->input('search')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        $rows = $configurations->getCollection();

        return view('admin-views.eta-configuration.list', [
            'configurations' => $configurations,
            // Keyed by id so each Blade loop is a lookup rather than a second computation.
            'moduleLabels' => $rows->mapWithKeys(fn ($config) => [$config->id => $this->moduleLabel($config)])->all(),
            'methodLabels' => $rows->mapWithKeys(fn ($config) => [$config->id => $config->methodLabel()])->all(),
            'timeDurations' => $rows->mapWithKeys(fn ($config) => [$config->id => $this->timeDuration($config)])->all(),
            'bufferTimes' => $rows->mapWithKeys(fn ($config) => [$config->id => $this->bufferTime($config)])->all(),
            // Which rows may not be switched off, and the modules that is true for. Computed for
            // the whole page in one query so the row can refuse before the round trip rather than
            // after it.
            'lockedModules' => $this->etaConfigurationService->lockedModulesFor($rows),
        ]);
    }

    public function create(): View
    {
        return view('admin-views.eta-configuration.create', $this->formData());
    }

    public function add(EtaConfigurationAddRequest $request): RedirectResponse
    {
        try {
            $configuration = $this->etaConfigurationService->create($request->payload());
        } catch (DuplicateEtaConfigurationException $e) {
            // The request already checks E1; this is the same rule re-checked under a lock, and
            // only a save that raced another one for the same pair arrives here. Reported the way
            // the validator would have, so the admin sees one behaviour rather than two.
            Toastr::error($e->getMessage());

            return back()->withInput();
        }

        $this->translationRepo->addByModel(
            request: $request, model: $configuration, modelPath: EtaConfiguration::class, attribute: 'name',
        );

        Toastr::success(translate('Added successfully'));

        return redirect()->route('admin.business-settings.zone.eta-configuration.list');
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $configuration = $this->etaConfigurationService->findForEdit($id);

        if (! $configuration) {
            Toastr::error(translate('No data found'));

            return back();
        }

        return view('admin-views.eta-configuration.edit', $this->formData($configuration) + [
            // S19 — dropping a module here does not merely remove its estimate, it takes the
            // module out of the zone altogether. Named before the save, not discovered after it.
            'soloModules' => $this->etaConfigurationService->soloModules($configuration),
            'zoneName' => $configuration->zone?->name ?? translate('messages.N/A'),
        ]);
    }

    public function update(EtaConfigurationUpdateRequest $request, $id): RedirectResponse
    {
        $configuration = $this->etaConfigurationService->update($id, $request->payload());

        if (! $configuration) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $this->translationRepo->updateByModel(
            request: $request, model: $configuration, modelPath: EtaConfiguration::class, attribute: 'name',
        );

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.zone.eta-configuration.list');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $request->boolean('status')) {
            $uncovered = $this->etaConfigurationService->modulesLeftWithoutEta($request['id']);

            if ($uncovered !== []) {
                Toastr::error(translate('messages.This is the only ETA configuration for these modules in this zone, so it cannot be switched off. Delete it instead, or disconnect the modules from the zone.') . ' ' . translate('messages.Modules') . ': ' . implode(', ', $uncovered));

                return back();
            }
        }

        if (! $this->etaConfigurationService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        // Same guard as the toggle (updateStatus() above) — a delete that removes the only
        // active ETA for a module has the same effect as switching it off, and the toggle
        // already refuses that. Leaving delete unguarded let a zone silently lose coverage the
        // toggle would have blocked (TC_162/TC_406/TC_405).
        $uncovered = $this->etaConfigurationService->modulesLeftWithoutEta($request['id']);

        if ($uncovered !== []) {
            Toastr::error(translate('messages.This is the only ETA configuration for these modules in this zone, so it cannot be deleted. Disconnect the modules from the zone instead, or add a replacement ETA configuration first.') . ' ' . translate('messages.Modules') . ': ' . implode(', ', $uncovered));

            return back();
        }

        if (! $this->etaConfigurationService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    /** The modules still unconfigured in a zone, for the form's picker when the zone changes. */
    public function getModules(string|int $zoneId, Request $request): JsonResponse
    {
        $picker = $this->etaConfigurationService->modulePickerForZone($zoneId, $request->input('configuration_id'));

        return response()->json([
            'modules' => $picker['modules']->map(fn ($m) => ['id' => $m->id, 'name' => $m->module_name, 'type' => $m->module_type])->values(),
            'modules_empty_message' => $this->moduleEmptyMessage($picker['state']),
        ]);
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $unit = translate('ETA minute unit');

        $rows = $this->etaConfigurationService->getListData(filters: ['search' => $request->input('search')])
            ->values()
            ->map(fn ($config, $index) => [
                'SL' => $index + 1,
                'Name' => $config->name,
                'Zone' => $config->zone?->name,
                'Module' => $config->modules->pluck('module_name')->implode(', '),
                'ETA Type' => $config->methodLabel(),
                'Minimum Delivery Time' => (int) $config->minimum_delivery_time.' '.$unit,
                // Blank rather than zero for the method that keeps no gap — a "0 min" gap would
                // read as a configured choice instead of one this method never had.
                'Gap Between Min & Max Time' => $config->usesTimeGap() ? (int) $config->time_gap.' '.$unit : '',
                'Preparation Buffer' => (int) $config->preparation_buffer.' '.$unit,
                'Transit Buffer' => (int) $config->transit_buffer.' '.$unit,
                'Status' => $config->status ? 'Active' : 'Inactive',
            ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
        ];

        return Excel::download(
            new EtaConfigurationExport($data),
            $type === 'csv' ? EtaConfigurationExportFile::EXPORT_CSV : EtaConfigurationExportFile::EXPORT_XLSX,
        );
    }

    /** Shared by create and edit so the two forms cannot drift apart. */
    private function formData(?EtaConfiguration $configuration = null): array
    {
        $zones = $this->zoneService->getSelectOptions();
        $selectedZoneId = old('zone_id', $configuration?->zone_id ?? request()->input('zone_id') ?? $zones->first()->id ?? null);
        $picker = $this->etaConfigurationService->modulePickerForZone($selectedZoneId, $configuration?->id);
        // Not getWebConfig('language') — that list is append-only and keeps a language's tab
        // showing after it's disabled (same bug fixed for Zone/Area). This form gets the same fix.
        $locales = Helpers::active_extra_languages();

        return [
            'configuration' => $configuration,
            'zones' => $zones,
            'modules' => $picker['modules'],
            'modulesEmptyMessage' => $this->moduleEmptyMessage($picker['state']),
            'selectedZoneId' => $selectedZoneId,
            'selectedModuleIds' => array_map('intval', (array) old(
                'module_ids',
                $configuration ? $configuration->modules->pluck('id')->all() : (array) request()->input('module_ids', []),
            )),
            'selectedMethod' => old('calculation_method', $configuration?->calculation_method ?? EtaConfiguration::METHOD_DISTANCE),
            'timings' => $this->timingFields($configuration),
            // The template asks "is the gap field shown?", not "which constant is this?" —
            // decided here so no model constant is read from a Blade (rule 8).
            'isDistanceMethod' => old('calculation_method', $configuration?->calculation_method ?? EtaConfiguration::METHOD_DISTANCE) === EtaConfiguration::METHOD_DISTANCE,
            // Two across while the gap field is there to sit beside them, three across once the
            // fixed method drops it — the design's own two layouts, decided here so the template
            // renders rather than branches. The form-script swaps the class as the method changes.
            'timingColumnClass' => $this->timingColumnClass(
                old('calculation_method', $configuration?->calculation_method ?? EtaConfiguration::METHOD_DISTANCE),
            ),
            // A new configuration opens with the default already in the field, so the range it
            // will produce is visible before saving rather than discovered afterwards. An EXISTING
            // one shows whatever it holds — overriding that would rewrite the admin's own value.
            'timeGap' => old('time_gap', $configuration?->time_gap ?? EtaConfiguration::DEFAULT_TIME_GAP),
            'methodCards' => $this->methodCards(),
            // The Parcel section shows only when Parcel is among the picked modules — decided
            // here, from the same ids the module picker already resolved, rather than the JS
            // guessing a name from a label it cannot rely on being spelled the same way twice.
            'parcelModuleIds' => app(ModuleService::class)->moduleIdsOfType('parcel'),
            'parcelTimings' => $this->parcelTimingFields($configuration),
            'parcelTimeGap' => old('parcel_time_gap', $configuration?->parcel_time_gap ?? EtaConfiguration::DEFAULT_TIME_GAP),
            // `Helpers::get_language_name()` hits `business_settings`, so the tab label is built
            // here rather than in the template (rule 8).
            'languages' => collect($locales)->map(fn ($locale) => [
                'code' => $locale,
                'label' => Helpers::get_language_name($locale).'('.strtoupper($locale).')',
            ])->all(),
            'translated' => collect($configuration?->translations ?? [])
                ->groupBy('locale')
                ->map(fn ($translations) => $translations->pluck('value', 'key')->all())
                ->all(),
            'mapSetupUrl' => route('admin.business-settings.third-party.config-setup'),
        ];
    }

    /**
     * The three timings both methods share, in the design's own order.
     *
     * They are described here rather than repeated three times in the template, which is also
     * what lets the form lay them out two-across or three-across without duplicating the markup.
     */
    private function timingFields(?EtaConfiguration $configuration): array
    {
        return [
            [
                'name' => 'minimum_delivery_time',
                'label' => translate('Minimum delivery time'),
                'tooltip' => translate('messages.The shortest estimate this configuration may produce. It is a floor, not an added amount: the buffers and the travel time are totalled first, and only a total below this is lifted to it.'),
                'value' => old('minimum_delivery_time', $configuration?->minimum_delivery_time),
            ],
            [
                'name' => 'preparation_buffer',
                'label' => translate('Preparation buffer'),
                // Only Food has a kitchen for this to describe (processing-time fix, 2026-09-14) —
                // EtaService ignores it for every other module's estimate, even when this same
                // configuration also covers Grocery, Pharmacy or another module alongside Food.
                'tooltip' => translate('Additional time for order preparation before dispatch. Only applied to food orders — ignored for every other module this configuration covers.'),
                'value' => old('preparation_buffer', $configuration?->preparation_buffer),
            ],
            [
                'name' => 'transit_buffer',
                'label' => translate('Transit buffer'),
                'tooltip' => translate('messages.Additional time added for traffic, rider allocation, or unexpected delays.'),
                'value' => old('transit_buffer', $configuration?->transit_buffer),
            ],
        ];
    }

    /**
     * Parcel's own two timings — no preparation buffer (no kitchen) and no method choice
     * (always distance-based), so this is shorter than timingFields() rather than a variant of it.
     */
    private function parcelTimingFields(?EtaConfiguration $configuration): array
    {
        return [
            [
                'name' => 'parcel_minimum_delivery_time',
                'label' => translate('Minimum delivery time'),
                'tooltip' => translate('messages.The shortest estimate this configuration may produce. It is a floor, not an added amount: the transit buffer and the travel time are totalled first, and only a total below this is lifted to it.'),
                'value' => old('parcel_minimum_delivery_time', $configuration?->parcel_minimum_delivery_time),
            ],
            [
                'name' => 'parcel_transit_buffer',
                'label' => translate('Transit buffer'),
                'tooltip' => translate('messages.Additional time added for traffic, rider allocation, or unexpected delays.'),
                'value' => old('parcel_transit_buffer', $configuration?->parcel_transit_buffer),
            ],
        ];
    }

    private function timingColumnClass(string $method): string
    {
        return $method === EtaConfiguration::METHOD_DISTANCE ? 'col-md-6' : 'col-md-4';
    }

    /** The design's two radio cards, each with its description printed under the title. */
    private function methodCards(): array
    {
        return [
            EtaConfiguration::METHOD_DISTANCE => [
                'title' => translate('Distance based'),
                'hint' => translate('messages.Calculate the ETA using the travel time from Google Maps, then add the configured preparation and transit buffer times.'),
            ],
            EtaConfiguration::METHOD_FIXED => [
                'title' => translate('Fixed delivery time'),
                'hint' => translate("messages.Calculate the ETA using the store's configured delivery time range, then add the configured preparation and transit buffer times."),
            ],
        ];
    }

    private function moduleEmptyMessage(string $state): string
    {
        return match ($state) {
            'none_connected' => translate('No module is connected to this zone yet. Connect one from zone setup.'),
            default => translate('messages.All module combinations for this zone have already been set up.'),
        };
    }

    /** "Shop Module" for one; "4 Module" for several, with the names carried as a tooltip. */
    private function moduleLabel(EtaConfiguration $configuration): array
    {
        $names = $configuration->modules->pluck('module_name');

        return [
            'text' => $names->count() === 1
                ? $names->first().' '.translate('messages.Module')
                : $names->count().' '.translate('messages.Module'),
            'tooltip' => $names->implode(', '),
            'many' => $names->count() > 1,
        ];
    }

    /**
     * The list's Time Duration cell.
     *
     * The mock labels these two lines "Base Time" and "Time per km", which is copy from an
     * earlier draft — the form has no per-km field at all. They name the two timings the column
     * actually carries, and the gap line is dropped for the method that keeps no gap.
     */
    private function timeDuration(EtaConfiguration $configuration): array
    {
        $unit = translate('ETA minute unit');

        $lines = [[
            'label' => translate('Minimum delivery time'),
            'value' => (int) $configuration->minimum_delivery_time.' '.$unit,
        ]];

        if ($configuration->usesTimeGap()) {
            $lines[] = [
                'label' => translate('ETA range gap'),
                'value' => (int) $configuration->time_gap.' '.$unit,
            ];
        }

        return $lines;
    }

    /** The list's Buffer Time cell — both buffers apply whichever method is chosen. */
    private function bufferTime(EtaConfiguration $configuration): array
    {
        $unit = translate('ETA minute unit');

        return [
            [
                'label' => translate('Preparation buffer'),
                'value' => (int) $configuration->preparation_buffer.' '.$unit,
            ],
            [
                'label' => translate('Transit buffer'),
                'value' => (int) $configuration->transit_buffer.' '.$unit,
            ],
        ];
    }
}
