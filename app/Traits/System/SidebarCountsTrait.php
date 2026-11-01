<?php

namespace App\Traits\System;

use App\Services\Admin\AdminSidebarCountService;
use Illuminate\Database\Eloquent\Model;

trait SidebarCountsTrait
{
    public static function bootSidebarCountsTrait(): void
    {
        static::saved(function (Model $model) {
            static::flushSidebarCounts(model: $model);
        });

        static::deleted(function (Model $model) {
            static::flushSidebarCounts(model: $model);
        });
    }

    protected static function flushSidebarCounts(Model $model): void
    {
        if (! array_key_exists('module_id', $model->getAttributes())) {
            AdminSidebarCountService::flush();

            return;
        }

        AdminSidebarCountService::flush(moduleId: (int) $model->module_id);

        $original = $model->getOriginal('module_id');

        if ($original !== null && (int) $original !== (int) $model->module_id) {
            AdminSidebarCountService::flush(moduleId: (int) $original);
        }
    }
}
