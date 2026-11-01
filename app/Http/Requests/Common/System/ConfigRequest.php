<?php

namespace App\Http\Requests\Common\System;

use App\Http\Requests\BaseRequest;

/**
 * `/config`.
 *
 * No rules — the endpoint takes no input — but it is where the request's HEADERS become plain
 * scalars, which is what rule 2 asks of them. `/config` is scoped by the same `zoneId` and
 * `moduleId` headers every other customer endpoint uses, and free delivery moved to a per
 * (zone, module) setup in S6, so the payload cannot be built without them.
 */
class ConfigRequest extends BaseRequest
{
    /**
     * The zone ids this request is scoped to, in the order the client sent them.
     *
     * The header carries a JSON array because a coordinate can fall inside several overlapping
     * zones. Order matters: §10.3 says the FIRST zone with a setup wins, so this must not be
     * sorted or de-duplicated into a different order.
     *
     * @return list<int>
     */
    public function zoneIds(): array
    {
        $decoded = json_decode((string) $this->header('zoneId'), true);

        return array_values(array_filter(
            array_map('intval', is_array($decoded) ? $decoded : [$decoded]),
        ));
    }

    public function moduleId(): mixed
    {
        return getModuleId($this->header('moduleId'));
    }
}
