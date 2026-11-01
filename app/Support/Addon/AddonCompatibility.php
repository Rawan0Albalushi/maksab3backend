<?php

namespace App\Support\Addon;

use Throwable;

class AddonCompatibility
{
    private static ?array $requiredVersions = null;

    private static array $installedVersions = [];

    public static function isCompatible(string $name): bool
    {
        if (self::requiredVersion(name: $name) === null) {
            return true;
        }

        return self::meetsRequirement(name: $name, version: self::installedVersion(name: $name));
    }

    public static function meetsRequirement(string $name, ?string $version): bool
    {
        $required = self::requiredVersion(name: $name);

        if ($required === null) {
            return true;
        }

        return $version !== null && version_compare($version, $required, '>=');
    }

    public static function requiredVersion(string $name): ?string
    {
        $required = self::requiredVersions()[$name] ?? null;

        return $required === null ? null : (string) $required;
    }

    public static function requiredVersions(): array
    {
        if (self::$requiredVersions === null) {
            $path = config_path('addon_compatibility.php');
            $versions = is_file($path) ? include $path : [];
            self::$requiredVersions = is_array($versions) ? $versions : [];
        }

        return self::$requiredVersions;
    }

    public static function installedVersion(string $name): ?string
    {
        if (array_key_exists($name, self::$installedVersions)) {
            return self::$installedVersions[$name];
        }

        $path = base_path('Modules/'.$name.'/Addon/info.php');

        try {
            $info = is_file($path) ? include $path : null;
        } catch (Throwable) {
            $info = null;
        }

        $version = is_array($info) && isset($info['version']) ? (string) $info['version'] : null;

        return self::$installedVersions[$name] = $version;
    }
}
