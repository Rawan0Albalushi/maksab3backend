<?php

namespace App\Traits\Payment;

use App\Models\WithdrawalMethod;

trait WithdrawalMethodFieldsTrait
{
    protected function withdrawalMethodFieldValues(WithdrawalMethod $method, array $values): array
    {
        $fieldValues = [];

        foreach (array_column($method->method_fields, 'input_name') as $field) {
            if (array_key_exists($field, $values)) {
                $fieldValues[$field] = $values[$field];
            }
        }

        return $fieldValues;
    }
}
