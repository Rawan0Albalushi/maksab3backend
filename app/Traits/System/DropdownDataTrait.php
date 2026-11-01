<?php

namespace App\Traits\System;

trait DropdownDataTrait
{
    public function getDropdownData(Object $data, object $request): array|object
    {
        $options = $data->map(fn ($row) => ['id' => $row->id, 'text' => $row->name]);

        if (isset($request->all)) {
            $options[] = (object) ['id' => 'all', 'text' => translate('All')];
        }

        return $options;
    }
}
