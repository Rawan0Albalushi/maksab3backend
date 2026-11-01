<?php

namespace App\Traits\System;

trait ReelsAddonTrait
{
    private function reelEngagementService(): ?object
    {
        if (! function_exists('addon_published_status') || ! addon_published_status('ReelsModule')) {
            return null;
        }

        $serviceClass = 'Modules\\ReelsModule\\Services\\Reel\\ReelEngagementService';

        if (! class_exists($serviceClass)) {
            return null;
        }

        return app($serviceClass);
    }
}
