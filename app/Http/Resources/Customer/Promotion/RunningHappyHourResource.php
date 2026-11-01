<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * The home-screen banner: the window running now, plus what the countdown ticks down from.
 *
 * Wraps the {happy_hour, window} pair HappyHourCustomerService::findRunning() returns. The window
 * half is computed -- a countdown and a store count -- and arrives already resolved, because a
 * resource may not query for it.
 *
 * `is_running` is answered even when nothing is on, so the client can poll this and simply hide
 * the banner rather than treating an empty result as an error.
 */
class RunningHappyHourResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $happyHour = $this->resource['happy_hour'] ?? null;
        $window = $this->resource['window'] ?? [];

        if (! $happyHour) {
            return array_merge(parent::toArray($request), [
                'is_running' => false,
                'happy_hour' => null,
            ]);
        }

        return array_merge(parent::toArray($request), [
            'is_running' => true,
            'happy_hour' => (new HappyHourResource($happyHour))->render() + [
                'started_at' => $window['started_at'] ?? null,
                'ends_at' => $window['ends_at'] ?? null,
                'remaining_seconds' => (int) ($window['remaining_seconds'] ?? 0),
                'store_count' => (int) ($window['store_count'] ?? 0),
            ],
        ]);
    }
}
