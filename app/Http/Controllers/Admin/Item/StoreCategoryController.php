<?php

namespace App\Http\Controllers\Admin\Item;

use App\CentralLogics\Helpers;
use App\Exports\StoreCategoryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAddRequest;
use App\Http\Requests\StoreCategoryUpdateRequest;
use App\Models\Store;
use App\Models\StoreCategory;
use App\Services\Store\StoreCategoryService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreCategoryController extends Controller
{
    public function __construct(protected StoreCategoryService $service)
    {
        $this->middleware(function ($request, $next) {
            if (!Helpers::storeCategoryStatus()) {
                Toastr::warning(translate('messages.Store category feature is disabled'));
                return back();
            }
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $filters = $this->service->adminListFilters(array_merge($request->all(), [
            'module_id' => Config::get('module.current_module_id'),
        ]));

        $categories = $this->service->buildQuery($filters)
            ->withStorage()
            ->with(['store' => fn ($query) => $query->select(['id', 'name', 'zone_id'])->with('zone:id,name')])
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->all());

        $summary = $this->service->adminSummary($filters);
        $filter_count = $this->service->adminListFilterCount($filters);
        $translated_locales = $this->service->getTranslatedLocales($categories->pluck('id')->all());
        $store = $filters['store_id'] ? Store::find($filters['store_id']) : null;
        $language = getWebConfig('language');

        return view('admin-views.store-category.index', compact(
            'categories',
            'language',
            'filters',
            'summary',
            'filter_count',
            'translated_locales',
            'store'
        ));
    }

    public function store(StoreCategoryAddRequest $request): RedirectResponse
    {
        $category = $this->service->create(
            storeId: (int) $request->store_id,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, ['lang' => $request->lang, 'name' => $request->name]);

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        $category = StoreCategory::withoutGlobalScope('translate')
            ->with(['translations', 'store'])
            ->findOrFail($id);
        $language = getWebConfig('language');

        return response()->json([
            'view' => view('admin-views.store-category._edit', compact('category', 'language'))->render(),
        ]);
    }

    public function update(StoreCategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $category = StoreCategory::findOrFail($id);
        $this->service->update(
            category: $category,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image'),
            storeId: (int) $request->store_id
        );
        $this->service->saveFormTranslations($category, ['lang' => $request->lang, 'name' => $request->name]);

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->service->updateStatus(StoreCategory::findOrFail($request['id']), (int) $request['status']);
        Toastr::success(translate('messages.Store category status updated'));
        return back();
    }

    public function updatePriority(Request $request, $id): RedirectResponse
    {
        $this->service->updatePriority(StoreCategory::findOrFail($id), (int) ($request->priority ?? 0));
        Toastr::success(translate('messages.Store category priority updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->service->delete(StoreCategory::findOrFail($request['id']));
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function getByStore(Request $request): JsonResponse
    {
        $storeId = (int) $request->store_id;

        $categories = StoreCategory::active()
            ->where('store_id', $storeId)
            ->orderBy('priority', 'desc')
            ->get(['id', 'name'])
            ->makeHidden('image_full_url');

        $hasCategories = $storeId
            ? \App\CentralLogics\Helpers::hasAnyStoreCategory($storeId)
            : false;

        return response()->json([
            'categories' => $categories,
            'has_categories' => $hasCategories,
        ]);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $filters = $this->service->adminListFilters(array_merge($request->query(), [
            'module_id' => Config::get('module.current_module_id'),
        ]));

        $categories = $this->service->buildQuery($filters)->with('store')->latest()->get();

        $data = [
            'data' => $categories,
            'search' => $filters['search'],
            'categoryWiseTax' => false,
            'showStore' => true,
        ];

        $extension = $request->query('type') === 'csv' ? 'csv' : 'xlsx';
        return Excel::download(new StoreCategoryExport($data), 'StoreCategories.' . $extension);
    }
}
