<?php

namespace App\Http\Requests\Common\System;

class DistanceRequest extends RouteRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'mode' => 'nullable|in:DRIVE,WALK',
        ]);
    }

    public function travelMode(): string
    {
        return $this->input('mode') ?? 'WALK';
    }
}
