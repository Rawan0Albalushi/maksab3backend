<?php

namespace App\Http\Requests\Common\Zone;

class ZoneCheckRequest extends ZoneCoordinateRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'zone_id' => 'required',
        ]);
    }

    public function zoneId(): mixed
    {
        return $this->input('zone_id');
    }
}
