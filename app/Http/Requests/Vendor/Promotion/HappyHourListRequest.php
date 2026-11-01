<?php

namespace App\Http\Requests\Vendor\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The store app's happy hour list. Same tabs as BOGO, minus nothing -- a happy hour reaches the
 * same five enrolment states even though it carries no selection.
 */
class HappyHourListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string|in:all,not_joined,pending,admin_requested,approved,rejected',
        ];
    }

    public function filters(): array
    {
        return [
            'search' => $this->input('search'),
            'type' => $this->input('type', 'all'),
        ];
    }
}
