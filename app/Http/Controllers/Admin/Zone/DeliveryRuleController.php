<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use App\Enums\ExportFileNames\Admin\DeliveryRule as DeliveryRuleExportFile;
use App\Exports\DeliveryRuleExport;
use App\Services\System\MeasurementUnitService;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\DeliveryRuleAddRequest;
use App\Http\Requests\Admin\DeliveryRuleStatusRequest;
use App\Http\Requests\Admin\DeliveryRuleUpdateRequest;
use App\Models\DeliveryRule;
use App\Services\System\DistanceService;
use App\Services\Parcel\DimensionService;
use App\Services\Parcel\WeightService;
use App\Services\System\ModuleService;
use App\Services\Zone\AreaService;
use App\Services\Zone\DeliveryRuleChargeService;
use App\Services\Zone\DeliveryRuleDimensionChargeService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\DeliveryRuleWeightChargeService;
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
 * Delivery Rule Setup — how one (zone, module) prices delivery.
 *
 * The controller orchestrates several services, which is the designed seam (architecture rule 4):
 * the rule service, the charge service, and the area / ZIP services that supply the coverage
 * table. None of them injects another.
 *
 * Everything the Blades render arrives finished — the unit label, the coverage rows, the existing
 * charges keyed by coverage id. No `Helpers::` call and no query lives in a template.
 */
class DeliveryRuleController extends BaseController
{
    public function __construct(
        protected DeliveryRuleService $deliveryRuleService,
        protected DeliveryRuleChargeService $deliveryRuleChargeService,
        protected ZoneService $zoneService,
        protected ModuleService $moduleService,
        protected AreaService $areaService,
        protected ZipCodeService $zipCodeService,
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        $filters = [
            'zone_id' => $request?->input('zone_id'),
            'module_id' => $request?->input('module_id'),
            'search' => $request?->input('search'),
        ];

        $deliveryRules = $this->deliveryRuleService->getList(
            filters: $filters,
            paginate: ['per_page' => config('default_pagination'), 'page' => $request?->input('page', 1)],
        );

        return view('admin-views.delivery-rule.list', [
            'rules' => $deliveryRules,
            'zones' => $this->zoneService->getSelectOptions(),
            'modules' => $this->moduleService->getSelectOptions(),
            // `getCollection()`, not the paginator: `collect()` on a paginator returns its
            // pagination envelope rather than its rows. Same shape the ETA list passes.
            'lockedModules' => $this->deliveryRuleService->lockedModulesFor($deliveryRules->getCollection()),
        ]);
    }

    public function create(): View
    {
        return view('admin-views.delivery-rule.create', $this->formData());
    }

    public function add(DeliveryRuleAddRequest $request): RedirectResponse|JsonResponse
    {
        $rule = $this->deliveryRuleService->create($request->payload());

        // The message has to match what actually happened: the first rule for a (zone, module)
        // arrives active, so telling that admin to "activate it when the charges are ready" would
        // send them looking for a switch that is already on.
        Toastr::success($rule->status
            ? translate('messages.Delivery rule created and activated. It is the first rule for this zone and module.')
            : translate('messages.Delivery rule created successfully. Activate it when the charges are ready.'));

        if ($request->ajax()) {
            return $this->savedResponse($rule->id, $rule->zone_id);
        }

        return redirect()->route('admin.business-settings.zone.delivery-rule.show', ['id' => $rule->id]);
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        $rule = $this->deliveryRuleService->find($id);

        if (! $rule) {
            Toastr::error(translate('No data found'));

            return back();
        }

        return view('admin-views.delivery-rule.edit', $this->formData($rule) + [
            'rule' => $rule,
            'soloModules' => $this->deliveryRuleService->soloModules($rule),
        ]);
    }

    public function update(DeliveryRuleUpdateRequest $request, $id): RedirectResponse|JsonResponse
    {
        $rule = $this->deliveryRuleService->update($id, $request->payload());

        if (! $rule) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        if ($request->ajax()) {
            return $this->savedResponse($rule->id, $rule->zone_id);
        }

        return back();
    }

