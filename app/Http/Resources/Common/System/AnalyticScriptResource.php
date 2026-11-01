<?php

namespace App\Http\Resources\Common\System;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class AnalyticScriptResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'type' => $this->resource->type,
            'script_id' => $this->resource->script_id,
        ]);
    }
}
