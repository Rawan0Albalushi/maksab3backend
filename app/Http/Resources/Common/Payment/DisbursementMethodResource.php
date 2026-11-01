<?php

namespace App\Http\Resources\Common\Payment;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class DisbursementMethodResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return array_merge($this->resource->toArray(), [
            'method_fields' => $this->userInputRows($this->resource->method_fields),
        ]);
    }

    private function userInputRows(mixed $methodFields): array
    {
        $fields = is_array($methodFields) ? $methodFields : json_decode((string) $methodFields, true);

        $rows = [];
        foreach ($fields ?? [] as $key => $value) {
            $rows[] = ['user_input' => $key, 'user_data' => $value];
        }

        return $rows;
    }
}