    /**
     * What the wizard's success dialog needs — the rule to open, and the zone the "Setup ETA"
     * button hands on to (D6).
     *
     * The wizard has always posted by AJAX and read `redirect` off the answer, but both save
     * methods returned a redirect, so jQuery followed it and handed the success handler a page
     * of HTML. `View Details` was then wired to `undefined` on every save. It answers JSON to an
     * AJAX post now, and keeps the redirect for a plain one.
     */
    private function savedResponse(int $ruleId, ?int $zoneId): JsonResponse
    {
        return response()->json([
            'redirect' => route('admin.business-settings.zone.delivery-rule.show', ['id' => $ruleId]),
            'eta_redirect' => route('admin.business-settings.zone.eta-configuration.create', array_filter(['zone_id' => $zoneId])),
        ]);
    }

    /** The tabbed detail screen from the design. */
    public function show(string|int $id): View|RedirectResponse
    {
        // `modules`, not `module`: the detail renders a badge per connected module, and the
        // singular legacy belongsTo is not on this screen at all. Automatic eager loading batches
        // a collection's lazy reads but cannot help a single record, so getting this list wrong
        // cost two queries on every detail view.
        // `weightCharges.weight` / `dimensionCharges.dimension` are loaded here rather than read
        // lazily in the template: automatic eager loading batches a collection's lazy reads but
        // cannot help a single record, so a missing relation here is a real query per row
        // (architecture rule 11).
        $rule = $this->deliveryRuleService->find($id, with: [
            'zone', 'modules', 'charges.area', 'charges.zipCode',
            'weightCharges.weight', 'dimensionCharges.dimension',
        ]);

        if (! $rule) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $showsParcelTiers = $this->deliveryRuleService->connectsParcel([
            'module_ids' => $rule->modules->pluck('id')->all(),
        ]);

        // "01 Jul, 2026 04:22 PM" — the design writes the detail's timestamp as one value, so it
        // is assembled here rather than the template gluing a date and a time together.
        $createdAt = $rule->created_at?->locale(app()->getLocale())->translatedFormat('d M, Y h:i A');

        $meta = [
            translate('Created at') => $createdAt,
            translate('messages.Minimum Delivery Charge') => Helpers::format_currency($rule->minimum_delivery_charge),
            translate('Delivery pricing method') => $rule->methodLabel(),
        ];

        if ($rule->usesChargeTable()) {
            $coverageLabel = $rule->pricing_method === DeliveryRule::METHOD_AREA
                ? translate('Total area')
                : translate('Total zip code');
            $meta[$coverageLabel] = $rule->charges->count();
        }

        if ($showsParcelTiers) {
            $meta[translate('Total weight rule')] = $rule->weightCharges->count();
            $meta[translate('Total dimension rule')] = $rule->dimensionCharges->count();
        } else {
            // Without parcel the design orders it Created At, Method, Total Area, Minimum.
            $minimum = $meta[translate('messages.Minimum Delivery Charge')];
            unset($meta[translate('messages.Minimum Delivery Charge')]);
            $meta[translate('messages.Minimum Delivery Charge')] = $minimum;
        }

        $metaItems = [];
        foreach ($meta as $label => $value) {
            $metaItems[] = ['label' => $label, 'value' => $value];
        }

        $weightTierRows = $rule->weightCharges
            ->map(fn ($row) => [
                'label' => $row->weight
                    ? $row->weight->from_weight.' - '.$row->weight->to_weight.' '.app(MeasurementUnitService::class)->weightUnitLabel()
                    : translate('messages.N/A'),
                'charge' => Helpers::format_currency($row->charge),
            ])
            ->values();

        $dimensionTierRows = $rule->dimensionCharges
            ->map(fn ($row) => [
                'label' => $row->dimension
                    // "Small (Max Dimension : 100 × 50 × 80 in)" — the design's own format, and
                    // the same one the wizard's Dimension Rules step prints.
                    ? $row->dimension->name.' ('.translate('Max dimension').' : '
                        .$row->dimension->max_length.' × '.$row->dimension->max_width
                        .' × '.$row->dimension->max_height.' '.app(MeasurementUnitService::class)->dimensionUnitLabel().')'
                    : translate('messages.N/A'),
                'charge' => Helpers::format_currency($row->charge),
            ])
            ->values();

        return view('admin-views.delivery-rule.show', [
            'rule' => $rule,
            'distanceUnitLabel' => app(DistanceService::class)->unitLabel(),
            'currencySymbol' => Helpers::currency_symbol(),
            // Rule 8 — every currency and date string the detail prints arrives formatted, so
            // the template holds no Helpers:: call. The markup it emits is unchanged.
            'createdDate' => Helpers::date_format($rule->created_at),
            'createdTime' => Helpers::time_format($rule->created_at),
            'isArea' => $rule->pricing_method === DeliveryRule::METHOD_AREA,
            'minimumCharge' => Helpers::format_currency($rule->minimum_delivery_charge),
            'perUnitCharge' => Helpers::format_currency($rule->per_km_charge),
            // A missing OR zero maximum means NO CAP — DeliveryRuleService::distanceCharge()
            // only applies `min()` when the value is present and above zero. Printing it as
            // "0.00" told the admin the exact opposite: that every delivery was capped at
            // nothing. Say what the engine actually does.
            'maximumCharge' => $rule->maximum_delivery_charge > 0
                ? Helpers::format_currency($rule->maximum_delivery_charge)
                : translate('messages.No limit'),
            'fixedCharge' => Helpers::format_currency($rule->fixed_charge),
            'chargeRows' => $rule->charges
                ->map(fn ($charge) => ['label' => $charge->label(), 'charge' => Helpers::format_currency($charge->charge)])
                ->values(),

            // The additive parcel tiers. Shown only for a rule that connects a parcel module —
            // capability-driven, never a module-name test (port doc §16.1). A rule that once
            // connected parcel and no longer does has had its tiers cleared, so there is nothing
            // to show and nothing to explain.
            'showsParcelTiers' => $showsParcelTiers,
            // A tab with nothing behind it is worse than no tab: it invites a click that lands on
            // "No data found". Each tier earns its tab only by having priced rows — which also
            // covers a tier switched OFF, since switching off clears them.
            'showsWeightTab' => $showsParcelTiers && $weightTierRows->isNotEmpty(),
            'showsDimensionTab' => $showsParcelTiers && $dimensionTierRows->isNotEmpty(),
            'zoneName' => $rule->zone?->name ?? translate('messages.N/A'),
            'moduleNames' => $rule->modules->pluck('module_name')->all(),
            'lockedModuleNames' => $rule->status ? $this->deliveryRuleService->modulesLeftUncovered($rule, null) : [],
            // The design's own order, and it differs between the two: a parcel rule puts the
            // minimum second and appends the two tier counts.
            'metaItems' => $metaItems,
            'weightTierEnabled' => (bool) $rule->weight_charge_status,
            'dimensionTierEnabled' => (bool) $rule->dimension_charge_status,
            // As on the wizard: the heading follows `weight_unit`, not a hardcoded "KG".
            'weightRangeHeading' => translate('From - to').' ('.app(MeasurementUnitService::class)->weightUnitLabel().')',
            'weightTierRows' => $weightTierRows,
            'dimensionTierRows' => $dimensionTierRows,
        ]);
    }

