<?php

namespace App\Http\Controllers\Admin\Promotion;

use App\Exports\BundleExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Promotion\BundleStoreRequest;
use App\Models\Store;
use App\Scopes\ZoneScope;
use App\Services\Promotion\BundleService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BundleController extends Controller
{
    private const ROUTE_PREFIX = 'admin.bundle';

    private const VIEW_PATH = 'admin-views.promotions.bundle';

    public function __construct(private readonly BundleService $service)
    {
    }

    public function index(Request $request): View
    {
        return view(self::VIEW_PATH.'.list', $this->service->panelList(
            search: $request->input('search'),
            moduleId: $this->moduleId(),
            storeId: null,
            routePrefix: self::ROUTE_PREFIX,
        ));
    }

    public function create(): View
    {
        return view(self::VIEW_PATH.'.create', $this->service->formData(
            bundle: null,
            stores: $this->selectableStores(),
            moduleId: $this->moduleId(),
            storeId: null,
            routePrefix: self::ROUTE_PREFIX,
            action: route(self::ROUTE_PREFIX.'.store'),
            heading: translate('Create product bundle'),
            submitLabel: translate('messages.Submit'),
            successMessage: translate('Added successfully'),
        ));
    }

    public function items(Request $request): JsonResponse
    {
        return response()->json($this->service->pickerOptions(
            (int) $request->input('store_id'),
            $request->input('search'),
            $this->moduleId(),
        ), 200);
    }

    public function store(BundleStoreRequest $request): JsonResponse
    {
        $this->service->ensureModuleAllowed($this->moduleId());

        $this->service->create($request, createdBy: 'admin');

        return response()->json(['redirect' => route(self::ROUTE_PREFIX.'.list')], 200);
    }

    public function edit(string $id): View
    {
        $bundle = $this->findOwned($id, translations: true)->load(['items', 'store:id,name']);

        return view(self::VIEW_PATH.'.edit', $this->service->formData(
            bundle: $bundle,
            stores: $this->selectableStores(),
            moduleId: $this->moduleId(),
            storeId: null,
            routePrefix: self::ROUTE_PREFIX,
            action: route(self::ROUTE_PREFIX.'.update', $bundle->id),
            heading: translate('Update product bundle'),
            submitLabel: translate('messages.Submit'),
            successMessage: translate('Updated successfully'),
        ));
    }

    public function update(BundleStoreRequest $request, string $id): JsonResponse
    {
        $this->service->modify($this->findOwned($id), $request);

        return response()->json(['redirect' => route(self::ROUTE_PREFIX.'.list')], 200);
    }

    public function show(Request $request, string $id): View
    {
        $data = $this->service->detailData($this->findOwned($id), self::ROUTE_PREFIX, showStore: true);

        return $request->ajax()
            ? view('partials.bundle._detail_drawer', $data)
            : view(self::VIEW_PATH.'.view', $data);
    }

    public function updateStatus(string $id, int $status): RedirectResponse
    {
        $this->service->setStatus($this->findOwned($id), $status);

        Toastr::success(translate('messages.Bundle status updated'));

        return back();
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->service->delete($this->findOwned($id));

        Toastr::success(translate('Deleted successfully'));

        return redirect()->route(self::ROUTE_PREFIX.'.list');
    }

    public function exportList(Request $request)
    {
        $bundles = $this->service->exportRows($request->input('search'), $this->moduleId(), null);

        return Excel::download(
            new BundleExport(['data' => $bundles, 'search' => $request->input('search'), 'showStore' => true, 'ownerLabel' => $this->service->ownerLabel($this->moduleId())]),
            'Bundle.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    private function moduleId(): ?int
    {
        return Config::get('module.current_module_id');
    }

    private function selectableStores(): Collection
    {
        return Store::withoutGlobalScope(ZoneScope::class)
            ->where('module_id', $this->moduleId())
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function findOwned(string $id, bool $translations = false)
    {
        return $this->service->findOwnedOrFail($id, $this->moduleId(), null, $translations);
    }
}
