<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// php -S handles one request at a time. Sending every photo through Laravel
// boots the framework (~180ms) and holds the only worker, so the home APIs sit
// behind the image queue. Local storage files are streamed here instead.
if (! function_exists('maksab_serve_storage')) {
    function maksab_serve_storage(string $relative): bool
    {
        $relative = ltrim(str_replace(["\\", "\0"], ['/', ''], $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return false;
        }

        $root = realpath(__DIR__.'/storage/app/public');
        $real = $root ? realpath($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative)) : false;
        $prefix = $root ? rtrim(str_replace('\\', '/', $root), '/').'/' : '';
        $normalized = $real ? str_replace('\\', '/', $real) : '';
        if (! $root || ! $real || ! is_file($real) || ! str_starts_with(strtolower($normalized), strtolower($prefix))) {
            return false;
        }

        $types = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        ];
        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        header('Content-Type: '.($types[$ext] ?? 'application/octet-stream'));
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: '.filesize($real));
        readfile($real);

        return true;
    }
}

if ($uri === '/image-proxy') {
    $target = $_GET['url'] ?? '';
    $path = rawurldecode((string) (parse_url(is_string($target) ? $target : '', PHP_URL_PATH) ?: ''));
    $relative = null;
    if (str_starts_with($path, '/storage/app/public/')) {
        $relative = substr($path, strlen('/storage/app/public/'));
    } elseif (str_starts_with($path, '/storage/')) {
        $relative = substr($path, strlen('/storage/'));
    }
    if ($relative !== null && maksab_serve_storage($relative)) {
        return true;
    }
}

if (str_starts_with($uri, '/storage/app/public/') && maksab_serve_storage(substr($uri, strlen('/storage/app/public/')))) {
    return true;
}

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if (
    $uri !== '/'
    && (
        (file_exists(__DIR__.$uri) && ! is_dir(__DIR__.$uri))
        || (file_exists(__DIR__.'/public'.$uri) && ! is_dir(__DIR__.'/public'.$uri))
    )
) {
    return false;
}

require_once __DIR__.'/public/index.php';
