<?php

namespace App\Support\Addon;

use Nwidart\Modules\Activators\FileActivator;
use Nwidart\Modules\Module;

class VersionGatedActivator extends FileActivator
{
    public function hasStatus(Module|string $module, bool $status): bool
    {
        $name = $module instanceof Module ? $module->getName() : $module;

        if (! AddonCompatibility::isCompatible(name: $name)) {
            return $status === false;
        }

        return parent::hasStatus($module, $status);
    }
}
