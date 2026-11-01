<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Enums\ExportFileNames\Admin\AdditionalDeliveryCharge as AdditionalDeliveryChargeExportFile;
use App\Exceptions\DuplicateAdditionalDeliveryChargeException;
use App\Exports\AdditionalDeliveryChargeExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\AdditionalDeliveryChargeAddRequest;
use App\Http\Requests\Admin\AdditionalDeliveryChargeUpdateRequest;
use App\Models\AdditionalDeliveryCharge;
use App\Models\DMVehicle;
use App\Services\Zone\AdditionalDeliveryChargeService;
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
 * Additional Delivery Charge — the express and slightly-delayed offers, per (zone, module).
 *
 * The views receive finished values: the module column's label and tooltip, the offer summaries,
 * the picker's contents and its empty-state message. No `Helpers::` call and no query in a
 * template (rule 8).
 */
class AdditionalDeliveryChargeController extends BaseController
{
    public function __construct(
        protected AdditionalDeliveryChargeService $chargeService,
        protected ZoneService $zoneService,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $setups = $this->chargeService->getList(
            filters: ['search' => $request?->input('search'), 'zone_id' => $request?->input('zone_id')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.additional-delivery-charge.list', [
            'setups' => $setups,
            'zones' => $this->zoneService->getSelectOptions(),
            'moduleLabels' => $setups->getCollection()->mapWithKeys(fn ($setup) => [
                $setup->id => $this->moduleLabel($setup),
            ])->all(),
            'currencySymbol' => Helpers::currency_symbol(),
        ]);
    }

    public function create(): View
    {
        return view('admin-views.additional-delivery-charge.create', $this->formData());
    }

    public function add(AdditionalDeliveryChargeAddRequest $request): RedirectResponse
    {
        if ($failure = $this->rejects($request->payload())) {
            return $failure;
        }

        try {
            $this->chargeService->create($request->payload());
        } catch (DuplicateAdditionalDeliveryChargeException $e) {
            // rejects() checked this a moment ago; only a save that raced another one for the
            // same pair reaches here. Reported the way the ordinary clash is, so the admin sees
            // one behaviour rather than two.
            Toastr::error($e->getMessage());

            return back()->withInput();
        }

        Toastr::success(translate('Added successfully'));

        return redirect()->route('admin.business-settings.zone.additional-delivery-charge.list');
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $setup = $this->chargeService->find($id);

        if (! $setup) {
            Toastr::error(translate('messages.Additional_delivery_charge_not_found'));

            return back();
        }

        return view('admin-views.additional-delivery-charge.edit', $this->formData($setup) + [
            'setup' => $setup,
            'soloModules' => $this->chargeService->soloModules($setup),
            'zoneName' => $setup->zone?->name ?? translate('messages.N/A'),
        ]);
    }

