<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

class ReelIdRequest extends ReelRequest
{
    public function rules(): array
    {
        return [
            'reel_id' => 'required|integer|exists:reels,id',
        ];
    }
}
