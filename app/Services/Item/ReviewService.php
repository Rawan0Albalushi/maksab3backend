<?php

namespace App\Services\Item;

use App\Models\Review;
use App\Scopes\StoreScope;
use App\Services\BaseService;
use App\Traits\Customer\PersonalizationTrait;
use App\Traits\Item\ItemRatingTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use App\Support\Storage\FileStorage;

class ReviewService extends BaseService
{
    use ItemRatingTrait;
    use PersonalizationTrait;

    private const ATTACHMENT_DIR = 'review';

    private const BREAKDOWN_LABELS = [5 => 'excellent', 4 => 'good', 3 => 'average', 2 => 'below', 1 => 'poor'];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->forItemQuery($filters['item_id'] ?? null)
            ->active()
            ->with(['customer' => fn ($query) => $query->select('id', 'f_name', 'l_name', 'image')->withStorage()])
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function ratingSummary(mixed $itemId): array
    {
        $rows = $this->forItemQuery($itemId)
            ->active()
            ->selectRaw('FLOOR(rating) as star, COUNT(*) as total')
            ->groupBy('star')
            ->pluck('total', 'star');

        $counts = [];
        $weighted = 0;

        foreach (array_keys(self::BREAKDOWN_LABELS) as $star) {
            $counts[$star] = (int) ($rows[$star] ?? 0);
            $weighted += $counts[$star] * $star;
        }

        $totalReviews = array_sum($counts);

        return [
            'avg_rating' => (float) ($totalReviews > 0 ? round($weighted / $totalReviews, 1) : 0),
            'total_reviews' => $totalReviews,
            'breakdown' => array_map(
                fn ($star) => ['label' => self::BREAKDOWN_LABELS[$star], 'star' => $star, 'count' => $counts[$star]],
                array_keys(self::BREAKDOWN_LABELS)
            ),
        ];
    }

    public function overallRating(mixed $itemId): float
    {
        return (float) self::calculateOverallRating(
            $this->forItemQuery($itemId)->get(['id', 'item_id', 'rating'])
        )[0];
    }

    public function alreadyReviewed(mixed $itemId, mixed $userId, mixed $orderId): bool
    {
        return Review::where(['item_id' => $itemId, 'user_id' => $userId, 'order_id' => $orderId])->exists();
    }

    public function create(array $data): Review
    {
        return DB::transaction(function () use ($data) {
            $review = new Review;
            $review->user_id = $data['user_id'];
            $review->item_id = $data['item_id'];
            $review->order_id = $data['order_id'];
            $review->module_id = $data['module_id'];
            $review->comment = $data['comment'] ?? null;
            $review->rating = $data['rating'];
            $review->attachment = json_encode(FileStorage::uploadAttachments(self::ATTACHMENT_DIR, $data['attachment'] ?? []));
            $review->save();

            $this->recordItemAction($data['user_id'], (int) $data['item_id'], 'review');

            return $review;
        });
    }

