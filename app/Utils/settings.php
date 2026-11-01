<?php

use App\CentralLogics\Helpers;

if (!function_exists('getWebConfig')) {
    function getWebConfig($name):string|object|array|null
    {
        return Helpers::get_business_settings($name);
    }
    function getWebConfigStatus($name):string|object|array|int
    {
        return Helpers::get_business_settings($name) ?? 0;
    }

    if (!function_exists('getDemoModeFormButton')) {
        function getDemoModeFormButton($type = ''): string
        {
            $result = '';
            if ($type == 'class') {
                $result = getEnvMode() != 'demo' ? '' : 'call-demo';
            } elseif ($type == 'button') {
                $result = getEnvMode() != 'demo' ? 'submit' : 'button';
            }
            return $result;
        }
    }

    if (!function_exists('showDemoModeInputValue')) {
        function showDemoModeInputValue($value = null): string
        {
            return getEnvMode() != 'demo' ? $value : '';
        }
    }
}
