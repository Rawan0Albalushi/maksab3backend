<?php

namespace Modules\ReelsModule\Http\Requests\Customer\Reel;

class ReelStatsRequest extends ReelRequest
{
    public function rules(): array
    {
        return [
            'reel_id' => 'required|integer|exists:reels,id',
        ];
    }
}
