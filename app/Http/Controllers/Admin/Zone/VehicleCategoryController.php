<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\VehicleCategory as VehicleCategoryExportFile;
use App\Exports\VehicleCategoryExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\VehicleCategoryAddRequest;
use App\Http\Requests\Admin\VehicleCategoryUpdateRequest;
use App\Models\DMVehicle;
use App\Services\DeliveryMan\DmVehicleService;
use App\Services\System\DistanceService;
use App\Services\System\MeasurementUnitService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Vehicles Category — moved here from Delivery Man on 2026-09-08.
 *
 * It belongs in Delivery Management, not under Users: a category is a delivery SETUP — the
 * coverage band, the weight and the package sizes a vehicle can take — that the dispatch side
 * reads. Its old home under `admin/users/delivery-man/vehicle` put it next to the people rather
 * than next to the rules it is part of. Same shape as its siblings here: a list with an empty
 * state, a full-page form, an export, and a status endpoint of its own.
 *
 * The views receive finished values — the size label, the connected dimension names, both unit
 * suffixes. No `Helpers::` call, no query and no `@php` block lives in these templates.
 */
class VehicleCategoryController extends BaseController
{
    public function __construct(
        protected DmVehicleService $vehicleService,
        protected TranslationRepositoryInterface $translationRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $vehicles = $this->vehicleService->getList(
            filters: ['search' => $request?->input('search')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        $rows = $vehicles->getCollection();

        return view('admin-views.vehicle-category.list', [
            'vehicles' => $vehicles,
            // Keyed by id so each Blade loop is a lookup rather than a second computation.
            'weightLabels' => $rows->mapWithKeys(fn ($vehicle) => [$vehicle->id => $this->weightLabel($vehicle)])->all(),
            'dimensionLabels' => $rows->mapWithKeys(fn ($vehicle) => [$vehicle->id => $this->dimensionLabel($vehicle)])->all(),
            'distanceUnitLabel' => $this->distanceUnitLabel(),
        ]);
    }

    public function create(): View
    {
        return view('admin-views.vehicle-category.create', $this->formData());
    }

    public function add(VehicleCategoryAddRequest $request): RedirectResponse
    {
        $vehicle = $this->vehicleService->create($request->payload());

        $this->translationRepo->addByModel(
            request: $request, model: $vehicle, modelPath: DMVehicle::class, attribute: 'type',
        );

        Toastr::success(translate('Added successfully'));

        return redirect()->route('admin.business-settings.zone.vehicle-category.list');
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $vehicle = $this->vehicleService->find($id, with: ['dimensions:id,name', 'translations']);

        if (! $vehicle) {
            Toastr::error(translate('messages.Vehicle category not found'));

            return back();
        }

        return view('admin-views.vehicle-category.edit', $this->formData($vehicle));
    }

    public function update(VehicleCategoryUpdateRequest $request, $id): RedirectResponse
    {
        $vehicle = $this->vehicleService->update($id, $request->payload());

        if (! $vehicle) {
            Toastr::error(translate('messages.Vehicle category not found'));

            return back();
        }

        $this->translationRepo->updateByModel(
            request: $request, model: $vehicle, modelPath: DMVehicle::class, attribute: 'type',
        );

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.zone.vehicle-category.list');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->vehicleService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('messages.Vehicle category not found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $dependants = $this->vehicleService->dependants($request['id']);

        if ($dependants['delivery_men'] > 0) {
            Toastr::error(translate('Deliverymen are registered with this vehicle category. move them to another category before deleting it.') . ' ' . translate('messages.Deliverymen') . ': ' . $dependants['delivery_men']);

            return back();
        }

        if ($dependants['express_setups'] > 0) {
            Toastr::error(translate('messages.This vehicle category is used by an Additional Delivery Charge express filter. Remove it there before deleting it.'));

            return back();
        }

        if (! $this->vehicleService->delete($request['id'])) {
            Toastr::error(translate('messages.Vehicle category not found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $distanceUnit = $this->distanceUnitLabel();
        $weightUnit = app(MeasurementUnitService::class)->weightUnitLabel();

        $rows = $this->vehicleService->getListData(filters: ['search' => $request->input('search')])
            ->values()
            ->map(fn ($row, $index) => [
                'SL' => $index + 1,
                'Type' => $row->type,
                'Total Delivery Man' => $row->delivery_men_count,
                'Minimum Coverage Area ('.$distanceUnit.')' => $row->starting_coverage_area,
                'Maximum Coverage Area ('.$distanceUnit.')' => $row->maximum_coverage_area,
                'Max Weight ('.$weightUnit.')' => $row->max_weight,
                'Dimension' => $this->dimensionLabel($row),
                'Status' => $row->status ? 'Active' : 'Inactive',
            ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
        ];

        return Excel::download(
            new VehicleCategoryExport($data),
            $type === 'csv' ? VehicleCategoryExportFile::EXPORT_CSV : VehicleCategoryExportFile::EXPORT_XLSX,
        );
    }

    /** What both form views need. */
    private function formData(?DMVehicle $vehicle = null): array
    {
        $locales = getWebConfig('language') ?: [];

        return [
            'vehicle' => $vehicle,
            'dimensions' => $this->vehicleService->dimensionOptions(),
            'selectedDimensionIds' => old('dimension_ids', $vehicle?->dimensions->pluck('id')->all() ?? []),
            // §3.4 — coverage bands and max weight are SETUP values, so both labels follow the
            // configured unit rather than the design's hardcoded "KM" and "kg".
            'distanceUnitLabel' => $this->distanceUnitLabel(),
            'weightUnitLabel' => app(MeasurementUnitService::class)->weightUnitLabel(),
            'languages' => collect($locales)->map(fn ($locale) => [
                'code' => $locale,
                'label' => Helpers::get_language_name($locale).'('.strtoupper($locale).')',
            ])->all(),
            'translated' => collect($vehicle?->translations ?? [])
                ->groupBy('locale')
                ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
                ->all(),
        ];
    }

    /** "1200 kg", exactly as the design prints it — blank when the row predates the field. */
    private function weightLabel(DMVehicle $vehicle): string
    {
        if ($vehicle->max_weight === null) {
            return translate('messages.N/A');
        }

        return rtrim(rtrim(number_format($vehicle->max_weight, 2, '.', ''), '0'), '.')
            .' '.app(MeasurementUnitService::class)->weightUnitLabel();
    }

    /** "Medium, Large" — the connected size classes by name. */
    private function dimensionLabel(DMVehicle $vehicle): string
    {
        $names = $vehicle->dimensions->pluck('name')->filter()->all();

        return $names === [] ? translate('messages.N/A') : implode(', ', $names);
    }

    private function distanceUnitLabel(): string
    {
        return app(DistanceService::class)->unitLabel();
    }
}
