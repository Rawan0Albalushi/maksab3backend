<?php

namespace App\Services\DeliveryMan;

use App\Models\DMReview;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Storage\FileStorage;

class DmReviewService extends BaseService
{

    private const ATTACHMENT_DIR = 'review';

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return DMReview::with(['customer.storage', 'delivery_man.storage'])
            ->where('delivery_man_id', $filters['delivery_man_id'] ?? null)
            ->active()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function countByRating(mixed $deliveryManId, mixed $rating): int
    {
        return DMReview::where(['delivery_man_id' => $deliveryManId, 'rating' => $rating])->count();
    }

    public function averageRating(mixed $deliveryManId): float
    {
        $row = DMReview::where('delivery_man_id', $deliveryManId)
            ->toBase()
            ->selectRaw('SUM(rating) as total, COUNT(*) as reviews')
            ->first();

        if (! $row->total) {
            return 0;
        }

        return (float) number_format($row->total / $row->reviews, 2);
    }

    public function alreadyReviewed(array $filters = []): bool
    {
        return DMReview::where([
            'delivery_man_id' => $filters['delivery_man_id'] ?? null,
            'user_id' => $filters['user_id'] ?? null,
            'order_id' => $filters['order_id'] ?? null,
        ])->exists();
    }

    public function create(array $data): DMReview
    {
        $review = new DMReview;
        $review->user_id = $data['user_id'];
        $review->delivery_man_id = $data['delivery_man_id'];
        $review->order_id = $data['order_id'];
        $review->comment = $data['comment'];
        $review->rating = $data['rating'];
        $review->attachment = json_encode(FileStorage::uploadAttachments(self::ATTACHMENT_DIR, $data['attachment'] ?? []));
        $review->save();

        return $review;
    }
}
