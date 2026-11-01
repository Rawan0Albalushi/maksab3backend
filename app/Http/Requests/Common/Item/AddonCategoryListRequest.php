<?php

namespace App\Http\Requests\Common\Item;

use App\Http\Requests\BaseRequest;

class AddonCategoryListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'module_id' => 'required',
        ];
    }

    public function filters(): array
    {
        return [
            'module_id' => $this->input('module_id'),
        ];
    }

}
