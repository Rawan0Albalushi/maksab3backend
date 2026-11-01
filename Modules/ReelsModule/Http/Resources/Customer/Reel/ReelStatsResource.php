<?php

namespace Modules\ReelsModule\Http\Resources\Customer\Reel;

use Illuminate\Http\Request;
use Modules\ReelsModule\Http\Resources\BaseReelResource;

class ReelStatsResource extends BaseReelResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), $this->counters(), [
            'is_liked' => (int) $this->liked(),
            'liked' => $this->liked(),
        ]);
    }

    private function liked(): bool
    {
        return (bool) ($this->resource->is_liked ?? false);
    }
}
