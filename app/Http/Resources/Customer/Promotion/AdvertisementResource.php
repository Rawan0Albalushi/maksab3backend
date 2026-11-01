<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AdvertisementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'add_type' => $this->add_type,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'is_rating_active' => $this->is_rating_active,
            'is_review_active' => $this->is_review_active,
            'cover_image_full_url' => $this->cover_image_full_url,
            'profile_image_full_url' => $this->profile_image_full_url,
            'video_attachment_full_url' => $this->video_attachment_full_url,
            'average_rating' => $this->when(isset($this->average_rating), fn () => $this->average_rating),
            'reviews_comments_count' => $this->when(isset($this->reviews_comments_count), fn () => $this->reviews_comments_count),
            'verified_seller' => (int) (data_get($this->store, 'verified_seller') ?? 0),
            'store' => $this->store,
        ]);
    }
}
