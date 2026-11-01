<?php

namespace App\Traits\System;

trait AddonHelperTrait
{

    public function getAddons(): array
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            if($directory == 'Gateways'){
                $sub_dirs = self::getDirectories('Modules/' . $directory);
                if (in_array('Addon', $sub_dirs)) {
                    $addons[] = 'Modules/' . $directory;
                }
            }
        }

        $array = [];
        foreach ($addons as $item) {
            $full_data = include($item . '/Addon/info.php');
            $array[] = [
                'addon_name' => $full_data['name'],
                'software_id' => $full_data['software_id'],
                'is_published' => $full_data['is_published'],
            ];
        }

        return $array;
    }

    public function getAddonAdminRoutes(): array
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            if($directory == 'Gateways'){
                $sub_dirs = self::getDirectories('Modules/' . $directory);
                if (in_array('Addon', $sub_dirs)) {
                    $addons[] = 'Modules/' . $directory;
                }
            }
        }

        $full_data = [];
        foreach ($addons as $item) {
            $info = include($item . '/Addon/info.php');
            if ($info['is_published']){
                $full_data[] = include($item . '/Addon/admin_routes.php');
            }
        }

        return $full_data;
    }

    public function getPaymentPublishStatus(): array
    {
        $dir = 'Modules';
        $directories = self::getDirectories($dir);
        $addons = [];
        foreach ($directories as $directory) {
            $sub_dirs = self::getDirectories($dir . '/' . $directory);
            if($directory == 'Gateways'){
                if (in_array('Addon', $sub_dirs)) {
                    $addons[] = $dir . '/' . $directory;
                }
            }
        }

        $array = [];
        foreach ($addons as $item) {
            $full_data = include($item . '/Addon/info.php');
            $array[] = [
                'is_published' => $full_data['is_published'],
            ];
        }

        return $array;
    }


    function getDirectories(string $path): array
    {
        $directories = [];
        $path= base_path($path);
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item == '..' || $item == '.')
                continue;
            if (is_dir($path . '/' . $item))
                $directories[] = $item;
        }
        return $directories;
    }
}
