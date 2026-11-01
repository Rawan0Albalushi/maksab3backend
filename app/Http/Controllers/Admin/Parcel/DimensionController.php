<?php

namespace App\Http\Controllers\Admin\Parcel;

use App\CentralLogics\Helpers;
use App\Services\System\MeasurementUnitService;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Dimension as DimensionExportFile;
use App\Exports\DimensionExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\DimensionAddRequest;
use App\Http\Requests\Admin\DimensionUpdateRequest;
use App\Models\Dimension as DimensionModel;
use App\Services\Parcel\DimensionService;
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
 * Dimension Setup — the parcel tier's package size classes.
 *
 * Same shape as Weight Setup and Area Setup: a list with an empty state, one off-canvas drawer
 * backing both add and edit.
 */
class DimensionController extends BaseController
{
    public function __construct(
        protected DimensionService $dimensionService,
        protected TranslationRepositoryInterface $translationRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $dimensions = $this->dimensionService->getList(
            filters: ['search' => $request?->input('search')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.dimension.list', [
            'dimensions' => $dimensions,
            // "100 × 50 × 80 in", exactly as the delivery-rule design prints it. Built here so
            // the Blade renders a string rather than assembling one.
            'sizeLabels' => $dimensions->getCollection()->mapWithKeys(
                fn ($dimension) => [$dimension->id => $dimension->size_label],
            )->all(),
        ]);
    }

    public function create(): JsonResponse
    {
        return response()->json([
            'view' => view('admin-views.dimension.partials._create', $this->drawerData())->render(),
        ]);
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        $dimension = $this->dimensionService->find($id, with: ['translations']);

        if (! $dimension) {
            return response()->json(['view' => null], 404);
        }

        return response()->json([
            'view' => view('admin-views.dimension.partials._edit', $this->drawerData($dimension))->render(),
        ]);
    }

    public function add(DimensionAddRequest $request): JsonResponse|RedirectResponse
    {
        $dimension = $this->dimensionService->create($request->payload());

        $this->translationRepo->addByModel(request: $request, model: $dimension, modelPath: DimensionModel::class, attribute: 'name');

        return $this->drawerResponse($request, translate('Added successfully'));
    }

    public function update(DimensionUpdateRequest $request, $id): JsonResponse|RedirectResponse
    {
        $dimension = $this->dimensionService->update($id, $request->payload());

        if (! $dimension) {
            return $this->drawerFailure($request, translate('No data found'));
        }

        $this->translationRepo->updateByModel(request: $request, model: $dimension, modelPath: DimensionModel::class, attribute: 'name');

        return $this->drawerResponse($request, translate('Updated successfully'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->dimensionService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $rules = $this->dimensionService->rulesPricingDimension($request['id']);

        if ($rules !== []) {
            Toastr::error(translate('messages.This dimension is priced by delivery rules. Remove it from those rules before deleting it.') . ' ' . translate('messages.Delivery rules') . ': ' . implode(', ', $rules));

            return back();
        }

        if (! $this->dimensionService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $rows = $this->dimensionService->getListData(filters: ['search' => $request->input('search')])
            ->values()
            ->map(fn ($row, $index) => [
                'SL' => $index + 1,
                'Dimension Name' => $row->name,
                'Max Length (in)' => $row->max_length,
                'Max Width (in)' => $row->max_width,
                'Max Height (in)' => $row->max_height,
                'Status' => $row->status ? 'Active' : 'Inactive',
            ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
        ];

        return Excel::download(
            new DimensionExport($data),
            $type === 'csv' ? DimensionExportFile::EXPORT_CSV : DimensionExportFile::EXPORT_XLSX,
        );
    }

    /** What both drawer partials need — see WeightController::drawerData() for why. */
    private function drawerData(?DimensionModel $dimension = null): array
    {
        $locales = getWebConfig('language') ?: [];

        return [
            'dimension' => $dimension,
            // Decision 4 — the suffix follows `dimension_unit`.
            'dimensionUnitLabel' => app(MeasurementUnitService::class)->dimensionUnitLabel(),
            'languages' => collect($locales)->map(fn ($locale) => [
                'code' => $locale,
                'label' => Helpers::get_language_name($locale).'('.strtoupper($locale).')',
            ])->all(),
            'translated' => collect($dimension?->translations ?? [])
                ->groupBy('locale')
                ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
                ->all(),
        ];
    }

    /**
     * Answer an off-canvas drawer submit.
     *
     * The drawer posts by AJAX and its handler reads `data.success`, then reloads. Returning a
     * redirect instead meant jQuery FOLLOWED it, rendering the page in the background and
     * CONSUMING the flashed Toastr message — so the toast came up empty and the real message was
     * already gone by the time the reload happened.
     *
     * A non-AJAX post still gets the flash-and-redirect the architecture contract asks of panel
     * forms, so the screen keeps working without JavaScript.
     */
    private function drawerResponse(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->ajax()) {
            return response()->json(['success' => $message]);
        }

        Toastr::success($message);

        return back();
    }

    /** As drawerResponse(), for the failure side. */
    private function drawerFailure(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->ajax()) {
            return response()->json(['errors' => [['code' => 'id', 'message' => $message]]]);
        }

        Toastr::error($message);

        return back();
    }

}