    /**
     * The zone's other rules, for the turn-off dialog's replacement picker.
     */
    public function zoneRules(string|int $zoneId, Request $request): JsonResponse
    {
        return response()->json([
            'rules' => $this->deliveryRuleService
                ->selectableForZone($zoneId, $request->input('exclude'))
                ->map(fn ($rule) => ['id' => $rule->id, 'name' => $rule->name])
                ->values(),
        ]);
    }

    /**
     * Turn a rule on, or off by nominating the rule that takes over.
     *
     * POST and JSON, because both screens drive this from a confirm dialog and repaint from the
     * response. Answers `{success}` or `{errors: [{code, message}]}` — the shape
     * `_status-scripts.blade.php` reads.
     */
    public function updateStatus(DeliveryRuleStatusRequest $request, string|int $id): JsonResponse
    {
        $payload = $request->payload();

        if ($payload['status']) {
            if (! $this->deliveryRuleService->updateStatus($id, true)) {
                return $this->statusFailure(translate('No data found'));
            }

            return response()->json([
                'success' => translate('messages.Delivery rule activated. Any other active rule for this zone and module has been switched off.'),
            ]);
        }

        // D2 — the default zone must always keep one rule. With a replacement being switched on
        // the zone still has one, so the guard only bites when there is nothing to hand over to.
        if ($this->deliveryRuleService->isLastRuleOfDefaultZone($id)) {
            return $this->statusFailure(
                translate('At least one delivery rule must remain for the default zone to keep the delivery system operational.'),
            );
        }

        // Named rather than a bare failure: "not found" is what the admin used to be told when the
        // replacement they picked covered different modules, which reads as a bug rather than as
        // the refusal it is. The dialog shows this message, so it says which modules would be left
        // with no way to price an order.
        $rule = $this->deliveryRuleService->find($id, ['modules']);
        $replacement = $payload['replacement_id']
            ? $this->deliveryRuleService->find($payload['replacement_id'], ['modules'])
            : null;

        if ($rule) {
            $uncovered = $this->deliveryRuleService->modulesLeftUncovered($rule, $replacement);

            if ($uncovered !== []) {
                return $this->statusFailure(
                    translate('messages.This is the only delivery rule for these modules in this zone, so it cannot be switched off. Hand over to another rule that already covers them, or delete this one instead.')
                    .' '.translate('messages.Modules').': '.implode(', ', $uncovered)
                );
            }
        }

        if (! $this->deliveryRuleService->deactivateWithReplacement($id, $payload['replacement_id'])) {
            return $this->statusFailure(translate('No data found'));
        }

        return response()->json(['success' => translate('Updated successfully')]);
    }