    public function update(AdditionalDeliveryChargeUpdateRequest $request, $id): RedirectResponse
    {
        if ($failure = $this->rejects($request->payload(), $id)) {
            return $failure;
        }

        if (! $this->chargeService->update($id, $request->payload())) {
            Toastr::error(translate('messages.Additional_delivery_charge_not_found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.zone.additional-delivery-charge.list');
    }

    public function updateStatus(string|int $id, string|int $status): RedirectResponse
    {
        if (! $this->chargeService->updateStatus($id, $status)) {
            Toastr::error(translate('messages.Additional_delivery_charge_not_found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(string|int $id): RedirectResponse
    {
        if (! $this->chargeService->delete($id)) {
            Toastr::error(translate('messages.Additional_delivery_charge_not_found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    /** The modules still free in a zone, for the form's picker when the zone changes. */
    public function getModules(string|int $zoneId, Request $request): JsonResponse
    {
        $picker = $this->chargeService->modulePickerForZone($zoneId, $request->input('setup_id'));

        return response()->json([
            'modules' => $picker['modules']->map(fn ($m) => ['id' => $m->id, 'name' => $m->module_name, 'type' => $m->module_type])->values(),
            'modules_empty_message' => $this->moduleEmptyMessage($picker['state']),
        ]);
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $zoneId = $request->input('zone_id');
        $scopedZoneId = $zoneId === 'all' ? null : $zoneId;

        $rows = $this->chargeService->getListData(filters: [
            'search' => $request->input('search'), 'zone_id' => $request->input('zone_id'),
        ])->values()->map(fn ($setup, $index) => [
            'SL' => $index + 1,
            'Zone' => $setup->zone?->name,
            'Module' => $setup->modules->pluck('module_name')->implode(', '),
            'Express Extra Charge' => $setup->express_extra_charge,
            'Express Reduce Delivery Time (min)' => $setup->express_reduce_delivery_time,
            'Delay Reduce Charge' => $setup->delay_reduce_charge,
            'Delay Add Delivery Time (min)' => $setup->delay_add_delivery_time,
            'Status' => $setup->status ? 'Active' : 'Inactive',
        ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
            'zone' => $scopedZoneId ? $this->zoneService->getNamesByIds($scopedZoneId) : null,
        ];

        return Excel::download(
            new AdditionalDeliveryChargeExport($data),
            $type === 'csv' ? AdditionalDeliveryChargeExportFile::EXPORT_CSV : AdditionalDeliveryChargeExportFile::EXPORT_XLSX,
        );
    }

    /**
     * The two guards a FormRequest cannot run, because both need a query (rule 2).
     *
     * Order matters: the overlap is what the admin most likely hit, and reporting a floor
     * problem on a combination they are not allowed to save anyway would send them to fix the
     * wrong field.
     */
    private function rejects(array $payload, mixed $exceptId = null): ?RedirectResponse
    {
        $clashing = $this->chargeService->conflictingModuleNames($payload['zone_id'], $payload['module_ids'], $exceptId);

        if ($clashing !== []) {
            // Reads as a sentence, and the same shape the delivery-rule and ETA screens use:
            // "... already exists in this zone for Food, Grocery". The old key ended on "and:",
            // which left the module list dangling off a conjunction.
            Toastr::error(translate('An additional delivery charge already exists in this zone for').' '.implode(', ', $clashing));

            return back()->withInput();
        }

        $unconnected = $this->chargeService->unconnectedModuleNames($payload['zone_id'], $payload['module_ids']);

        if ($unconnected !== []) {
            Toastr::error(translate('messages.This zone is not connected to').': '.implode(', ', $unconnected));

            return back()->withInput();
        }

        $errors = $this->chargeService->setupErrors($payload, $payload['zone_id']);

        if ($errors !== []) {
            Toastr::error($this->setupErrorMessage($errors));

            return back()->withInput();
        }

        return null;
    }

    private function setupErrorMessage(array $errors): string
    {
        $moduleId = array_key_first($errors);
        $name = app(\App\Services\System\ModuleService::class)->find($moduleId)?->module_name ?? translate('messages.N/A');

        return match ($errors[$moduleId]) {
            'express_required' => translate('messages.Express_delivery_requires_an_extra_charge_and_a_reduced_delivery_time_for_module'),
            'min_time_lt_reduce_time' => translate('messages.The_reduced_delivery_time_cannot_be_longer_than_the_minimum_delivery_time_for_module'),
            'reduce_charge_exceeds_minimum' => translate('messages.The_reduced_charge_cannot_be_greater_than_the_minimum_delivery_charge_of_this_zone_for_module'),
            default => translate('messages.Slightly_delayed_delivery_requires_a_reduced_charge_and_an_added_delivery_time_for_module'),
        }.': '.$name;
    }

    /** Shared by create and edit so the two forms cannot drift apart. */
    private function formData(?AdditionalDeliveryCharge $setup = null): array
    {
        $zones = $this->zoneService->getSelectOptions();
        $selectedZoneId = old('zone_id', $setup?->zone_id ?? request()->input('zone_id') ?? $zones->first()->id ?? null);
        $picker = $this->chargeService->modulePickerForZone($selectedZoneId, $setup?->id);

        $expressTime = AdditionalDeliveryCharge::minutesToPair($setup?->express_reduce_delivery_time);
        $delayTime = AdditionalDeliveryCharge::minutesToPair($setup?->delay_add_delivery_time);

        return [
            'setup' => $setup,
            'zones' => $zones,
            'modules' => $picker['modules'],
            'modulesEmptyMessage' => $this->moduleEmptyMessage($picker['state']),
            'selectedZoneId' => $selectedZoneId,
            'selectedModuleIds' => array_map('intval', (array) old(
                'module_ids',
                $setup ? $setup->modules->pluck('id')->all() : (array) request()->input('module_ids', []),
            )),
            // The vehicle categories are rows an admin maintains, so the checkboxes are whatever
            // is on the platform — never a hardcoded list copied off the mock.
            'vehicles' => DMVehicle::where('status', 1)->orderBy('id')->get(['id', 'type']),
            'selectedVehicleIds' => array_map('intval', (array) old(
                'vehicle_ids',
                $setup ? $setup->vehicles->pluck('id')->all() : [],
            )),
            'expressExtraCharge' => old('express_extra_charge', $setup?->express_extra_charge),
            'expressReduceTime' => old('express_reduce_delivery_time', $setup ? $expressTime['value'] : null),
            'expressReduceTimeUnit' => old('express_reduce_delivery_time_unit', $expressTime['unit']),
            'delayReduceCharge' => old('delay_reduce_charge', $setup?->delay_reduce_charge),
            'delayAddTime' => old('delay_add_delivery_time', $setup ? $delayTime['value'] : null),
            'delayAddTimeUnit' => old('delay_add_delivery_time_unit', $delayTime['unit']),
            'currencySymbol' => Helpers::currency_symbol(),
        ];
    }

    private function moduleEmptyMessage(string $state): string
    {
        return match ($state) {
            'none_connected' => translate('No module is connected to this zone yet. Connect one from zone setup.'),
            default => translate('messages.All module combinations for this zone have already been set up.'),
        };
    }

    /** "Shop Module" for one; "3 Module" for several, with the names carried as a tooltip. */
    private function moduleLabel(AdditionalDeliveryCharge $setup): array
    {
        $names = $setup->modules->pluck('module_name');

        return [
            'text' => $names->count() === 1
                ? $names->first().' '.translate('messages.Module')
                : $names->count().' '.translate('messages.Module'),
            'tooltip' => $names->implode(', '),
            'many' => $names->count() > 1,
        ];
    }
}
