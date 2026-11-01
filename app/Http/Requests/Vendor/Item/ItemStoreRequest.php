<?php

namespace App\Http\Requests\Vendor\Item;

use App\Rules\ImageFile;
use Illuminate\Validation\Rule;

class ItemStoreRequest extends ItemFormRequest
{
    public function rules(): array
    {
        return parent::rules() + [
            'image' => ImageFile::rules(Rule::requiredIf(fn () => $this->vendorStore()?->module?->module_type !== 'food')),
            'translations' => 'required',
        ];
    }
}
