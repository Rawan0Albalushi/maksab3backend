<?php

namespace App\Http\Requests\Common\System;

use App\Http\Requests\BaseRequest;

class RouteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'origin_lat' => 'required|numeric|between:-90,90',
            'origin_lng' => 'required|numeric|between:-180,180',
            'destination_lat' => 'required|numeric|between:-90,90',
            'destination_lng' => 'required|numeric|between:-180,180',
        ];
    }

    public function origin(): array
    {
        return ['lat' => $this->input('origin_lat'), 'lng' => $this->input('origin_lng')];
    }

    public function destination(): array
    {
        return ['lat' => $this->input('destination_lat'), 'lng' => $this->input('destination_lng')];
    }

    public function travelMode(): string
    {
        return strtoupper($this->input('mode') ?? 'DRIVE');
    }
}
