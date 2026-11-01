<?php

use Modules\Service\Services\BusinessSettingService;

if (file_exists(('./Modules/RideShare/Lib/Helpers.php'))) {
    require_once ('./Modules/RideShare/Lib/Helpers.php');
    require_once ('./Modules/RideShare/Lib/ReverbPusherHelpers.php');
    require_once ('./Modules/RideShare/Lib/TripRequestUpdate.php');
}

if (! function_exists('service_api_module_active')) {
    function service_api_module_active(): bool
    {
        $module = config('module.current_module_data');

        return addon_published_status('Service')
            && $module
            && ($module['module_type'] ?? null) === 'service';
    }
}

if (! function_exists('service_addon_active')) {
    function service_addon_active(): bool
    {
        return addon_published_status('Service') === 1;
    }
}

if (! function_exists('module_type_addon_active')) {
    /**
     * Whether the addon that provides this module type is published.
     *
     * Three of the eight types in config('module.module_type') are shipped as addons and only
     * exist while their addon is: a `service` module on an install without the Service addon has
     * a row in `modules` and a type in the config, but nothing behind it -- no routes, no
     * screens, no icon. Anywhere the panel lists module types it has to ask this first, or it
     * offers a module the customer can never be served from.
     *
     * A type with no addon behind it is always active, so the caller can test every type the
     * same way instead of special-casing three names at each call site.
     */
    function module_type_addon_active(?string $moduleType): bool
    {
        $addon = match ($moduleType) {
            'service' => 'Service',
            'rental' => 'Rental',
            'ride-share' => 'RideShare',
            default => null,
        };

        return $addon === null || addon_published_status($addon) === 1;
    }
}

if (! function_exists('service_setting_enabled')) {
    function service_setting_enabled(string $key): bool
    {
        return service_addon_active() && BusinessSettingService::isEnabled(key: $key);
    }
}
