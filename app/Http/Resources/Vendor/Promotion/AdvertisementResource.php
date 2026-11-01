<?php

namespace App\Http\Resources\Vendor\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AdvertisementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->id,
            'add_type' => $this->add_type,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'status' => $this->status,
            'active' => $this->active,
            'priority' => $this->priority,
            'pause_note' => $this->pause_note,
            'cancellation_note' => $this->cancellation_note,
            'is_rating_active' => $this->is_rating_active,
            'is_review_active' => $this->is_review_active,
            'is_paid' => $this->is_paid,
            'is_updated' => $this->is_updated,
            'cover_image_full_url' => $this->cover_image_full_url,
            'profile_image_full_url' => $this->profile_image_full_url,
            'video_attachment_full_url' => $this->video_attachment_full_url,
            'created_at' => $this->created_at,
            'translations' => $this->whenLoaded('translations'),
        ]);
    }
}
