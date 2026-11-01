<?php

namespace App\Http\Requests\Common\Zone;

use App\Http\Requests\BaseRequest;

class ZoneCoordinateRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ];
    }

    public function latitude(): mixed
    {
        return $this->input('lat');
    }

    public function longitude(): mixed
    {
        return $this->input('lng');
    }
}
