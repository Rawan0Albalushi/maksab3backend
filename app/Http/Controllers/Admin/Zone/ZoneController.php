<?php

namespace App\Http\Controllers\Admin\Zone;

use App\CentralLogics\Helpers;
use Illuminate\View\View;
use App\Exports\ZoneExport;
use Illuminate\Http\Request;
use App\Services\Builder\StorefrontVisibilityService;
use App\Services\Order\CartService;
use App\Services\Order\OrderService;
use App\Services\System\ModuleService;
use App\Services\Zone\ZoneService;
use Illuminate\Http\JsonResponse;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\BaseController;
use App\Enums\ExportFileNames\Admin\Zone;
use App\Http\Requests\Admin\ZoneAddRequest;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\Admin\ZoneUpdateRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Enums\ViewPaths\Admin\Zone as ZoneViewPath;
use App\Http\Requests\Admin\ZoneConnectModuleRequest;
use App\Http\Requests\Admin\ZoneModuleUpdateRequest;
use App\Contracts\Repositories\ZoneRepositoryInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Contracts\Repositories\TranslationRepositoryInterface;

class ZoneController extends BaseController
{
    public function __construct(
        protected ZoneRepositoryInterface $zoneRepo,
        protected ZoneService $zoneService,
        protected ModuleService $moduleService,
        protected OrderService $orderService,
        protected CartService $cartService,
        protected TranslationRepositoryInterface $translationRepo
    ) {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getAddView($request);
    }

    public function add(ZoneAddRequest $request): JsonResponse
    {
        $zoneId = $this->zoneRepo->getLatest()?->id + 1;
        $zone = $this->zoneRepo->add(data: $this->zoneService->getAddData($request->all(), zoneId: $zoneId));

        $this->translationRepo->addByModel(request: $request, model: $zone, modelPath: 'App\Models\Zone', attribute: 'name');
        $this->translationRepo->addByModel(request: $request, model: $zone, modelPath: 'App\Models\Zone', attribute: 'display_name');

        $zones = $this->zoneRepo->getListWhere(
            relations: ['stores.vendor', 'modules', 'deliverymen'],
            dataLimit: config('default_pagination'),
        );

        return response()->json([
            'view' => view('admin-views.zone.partials._table_rows', [
                'zones' => $zones,
                'readiness' => $this->zoneService->readinessFor($zones),
            ])->render(),
            'id' => $zone->id,
            'total' => $zones->count(),
        ]);
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        if (getEnvMode() == 'demo' && $id == 1) {
            Toastr::warning(translate('messages.You can not edit this zone please add a new zone to edit'));
            return back();
        }

        $zone = $this->zoneRepo->getWithCoordinateWhere(
            params: ['id' => $id]
        );

        $area = json_decode($zone['coordinates'][0]->toJson(), true);
        $language = Helpers::active_extra_languages();
        $defaultLang = str_replace('_', '-', app()->getLocale());

        return view(ZoneViewPath::UPDATE[VIEW], compact(['zone', 'area', 'language', 'defaultLang']));
    }

