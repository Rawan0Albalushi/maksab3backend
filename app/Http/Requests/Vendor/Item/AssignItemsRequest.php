<?php

namespace App\Http\Requests\Vendor\Item;

use App\Http\Requests\BaseRequest;

class AssignItemsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'category_id' => 'required|integer',
            'item_ids' => 'nullable|string',
        ];
    }

    public function itemIds(): array
    {
        $raw = $this->input('item_ids', []);

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        return collect($raw)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
    }
}
