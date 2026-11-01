<?php

namespace App\Http\Resources\DeliveryMan\Disbursement;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DisbursementResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $payload = $this->resource->toArray();

        if (isset($payload['withdraw_method']['method_fields']) && is_string($payload['withdraw_method']['method_fields'])) {
            $payload['withdraw_method']['method_fields'] = json_decode($payload['withdraw_method']['method_fields'], true);
        }

        return $payload;
    }
}
