<?php

namespace App\Support\Promotion;

use App\CentralLogics\Helpers;
use App\Models\Module;

class BundleSettings
{
    public const STATUS_KEY = 'product_bundle_status';

    public const MODULES_KEY = 'product_bundle_modules';

    public const EXCLUDED_TYPES = ['parcel', 'rental', 'ride-share'];

    private static array $moduleIdCache = [];

    public static function enabled(): bool
    {
        return (bool) Helpers::get_business_settings(self::STATUS_KEY, false);
    }

    /**
     * The module types a bundle may be offered in.
     *
     * EXCLUDED_TYPES are the ones that cannot hold a bundle at all. `service` can, but only
     * while the Service addon is published -- without it the type still sits in
     * config('module.module_type') and a `service` row still sits in `modules`, so the setting
     * screen offered a checkbox for a module the install does not actually have. Filtering here
     * rather than in the view covers enabledModuleTypes() and availableModuleIds() with it, so
     * a selection saved while the addon was on stops applying the moment it is turned off.
     */
    public static function moduleTypes(): array
    {
        return array_values(array_filter(
            array_diff(config('module.module_type', []), self::EXCLUDED_TYPES),
            fn ($type) => module_type_addon_active($type),
        ));
    }

    public static function selectedModules(): array
    {
        $stored = Helpers::get_business_settings(self::MODULES_KEY);

        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return is_array($stored) ? $stored : [];
    }

    public static function enabledModuleTypes(): array
    {
        if (! self::enabled()) {
            return [];
        }

        $selected = self::selectedModules();

        return array_values(array_filter(
            self::moduleTypes(),
            fn ($type) => ($selected[$type] ?? 0) == 1,
        ));
    }

    public static function availableModuleIds(): array
    {
        $types = self::enabledModuleTypes();

        if ($types === []) {
            return [];
        }

        $key = implode('|', $types);

        return self::$moduleIdCache[$key] ??= Module::whereIn('module_type', $types)
            ->pluck('id')->map('intval')->all();
    }

    public static function moduleTypeLabel(string $moduleType): string
    {
        return translate($moduleType === 'ecommerce' ? 'Shop' : $moduleType);
    }

    public static function allowsModuleType(?string $moduleType): bool
    {
        return $moduleType !== null && in_array($moduleType, self::enabledModuleTypes(), true);
    }

    public static function allowsModule(mixed $moduleId): bool
    {
        if (blank($moduleId)) {
            return false;
        }

        return in_array((int) $moduleId, self::availableModuleIds(), true);
    }
}
