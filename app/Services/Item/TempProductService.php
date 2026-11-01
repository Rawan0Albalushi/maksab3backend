<?php

namespace App\Services\Item;

use App\CentralLogics\Helpers;
use App\Models\Item;
use App\Models\TempProduct;
use App\Scopes\StoreScope;
use App\Services\BaseService;
use App\Traits\Item\ProductPayloadTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TempProductService extends BaseService
{
    use ProductPayloadTrait;

    private const LIST_COLUMNS = ['id', 'name', 'image', 'price', 'is_rejected', 'item_id', 'category_ids', 'video', 'video_link'];

    private const LIVE_ITEM_EXISTS = 'EXISTS (SELECT 1 FROM items WHERE items.id = temp_products.item_id AND items.is_approved = 1)';

    private const TAXONOMIES = [
        'resolved_tags' => [TagService::class, 'tag_ids', ['tag', 'id']],
        'resolved_nutritions' => [NutritionService::class, 'nutrition_ids', ['nutrition', 'id']],
        'resolved_allergies' => [AllergyService::class, 'allergy_ids', ['allergy', 'id']],
        'resolved_generics' => [GenericNameService::class, 'generic_ids', ['generic_name', 'id']],
    ];

    public function findForStore(mixed $id, mixed $storeId): ?TempProduct
    {
        return TempProduct::where('id', $id)->where('store_id', $storeId)->first();
    }

    public function getListForStore(array $filters = [], array $paginate = [], array $keywords = []): LengthAwarePaginator
    {
        $categoryId = $filters['category_id'] ?? 'all';
        $subCategoryId = $filters['sub_category_id'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $items = TempProduct::withStorage()
            ->when(is_numeric($categoryId), fn ($query) => $query->whereHas(
                'category',
                fn ($category) => $category->whereId($categoryId)->orWhere('parent_id', $categoryId)
            ))
            ->where('store_id', $filters['store_id'] ?? null)
            ->when($filters['name'] ?? null, fn ($query) => $query->where(function ($inner) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $inner->where('name', 'like', "%{$keyword}%");
                }
            }))
            ->when(is_numeric($subCategoryId), fn ($query) => $query->where('category_id', $subCategoryId))
            ->when($filters['store_category_id'] ?? null, fn ($query, $storeCategoryId) => $query->where('store_category_id', $storeCategoryId))
            ->when($status === 'pending', fn ($query) => $query->where('is_rejected', 0))
            ->when($status === 'rejected', fn ($query) => $query->where('is_rejected', 1))
            ->type($filters['type'] ?? 'all')
            ->latest()
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $this->attachCategoryNames($items->getCollection());

        return $items;
    }

    public function findDetailForStore(mixed $tempProductId, mixed $storeId): ?TempProduct
    {
        $product = TempProduct::with([
            'module.storage', 'store.storage', 'store.discount', 'store.storeConfig', 'storeCategory.storage',
            'pharmacy_item_details', 'ecommerce_item_details.brand', 'taxVats', 'seoData', 'translations',
            'unit', 'storage',
        ])
            ->where('id', $tempProductId)
            ->where('store_id', $storeId)
            ->first();

        if (! $product) {
            return null;
        }

        $models = new EloquentCollection([$product]);
        $this->attachCategoryNames($models);
        $this->attachTaxes($models);

        foreach (self::TAXONOMIES as $attribute => [$service, $column, $columns]) {
            $product->{$attribute} = app($service)->getByIds($this->decodedInput($product->{$column}), $columns);
        }

        return $product;
    }

    public function findItemId(mixed $tempProductId): mixed
    {
        return TempProduct::where('id', $tempProductId)->value('item_id');
    }

    public function countUnscopedForModule(mixed $moduleId): int
    {
        return TempProduct::withoutGlobalScope(StoreScope::class)->module($moduleId)->count();
    }

    public function findOrNewForItem(mixed $itemId): TempProduct
    {
        return TempProduct::firstOrNew(['item_id' => $itemId]);
    }

    public function adminApprovalFilters(array $input): array
    {
        return [
            'module_id' => $input['module_id'] ?? null,
            'search' => trim((string) ($input['search'] ?? '')),
            'store_id' => $this->adminApprovalId($input['store_id'] ?? null),
            'zone_id' => $this->adminApprovalId($input['zone_id'] ?? null),
            'category_id' => $this->adminApprovalId($input['category_id'] ?? null),
            'sub_category_id' => $this->adminApprovalId($input['sub_category_id'] ?? null),
            'status' => array_values(array_intersect(['pending', 'rejected'], (array) ($input['status'] ?? []))),
            'kind' => array_values(array_intersect(['new', 'update'], (array) ($input['kind'] ?? []))),
            'type' => array_values(array_intersect(['veg', 'non_veg'], (array) ($input['type'] ?? []))),
            'from_date' => $this->adminApprovalDate($input['from_date'] ?? null),
            'to_date' => $this->adminApprovalDate($input['to_date'] ?? null),
        ];
    }

    public function adminApprovalFilterCount(array $filters): int
    {
        return count(array_filter([
            $filters['store_id'],
            $filters['zone_id'],
            $filters['category_id'],
            $filters['sub_category_id'],
            $filters['status'],
            $filters['kind'],
            $filters['type'],
            $filters['from_date'],
            $filters['to_date'],
        ]));
    }

    public function adminApprovalList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        return $this->adminApprovalQuery($filters)
            ->withStorage()
            ->with([
                'store' => fn ($query) => $query->select(['id', 'name', 'zone_id'])
                    ->with(['zone' => fn ($zone) => $zone->select(['id', 'name'])]),
            ])
            ->select('temp_products.*')
            ->selectRaw(self::LIVE_ITEM_EXISTS.' as is_live_update')
            ->orderBy('is_rejected')
            ->orderByDesc('updated_at')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))
            ->withQueryString();
    }

    public function adminApprovalExportQuery(array $filters): mixed
    {
        return $this->adminApprovalQuery($filters)
            ->with(['unit', 'store', 'taxVats.tax'])
            ->orderBy('is_rejected')
            ->orderByDesc('updated_at');
    }

    public function adminApprovalSummary(array $filters): array
    {
        $row = $this->adminApprovalQuery(array_merge($filters, ['status' => [], 'kind' => []]))
            ->without('storeCategory')
            ->selectRaw('COUNT(*) as total_requests')
            ->selectRaw('SUM(CASE WHEN is_rejected = 1 THEN 1 ELSE 0 END) as rejected_requests')
            ->selectRaw('SUM(CASE WHEN '.self::LIVE_ITEM_EXISTS.' THEN 0 ELSE 1 END) as new_requests')
            ->selectRaw('MIN(CASE WHEN is_rejected = 0 THEN updated_at END) as oldest_pending')
            ->first();

        $total = (int) ($row->total_requests ?? 0);
        $rejected = (int) ($row->rejected_requests ?? 0);
        $new = (int) ($row->new_requests ?? 0);
        $oldest = $row?->oldest_pending;

        return [
            'total' => $total,
            'pending' => $total - $rejected,
            'rejected' => $rejected,
            'new' => $new,
            'update' => $total - $new,
            'oldest_pending_days' => $oldest ? (int) Carbon::parse($oldest)->startOfDay()->diffInDays(Carbon::now()->startOfDay()) : null,
        ];
    }

    private function adminApprovalQuery(array $filters): mixed
    {
        $keywords = array_values(array_filter(explode(' ', $filters['search'] ?? ''), fn ($word) => $word !== ''));
        $status = $filters['status'] ?? [];
        $kind = $filters['kind'] ?? [];
        $type = $filters['type'] ?? [];

        return TempProduct::withoutGlobalScope(StoreScope::class)
            ->when($filters['module_id'] ?? null, fn ($query, $moduleId) => $query->module($moduleId))
            ->when($filters['store_id'] ?? null, fn ($query, $id) => $query->where('store_id', $id))
            ->when($filters['sub_category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereHas('category',
                fn ($category) => $category->whereId($id)->orWhere('parent_id', $id)))
            ->when($filters['zone_id'] ?? null, fn ($query, $id) => $query->whereHas('store',
                fn ($store) => $store->where('zone_id', $id)))
            ->when($keywords, fn ($query) => $query->where(function ($outer) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $outer->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$keyword}%"));
                }
            }))
            ->when(count($status) === 1, fn ($query) => $query->where('is_rejected', $status[0] === 'rejected' ? 1 : 0))
            ->when(count($kind) === 1, fn ($query) => $query->whereRaw(
                ($kind[0] === 'new' ? 'NOT ' : '').self::LIVE_ITEM_EXISTS
            ))
            ->when(count($type) === 1, fn ($query) => $query->type($type[0]))
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('updated_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('updated_at', '<=', $date));
    }

    public function replacesLiveItem(TempProduct $temp): bool
    {
        return $temp->item_id
            && DB::table('items')->where('id', $temp->item_id)->where('is_approved', 1)->exists();
    }

    public function liveItemFor(TempProduct $temp): ?Item
    {
        return $temp->item_id
            ? Item::withStorage()->with(['unit', 'tags'])->where('is_approved', 1)->find($temp->item_id)
            : null;
    }

    public function pendingRequestIdFor(mixed $itemId): mixed
    {
        return TempProduct::withoutGlobalScope(StoreScope::class)->where('item_id', $itemId)->value('id');
    }

    public function approvalChanges(TempProduct $temp, ?Item $live): array
    {
        if (! $live) {
            return [];
        }

        $moduleType = $temp->module?->module_type;

        $rows = [
            $this->changeRow(translate('messages.Name'), $live->getRawOriginal('name'), $temp->getRawOriginal('name')),
            $this->changeRow(translate('messages.Description'),
                trim(strip_tags((string) $live->getRawOriginal('description'))),
                trim(strip_tags((string) $temp->getRawOriginal('description')))),
            $this->changeRow(translate('messages.Category'),
                $this->categoryPath($live->category_ids), $this->categoryPath($temp->category_ids)),
            $this->changeRow(translate('Unit price'),
                Helpers::format_currency($live->price), Helpers::format_currency($temp->price)),
            $this->changeRow(translate('Discount'),
                $this->discountLabel($live->discount, $live->discount_type),
                $this->discountLabel($temp->discount, $temp->discount_type)),
            $this->changeRow(translate('messages.Maximum cart quantity'),
                $live->maximum_cart_quantity, $temp->maximum_cart_quantity),
            $this->changeRow(translate('messages.Tags'),
                $live->tags->pluck('tag')->sort()->implode(', '),
                Helpers::tags_by_ids(Helpers::decodeJsonToArray($temp->tag_ids))->pluck('tag')->sort()->implode(', ')),
        ];

        if (config('module.'.$moduleType.'.stock')) {
            $rows[] = $this->changeRow(translate('messages.Total stock'),
                max((int) $live->stock, 0), max((int) $temp->stock, 0));
            $rows[] = $this->changeRow(translate('Unit'), $live->unit?->unit, $temp->unit?->unit);
        }

        if (config('module.'.$moduleType.'.veg_non_veg')) {
            $rows[] = $this->changeRow(translate('messages.Item type'),
                $this->vegLabel($live->veg), $this->vegLabel($temp->veg));
        }

        if (config('module.'.$moduleType.'.organic')) {
            $rows[] = $this->changeRow(translate('Is organic'),
                $this->yesNoLabel($live->organic), $this->yesNoLabel($temp->organic));
        }

        if (config('module.'.$moduleType.'.item_available_time')) {
            $rows[] = $this->changeRow(translate('messages.Available time'),
                $this->timeRangeLabel($live->available_time_starts, $live->available_time_ends),
                $this->timeRangeLabel($temp->available_time_starts, $temp->available_time_ends));
        }

        if ((string) $live->image !== (string) $temp->image) {
            $rows[] = [
                'label' => translate('Item image'),
                'from' => $live->image_full_url,
                'to' => $temp->image_full_url,
                'kind' => 'image',
            ];
        }

        $rows[] = $this->changeRow(translate('messages.Gallery images'),
            count($live->images ?? []), count($temp->images ?? []));

        $variationColumn = $moduleType === 'food' ? 'food_variations' : 'variations';
        $rows[] = $this->editedRow(translate('messages.Variations'),
            $live->{$variationColumn}, $temp->{$variationColumn});

        if (config('module.'.$moduleType.'.add_on')) {
            $rows[] = $this->editedRow(translate('Addons'), $live->add_ons, $temp->add_ons);
        }

        return array_values(array_filter($rows));
    }

    private function changeRow(string $label, mixed $from, mixed $to): ?array
    {
        $from = trim((string) $from);
        $to = trim((string) $to);

        if ($from === $to) {
            return null;
        }

        return ['label' => $label, 'from' => $from, 'to' => $to, 'kind' => 'text'];
    }

    private function editedRow(string $label, mixed $from, mixed $to): ?array
    {
        if ($this->normalisedJson($from) === $this->normalisedJson($to)) {
            return null;
        }

        return ['label' => $label, 'from' => null, 'to' => null, 'kind' => 'edited'];
    }

    private function normalisedJson(mixed $value): string
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return json_encode($decoded ?: []);
    }

    private function categoryPath(mixed $categoryIds): string
    {
        $parent = Helpers::get_category_name($categoryIds);
        $child = Helpers::get_sub_category_name($categoryIds);

        return $child && $child !== 'NA' ? $parent.' › '.$child : (string) $parent;
    }

    private function discountLabel(mixed $discount, ?string $type): string
    {
        return $type === 'percent'
            ? rtrim(rtrim(number_format((float) $discount, 2, '.', ''), '0'), '.').'%'
            : Helpers::format_currency($discount);
    }

    private function vegLabel(mixed $veg): string
    {
        return $veg ? translate('Veg') : translate('Non veg');
    }

    private function yesNoLabel(mixed $value): string
    {
        return $value ? translate('messages.Yes') : translate('messages.No');
    }

    private function timeRangeLabel(?string $starts, ?string $ends): string
    {
        if (! $starts || ! $ends) {
            return '';
        }

        $format = config('timeformat');

        return date($format, strtotime($starts)).' - '.date($format, strtotime($ends));
    }

    private function adminApprovalId(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function adminApprovalDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function decodedInput(mixed $value): array
    {
        return is_array($value) ? $value : Helpers::decodeJsonToArray($value);
    }
}