    /** The error shape the status dialog reads. */
    private function statusFailure(string $message): JsonResponse
    {
        return response()->json(['errors' => [['code' => 'status', 'message' => $message]]]);
    }

    public function delete(Request $request): RedirectResponse
    {
        if ($this->deliveryRuleService->isLastRuleOfDefaultZone($request['id'])) {
            Toastr::error(
                translate('At least one delivery rule must remain for the default zone to keep the delivery system operational.'),
                translate('You can not delete this delivery rule'),
            );

            return back();
        }

        // Same guard as the toggle (updateStatus() above) — deleting an ACTIVE rule that is the
        // only one covering a module has the same effect as switching it off, which the toggle
        // already refuses. An inactive rule contributes no coverage to begin with, so it stays
        // freely deletable (TC_162/TC_406/TC_405 — the same delete-vs-deactivate asymmetry).
        $rule = $this->deliveryRuleService->find($request['id'], ['modules']);

        if ($rule && $rule->status) {
            $uncovered = $this->deliveryRuleService->modulesLeftUncovered($rule, null);

            if ($uncovered !== []) {
                Toastr::error(
                    translate('messages.This is the only delivery rule for these modules in this zone, so it cannot be deleted. Add a replacement rule that covers them first, or switch this one off instead.')
                    .' '.translate('messages.Modules').': '.implode(', ', $uncovered)
                );

                return back();
            }
        }

        if (! $this->deliveryRuleService->delete($request['id'])) {
            Toastr::error(translate('No data found'));

            return back();
        }

        Toastr::success(translate('Deleted successfully'));

        return redirect()->route('admin.business-settings.zone.delivery-rule.list');
    }

    /**
     * Everything the form must redraw when the zone changes: that zone's coverage rows, and the
     * modules still free in it.
     *
     * Keyed by path segment rather than a query string because that is the shape the ported form
     * script calls with.
     */
    public function getCoverage(string|int $zoneId, Request $request): JsonResponse
    {
        $exceptRuleId = $request->input('rule_id');
        $picker = $this->deliveryRuleService->modulePickerForZone($zoneId, $exceptRuleId);

        return response()->json([
            'areas' => $this->areaService->activeForZone($zoneId)
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(),
            'zip_codes' => $this->zipCodeService->activeForZone($zoneId)
                ->map(fn ($z) => ['id' => $z->id, 'zip_code' => $z->zip_code])->values(),
            'modules' => $picker['modules']->map(fn ($m) => ['id' => $m->id, 'name' => $m->module_name])->values(),
            'modules_empty_message' => $this->moduleEmptyMessage($picker['state']),
        ]);
    }

    /**
     * The service reports WHY the dropdown is empty; the sentence is chosen here, because
     * translating is the rendering layer's job. Takes the state the caller already resolved, so
     * the picker's query set is not run a second time just to pick a message.
     */
    private function moduleEmptyMessage(string $state): string
    {
        return match ($state) {
            'none_connected' => translate('No module is connected to this zone yet. Connect one from zone setup.'),
            default => translate('messages.All module combinations for this zone have already been set up.'),
        };
    }

