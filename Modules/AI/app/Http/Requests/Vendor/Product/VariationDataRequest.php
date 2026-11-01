<?php

namespace Modules\AI\app\Http\Requests\Vendor\Product;

use Illuminate\Contracts\Validation\Validator;
use Modules\AI\app\Traits\ConversationTrait;

class VariationDataRequest extends GeneralAndPriceDataRequest
{
    use ConversationTrait;

    public function withValidator(Validator $validator): void
    {
        $this->descriptionEmptyValidation($this->input('description'), $validator);
    }
}
