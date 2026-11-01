<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Enums\ExportFileNames\Admin\FreeDelivery as FreeDeliveryExportFile;
use App\Exports\FreeDeliveryExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\FreeDeliveryAddRequest;
use App\Http\Requests\Admin\FreeDeliveryUpdateRequest;
use App\Models\FreeDelivery;
use App\Services\System\ModuleService;
use App\Services\Zone\FreeDeliveryService;
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
 * Free Delivery Setup — per (zone, module), §10.
 *
 * The views receive finished values: the module column's label and tooltip, the type label, the
 * picker's contents and its empty-state message. No `Helpers::` call and no query in a template.
 */
class FreeDeliveryController extends BaseController
{
    public function __construct(
        protected FreeDeliveryService $freeDeliveryService,
        protected ZoneService $zoneService,
        protected ModuleService $moduleService,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $setups = $this->freeDeliveryService->getList(
            filters: ['search' => $request?->input('search'), 'zone_id' => $request?->input('zone_id')],
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.free-delivery.list', [
            'setups' => $setups,
            'zones' => $this->zoneService->getSelectOptions(),
            // "Shop Module" for one, "4 Module" plus a tooltip for several — the design's own
            // shape, built here so the Blade renders rather than decides.
            'moduleLabels' => $setups->getCollection()->mapWithKeys(fn ($setup) => [
                $setup->id => $this->moduleLabel($setup),
            ])->all(),
            'typeLabels' => $setups->getCollection()->mapWithKeys(fn ($setup) => [
                $setup->id => $this->typeLabel($setup),
            ])->all(),
            // The threshold is the whole point of a Specific Criteria row, and the list did not
            // show it — an admin had to open each record to see what the order has to reach.
            // "All Store" rows have no threshold, so they read N/A rather than a misleading 0.
            'minimumLabels' => $setups->getCollection()->mapWithKeys(fn ($setup) => [
                $setup->id => $this->minimumLabel($setup),
            ])->all(),
        ]);
    }

    /** "৳ 500.00" for a Specific Criteria setup, N/A where no threshold applies. */
    private function minimumLabel(FreeDelivery $setup): string
    {
        if ($setup->type !== FreeDelivery::TYPE_CRITERIA || $setup->minimum_order_amount === null) {
            return translate('messages.N/A');
        }

        return Helpers::format_currency($setup->minimum_order_amount);
    }

    public function create(): View
    {
        return view('admin-views.free-delivery.create', $this->formData());
    }

    public function add(FreeDeliveryAddRequest $request): RedirectResponse
    {
        $this->freeDeliveryService->create($request->payload());

        Toastr::success(translate('Added successfully'));

        return redirect()->route('admin.business-settings.zone.free-delivery.list');
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $setup = $this->freeDeliveryService->find($id);

        if (! $setup) {
            Toastr::error(translate('No data found'));

            return back();
        }

        return view('admin-views.free-delivery.edit', $this->formData($setup) + [
            'setup' => $setup,
            // S19 — which of the picker's modules would lose free delivery entirely if the admin
            // dropped them and saved. The form warns before that save, not after it.
            'soloModules' => $this->freeDeliveryService->soloModules($setup),
            'zoneName' => $setup->zone?->name ?? translate('messages.N/A'),
        ]);
    }

    public function update(FreeDeliveryUpdateRequest $request, $id): RedirectResponse
    {
        if (! $this->freeDeliveryService->update($id, $request->payload())) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.zone.free-delivery.list');
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (! $this->freeDeliveryService->updateStatus($request['id'], $request['status'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        if (! $this->freeDeliveryService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    /** The modules still free in a zone, for the form's picker when the zone changes. */
    public function getModules(string|int $zoneId, Request $request): JsonResponse
    {
        $picker = $this->freeDeliveryService->modulePickerForZone($zoneId, $request->input('setup_id'));

        return response()->json([
            'modules' => $picker['modules']->map(fn ($m) => ['id' => $m->id, 'name' => $m->module_name, 'type' => $m->module_type])->values(),
            'modules_empty_message' => $this->moduleEmptyMessage($picker['state']),
        ]);
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $zoneId = $request->input('zone_id');
        $scopedZoneId = $zoneId === 'all' ? null : $zoneId;

        $rows = $this->freeDeliveryService->getListData(filters: [
            'search' => $request->input('search'), 'zone_id' => $request->input('zone_id'),
        ])->values()->map(fn ($setup, $index) => [
            'SL' => $index + 1,
            'Zone' => $setup->zone?->name,
            'Module' => $setup->modules->pluck('module_name')->implode(', '),
            'Free Delivery Type' => $this->typeLabel($setup),
            'Minimum Order Amount' => $setup->minimum_order_amount,
            'Status' => $setup->status ? 'Active' : 'Inactive',
        ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
            'zone' => $scopedZoneId ? $this->zoneService->getNamesByIds($scopedZoneId) : null,
        ];

        return Excel::download(
            new FreeDeliveryExport($data),
            $type === 'csv' ? FreeDeliveryExportFile::EXPORT_CSV : FreeDeliveryExportFile::EXPORT_XLSX,
        );
    }

    /** Shared by create and edit so the two forms cannot drift apart. */
    private function formData(?FreeDelivery $setup = null): array
    {
        $zones = $this->zoneService->getSelectOptions();
        $selectedZoneId = old('zone_id', $setup?->zone_id ?? request()->input('zone_id') ?? $zones->first()->id ?? null);
        $picker = $this->freeDeliveryService->modulePickerForZone($selectedZoneId, $setup?->id);

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
            'selectedType' => old('type', $setup?->type ?? FreeDelivery::TYPE_ALL),
            'minimumOrderAmount' => old('minimum_order_amount', $setup?->minimum_order_amount),
            'typeCards' => $this->typeCards(),
            'currencySymbol' => Helpers::currency_symbol(),
        ];
    }

    /** The design's two radio cards, each with the description printed under its title. */
    private function typeCards(): array
    {
        return [
            FreeDelivery::TYPE_ALL => [
                'title' => translate('Free delivery for all store'),
                'hint' => translate('Enable free delivery for all orders in the selected zone and module. Customers will not be charged any delivery fee, regardless of the order amount.'),
            ],
            FreeDelivery::TYPE_CRITERIA => [
                'title' => translate('Specific criteria'),
                'hint' => translate('messages.Enable free delivery only when an order meets the configured minimum order amount. Customers who do not meet the requirement will pay the normal delivery charge.'),
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
    private function moduleLabel(FreeDelivery $setup): array
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

    private function typeLabel(FreeDelivery $setup): string
    {
        return $setup->type === FreeDelivery::TYPE_CRITERIA
            ? translate('Specific criteria')
            : translate('All store');
    }
}
