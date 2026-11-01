<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

use App\Http\Requests\BaseRequest;

class ImageAnalysisRequest extends BaseRequest
{
    private const MAX_KILOBYTES = 1024;

    public function rules(): array
    {
        return [
            'image' => $this->imageRule('required', self::MAX_KILOBYTES),
        ];
    }
}
