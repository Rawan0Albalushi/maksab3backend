<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Exports\StoreCategoryExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryAddRequest;
use App\Http\Requests\StoreCategoryUpdateRequest;
use App\Models\Item;
use App\Models\StoreCategory;
use App\Services\Store\StoreCategoryService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreCategoryController extends Controller
{
    public function __construct(protected StoreCategoryService $service)
    {
        $this->middleware(function ($request, $next) {
            if (!Helpers::vendorCategoryStatus()) {
                Toastr::warning(translate('messages.Store category feature is disabled'));
                return back();
            }
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
        $categories = $this->service->buildQuery([
            'search' => $request['search'] ?? null,
            'priority' => $request->query('priority'),
            'store_id' => Helpers::get_store_id(),
        ])
            ->withStorage()
            ->latest()
            ->paginate(config('default_pagination'))
            ->appends($request->all());

        $language = getWebConfig('language');

        return view('vendor-views.store-category.index', compact('categories', 'language'));
    }

    public function create(Request $request): JsonResponse|RedirectResponse
    {
        if (!$request->ajax()) {
            return redirect()->route('vendor.store-category.list');
        }

        $language = getWebConfig('language');
        $category = null;

        return response()->json([
            'view' => view('vendor-views.store-category._form', compact('category', 'language'))->render(),
        ]);
    }

    public function store(StoreCategoryAddRequest $request): RedirectResponse
    {
        $category = $this->service->create(
            storeId: Helpers::get_store_id(),
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, ['lang' => $request->lang, 'name' => $request->name]);

        Toastr::success(translate('Added successfully'));

        return redirect()->route('vendor.store-category.list', ['assign_items' => $category->id]);
    }

    public function getUpdateView(Request $request, string|int $id): JsonResponse|RedirectResponse
    {
        if (!$request->ajax()) {
            return redirect()->route('vendor.store-category.list');
        }

        $category = $this->ownedQuery()
            ->withoutGlobalScope('translate')
            ->with('translations')
            ->findOrFail($id);
        $language = getWebConfig('language');

        return response()->json([
            'view' => view('vendor-views.store-category._form', compact('category', 'language'))->render(),
        ]);
    }

    public function update(StoreCategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $category = $this->ownedQuery()->findOrFail($id);
        $this->service->update(
            category: $category,
            name: $request->name[0],
            priority: $request->filled('priority') ? (int) $request->priority : 0,
            image: $request->file('image')
        );
        $this->service->saveFormTranslations($category, ['lang' => $request->lang, 'name' => $request->name]);

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->service->updateStatus($this->ownedQuery()->findOrFail($request['id']), (int) $request['status']);
        Toastr::success(translate('messages.Store category status updated'));
        return back();
    }

    public function updatePriority(Request $request, $id): RedirectResponse
    {
        $this->service->updatePriority($this->ownedQuery()->findOrFail($id), (int) ($request->priority ?? 0));
        Toastr::success(translate('messages.Store category priority updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->service->delete($this->ownedQuery()->findOrFail($request['id']));
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function getAll(Request $request): JsonResponse
    {
        $categories = $this->ownedQuery()
            ->where('status', 1)
            ->when($request->q, function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            })
            ->orderBy('priority', 'desc')
            ->limit(20)
            ->get(['id', 'name as text'])->makeHidden('image_full_url');

        return response()->json($categories);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $categories = $this->service->buildQuery([
            'search' => $request->query('search'),
            'priority' => $request->query('priority'),
            'store_id' => Helpers::get_store_id(),
        ])->latest()->get();

        $data = [
            'data' => $categories,
            'search' => $request->query('search'),
            'categoryWiseTax' => false,
        ];

        $extension = $request->query('type') === 'csv' ? 'csv' : 'xlsx';
        return Excel::download(new StoreCategoryExport($data), 'StoreCategories.' . $extension);
    }

    private function ownedQuery()
    {
        return StoreCategory::where('store_id', Helpers::get_store_id());
    }

    public function assignItemsView(string|int $id, Request $request): JsonResponse
    {
        $storeId = Helpers::get_store_id();
        $category = $this->ownedQuery()->findOrFail($id);

        $items = $this->queryAssignableItems((int) $category->id, $request->input('search'))->get();
        $unassignedCount = $this->queryAssignableItems((int) $category->id)
            ->whereNull('store_category_id')
            ->count();
        $isService = $this->isServiceModule();

        return response()->json([
            'view' => view('vendor-views.store-category._assign_items', compact(
                'category',
                'items',
                'unassignedCount',
                'isService'
            ))->render(),
        ]);
    }

    public function searchAssignableItems(string|int $id, Request $request): JsonResponse
    {
        $category = $this->ownedQuery()->findOrFail($id);
        $items = $this->queryAssignableItems((int) $category->id, $request->input('search'))->get();

        return response()->json([
            'view' => view('vendor-views.store-category._assign_items_list', [
                'category' => $category,
                'items' => $items,
                'isService' => $this->isServiceModule(),
            ])->render(),
        ]);
    }

    public function storeAssignedItems(string|int $id, Request $request): JsonResponse
    {
        $request->validate([
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'integer',
        ]);

        $storeId = Helpers::get_store_id();
        $category = $this->ownedQuery()->findOrFail($id);

        $submittedIds = collect($request->input('item_ids', []))
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $model = $this->bindableModel();

        $allowedNewIds = [];
        if (!empty($submittedIds)) {
            $allowedNewIds = $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $submittedIds)
                ->where(function ($q) use ($category) {
                    $q->whereNull('store_category_id')
                      ->orWhere('store_category_id', $category->id);
                })
                ->pluck('id')
                ->all();
        }

        if (!empty($allowedNewIds)) {
            $model::query()
                ->where('store_id', $storeId)
                ->whereIn('id', $allowedNewIds)
                ->update(['store_category_id' => $category->id]);
        }

        $model::query()
            ->where('store_id', $storeId)
            ->where('store_category_id', $category->id)
            ->when(!empty($allowedNewIds), fn ($q) => $q->whereNotIn('id', $allowedNewIds))
            ->update(['store_category_id' => null]);

        return response()->json([
            'success' => true,
            'message' => $this->isServiceModule()
                ? translate('messages.Services assigned successfully')
                : translate('messages.Items assigned successfully'),
            'assigned_count' => count($allowedNewIds),
        ]);
    }

    private function isServiceModule(): bool
    {
        return Helpers::get_store_data()?->module_type === 'service' && addon_published_status('Service');
    }

    /** @return class-string<\Illuminate\Database\Eloquent\Model> */
    private function bindableModel(): string
    {
        return $this->isServiceModule() ? \Modules\Service\Entities\Service::class : Item::class;
    }

    private function queryAssignableItems(int $categoryId, ?string $search = null)
    {
        $storeId = Helpers::get_store_id();
        $model = $this->bindableModel();

        return $model::query()
            ->where('store_id', $storeId)
            ->where(function ($q) use ($categoryId) {
                $q->whereNull('store_category_id')
                  ->orWhere('store_category_id', $categoryId);
            })
            ->when($search, function ($q) use ($search) {
                $term = '%' . trim($search) . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                          ->orWhere('id', 'like', $term);
                });
            })
            ->orderByDesc('id');
    }
}
