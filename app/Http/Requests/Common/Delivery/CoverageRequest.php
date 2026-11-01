<?php

namespace App\Http\Requests\Common\Delivery;

use App\Http\Requests\BaseRequest;

/**
 * GET /delivery-charge/coverage-list — what a customer must pick from, for a (zone, module).
 *
 * The module id comes from the header like everywhere else in this API, and is resolved HERE so
 * the service never reads a header (architecture rule 2).
 */
class CoverageRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'zone_id' => 'required|integer|exists:zones,id',
            'module_id' => 'nullable|integer|exists:modules,id',
        ];
    }

    public function filters(): array
    {
        return [
            'zone_id' => (int) $this->input('zone_id'),
            'module_id' => (int) ($this->input('module_id') ?: $this->header('moduleId')),
        ];
    }
}
