<?php

namespace Modules\ReelsModule\Http\Requests\Customer\Reel;

class ReelLikeRequest extends ReelRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user('api');
    }

    public function rules(): array
    {
        return [
            'reel_id' => 'required|integer|exists:reels,id',
        ];
    }
}
