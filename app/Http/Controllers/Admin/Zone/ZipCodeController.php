<?php

namespace App\Http\Controllers\Admin\Zone;

use App\Enums\ExportFileNames\Admin\ZipCode as ZipCodeExportFile;
use App\Exports\ZipCodeExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\ZipCodeAddRequest;
use App\Http\Requests\Admin\ZipCodeUpdateRequest;
use App\Services\Zone\ZipCodeService;
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
 * Zip Code Setup.
 *
 * The views are ported from the design source, so this controller supplies exactly the variables
 * they expect — `$zipCodes`, `$zones` — and nothing more. Add and edit share one
 * right-hand off-canvas drawer, which is fetched as rendered HTML rather than being duplicated
 * into the page for every row.
 */
class ZipCodeController extends BaseController
{
    public function __construct(
        protected ZipCodeService $zipCodeService,
        protected ZoneService $zoneService,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $zoneId = $request?->input('zone_id');

        $zipCodes = $this->zipCodeService->getList(
            filters: ['search' => $request?->input('search'), 'zone_id' => $zoneId === 'all' ? null : $zoneId],
            withCount: [],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.zip-code.list', [
            'zipCodes' => $zipCodes,
            'zones' => $this->zoneService->getSelectOptions(),
        ]);
    }

    /** The add drawer, rendered server-side so one partial backs both add and edit. */
    public function create(): JsonResponse
    {
        return response()->json([
            'view' => view('admin-views.zip-code.partials._create', [
                'zones' => $this->zoneService->getSelectOptions(),
                ])->render(),
        ]);
    }

    /** The same drawer, prefilled. */
    public function getUpdateView(string|int $id): JsonResponse
    {
        $zipCode = $this->zipCodeService->find($id);

        if (! $zipCode) {
            return response()->json(['view' => null], 404);
        }

        return response()->json([
            'view' => view('admin-views.zip-code.partials._edit', [
                'zipCode' => $zipCode,
                'zones' => $this->zoneService->getSelectOptions(),
                ])->render(),
        ]);
    }

    public function add(ZipCodeAddRequest $request): JsonResponse|RedirectResponse
    {
        $this->zipCodeService->create($request->payload());


        return $this->drawerResponse($request, translate('Added successfully'));
    }

    public function update(ZipCodeUpdateRequest $request, $id): JsonResponse|RedirectResponse
    {
        $zipCode = $this->zipCodeService->update($id, $request->payload());

        if (! $zipCode) {
            return $this->drawerFailure($request, translate('No data found'));
        }


        return $this->drawerResponse($request, translate('Updated successfully'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->zipCodeService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $rules = $this->zipCodeService->rulesPricingZipCode($request['id']);

        if ($rules !== []) {
            Toastr::error(translate('messages.This zip code is priced by delivery rules. Remove it from those rules before deleting it.') . ' ' . translate('messages.Delivery rules') . ': ' . implode(', ', $rules));

            return back();
        }

        if (! $this->zipCodeService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    /** Export the filtered list. The design puts an Export dropdown on both screens. */
    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $zoneId = $request->input('zone_id');
        $scopedZoneId = $zoneId === 'all' ? null : $zoneId;

        $data = [
            'data' => $this->zipCodeService->getListData(
                filters: ['search' => $request->input('search'), 'zone_id' => $scopedZoneId],
                withCount: [],
            )->values(),
            'search' => $request->input('search'),
            'zone' => $scopedZoneId ? $this->zoneService->getNamesByIds($scopedZoneId) : null,
        ];

        return Excel::download(
            new ZipCodeExport($data),
            $type === 'csv' ? ZipCodeExportFile::EXPORT_CSV : ZipCodeExportFile::EXPORT_XLSX,
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

}
