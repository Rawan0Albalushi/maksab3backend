<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

class ReelUpdateRequest extends ReelStoreRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'reel_id' => 'required|integer|exists:reels,id',
            'thumbnail' => $this->imageRule(),
            'video' => $this->videoRule('nullable', $this->maxUploadSizeMb() * 1024),
        ]);
    }

    protected function excludedReelId(): ?int
    {
        return (int) $this->input('reel_id') ?: null;
    }
}
