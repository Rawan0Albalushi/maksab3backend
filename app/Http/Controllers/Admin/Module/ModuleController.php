<?php

namespace App\Http\Controllers\Admin\Module;

use App\Contracts\Repositories\ModuleRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Module;
use App\Enums\ViewPaths\Admin\Module as ModuleViewPath;
use App\Exports\ModuleExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\ModuleAddRequest;
use App\Http\Requests\Admin\ModuleUpdateRequest;
use App\Services\Item\ItemService;
use App\Services\Store\StoreService;
use App\Services\System\ModuleService;
use App\Services\Zone\ZoneService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ModuleController extends BaseController
{
    public function __construct(
        protected ModuleRepositoryInterface $moduleRepo,
        protected ModuleService $moduleService,
        protected StoreService $storeService,
        protected ItemService $itemService,
        protected ZoneService $zoneService,
        protected TranslationRepositoryInterface $translationRepo
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getListView($request);
    }

    public function add(ModuleAddRequest $request): RedirectResponse
    {
        $module = $this->moduleRepo->add(data: $this->moduleService->getAddData($request->all()));
        $this->translationRepo->addByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'module_name');
        $this->translationRepo->addByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'description');
        $this->translationRepo->addByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'short_description');

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function getAddView(): View
    {
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(ModuleViewPath::ADD[VIEW], compact('language','defaultLang'));
    }

    public function getUpdateView(string|int $id): View|RedirectResponse
    {
        if(getEnvMode()=='demo' && in_array($id, [1,2,3,4,5]))
        {
            Toastr::warning(translate('messages.You can not edit this module please add a new module to edit'));
            return back();
        }

        $module = $this->moduleRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        return view(ModuleViewPath::UPDATE[VIEW], compact('module','language','defaultLang'));
    }

    public function update(ModuleUpdateRequest $request, $id): RedirectResponse
    {
        if(getEnvMode()=='demo' && in_array($id, [1,2,3,4,5]))
        {
            Toastr::warning(translate('messages.You can not edit this module please add a new module to edit'));
            return back();
        }
        $module = $this->moduleRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $module = $this->moduleRepo->update(id: $id ,data: $this->moduleService->getUpdateData($request->all(),module: $module));
        $this->translationRepo->updateByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'module_name');
        $this->translationRepo->updateByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'description');
        $this->translationRepo->updateByModel(request: $request, model: $module, modelPath: 'App\Models\Module', attribute: 'short_description');

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->moduleRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);
        Toastr::success(translate('messages.Module status updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->moduleRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function show($id): JsonResponse
    {
        $module = $this->moduleRepo->getFirstWhere(params: ['id' => $id]);
        return response()->json(['data'=>config('module.'.$module['module_type']),'type'=>$module['module_type']]);
    }

    public function getType(Request $request): JsonResponse
    {
        return response()->json(['data'=>config('module.'.$request['module_type'])]);
    }

    public function search(Request $request): JsonResponse
    {
        $modules = $this->moduleRepo->getSearchListWhere(
            searchValue: $request['search'],
            relations: ['storage'],
            dataLimit: 50
        );
        $metrics = $this->moduleMetrics($modules->pluck('id')->all());
        return response()->json([
            'view'=>view(ModuleViewPath::SEARCH[VIEW],compact('modules', 'metrics'))->render(),
            'count'=>$modules->count()
        ]);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $collection = $this->moduleRepo->getExportList($request);

        $data=[
            'data' =>$collection,
            'search' =>$request['search'] ?? null,
            'module_type' =>($request['module_type'] && $request['module_type'] != 'all') ? $request['module_type'] : null,
            'status' =>($request['status'] !== null && $request['status'] !== '' && $request['status'] != 'all') ? $request['status'] : null,
        ];
        if($request['type'] == 'csv'){
            return Excel::download(new ModuleExport($data), Module::EXPORT_CSV);
        }
        return Excel::download(new ModuleExport($data), Module::EXPORT_XLSX);
    }

    private function getListView(Request $request): View
    {
        $modules = $this->moduleRepo->getListWhere(
            searchValue: $request['search'],
            filters: $this->getListFilters($request),
            relations: ['storage'],
            dataLimit: config('default_pagination')
        );
        $metrics = $this->moduleMetrics($modules->pluck('id')->all());
        // The summary strip describes the whole catalogue, so it takes only the
        // add-on filters — narrowing it with the pickers would just restate the
        // row count the table already shows.
        $summary = $this->moduleRepo->getStatusSummary($this->getAddonFilters());
        return view(ModuleViewPath::INDEX[VIEW], compact('modules', 'metrics', 'summary'));
    }

    private function getListFilters(Request $request): array
    {
        $filters = [];

        if ($request['module_type'] && $request['module_type'] != 'all') {
            $filters[] = ['module_type', '=', $request['module_type']];
        }

        if ($request['status'] !== null && $request['status'] !== '' && $request['status'] != 'all') {
            $filters[] = ['status', '=', $request['status']];
        }

        return array_merge($filters, $this->getAddonFilters());
    }

    /*
     * Module types that belong to an unlicensed add-on are hidden everywhere on
     * this screen — list, summary and export — so the counts always match the
     * rows an admin can actually see.
     */
    private function getAddonFilters(): array
    {
        $filters = [];

        if (!addon_published_status('Rental')) {
            $filters[] = ['module_type', '!=', 'rental'];
        }

        if (!addon_published_status('RideShare')) {
            $filters[] = ['module_type', '!=', 'ride-share'];
        }

        return $filters;
    }

    /*
     * Three grouped queries for the whole page, not three per row: stores and
     * vendors, catalogue depth, and zone coverage — each keyed by module id.
     */
    private function moduleMetrics(array $moduleIds): array
    {
        $storeMetrics = $this->storeService->getMetricsByModule($moduleIds);
        $itemMetrics = $this->itemService->getCountsByModule($moduleIds);
        $zoneCounts = $this->zoneService->getCountsByModule($moduleIds);
        $metrics = [];

        foreach ($moduleIds as $moduleId) {
            $metrics[$moduleId] = array_merge(
                ['stores' => 0, 'active_stores' => 0, 'vendors' => 0, 'items' => 0, 'active_items' => 0],
                $storeMetrics[$moduleId] ?? [],
                $itemMetrics[$moduleId] ?? [],
                ['zones' => $zoneCounts[$moduleId] ?? 0]
            );
        }

        return $metrics;
    }

}
