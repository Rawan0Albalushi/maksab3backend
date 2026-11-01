<?php

namespace Modules\ReelsModule\Http\Resources\Customer\Reel;

use Illuminate\Http\Request;
use Modules\ReelsModule\Http\Resources\BaseReelResource;

class ReelListResource extends BaseReelResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'reel_id' => $this->resource->id,
            'description' => $this->resource->description,
            'thumbnail_full_url' => $this->resource->thumbnail_full_url,
            'store_id' => $this->resource->store_id,
            'store_name' => $this->resource->store?->name,
            'store_logo_full_url' => $this->resource->store?->logo_full_url,
        ], $this->productReferences(), [
            'order_now_button' => $this->orderNowButton(),
        ], $this->productBlocks(), [
            'verified_seller' => $this->verifiedSeller(),
            'stats' => (new ReelStatsResource($this->resource))->toArray($request),
        ]);
    }
}
