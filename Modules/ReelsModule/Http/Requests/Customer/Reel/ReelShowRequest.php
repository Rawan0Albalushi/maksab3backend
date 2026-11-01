<?php

namespace Modules\ReelsModule\Http\Requests\Customer\Reel;

class ReelShowRequest extends ReelRequest
{
    public function rules(): array
    {
        return [
            'reel_id' => 'required|integer|exists:reels,id',
            'guest_id' => $this->guestRule(),
            'stream' => 'nullable|boolean',
        ];
    }

    public function wantsStream(): bool
    {
        return $this->boolean('stream') || $this->headers->has('Range');
    }
}
