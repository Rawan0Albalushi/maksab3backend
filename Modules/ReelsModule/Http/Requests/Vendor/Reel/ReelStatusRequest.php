<?php

namespace Modules\ReelsModule\Http\Requests\Vendor\Reel;

class ReelStatusRequest extends ReelIdRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'status' => 'required|boolean',
        ]);
    }
}