    /** Shared by create and edit so the two forms cannot drift apart. */
    private function formData(?DeliveryRule $rule = null): array
    {
        $zones = $this->zoneService->getSelectOptions();

        // Rule 8 — the ported partial computed all of this in an @php block. It is assembled here
        // instead and the templates render it, which leaves the markup they emit unchanged.
        // `old()` reads the flashed input a failed validation redirected back with, so a rejected
        // form comes back filled in rather than blank.
        $selectedZoneId = old('zone_id', $rule?->zone_id ?? request()->input('zone_id') ?? $zones->first()->id ?? null);
        $selectedModuleIds = array_map('intval', (array) old(
            'module_ids',
            $rule ? $rule->modules->pluck('id')->all() : (array) request()->input('module_ids', []),
        ));
        $selectedMethod = old('pricing_method', $rule?->pricing_method ?? DeliveryRule::METHOD_AREA);

        // One pass: the modules to offer and why the list is empty when it is.
        $picker = $this->deliveryRuleService->modulePickerForZone($selectedZoneId, $rule?->id);

        // Fetched once and reused for both the tables and their formatted labels.
        $weightBands = app(WeightService::class)->activeBands();
        $dimensionSizes = app(DimensionService::class)->activeSizes();

        // Existing amounts keyed by coverage id, so each table can prefill its inputs.
        $areaCharges = [];
        $zipCharges = [];
        foreach ($rule?->charges ?? [] as $charge) {
            if ($charge->area_id) {
                $areaCharges[$charge->area_id] = $charge->charge;
            }
            if ($charge->zip_code_id) {
                $zipCharges[$charge->zip_code_id] = $charge->charge;
            }
        }

        return [
            'rule' => $rule,
            'zones' => $zones,
            // Only the modules still free in this zone — the taken ones are hidden, not
            // disabled. D1 remains the server-side guard.
            'modules' => $picker['modules'],
            'modulesEmptyMessage' => $this->moduleEmptyMessage($picker['state']),
            'selectedZoneId' => $selectedZoneId,
            'selectedModuleIds' => $selectedModuleIds,
            'selectedMethod' => $selectedMethod,
            'pricingMethods' => $this->pricingMethodCards(),
            'areaCharges' => $areaCharges,
            'zipCharges' => $zipCharges,
            // The per-zone seed the form script restores from when the admin returns to a zone.
            'chargeSeed' => $rule?->zone_id
                ? [(string) $rule->zone_id => ['area_charges' => (object) $areaCharges, 'zip_charges' => (object) $zipCharges]]
                : (object) [],
            'currencySymbol' => Helpers::currency_symbol(),
            'distanceUnitLabel' => app(DistanceService::class)->unitLabel(),
            'areas' => $selectedZoneId ? $this->areaService->activeForZone($selectedZoneId) : collect(),
            'zipCodes' => $selectedZoneId ? $this->zipCodeService->activeForZone($selectedZoneId) : collect(),

            // The wizard's two parcel steps. Rendered always but hidden unless a parcel-capable
            // module is connected — the stepper appears and disappears as the multi-select
            // changes, which is a client-side decision made against `parcelModuleIds`.
            'parcelModuleIds' => app(ModuleService::class)->parcelCapableModuleIds(),
            'weightBands' => $weightBands,
            'dimensionSizes' => $dimensionSizes,
            'weightCharges' => $rule ? app(DeliveryRuleWeightChargeService::class)->keyedByWeight($rule->id) : [],
            'dimensionCharges' => $rule ? app(DeliveryRuleDimensionChargeService::class)->keyedByDimension($rule->id) : [],
            'weightChargeStatus' => (bool) old('weight_charge_status', $rule?->weight_charge_status ?? false),
            'dimensionChargeStatus' => (bool) old('dimension_charge_status', $rule?->dimension_charge_status ?? false),
            // "0.00 - 2.00 KG" / "100 × 50 × 80 in", formatted here so the tables only render.
            // Mapped from the collections above rather than re-fetching: asking the service twice
            // for the same rows is a duplicate query, and duplicates are defects here.
            'weightBandLabels' => $weightBands
                ->mapWithKeys(fn ($band) => [$band->id => $band->from_weight.' - '.$band->to_weight.' '.app(MeasurementUnitService::class)->weightUnitLabel()])->all(),
            // "Small (Max Dimension : 100 × 50 × 80 in)" — name FIRST. Measurements alone left
            // the wizard showing three rows of numbers with nothing to tell the size classes
            // apart, and it disagreed with the details page, which has always printed the name.
            'dimensionSizeLabels' => $dimensionSizes
                ->mapWithKeys(fn ($size) => [$size->id => $size->name.' ('.translate('Max dimension').' : '
                    .$size->max_length.' × '.$size->max_width.' × '.$size->max_height
                    .' '.app(MeasurementUnitService::class)->dimensionUnitLabel().')'])->all(),
            // §3.4 — the heading follows the setting, exactly as the rows under it do. It read
            // "From - To (KG)" while a row said "1.00 - 2.00 lb".
            'weightRangeHeading' => translate('From - to').' ('.app(MeasurementUnitService::class)->weightUnitLabel().')',
            'wizardSteps' => [
                translate('General information'),
                translate('Weight rules'),
                translate('Dimension rules'),
            ],
            'dimensionRuleNote' => e(translate('messages.Dimension rules are listed below. Enter the additional charge for each dimension, or zero if no additional charge should be applied.'))
                .' '.e(translate('messages.Manage dimension rules')).': '
                .'<a href="'.e(route('admin.business-settings.zone.dimension.list')).'">'
                .e(translate('Dimension setup')).'</a>',
            'areaSetupRoute' => route('admin.business-settings.zone.area.list'),
            'weightSetupRoute' => route('admin.business-settings.zone.weight.list'),
            'dimensionSetupRoute' => route('admin.business-settings.zone.dimension.list'),
        ];
    }