    public function update(ZoneUpdateRequest $request, $id): RedirectResponse
    {
        $zone = $this->zoneRepo->update(id: $id, data: $this->zoneService->getUpdateData($request->all(), zoneId: $id));

        $this->translationRepo->updateByModel(request: $request, model: $zone, modelPath: 'App\Models\Zone', attribute: 'name');
        $this->translationRepo->updateByModel(request: $request, model: $zone, modelPath: 'App\Models\Zone', attribute: 'display_name');

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        if (getEnvMode() == 'demo' && $request['id'] == 1) {
            Toastr::warning(translate('messages.You can not delete this zone please add a new zone to delete'));
            return back();
        }
        $zone = $this->zoneRepo->getFirstWhere(params: ['id' => $request['id']], relations: ['stores.vendor']);
        if($zone->is_default){
            Toastr::warning('Sorry! This zone is set as default.You can not delete this zone!');
            return back();
        }
        if ($this->orderService->hasRunningOrdersInZone($request['id'])) {
            Toastr::warning(translate('messages.You can not delete this zone Please complete the ongoing orders of this zone'));
            return back();
        }
        // Same shape as the row's own "Vendors"/"Deliverymen" counts (_table_rows.blade.php) —
        // a zone still serving either is left with dangling stores/deliverymen if deleted (TC_149).
        $vendorCount = $zone->stores->filter(fn ($store) => $store->vendor && $store->vendor->status == 1)->count();
        $deliverymanCount = $zone->deliverymen()->count();

        if ($vendorCount > 0 || $deliverymanCount > 0) {
            Toastr::warning(translate(
                'Sorry! This zone still has :vendor vendor(s) and :dm deliveryman(s) assigned. Reassign or remove them before deleting this zone.',
                ['vendor' => $vendorCount, 'dm' => $deliverymanCount]
            ));

            return back();
        }
        $this->zoneRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function exportList(Request $request, string $type): BinaryFileResponse
    {
        $collection = $this->zoneRepo->getExportList($request);
        $data = [
            'data' => $collection,
            'search' => $request['search'] ?? null,
        ];
        if ($type == 'csv') {
            return Excel::download(new ZoneExport($data), Zone::EXPORT_CSV);
        }
        return Excel::download(new ZoneExport($data), Zone::EXPORT_XLSX);
    }

    public function zoneFilter($id): RedirectResponse
    {
        if ($id == 'all') {
            if (session()->has('zone_id')) {
                session()->forget('zone_id');
            }
        } else {
            session()->put('zone_id', $id);
        }

        return back();
    }

    /**
     * The Connect Module drawer, rendered for one zone.
     *
     * Returns markup rather than a page: the design opens this beside the zone list so the admin
     * keeps their place, the same shape Weight and Dimension Setup already use.
     */
    public function connectModuleView(string|int $id): JsonResponse
    {
        $zone = $this->zoneRepo->getFirstWhere(params: ['id' => $id], relations: ['modules']);

        if (! $zone) {
            return response()->json(['view' => null], 404);
        }

        return response()->json([
            'view' => view('admin-views.zone.partials._connect-module', $this->connectModuleData($zone))->render(),
        ]);
    }

    public function connectModule(ZoneConnectModuleRequest $request, string|int $id): JsonResponse|RedirectResponse
    {
        $zone = $this->zoneService->connectModules(
            zoneId: $id,
            payments: $request->paymentColumns(),
            moduleIds: $request->moduleIds(),
            codLimits: $request->codLimits(),
        );

        if (! $zone) {
            return $this->drawerFailure($request, translate('No data found'));
        }

        $zone = $zone->fresh();

        // Z3 again, this time on the save that can CAUSE the gap rather than the toggle that
        // guards it: disconnecting a zone's one complete module (or leaving only modules that
        // were never configured) makes it unservable exactly as surely as never having set it
        // up at all. Leaving `status` untouched here left an ACTIVE zone showing as on while
        // nothing behind it could actually be served -- worse than the toggle refusing to
        // switch it on, since nothing about the list said so. The default zone is exempt: the
        // toggle itself refuses to deactivate it (every location-less customer lands there), so
        // silently doing it here on its behalf would be a bigger surprise than the gap.
        if ((int) $zone->status === 1 && ! $zone->is_default && $zone->readinessGaps()) {
            $this->deactivateZone($zone);
            Toastr::warning(
                translate('messages.Zone switched off — no connected module has both a delivery charge rule and an ETA configuration.')
            );
            $zone = $zone->fresh();
        }

        return $this->drawerResponse(
            $request,
            translate('Updated successfully'),
            $this->setupGuideData($zone),
        );
    }

    /**
     * The full switch-off, shared by the status toggle and the connect-module save that can
     * make a zone unready behind the admin's back.
     *
     * Everything a manual deactivation already does: the record itself, the storefronts it
     * silently keeps answering on their own domains, the carts nothing can now check out, and
     * the vendors who would otherwise only notice when their orders stopped arriving.
     */
    private function deactivateZone(\App\Models\Zone $zone): void
    {
        $this->zoneRepo->update(id: $zone->id, data: ['status' => 0]);

        $hidden = app(StorefrontVisibilityService::class)->hideStorefronts($zone->id);

        if ($hidden > 0) {
            Toastr::warning(translate('messages.Website visibility turned off for').' '.$hidden.' '
                .translate('messages.store websites in this zone'));
        }

        $this->cartService->deleteByZone($zone->id);
        $this->zoneService->notifyVendorsOfDeactivation($zone->id, $zone->name);
    }

    /**
     * What the post-save "set up delivery charge & ETA" prompt needs, or null once the zone is
     * ready — nothing to prompt then (TC_58). Reuses the same resolved readiness the row's
     * warning mark and status toggle read, so this cannot say something they wouldn't.
     */
    private function setupGuideData(\App\Models\Zone $zone): ?array
    {
        $readiness = $this->zoneService->readinessFor([$zone])[(int) $zone->id];

        if ($readiness['ready']) {
            return null;
        }

        return [
            'title' => translate($readiness['titleKey'], ['zone' => $zone->labelStem()]),
            'text' => $readiness['prompt'],
            'hasRule' => $readiness['hasRule'],
            'hasEta' => $readiness['hasEta'],
            'zoneId' => $zone->id,
        ];
    }

    /**
     * Fresh readiness for one zone, read on demand by the status toggle before it decides which
     * dialog to show.
     *
     * The row's data-* attributes carry the same fields, but they are a snapshot from whenever
     * that row was last rendered — a delivery rule or ETA configuration added in another tab, or
     * a status change made earlier in the same session without a full reload, would leave that
     * snapshot saying something updateStatus() itself would no longer agree with. This asks the
     * same ZoneService::readinessFor() the page load and updateStatus() both already trust, so the
     * dialog the admin sees cannot fall behind what the guard is actually about to check.
     */
    public function readiness(mixed $id): JsonResponse
    {
        $zone = $this->zoneRepo->getFirstWhere(params: ['id' => $id]);

        if (! $zone) {
            return response()->json(['message' => translate('No data found')], 404);
        }

        $readiness = $this->zoneService->readinessFor([$zone])[(int) $zone->id];

        return response()->json([
            'status' => (int) $zone->status,
            'toggleEnabled' => $readiness['toggleEnabled'],
            'requiresConfirmation' => $readiness['requiresConfirmation'],
            'title' => translate($readiness['titleKey'], ['zone' => $zone->labelStem()]),
            'prompt' => $readiness['prompt'],
            'hasRule' => (int) $readiness['hasRule'],
            'hasEta' => (int) $readiness['hasEta'],
            'partialTitle' => translate($readiness['partialTitleKey'], ['zone' => $zone->labelStem()]),
            'partialPrompt' => $readiness['partialPrompt'],
            'unavailableModules' => implode(', ', $readiness['unavailableModuleNames']),
            'liveStorefronts' => $readiness['liveStorefronts'],
        ]);
    }

    /**
     * Why the zone may not be switched on, naming the modules that are short.
     *
     * The bare message says a delivery rule or an ETA is missing; on a zone serving eight modules
     * that leaves the admin opening two screens to find which one is holding it back.
     */
    private function readinessWarning(\App\Models\Zone $zone, array $gaps): string
    {
        $message = translate($zone->readinessMessageKey($gaps));
        $missing = \App\Models\Zone::missingByModule(
            $zone->connectedModuleIds(),
            $zone->ruledModuleIds(),
            $zone->timedModuleIds(),
        );
        $names = app(ModuleService::class)->getSelectOptions()->pluck('module_name', 'id');

        $parts = [];

        foreach ([
            'delivery_rule' => translate('messages.delivery rule'),
            'eta' => translate('messages.ETA configuration'),
        ] as $key => $label) {
            $ids = array_values(array_filter($missing[$key], fn ($id) => $names->has($id)));

            if ($ids !== []) {
                $parts[] = $label.': '.implode(', ', array_map(fn ($id) => $names[$id], $ids));
            }
        }

        return $parts === [] ? $message : $message.' — '.implode('; ', $parts);
    }

    /**
     * What a switch-on leaves dark, or null when it leaves nothing dark.
     *
     * The sentence itself comes from the model like every other one; this only resolves the
     * module ids into names. Inactive modules are left out for the reason ZoneService gives —
     * they are not served anywhere, so naming them reads as a zone problem the admin cannot fix.
     */
    private function unavailableWarning(\App\Models\Zone $zone): ?string
    {
        $names = app(ModuleService::class)->getSelectOptions()->pluck('module_name', 'id');

        $unavailable = array_values(array_filter(
            array_map(fn ($id) => $names[$id] ?? null, $zone->incompleteModuleIds())
        ));

        if ($unavailable === []) {
            return null;
        }

        return translate(
            $zone->readinessMessageKey([], partial: true),
            ['modules' => implode(', ', $unavailable)]
        );
    }

    /**
     * What the drawer renders from.
     *
     * The COD toggle starts ON when any connected module already carries a ceiling — an admin who
     * set one should find the panel showing it, not folded away as though it were never set.
     */
    private function connectModuleData(mixed $zone): array
    {
        $connected = $zone->modules;
        $codLimits = $connected->mapWithKeys(
            fn ($module) => [$module->id => (float) ($module->pivot->maximum_cod_order_amount ?? 0)]
        )->all();

        // getSelectOptions() is active-modules-only. A module connected to this zone can since
        // have been deactivated platform-wide — it must still render here (so the admin can see
        // and disconnect it) or its chip silently vanishes with no way to manage it from this
        // drawer at all (TC_70/TC_97).
        $modules = $this->moduleService->getSelectOptions();
        $connectedButInactive = $connected->whereNotIn('id', $modules->pluck('id'));
        if ($connectedButInactive->isNotEmpty()) {
            $modules = $modules->concat($connectedButInactive)->sortBy('module_name')->values();
        }

        return [
            'zone' => $zone,
            'modules' => $modules,
            'selectedModuleIds' => $connected->pluck('id')->map(fn ($moduleId) => (int) $moduleId)->all(),
            'activeStoreModuleIds' => $this->zoneService->activeStoreModuleIds($zone->id),
            // Modules whose stores in this zone are serving a website right now. Removing one
            // takes those sites down, which the disconnect warning has to say outright -- a
            // storefront answers on its own domain and is the one thing that would otherwise
            // keep working with no route left behind it.
            'builderStoreModuleIds' => app(StorefrontVisibilityService::class)->moduleIdsWithLiveStorefronts($zone->id),
            'codLimits' => $codLimits,
            'codLimitEnabled' => collect($codLimits)->contains(fn ($amount) => $amount > 0),
            'paymentMethods' => $this->connectModulePaymentMethods($zone),
            'codFieldLabel' => translate('Max COD order amount') . ' (' . Helpers::currency_symbol() . ')',
            'readiness' => $this->zoneService->readinessFor([$zone])[(int) $zone->id],
        ];
    }

    /** @return array<string, array{label: string, checked: bool}> */
    private function connectModulePaymentMethods(mixed $zone): array
    {
        $labels = [
            'cash_on_delivery' => translate('Cash on delivery'),
            'digital_payment' => translate('Digital payment'),
            'offline_payment' => translate('Offline payment'),
        ];

        $offered = [];

        foreach (ZoneConnectModuleRequest::platformAllows() as $method => $platformAllows) {
            if ($platformAllows) {
                $offered[$method] = ['label' => $labels[$method], 'checked' => (int) $zone->{$method} === 1];
            }
        }

        return $offered;
    }

    private function drawerResponse(Request $request, string $message, ?array $setupGuide = null): JsonResponse|RedirectResponse
    {
        if ($request->ajax()) {
            return response()->json(array_filter([
                'success' => $message,
                'setupGuide' => $setupGuide,
            ], fn ($value) => $value !== null));
        }

        Toastr::success($message);

        return back();
    }

    private function drawerFailure(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->ajax()) {
            return response()->json(['errors' => [['code' => 'id', 'message' => $message]]]);
        }

        Toastr::error($message);

        return back();
    }

