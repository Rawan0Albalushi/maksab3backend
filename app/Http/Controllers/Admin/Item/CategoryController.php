<?php

namespace App\Http\Controllers\Admin\Item;

use App\CentralLogics\Helpers;
use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Category;
use App\Enums\ViewPaths\Admin\Category as CategoryViewPath;
use App\Exports\CategoryExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CategoryAddRequest;
use App\Http\Requests\Admin\CategoryBulkExportRequest;
use App\Http\Requests\Admin\CategoryBulkImportRequest;
use App\Http\Requests\Admin\CategoryUpdateRequest;
use App\Services\Item\CategoryService;
use App\Traits\Report\ImportExportTrait;
use Brian2694\Toastr\Facades\Toastr;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Modules\TaxModule\Entities\SystemTaxSetup;
use Modules\TaxModule\Entities\Taxable;
use OpenSpout\Common\Exception\InvalidArgumentException;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryController extends BaseController
{
    use ImportExportTrait;

    public function __construct(
        protected CategoryRepositoryInterface $categoryRepo,
        protected CategoryService $categoryService,
        protected TranslationRepositoryInterface $translationRepo
    ) {}

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getCategoryView($request);
    }

    private function categoryTaxPayer(): string
    {
        return Config::get('module.current_module_type') === 'service' ? 'service_provider' : 'vendor';
    }

    private function categoryTaxSetup()
    {
        return SystemTaxSetup::where('is_active', 1)
            ->where('tax_payer', $this->categoryTaxPayer())
            ->where('is_default', 1)
            ->first();
    }

    private function getCategoryView(Request $request): View
    {
        $status = $request->query('status', 'all');
        $position = $request['position'] ?? 0;
        $filters = ['position' => $position];
        if ($status === 'active') {
            $filters['status'] = 1;
        } elseif ($status === 'inactive') {
            $filters['status'] = 0;
        }

        $isSubCategory = $position == 1;
        $isAjax = $request->ajax();
        $taxData = $isSubCategory
            ? ['categoryWiseTax' => false, 'taxVats' => []]
            : Helpers::getTaxSystemType(getTaxVatList: !$isAjax, tax_payer: $this->categoryTaxPayer());
        $categoryWiseTax = $taxData['categoryWiseTax'];

        $relations = [];
        if ($isSubCategory) {
            $relations = ['parent'];
        } elseif ($categoryWiseTax) {
            $relations = ['taxVats.tax'];
        }

        $categories = $this->categoryRepo->getListWhere(
            searchValue: $request['search'],
            filters: $filters,
            relations: $relations,
            dataLimit: config('default_pagination'),
            withStorage: !$isSubCategory
        );

        $listData = $this->getListColumnData($categories, isSubCategory: $isSubCategory);

        if ($isAjax) {
            return view($isSubCategory
                ? 'admin-views.category.partials._list-sub'
                : 'admin-views.category.partials._list-main',
                array_merge(compact('categories', 'status', 'categoryWiseTax'), $listData));
        }

        $mainCategories = $isSubCategory
            ? $this->categoryRepo->getMainList(filters: ['position' => 0], withStorage: false)
            : collect();

        $language = getWebConfig('language');
        $taxVats = $taxData['taxVats'];

        return view($this->viewByPosition($position), array_merge(
            compact('categories', 'language', 'mainCategories', 'categoryWiseTax', 'taxVats', 'status'),
            $listData
        ));
    }

    /**
     * Per-row aggregates for the category lists. Kept out of the paginator query so each lookup
     * stays one query over the page's ids rather than a subquery per row.
     *
     * A sub category has no children, so it gets no sub-category count; and its items are counted
     * over items.category_id rather than top_category_id — see CategoryService::getItemCounts().
     */
    private function getListColumnData(LengthAwarePaginator $categories, bool $isSubCategory): array
    {
        $categoryIds = $categories->pluck('id')->all();

        return [
            'subCategoryCounts' => $isSubCategory
                ? []
                : $this->categoryService->getSubCategoryCounts(categoryIds: $categoryIds),
            'itemCounts' => $this->categoryService->getItemCounts(
                categoryIds: $categoryIds,
                column: $isSubCategory ? 'category_id' : 'top_category_id'
            ),
            'translatedLocales' => $this->categoryService->getTranslatedLocales(categoryIds: $categoryIds),
        ];
    }

    private function viewByPosition(int $position): string
    {
        return match ($position) {
            1 => CategoryViewPath::SUB_CATEGORY_INDEX['view'],
            default => CategoryViewPath::INDEX['view'],
        };
    }

    public function add(CategoryAddRequest $request): RedirectResponse
    {
        $parentCategory = $this->categoryRepo->getFirstWhere(params: ['id' => $request['parent_id']]);
        $category = $this->categoryRepo->add(
            data: $this->categoryService->getAddData($request->all(),
                parentCategory: $parentCategory
            )
        );
        $this->translationRepo->addByModel(request: $request, model: $category, modelPath: 'App\Models\Category', attribute: 'name');

        if (addon_published_status('TaxModule')) {
            $SystemTaxVat = $this->categoryTaxSetup();
            if ($SystemTaxVat?->tax_type == 'category_wise') {

                foreach ($request['tax_ids'] ?? [] as $tax_ids) {
                    Taxable::create(
                        [
                            'taxable_type' => 'App\Models\Category',
                            'taxable_id' => $category->id,
                            'system_tax_setup_id' => $SystemTaxVat->id, 'tax_id' => $tax_ids,
                        ],
                    );
                }

            }
        }

        Toastr::success($request['position'] == 0 ? translate('Added successfully') : translate('Added successfully'));

        return back();
    }

    public function getUpdateView(string|int $id): JsonResponse
    {
        // `parent` eager loaded: the edit panel names the main category a sub sits under, and
        // AdminLazyLoadSweepTest fails a view that reaches for it lazily.
        $category = $this->categoryRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id], relations: ['parent']);
        $language = getWebConfig('language');

        $taxData = Helpers::getTaxSystemType(tax_payer: $this->categoryTaxPayer());
        $categoryWiseTax = $taxData['categoryWiseTax'];
        $taxVats = $taxData['taxVats'];
        $taxVatIds = $categoryWiseTax ? $category->taxVats()->pluck('tax_id')->toArray() : [];

        return response()->json([
            'view' => view('admin-views.category._edit', compact('category', 'taxVats', 'categoryWiseTax', 'language', 'taxVatIds'))->render(),
        ]);
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['id'], data: ['status' => $request['status']]);
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['id']]);
        Toastr::success(translate($category && $category->position == 1 ? 'messages.sub_category_status_updated' : 'messages.category_status_updated'));

        return back();
    }

    public function updateFeatured(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['id'], data: ['featured' => $request['featured']]);
        Toastr::success(translate('messages.Category featured updated'));

        return back();
    }

    public function update(CategoryUpdateRequest $request, string|int $id): RedirectResponse
    {
        $mainCategory = $this->categoryRepo->getFirstWhere(params: ['id' => $id]);
        $category = $this->categoryRepo->update(id: $id, data: $this->categoryService->getUpdateData($request->all(), object: $mainCategory));
        $this->translationRepo->updateByModel(request: $request, model: $category, modelPath: 'App\Models\Category', attribute: 'name');

        if (addon_published_status('TaxModule') && $category['position'] == 0) {
            $taxVatIds = $category->taxVats()->pluck('tax_id')->toArray() ?? [];
            $newTaxVatIds = array_map('intval', $request['tax_ids'] ?? []);
            sort($newTaxVatIds);
            sort($taxVatIds);
            if ($newTaxVatIds != $taxVatIds) {
                $category->taxVats()->delete();
                $SystemTaxVat = $this->categoryTaxSetup();
                if ($SystemTaxVat?->tax_type == 'category_wise') {
                    foreach ($request['tax_ids'] ?? [] as $tax_ids) {
                        Taxable::create(
                            [
                                'taxable_type' => 'App\Models\Category',
                                'taxable_id' => $category->id,
                                'system_tax_setup_id' => $SystemTaxVat->id, 'tax_id' => $tax_ids,
                            ],
                        );
                    }

                }
            }
        }

        Toastr::success($category['position'] == 0 ? translate('Updated successfully') : translate('Updated successfully'));

        return redirect()->route('admin.category.add', ['position' => $mainCategory->position]);
    }

    public function delete(Request $request): RedirectResponse
    {
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['id']]);
        $isSubCategory = $category && $category->position == 1;

        if ($this->categoryRepo->delete(id: $request['id'])) {
            Toastr::success(translate($isSubCategory ? 'messages.Deleted successfully' : 'messages.Deleted successfully'));
        } else {
            Toastr::warning(translate('Remove subcategories first'));
        }

        return back();
    }

    public function getNameList(Request $request): JsonResponse
    {
        $data = $this->categoryRepo->getNameList(request: $request, dataLimit: 8);
        $data[] = (object) ['id' => 'all', 'text' => translate('All')];

        return response()->json($data);
    }

    public function updatePriority(Request $request): RedirectResponse
    {
        $this->categoryRepo->update(id: $request['category'], data: ['priority' => $request['priority']]);
        $category = $this->categoryRepo->getFirstWhere(params: ['id' => $request['category']]);
        Toastr::success(translate($category && $category->position == 1 ? 'messages.Updated successfully' : 'messages.Updated successfully'));

        return back();
    }

    public function getBulkImportView(): View
    {
        return view(CategoryViewPath::BULK_IMPORT['view'], [
            'summary' => $this->categoryRepo->getBulkDataSummary(),
            'parents' => $this->categoryRepo->getMainList(filters: ['position' => 0], withStorage: false)->sortBy('id'),
        ]);
    }

    public function importBulkData(CategoryBulkImportRequest $request): RedirectResponse
    {
        $data = $this->categoryService->getImportData(file: $request->file('products_file'));

        if (array_key_exists('flag', $data) && $data['flag'] == 'wrong_format') {
            Toastr::error(translate('messages.You have uploaded a wrong format file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'required_fields') {
            Toastr::error(translate('messages.Please fill all required fields'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_position') {
            Toastr::error(translate('messages.Invalid category position in file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_parent') {
            Toastr::error(translate('messages.Invalid parent category in file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'duplicate_name') {
            Toastr::error(translate('messages.Duplicate category name in file'));

            return back();
        }

        try {
            DB::beginTransaction();
            $this->categoryRepo->addByChunk(data: $data);
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.Failed to import data'));

            return back();
        }

        Toastr::success(translate('messages.category_imported_successfully'));

        return back();
    }

    public function updateBulkData(CategoryBulkImportRequest $request): RedirectResponse
    {
        $data = $this->categoryService->getImportData(file: $request->file('products_file'), toAdd: false);

        if (array_key_exists('flag', $data) && $data['flag'] == 'wrong_format') {
            Toastr::error(translate('messages.You have uploaded a wrong format file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'required_fields') {
            Toastr::error(translate('messages.Please fill all required fields'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_position') {
            Toastr::error(translate('messages.Invalid category position in file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'invalid_parent') {
            Toastr::error(translate('messages.Invalid parent category in file'));

            return back();
        }

        if (array_key_exists('flag', $data) && $data['flag'] == 'duplicate_name') {
            Toastr::error(translate('messages.Duplicate category name in file'));

            return back();
        }

        try {
            DB::beginTransaction();
            $this->categoryRepo->updateByChunk(data: $data);
            DB::commit();
        } catch (Exception) {
            DB::rollBack();
            Toastr::error(translate('messages.Failed to import data'));

            return back();
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function getBulkExportView(): View
    {
        return view(CategoryViewPath::BULK_EXPORT['view'], [
            'summary' => $this->categoryRepo->getBulkDataSummary(),
        ]);
    }

    /**
     * @throws IOException
     * @throws WriterNotOpenedException
     * @throws UnsupportedTypeException
     * @throws InvalidArgumentException
     */
    public function exportBulkData(CategoryBulkExportRequest $request): StreamedResponse|string
    {
        $categories = $this->categoryRepo->getBulkExportList(request: $request);

        return (new FastExcel($this->categoryService->getExportData(collection: $this->exportGenerator(data: $categories))))->download(Category::EXPORT_XLSX);
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $categories = $this->categoryRepo->getExportList(request: $request);
        $isSubCategory = (int) ($request['position'] ?? 0) === 1;

        $taxData = Helpers::getTaxSystemType();
        $categoryWiseTax = $taxData['categoryWiseTax'];

        $data = [
            'data' => $categories,
            'search' => $request['search'] ?? null,
            'categoryWiseTax' => $categoryWiseTax,
            'module' => Config::get('module.current_module_type'),
            'isSubCategory' => $isSubCategory,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new CategoryExport($data), $isSubCategory ? Category::SUB_EXPORT_CSV : Category::EXPORT_CSV);
        }

        return Excel::download(new CategoryExport($data), $isSubCategory ? Category::SUB_EXPORT_XLSX : Category::EXPORT_XLSX);
    }
}