    /**
     * The four radio cards of "Delivery Pricing Method", each with the description the design
     * prints under its title.
     */
    private function pricingMethodCards(): array
    {
        return [
            DeliveryRule::METHOD_AREA => [
                'title' => translate('Area wise'),
                'hint' => translate('messages.Set a fixed delivery charge for each service area.'),
            ],
            DeliveryRule::METHOD_ZIP => [
                'title' => translate('Zip code wise'),
                'hint' => translate('messages.Set a fixed delivery charge for each ZIP code.'),
            ],
            DeliveryRule::METHOD_DISTANCE => [
                'title' => translate('Distance wise'),
                'hint' => translate('messages.Calculate delivery charges based on travel distance.'),
            ],
            DeliveryRule::METHOD_FIXED => [
                'title' => translate('Fixed amount'),
                'hint' => translate('messages.Apply the same delivery charge to all bookings.'),
            ],
        ];
    }

    /** Export the filtered list. */
    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $zoneId = $request->input('zone_id');
        $scopedZoneId = $zoneId === 'all' ? null : $zoneId;

        $rows = $this->deliveryRuleService->getList(
            filters: ['search' => $request->input('search'), 'zone_id' => $request->input('zone_id'), 'module_id' => $request->input('module_id')],
            paginate: ['per_page' => 100000, 'page' => 1],
        )->getCollection()->values()->map(fn ($rule, $index) => [
            'SL' => $index + 1,
            'Rule Name' => $rule->name,
            'Zone' => $rule->zone?->name,
            // A rule connects to MANY modules; the legacy `module_id` names at most one of
            // them. Read the pivot getList() already eager-loads, as the list screen does.
            'Module' => $rule->modules->pluck('module_name')->implode(', '),
            'Delivery Method' => $rule->methodLabel(),
            'Minimum Delivery Charge' => $rule->minimum_delivery_charge,
            'Status' => $rule->status ? 'Active' : 'Inactive',
        ]);

        $data = [
            'data' => $rows,
            'search' => $request->input('search'),
            'zone' => $scopedZoneId ? $this->zoneService->getNamesByIds($scopedZoneId) : null,
        ];

        return Excel::download(
            new DeliveryRuleExport($data),
            $type === 'csv' ? DeliveryRuleExportFile::EXPORT_CSV : DeliveryRuleExportFile::EXPORT_XLSX,
        );
    }
}
