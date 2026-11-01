<?php

namespace App\Http\Requests\Customer\Promotion;

use App\Http\Requests\BaseRequest;

/**
 * The happy hour stores list.
 *
 * `running` is the only filter: without it the list is every participating store carrying an
 * `is_running_now` flag, so one endpoint drives both a "happy hour stores" rail and a live-only
 * view. Accepted as a boolean-ish because clients send 1, "1" and true interchangeably.
 */
class HappyHourStoreListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'limit' => 'nullable|integer|min:1',
            'offset' => 'nullable|integer|min:1',
            'running' => 'nullable|boolean',
        ];
    }

    public function filters(): array
    {
        return [
            'running_only' => $this->boolean('running'),
        ];
    }
}