    public function getModuleSetupView($id): View
    {
        $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $id],
            relations: ['modules']
        );
        $cash_on_delivery = Helpers::get_business_settings('cash_on_delivery');
        $digital_payment = Helpers::get_business_settings('digital_payment');
        $offline_payment = Helpers::get_business_settings('offline_payment_status');

        return view(ZoneViewPath::MODULE_SETUP[VIEW], compact('zone', 'cash_on_delivery', 'digital_payment', 'offline_payment'));
    }

    public function getLatestModuleSetupView(): View
    {
        $zone = $this->zoneRepo->getLatest(
            relations: ['modules']
        );
        $cash_on_delivery = Helpers::get_business_settings('cash_on_delivery');
        $digital_payment = Helpers::get_business_settings('digital_payment');
        $offline_payment = Helpers::get_business_settings('offline_payment_status');

        return view(ZoneViewPath::MODULE_SETUP[VIEW], compact('zone', 'cash_on_delivery', 'digital_payment', 'offline_payment'));
    }

    public function updateModuleSetup(ZoneModuleUpdateRequest $request, $id): RedirectResponse
    {

        if (!$request->cash_on_delivery && !$request->digital_payment && !$request->offline_payment) {
            Toastr::error(translate('Please select at least one payment method'));
            return back()->withInput();
        }

        $ride_share_module = $this->moduleService->hasRideShareModule($request->module_id ?? []);
        if ($ride_share_module && addon_published_status('RideShare') && !$request->cash_on_delivery && !$request->digital_payment) {
            Toastr::error(translate('Both Cash on delivery and Digital payment cannot be inactive for RideShare module'));
            return back()->withInput();
        }
        
        $cash_on_delivery = Helpers::get_business_settings('cash_on_delivery');
        $digital_payment = Helpers::get_business_settings('digital_payment');
        $offline_payment = Helpers::get_business_settings('offline_payment_status');

        $paymentData = [
            'cash_on_delivery' => $request->cash_on_delivery && data_get($cash_on_delivery, 'status') == 1 ? 1 : 0,
            'digital_payment' => $request->digital_payment && data_get($digital_payment, 'status') == 1 ?: 0,
            'offline_payment' => $request->offline_payment && $offline_payment == 1 ? 1 : 0
        ];
        $data = $this->zoneService->validateModuleDeliveryCharge(
            moduleData: $request->module_data,
            selectedModules: $request->module_id,
            serviceModuleIds: $this->moduleService->getServiceModuleIds($request->module_id ?? [])
        );

        if (!empty($data) && array_key_exists('flag', $data)) {
            $module = $this->moduleService->find($data['module_id']);
            $moduleName = $module?->module_name ?? 'Unknown Module';

            if (!in_array($module?->module_type, ['parcel', 'rental'])) {
                switch ($data['flag']) {
                    case 'fixed_required':
                        Toastr::error(translate("Fixed delivery charge is required for module") . ":" . '' . $moduleName);
                        break;

                    case 'distance_required':
                        Toastr::error(translate("Per km and minimum delivery charge are required for module") . ": " . $moduleName);
                        break;

                    case 'max_lt_min':
                        Toastr::error(translate("Maximum delivery charge must be greater than or equal to minimum for module") . ":" . '' . $moduleName);
                        break;

                    case 'unknown_type':
                        Toastr::error(translate("Unknown delivery charge type selected for module") . ":" . '' . $moduleName);
                        break;

                    default:
                        Toastr::error(translate("Invalid delivery charge configuration for module") . ":" . '' . $moduleName);
                        break;
                }

                return back()->withInput();
            }
        }
        $filteredModuleData = collect($request->module_data)
            ->only($request->module_id)
            ->toArray();
        // Express and slightly-delay offers moved to the Additional Charge screen, which owns
        // them per (zone, module). Leaving the inline editor here would have given the same
        // charge two places to be set and one of them would have been wrong.

        $this->zoneRepo->zoneModuleSetupUpdate(id: $id, data: $paymentData, moduleData: $filteredModuleData);

        Toastr::success(translate('Updated successfully'));
        return redirect()->route('admin.business-settings.zone.home');
    }

    public function getInstruction(): View
    {
        session()->put('zone-instruction', 1);
        $zones = $this->zoneRepo->getWithCountLatest(
            relations: ['stores.vendor', 'deliverymen'],
            dataLimit: config('default_pagination')
        );
        $language = Helpers::active_extra_languages();
        $config = Helpers::get_business_settings('cash_on_delivery');
        $digital_payment = Helpers::get_business_settings('digital_payment');
        $offline_payment = Helpers::get_business_settings('offline_payment_status');
        // Z3 — the list disables the toggle for a zone that cannot be switched on, and says why.
        // Batched: two queries for the page, not two per row (rule 11).
        $readiness = $this->zoneService->readinessFor($zones);

        return view(ZoneViewPath::INDEX[VIEW], compact('zones', 'language', 'config', 'digital_payment', 'offline_payment', 'readiness'));
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        if (getEnvMode() == 'demo' && $request['id'] == 1) {
            Toastr::warning('Sorry!You can not inactive this zone!');
            return back();
        }

         $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $request->id],
        );

        if($zone->is_default && $request->status == 0){
            Toastr::warning('Sorry! This zone is set as default.You can not inactive this zone!');
            return back();
        }

        // DESIGN RULE Z3, as loosened by S19 — a zone may serve customers once AT LEAST ONE of
        // its connected modules can both be priced and be timed. The modules that cannot are no
        // longer a blocker; they are simply unavailable in the zone. This is the guard for a
        // direct hit on the URL; the list disables the toggle too.
        if ($request['status'] == 1 && $gaps = $zone->readinessGaps()) {
            Toastr::warning($this->readinessWarning($zone, $gaps));

            return back();
        }

        if ($request['status'] == 0) {
            // A storefront is served by its own domain and never re-checks the zone behind it,
            // so one left visible here would keep answering while every other route into the
            // store has gone. The toggle's confirmation says how many are affected before this
            // runs -- see _table_rows.blade.php. Nothing told an affected vendor their zone had
            // gone inactive until notifyVendorsOfDeactivation() -- new orders simply stopped
            // resolving into it (TC_18).
            $this->deactivateZone($zone);
        } else {
            $this->zoneRepo->update(id: $request['id'], data: ['status' => $request['status']]);
        }
        Toastr::success(translate('messages.Zone status updated'));

        // The confirm dialog in the list says this before the switch is flipped, but the
        // controller must not depend on the UI having shown it: a direct URL hit switches the
        // zone on just the same, and the admin is owed the same sentence.
        if ($request['status'] == 1 && $warning = $this->unavailableWarning($zone->fresh())) {
            Toastr::warning($warning);
        }

        return back();
    }

    public function updateDigitalPayment(Request $request): RedirectResponse
    {

        $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $request['id']],
            relations: ['modules']
        );

        if (count($zone->modules) == 0) {
            Toastr::error(translate('You must connect at least one module to enable digital payment'));
            return back();
        }

        if (!$request['digital_payment'] && $zone['offline_payment'] != 1 && $zone['cash_on_delivery'] != 1) {
            Toastr::error(translate('You must enable at least one payment method'));
            return back();

        }
        $this->zoneRepo->update(id: $request['id'], data: ['digital_payment' => $request['digital_payment']]);
        Toastr::success(translate('messages.Zone digital payment status updated'));
        return back();
    }

    public function updateCashOnDelivery(Request $request): RedirectResponse
    {
        $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $request['id']],
            relations: ['modules']
        );

        if (count($zone->modules) == 0) {
            Toastr::error(translate('You must connect at least one module to enable cash on delivery'));
            return back();
        }

        if (!$request['cash_on_delivery'] && $zone['offline_payment'] != 1 && $zone['digital_payment'] != 1) {
            Toastr::error(translate('You must enable at least one payment method'));
            return back();

        }
        $this->zoneRepo->update(id: $request['id'], data: ['cash_on_delivery' => $request['cash_on_delivery']]);
        Toastr::success(translate('messages.Zone cash on delivery status updated'));
        return back();
    }

    public function updateOfflinePayment(Request $request): RedirectResponse
    {
        $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $request['id']],
            relations: ['modules']
        );

        if (count($zone->modules) == 0) {
            Toastr::error(translate('You must connect at least one module to enable offline payment'));
            return back();
        }

        if (!$request['offline_payment'] && $zone['cash_on_delivery'] != 1 && $zone['digital_payment'] != 1) {
            Toastr::error(translate('You must enable at least one payment method'));
            return back();

        }
        $this->zoneRepo->update(id: $request['id'], data: ['offline_payment' => $request['offline_payment']]);
        Toastr::success(translate('messages.Zone offline payment status updated'));
        return back();
    }

    public function getCoordinates($id): JsonResponse
    {
        $zone = $this->zoneRepo->getWithCoordinateWhere(
            params: ['id' => $id]
        );
        $area = json_decode($zone['coordinates'][0]->toJson(), true);
        $data = $this->zoneService->formatCoordinates(coordinates: $area['coordinates']);
        $center = (object) ['lat' => (float) trim(explode(' ', $zone['center'])[1], 'POINT()'), 'lng' => (float) trim(explode(' ', $zone['center'])[0], 'POINT()')];
        return response()->json(['coordinates' => $data, 'center' => $center]);
    }

    public function getAllZoneCoordinates($id = 0): JsonResponse
    {
        $zones = $this->zoneRepo->getActiveListExcept(
            params: ['id' => $id]
        );

        $data = $this->zoneService->formatZoneCoordinates(zones: $zones);

        return response()->json($data);
    }

    public function get_zone(Request $request)
    {
        if (! is_numeric($request->lat) || ! is_numeric($request->lng)) {
            return response()->json(['errors' => [['code' => 'coordinates', 'message' => translate('No data found')]]], 404);
        }

        $zone = Helpers::getCoordinatesZone($request->lat, $request->lng);

        return response()->json($zone);
    }

    public function get_zones(Request $request): JsonResponse
    {
        $data = $this->zoneService->getSearchOptions($request->q)
            ->map(fn ($zone) => ['id' => $zone->id, 'text' => $zone->name]);

        if ($request->boolean('all')) {
            $data->prepend(['id' => 'all', 'text' => translate('All')]);
        }

        return response()->json($data);
    }

    public function checkLocation(Request $request): JsonResponse
    {
        if (! $request->filled(['latitude', 'longitude', 'zone_id'])) {
            return response()->json([
                'errors' => [
                    ['code' => 'validation', 'message' => translate('messages.Please select a location within the selected zone.')]
                ]
            ], 200);
        }

        if (! $this->zoneService->containsCoordinates($request->zone_id, $request->latitude, $request->longitude)) {
            return response()->json([
                'errors' => [
                    [
                        'code' => 'coordinates',
                        'message' => translate('messages.Please select a location within the selected zone.')
                    ]
                ]
            ], 200);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    public function defaultStatus($id){

        $zone = $this->zoneRepo->getFirstWhere(
            params: ['id' => $id]
        );

        // Making a zone default switches it ON, so it needs the same guard as the status toggle —
        // and the whole guard, not half of it. Checking only the delivery rule would let "make
        // default" put a zone live that the toggle beside it refuses to activate.
        if ($gaps = $zone->readinessGaps()) {
            Toastr::warning(translate($zone->readinessMessageKey($gaps)));

            // Same next-step prompt as TC_58's post-creation flow, not just the toast: this
            // request reloads the whole index page (no AJAX here), so the guide is flashed and
            // picked back up by index.blade.php the way a Connect-Module save already stashes it
            // across its own reload (sessionStorage) — see _connect-module-scripts.blade.php.
            return back()->with('zoneSetupGuide', $this->setupGuideData($zone));
        }

        $zone->is_default = 1;
        $zone->status = 1;
        $zone->save();

        $this->zoneService->clearDefaultExcept($id);
        Toastr::success(translate('messages.Zone default status updated'));

        // The default zone is where every customer without a location lands, so being told which
        // of its modules they will not be offered matters more here, not less.
        if ($warning = $this->unavailableWarning($zone->fresh())) {
            Toastr::warning($warning);
        }

        return back();
    }

    private function getAddView(Request $request): View
    {
        $zones = $this->zoneRepo->getListWhere(
            searchValue: $request['search'],
            dataLimit: config('default_pagination'),
            relations: ['stores.vendor', 'modules', 'deliverymen'],
        );
        $language = Helpers::active_extra_languages();

        $config = Helpers::get_business_settings('cash_on_delivery');
        $digital_payment = Helpers::get_business_settings('digital_payment');
        $offline_payment = Helpers::get_business_settings('offline_payment_status');

        // Z3 — the list disables the toggle for a zone that cannot be switched on, and says why.
        // Batched: two queries for the page, not two per row (rule 11).
        $readiness = $this->zoneService->readinessFor($zones);

        return view(ZoneViewPath::INDEX[VIEW], compact('zones', 'language', 'config', 'digital_payment', 'offline_payment', 'readiness'));
    }

}

