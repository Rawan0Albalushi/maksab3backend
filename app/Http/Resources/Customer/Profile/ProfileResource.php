<?php

namespace App\Http\Resources\Customer\Profile;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProfileResource extends BaseResource
{
    public function __construct(mixed $resource, private readonly array $extras = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => (int) $this->resource->id,
            'f_name' => $this->resource->f_name,
            'l_name' => $this->resource->l_name,
            'phone' => $this->resource->phone,
            'email' => $this->resource->email,
            'image' => $this->resource->image,
            'image_full_url' => $this->resource->image_full_url,
            'is_phone_verified' => (int) $this->resource->is_phone_verified,
            'is_email_verified' => (int) $this->resource->is_email_verified,
            'email_verified_at' => $this->resource->email_verified_at,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'status' => (int) $this->resource->status,
            'pro_status' => $this->resource->pro_status,
            'login_medium' => $this->resource->login_medium,
            'zone_id' => $this->resource->zone_id,
            'wallet_balance' => (float) $this->resource->wallet_balance,
            'loyalty_point' => (int) $this->resource->loyalty_point,
            'ref_code' => $this->resource->ref_code,
            'ref_by' => $this->resource->ref_by,
            'current_language_key' => $this->resource->current_language_key,
            'module_ids' => $this->resource->module_ids,
            'is_from_pos' => (int) $this->resource->is_from_pos,
            'userinfo' => $this->userInfo(),
            'order_count' => $this->extras['order_count'] ?? 0,
            'member_since_days' => $this->extras['member_since_days'] ?? 0,
            'selected_modules_for_interest' => $this->extras['selected_modules_for_interest'] ?? [],
            'is_valid_for_discount' => $this->extras['is_valid_for_discount'] ?? false,
            'discount_amount' => $this->extras['discount_amount'] ?? 0,
            'discount_amount_type' => $this->extras['discount_amount_type'] ?? '',
            'validity' => $this->extras['validity'] ?? '',
            'pro_subscription' => $this->extras['pro_subscription'] ?? null,
            'average_rating' => $this->extras['rating']['average_rating'] ?? 0,
            'total_review' => $this->extras['rating']['total_review'] ?? 0,
        ]);
    }

    private function userInfo(): ?array
    {
        $info = $this->resource->relationLoaded('userinfo') ? $this->resource->userinfo : null;

        if (! $info) {
            return null;
        }

        return [
            'id' => (int) $info->id,
            'f_name' => $info->f_name,
            'l_name' => $info->l_name,
            'phone' => $info->phone,
            'image_full_url' => $info->image_full_url,
        ];
    }
}