    public function getStoreReviewList(mixed $storeId, array $paginate = []): LengthAwarePaginator
    {
        return Review::with([
            'customer' => fn ($query) => $query->select('id', 'f_name', 'l_name', 'image')->withStorage(),
            'item' => fn ($query) => $query->withStorage()->with('translations'),
            'item.unit', 'item.store.storage', 'item.store.discount', 'item.store.storeConfig', 'item.module',
        ])
            ->whereHas('item', fn ($query) => $query->where('store_id', $storeId))
            ->active()
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getVendorList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $storeId = $filters['store_id'] ?? null;
        $search = $filters['search'] ?? null;
        $keywords = explode(' ', (string) $search);

        return Review::with(['customer.storage', 'item' => fn ($item) => $item->without('storeCategory')->withStorage()])
            ->whereHas('item', fn ($item) => $item->where('store_id', $storeId))
            ->when($search, fn ($query) => $query->where(function ($outer) use ($keywords, $search) {
                $outer->whereHas('item', function ($item) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $item->where('name', 'like', "%{$keyword}%");
                    }
                })->orWhereHas('customer', function ($customer) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $customer->where('f_name', 'like', "%{$keyword}%")->orWhere('l_name', 'like', "%{$keyword}%");
                    }
                })->orWhere('rating', $search)->orWhere('review_id', $search);
            }))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function updateReply(array $data, mixed $storeId): ?Review
    {
        $review = Review::whereHas('item', fn ($item) => $item->where('store_id', $storeId))
            ->where('id', $data['id'])
            ->first();

        if (! $review) {
            return null;
        }

        $review->reply = $data['reply'];
        $review->replied_at = now();
        $review->store_id = $storeId;
        $review->save();

        return $review;
    }

    public function adminFilters(array $input): array
    {
        return [
            'module_id' => $input['module_id'] ?? null,
            'search' => trim((string) ($input['search'] ?? '')),
            'rating' => array_values(array_filter(
                array_map('intval', (array) ($input['rating'] ?? [])),
                fn ($rating) => $rating >= 1 && $rating <= 5
            )),
            'visibility' => array_values(array_intersect(['visible', 'hidden'], (array) ($input['visibility'] ?? []))),
            'reply' => array_values(array_intersect(['replied', 'awaiting'], (array) ($input['reply'] ?? []))),
            'from_date' => $this->adminFilterDate($input['from_date'] ?? null),
            'to_date' => $this->adminFilterDate($input['to_date'] ?? null),
        ];
    }

    public function adminFilterCount(array $filters): int
    {
        return count(array_filter([
            $filters['rating'],
            $filters['visibility'],
            $filters['reply'],
            $filters['from_date'],
            $filters['to_date'],
        ]));
    }

    public function adminList(array $filters, array $paginate = []): LengthAwarePaginator
    {
        return $this->adminQuery($filters)
            ->with(['item.storage', 'item.store', 'customer.storage'])
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate))
            ->withQueryString();
    }

    public function adminExportQuery(array $filters): Builder
    {
        return $this->adminQuery($filters)
            ->with(['item.store', 'customer'])
            ->latest()
            ->orderBy('id');
    }

    public function adminSummary(array $filters): array
    {
        $row = $this->adminQuery(array_merge($filters, ['rating' => [], 'visibility' => [], 'reply' => []]))
            ->selectRaw('COUNT(*) as total_reviews')
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating')
            ->selectRaw('SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as visible_count')
            ->selectRaw("SUM(CASE WHEN reply IS NULL OR reply = '' THEN 0 ELSE 1 END) as replied_count")
            ->selectRaw('SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as star_5')
            ->selectRaw('SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as star_4')
            ->selectRaw('SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as star_3')
            ->selectRaw('SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as star_2')
            ->selectRaw('SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as star_1')
            ->first();

        $total = (int) ($row->total_reviews ?? 0);
        $visible = (int) ($row->visible_count ?? 0);
        $replied = (int) ($row->replied_count ?? 0);
        $breakdown = [];

        foreach ([5, 4, 3, 2, 1] as $star) {
            $count = (int) ($row->{'star_'.$star} ?? 0);
            $breakdown[$star] = [
                'count' => $count,
                'percent' => $total > 0 ? round($count * 100 / $total) : 0,
            ];
        }

        return [
            'total' => $total,
            'average' => $total > 0 ? round((float) $row->average_rating, 1) : 0.0,
            'visible' => $visible,
            'hidden' => $total - $visible,
            'replied' => $replied,
            'awaiting' => $total - $replied,
            'breakdown' => $breakdown,
        ];
    }

    private function adminQuery(array $filters): Builder
    {
        $search = $filters['search'] ?? '';
        $keywords = array_values(array_filter(explode(' ', $search), fn ($word) => $word !== ''));
        $visibility = $filters['visibility'] ?? [];
        $reply = $filters['reply'] ?? [];

        return Review::query()
            ->whereHas('item', fn ($item) => $item
                ->where('module_id', $filters['module_id'] ?? null)
                ->withoutGlobalScope(StoreScope::class))
            ->when($keywords, fn ($query) => $query->where(function ($outer) use ($keywords, $search) {
                $outer->whereHas('item', function ($item) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $item->where('name', 'like', "%{$keyword}%");
                    }
                })->orWhereHas('customer', function ($customer) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $customer->where(fn ($name) => $name
                            ->where('f_name', 'like', "%{$keyword}%")
                            ->orWhere('l_name', 'like', "%{$keyword}%"));
                    }
                })->orWhere('review_id', $search);

                if (is_numeric($search)) {
                    $outer->orWhere('rating', $search);
                }
            }))
            ->when($filters['rating'] ?? [], fn ($query, $ratings) => $query->whereIn('rating', $ratings))
            ->when(count($visibility) === 1, fn ($query) => $query->where('status', $visibility[0] === 'visible' ? 1 : 0))
            ->when(count($reply) === 1, fn ($query) => $reply[0] === 'replied'
                ? $query->whereNotNull('reply')->where('reply', '!=', '')
                : $query->where(fn ($pending) => $pending->whereNull('reply')->orWhere('reply', '')))
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }

    private function adminFilterDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function forItemQuery(mixed $itemId): mixed
    {
        return Review::where('item_id', $itemId);
    }
}
