<?php

namespace App\Http\Controllers\Vendor\Promotion;

use App\CentralLogics\Helpers;
use App\Exports\BundleExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\Promotion\BundleStoreRequest;
use App\Models\Store;
use App\Services\Promotion\BundleService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class BundleController extends Controller
{
    private const ROUTE_PREFIX = 'vendor.bundle';

    private const VIEW_PATH = 'vendor-views.promotions.bundle';

    private ?Store $ownStore = null;

    public function __construct(private readonly BundleService $service)
    {
    }

    public function index(Request $request): View
    {
        return view(self::VIEW_PATH.'.list', $this->service->panelList(
            search: $request->input('search'),
            moduleId: $this->moduleId(),
            storeId: $this->storeId(),
            routePrefix: self::ROUTE_PREFIX,
        ));
    }

    public function create(): View
    {
        return view(self::VIEW_PATH.'.create', $this->service->formData(
            bundle: null,
            stores: $this->selectableStores(),
            moduleId: $this->moduleId(),
            storeId: $this->storeId(),
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
            $this->storeId(),
            $request->input('search'),
            $this->moduleId(),
        ), 200);
    }

    public function store(BundleStoreRequest $request): JsonResponse
    {
        $this->service->ensureModuleAllowed($this->moduleId());
        $this->service->ensureOwnStore($this->storeId(), (int) $request->input('store_id'));

        $this->service->create($request, createdBy: 'vendor');

        return response()->json(['redirect' => route(self::ROUTE_PREFIX.'.list')], 200);
    }

    public function edit(string $id): View
    {
        $bundle = $this->findOwned($id, translations: true)->load('items');

        return view(self::VIEW_PATH.'.edit', $this->service->formData(
            bundle: $bundle,
            stores: $this->selectableStores(),
            moduleId: $this->moduleId(),
            storeId: $this->storeId(),
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
        $data = $this->service->detailData($this->findOwned($id), self::ROUTE_PREFIX, showStore: false);

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
        $bundles = $this->service->exportRows($request->input('search'), $this->moduleId(), $this->storeId());

        return Excel::download(
            new BundleExport(['data' => $bundles, 'search' => $request->input('search'), 'showStore' => false, 'ownerLabel' => $this->service->ownerLabel($this->moduleId())]),
            'Bundle.'.($request->input('type') === 'csv' ? 'csv' : 'xlsx')
        );
    }

    private function ownStore(): Store
    {
        return $this->ownStore ??= Helpers::get_store_data() ?? abort(404);
    }

    private function moduleId(): ?int
    {
        return $this->ownStore()->module_id;
    }

    private function storeId(): ?int
    {
        return $this->ownStore()->id;
    }

    private function selectableStores(): Collection
    {
        return collect([$this->ownStore()->only(['id', 'name'])])
            ->map(fn (array $row) => (object) $row);
    }

    private function findOwned(string $id, bool $translations = false)
    {
        return $this->service->findOwnedOrFail($id, $this->moduleId(), $this->storeId(), $translations);
    }
}
