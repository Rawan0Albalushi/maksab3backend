<?php

namespace App\Http\Controllers\Admin\Parcel;

use App\CentralLogics\Helpers;
use App\Services\System\MeasurementUnitService;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Weight as WeightExportFile;
use App\Exports\WeightExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\WeightAddRequest;
use App\Http\Requests\Admin\WeightUpdateRequest;
use App\Models\Weight as WeightModel;
use App\Services\Parcel\WeightService;
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
 * Weight Setup — the parcel tier's weight bands.
 *
 * Same shape as Area Setup: a list with an empty state, and one right-hand off-canvas drawer that
 * backs both add and edit, fetched as rendered HTML rather than duplicated per row.
 *
 * Everything the Blades render arrives finished — the language list with its display names, the
 * band labels, the translation map for the edit drawer. No `Helpers::` call and no `@php` block
 * lives in a template here.
 */
class WeightController extends BaseController
{
    public function __construct(
        protected WeightService $weightService,
        protected TranslationRepositoryInterface $translationRepo,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $weights = $this->weightService->getList(
            filters: ['search' => $request?->input('search')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.weight.list', [
            'weights' => $weights,
            // The list prints "0.00 - 2.00 KG" per row; formatting it here keeps the Blade to
            // rendering. Keyed by id so the loop is a lookup, not a second computation.
            'bandLabels' => $weights->getCollection()->mapWithKeys(
                fn ($weight) => [$weight->id => $weight->band_label],
            )->all(),
        ]);
    }

    /** The add drawer, rendered server-side so one partial backs both add and edit. */
    public function create(): JsonResponse
    {
        return response()->json([
            'view' => view('admin-views.weight.partials._create', $this->drawerData())->render(),
        ]);
    }

    /** The same drawer, prefilled. */
    public function getUpdateView(string|int $id): JsonResponse
    {
        $weight = $this->weightService->find($id, with: ['translations']);

        if (! $weight) {
            return response()->json(['view' => null], 404);
        }

        return response()->json([
            'view' => view('admin-views.weight.partials._edit', $this->drawerData($weight))->render(),
        ]);
    }

    public function add(WeightAddRequest $request): JsonResponse|RedirectResponse
    {
        $weight = $this->weightService->create($request->payload());

        $this->translationRepo->addByModel(request: $request, model: $weight, modelPath: WeightModel::class, attribute: 'name');

        return $this->drawerResponse($request, translate('Added successfully'));
    }

    public function update(WeightUpdateRequest $request, $id): JsonResponse|RedirectResponse
    {
        $weight = $this->weightService->update($id, $request->payload());

        if (! $weight) {
            return $this->drawerFailure($request, translate('No data found'));
        }

        $this->translationRepo->updateByModel(request: $request, model: $weight, modelPath: WeightModel::class, attribute: 'name');

        return $this->drawerResponse($request, translate('Updated successfully'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->weightService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $rules = $this->weightService->rulesPricingWeight($request['id']);

        if ($rules !== []) {
            Toastr::error(translate('messages.This weight class is priced by delivery rules. Remove it from those rules before deleting it.') . ' ' . translate('messages.Delivery rules') . ': ' . implode(', ', $rules));

            return back();
        }

        if (! $this->weightService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    /** Export the filtered list. The design puts an Export dropdown on every setup screen. */
    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $rows = $this->weightService->getListData(filters: ['search' => $request->input('search')])
            ->values()
            ->map(fn ($row, $index) => [
                'SL' => $index + 1,
                'Weight Name' => $row->name,
                'From (KG)' => $row->from_weight,
                'To (KG)' => $row->to_weight,
                'Status' => $row->status ? 'Active' : 'Inactive',
            ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
        ];

        return Excel::download(
            new WeightExport($data),
            $type === 'csv' ? WeightExportFile::EXPORT_CSV : WeightExportFile::EXPORT_XLSX,
        );
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

    /**
     * What both drawer partials need.
     *
     * `languages` carries the tab label already built, because `Helpers::get_language_name()` hits
     * `business_settings` and a Blade is not where that belongs (rule 8). `translated` is the
     * per-locale map the edit form prefills from — flat, so the template only indexes it.
     */
    private function drawerData(?WeightModel $weight = null): array
    {
        $locales = getWebConfig('language') ?: [];

        return [
            'weight' => $weight,
            // Decision 4 — the suffix follows `weight_unit`. The band numbers are SETUP values
            // and are never converted; only what they are read as changes.
            'weightUnitLabel' => app(MeasurementUnitService::class)->weightUnitLabel(),
            'languages' => collect($locales)->map(fn ($locale) => [
                'code' => $locale,
                'label' => Helpers::get_language_name($locale).'('.strtoupper($locale).')',
            ])->all(),
            'translated' => collect($weight?->translations ?? [])
                ->groupBy('locale')
                ->map(fn ($rows) => $rows->pluck('value', 'key')->all())
                ->all(),
        ];
    }

}
