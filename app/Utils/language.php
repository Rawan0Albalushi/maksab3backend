<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

// translate() intentionally lives only in app/helpers.php.
//
// A second definition used to sit here. It never ran — composer's `files`
// autoload list loads app/helpers.php first, so function_exists() short-circuited
// this one — but it was a loaded gun: it had no static cache, so had the autoload
// order ever shifted, every single translate() call would have include()d the
// ~850 KB messages.php afresh (~3 ms and 4 MB each, thousands of times a request).
// Removed rather than kept in sync, since the failure mode is silent.


if (!function_exists('removeSpecialCharacters')) {

    function removeSpecialCharacters(string $text): string
    {
        return str_ireplace(['\'', '"', ',', ';', '<', '>', '?'], ' ', preg_replace('/\s\s+/', ' ', $text));
    }
}

if (!function_exists('getDefaultLanguage')) {
    function getDefaultLanguage(): string
    {
        if (strpos(url()->current(), '/api')) {
            $lang = App::getLocale();
        } elseif (session()->has('local')) {
            $lang = session('local');
        } else {
            $data = getWebConfig('language');
            $code = 'en';
            $direction = 'ltr';
            foreach ($data as $ln) {
                if (array_key_exists('default', $ln) && $ln['default']) {
                    $code = $ln['code'];
                    if (array_key_exists('direction', $ln)) {
                        $direction = $ln['direction'];
                    }
                }
            }
            session()->put('local', $code);
            Session::put('direction', $direction);
            $lang = $code;
        }
        return $lang;
    }
}
