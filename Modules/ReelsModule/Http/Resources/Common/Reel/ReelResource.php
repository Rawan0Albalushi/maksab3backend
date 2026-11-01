<?php

namespace Modules\ReelsModule\Http\Resources\Common\Reel;

use Illuminate\Http\Request;
use Modules\ReelsModule\Http\Resources\BaseReelResource;

class ReelResource extends BaseReelResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'id' => $this->resource->id,
            'description' => $this->resource->description,
        ], $this->productReferences(), [
            'order_now_button' => $this->orderNowButton(),
            'video_url' => $this->videoUrl(),
            'thumbnail_url' => $this->resource->thumbnail_full_url,
            'store' => [
                'id' => $this->resource->store?->id,
                'name' => $this->resource->store?->name,
                'logo_full_url' => $this->resource->store?->logo_full_url,
                'verified_seller' => $this->verifiedSeller(),
            ],
        ], $this->productBlocks(withStock: true), $this->counters(), [
            'translations' => $this->translationRows(),
        ]);
    }

    private function videoUrl(): ?string
    {
        $disk = $this->resource->storage->firstWhere('key', 'video')?->value ?? 'public';

        return $disk === 'public'
            ? route('customer.reels.show', ['reel_id' => $this->resource->id, 'stream' => 1])
            : $this->resource->video_full_url;
    }
}
