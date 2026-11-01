<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class AdminSearchShortcuts
{
    public static function all(string $keyword, ?string $moduleType): array
    {
        $shortcuts = array_merge(
            self::earningReportRoutes($keyword),
            self::transactionReportRoutes($keyword),
            self::performanceReportRoutes($keyword),
            self::builderRoutes($keyword),
            self::catalogCategoryRoutes($keyword),
        );

        if ($moduleType === 'service') {
            $shortcuts = array_merge(
                self::serviceCatalogRoutes($keyword),
                self::serviceListRoutes($keyword),
                self::providerCategoryRoutes($keyword),
                self::bookingKeywordRoutes($keyword, 'admin.service.booking.list'),
                self::serviceTaxRoutes($keyword),
                self::serviceSettingsRoutes($keyword),
                $shortcuts,
            );
        }

        return $shortcuts;
    }

    private static function catalogCategoryRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '' || ! Route::has('admin.category.add')) {
            return [];
        }

        $tokens = preg_split('/[^a-z0-9]+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);

        if (array_intersect($tokens, ['add', 'create', 'new']) === [] || ! in_array('category', $tokens, true)) {
            return [];
        }

        $isSub = in_array('sub', $tokens, true);
        if (! $isSub && ! in_array('main', $tokens, true)) {
            return [];
        }

        $uri = 'admin/category/add?position='.($isSub ? 1 : 0);

        return [[
            'routeName' => $isSub ? 'Sub Category' : 'Main Category',
            'URI' => $uri,
            'fullRoute' => url($uri),
            'module_type' => null,
            'data_from' => 'files',
            'pinned' => true,
        ]];
    }

    private static function normalizedTokens(string $keyword): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', strtolower(trim($keyword)), -1, PREG_SPLIT_NO_EMPTY);

        return array_map(fn ($token) => Str::singular($token), $tokens);
    }

    private static function serviceListRoutes(string $keyword): array
    {
        if (! Route::has('admin.service.list')) {
            return [];
        }

        $normalized = self::normalizedTokens($keyword);
        sort($normalized);

        if ($normalized !== ['list', 'service']) {
            return [];
        }

        return [[
            'routeName' => 'Service List',
            'URI' => 'admin/service/list',
            'fullRoute' => url('admin/service/list'),
            'module_type' => 'service',
            'data_from' => 'files',
            'pinned' => true,
        ]];
    }

    private static function providerCategoryRoutes(string $keyword): array
    {
        if (! Route::has('admin.store-category.list')) {
            return [];
        }

        $normalized = self::normalizedTokens($keyword);

        if (! in_array('provider', $normalized, true) || ! in_array('category', $normalized, true)) {
            return [];
        }

        return [[
            'routeName' => 'Provider Categories',
            'URI' => 'admin/store-category/list',
            'fullRoute' => url('admin/store-category/list'),
            'module_type' => 'service',
            'data_from' => 'files',
            'pinned' => true,
        ]];
    }

    private static function serviceCatalogRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if ($keyword === '' || ! Route::has('admin.service.add')) {
            return [];
        }

        $tokens = preg_split('/[^a-z0-9]+/', $keyword, -1, PREG_SPLIT_NO_EMPTY);
        $hasVerb = array_intersect($tokens, ['add', 'create', 'new']) !== [];
        $hasService = in_array('service', $tokens, true);

        if (! $hasVerb || (! $hasService && ! in_array($keyword, ['add', 'create', 'new', 'add new'], true))) {
            return [];
        }

        $url = route('admin.service.add');

        return [[
            'routeName' => 'Service Add New',
            'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
            'fullRoute' => $url,
            'module_type' => 'service',
            'data_from' => 'files',
            'pinned' => true,
        ]];
    }

    private static function builderRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));

        if ($keyword === '' || ! (str_contains('website builder vendor website builder page builder', $keyword) || str_contains($keyword, 'builder'))) {
            return [];
        }

        $uri = 'admin/business-settings/business-setup/store';

        return [[
            'routeName' => 'Vendor Website Builder',
            'URI' => $uri,
            'fullRoute' => url($uri),
            'module_type' => null,
            'data_from' => 'files',
        ]];
    }

    private static function bookingKeywordRoutes(string $keyword, string $routeName): array
    {
        $keyword = strtolower(trim($keyword));
        if (! str_contains($keyword, 'booking') || ! Route::has($routeName)) {
            return [];
        }

        $base = route($routeName);

        $tabs = [
            'pending' => ['booking_status' => 'pending'],
            'accepted' => ['booking_status' => 'accepted'],
            'confirmed' => ['booking_status' => 'confirmed'],
            'ongoing' => ['booking_status' => 'ongoing'],
            'completed' => ['booking_status' => 'completed'],
            'on hold' => ['booking_status' => 'on_hold'],
            'on_hold' => ['booking_status' => 'on_hold'],
            'canceled' => ['booking_status' => 'canceled'],
            'cancelled' => ['booking_status' => 'canceled'],
            'regular' => ['type' => 'regular'],
            'repeat' => ['type' => 'repeat'],
        ];

        $entry = function (string $label, array $params) use ($base) {
            $url = empty($params) ? $base : $base.'?'.http_build_query($params);

            return [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => 'service',
                'data_from' => 'files',
            ];
        };

        $label = fn (string $token) => ucwords(str_replace('_', ' ', $token)).' Booking';

        foreach ($tabs as $token => $params) {
            if (str_contains($keyword, $token)) {
                return [$entry($label($token === 'cancelled' ? 'canceled' : $token), $params)];
            }
        }

        $routes = [$entry('All Booking', [])];
        foreach ($tabs as $token => $params) {
            if (in_array($token, ['on_hold', 'cancelled'], true)) {
                continue;
            }
            $routes[] = $entry($label($token), $params);
        }

        return $routes;
    }

    private static function serviceTaxRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if (! preg_match('/\b(tax|compliance|vat)\b/', $keyword)) {
            return [];
        }

        $wantsProvider = str_contains($keyword, 'provider');

        $pages = [
            ['Service Tax Report', 'admin.transactions.service.report.getTaxReport', false],
            ['Service Provider Tax Report', 'admin.transactions.service.report.providerWiseTaxes', true],
        ];

        $routes = [];
        foreach ($pages as [$label, $routeName, $isProvider]) {
            if (($wantsProvider && ! $isProvider) || ! Route::has($routeName)) {
                continue;
            }

            $url = route($routeName);
            $routes[] = [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => 'service',
                'data_from' => 'files',
            ];
        }

        return $routes;
    }

    private static function serviceSettingsRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));

        $pages = [
            ['Bookings Settings', 'admin.business-settings.service.booking', ['booking', 'setting']],
            ['Providers & Serviceman Settings', 'admin.business-settings.service.provider-serviceman', ['provider', 'serviceman', 'setting']],
        ];

        $routes = [];
        foreach ($pages as [$label, $routeName, $tokens]) {
            if (! Route::has($routeName)) {
                continue;
            }

            $matched = false;
            foreach ($tokens as $token) {
                if (str_contains($keyword, $token)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $url = route($routeName);
            $routes[] = [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => 'service',
                'data_from' => 'files',
            ];
        }

        return $routes;
    }

    private static function earningReportRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        $base = 'admin.transactions.report.admin-earning-report';
        if (! str_contains($keyword, 'earning') || ! Route::has($base)) {
            return [];
        }

        $entry = function (string $label, string $url) {
            return [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => null,
                'data_from' => 'files',
            ];
        };

        $routes = [
            $entry('Admin Earning Report - Order Modules', route($base, ['tab' => 'all'])),
            $entry('Admin Earning Report - Parcel Module', route($base, ['tab' => 'parcel'])),
        ];

        if (addon_published_status('Rental')) {
            $routes[] = $entry('Admin Earning Report - Rental Module', route($base, ['tab' => 'rental']));
        }

        $rideRoute = 'admin.transactions.ride-share.report.admin-earning-report';
        if (addon_published_status('RideShare') && Route::has($rideRoute)) {
            $routes[] = $entry('Admin Earning Report - Ride Share', route($rideRoute));
        }

        if (addon_published_status('Service')) {
            $routes[] = $entry('Admin Earning Report - Service Module', route($base, ['tab' => 'service']));
        }

        return $routes;
    }

    private static function transactionReportRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));
        if (! str_contains($keyword, 'transaction')) {
            return [];
        }

        $pages = [
            ['Order Transaction Report', 'admin.transactions.report.day-wise-report', null],
            ['Parcel Transaction Report', 'admin.transactions.report.parcel-transaction-report', null],
            ['Rental Transaction Report', 'admin.transactions.rental.report.transaction-report', 'Rental'],
            ['Ride Transaction Report', 'admin.transactions.ride-share.transaction.index', 'RideShare'],
            ['Service Transaction Report', 'admin.transactions.service.report.transaction-report', 'Service'],
        ];

        $routes = [];
        foreach ($pages as [$label, $routeName, $addon]) {
            if (($addon && ! addon_published_status($addon)) || ! Route::has($routeName)) {
                continue;
            }

            $url = route($routeName);
            $routes[] = [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => null,
                'data_from' => 'files',
            ];
        }

        return $routes;
    }

    private static function performanceReportRoutes(string $keyword): array
    {
        $keyword = strtolower(trim($keyword));

        $pages = [
            ['Store Wise Report', 'admin.transactions.report.store-summary-report', null, ['store']],
            ['Item Report', 'admin.transactions.report.item-wise-report', null, ['item']],
            ['Provider Wise Report', 'admin.transactions.rental.report.provider-summary-report', 'Rental', ['provider']],
            ['Vehicle Report', 'admin.transactions.rental.report.vehicle-wise-report', 'Rental', ['vehicle']],
            ['Service Provider Wise Report', 'admin.transactions.service.report.provider-summary-report', 'Service', ['provider']],
            ['Service Report', 'admin.transactions.service.report.service-wise-report', 'Service', ['service report', 'service wise', 'service-report']],
        ];

        $routes = [];
        foreach ($pages as [$label, $routeName, $addon, $tokens]) {
            if (($addon && ! addon_published_status($addon)) || ! Route::has($routeName)) {
                continue;
            }

            $matched = false;
            foreach ($tokens as $token) {
                if (str_contains($keyword, $token)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                continue;
            }

            $url = route($routeName);
            $routes[] = [
                'routeName' => $label,
                'URI' => ltrim(str_replace(url('/'), '', $url), '/'),
                'fullRoute' => $url,
                'module_type' => null,
                'data_from' => 'files',
            ];
        }

        return $routes;
    }

}
