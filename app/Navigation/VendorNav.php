<?php

namespace App\Navigation;

use App\CentralLogics\Helpers;

/**
 * Single source of truth for vendor panel navigation permissions.
 *
 * Both sidebars render the same set of permission-bearing units, only grouped
 * differently:
 *
 *   v1 L0 (nav-subtitle)  ==  v2 L1 (rail section)   -> container, visibility derived
 *   v1 L1 (nav item)      ==  v2 L2 (panel group)    -> the permission unit
 *   v1 L2 (submenu item)  ==  v2 L3 (nav leaf)       -> leaf, inherits its unit
 *
 * A unit is visible when its permission is granted AND its `visible` condition
 * (module type, store flag, business setting) passes. Container levels are never
 * declared by hand -- sectionVisible() derives them from their children, so a
 * section cannot render empty.
 *
 * ModulePermissionMiddleware resolves vendor guards through can() as well, so a
 * unit hidden from the nav is also unreachable by typing its URL.
 *
 * Deliberately carries no labels: translate() include()s a language file (and
 * may write to it) on every call, and this registry is touched by middleware on
 * every guarded request. Labels stay in the blades.
 *
 * External modules (ReelsModule, Service, Rental, RideShare) are absent -- they
 * keep their own gates.
 */
class VendorNav
{
    private static ?array $units = null;

    private static array $canCache = [];

    private static ?array $context = null;

    /**
     * Per-request store facts the visibility conditions depend on.
     *
     * Resolved once: get_store_data() and get_business_settings() each hit the
     * DB, and the v1 sidebar alone evaluates these ~40 times per render.
     */
    public static function context(): array
    {
        if (self::$context !== null) {
            return self::$context;
        }

        $store = Helpers::get_store_data();
        $moduleType = $store?->module?->module_type ?? '';

        return self::$context = [
            'store' => $store,
            'module_type' => $moduleType,
            'is_food' => $moduleType === 'food',
            'needs_catalog_extras' => in_array($moduleType, ['grocery', 'ecommerce'], true),
            'item_section' => (bool) ($store?->item_section),
            'product_approval' => (bool) Helpers::get_business_settings('product_approval'),
            'product_gallery' => (bool) Helpers::get_business_settings('product_gallery'),
            'review_section' => (bool) (Helpers::get_business_settings('review_section') ?? 1),
            'store_reviews' => (bool) ($store?->reviews_section),
            'self_delivery' => (bool) ($store?->self_delivery_system),
            'store_category' => (bool) Helpers::storeCategoryStatus(),
        ];
    }

    /**
     * Every permission-bearing unit, keyed by permission.
     *
     * v1_section is the L0 header it sits under (null = standalone top-level item).
     * v2_section is the L1 rail section it sits under.
     * visible is an extra non-role condition; null means "permission alone decides".
     */
    public static function units(): array
    {
        if (self::$units !== null) {
            return self::$units;
        }

        return self::$units = [
            'order' => ['v1_section' => 'order_management', 'v2_section' => 'sales', 'form_section' => 'sales', 'label' => 'messages.All orders'],
            'pos' => ['v1_section' => null, 'v2_section' => 'sales', 'form_section' => 'sales', 'label' => 'messages.POS'],

            'item' => ['v1_section' => 'item_management', 'v2_section' => 'catalog', 'form_section' => 'catalog', 'label' => 'messages.Items'],
            'addon' => ['v1_section' => 'item_management', 'v2_section' => 'catalog', 'form_section' => 'catalog', 'label' => 'messages.Addons'],
            'category' => ['v1_section' => 'item_management', 'v2_section' => 'catalog', 'form_section' => 'catalog', 'label' => 'messages.Categories'],
            'my_category' => [
                'v1_section' => 'item_management',
                'v2_section' => 'catalog',
                'form_section' => 'catalog',
                'label' => 'messages.My category',
                'visible' => fn () => self::context()['store_category'],
            ],
            'flash_sale' => [
                'v1_section' => null,
                'v2_section' => 'marketing',
                'form_section' => 'marketing',
                'label' => 'messages.Flash sales',
                'visible' => fn () => self::context()['needs_catalog_extras'],
            ],

            'campaign' => ['v1_section' => 'marketing_section', 'v2_section' => 'marketing', 'form_section' => 'marketing', 'label' => 'messages.Campaign'],
            'coupon' => ['v1_section' => 'marketing_section', 'v2_section' => 'marketing', 'form_section' => 'marketing', 'label' => 'messages.Coupon'],
            'banner' => ['v1_section' => 'marketing_section', 'v2_section' => 'marketing', 'form_section' => 'marketing', 'label' => 'messages.Banner'],

            // Advertisement Management is a single grantable permission covering
            // creating ads and managing the ad list. Only this unit is offered in
            // the role form (inside the Marketing card); the two below derive
            // their grant from it.
            'advertisement_management' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'marketing',
                'label' => 'Advertisement',
            ],
            'advertisement' => ['v1_section' => 'advertisement_management', 'v2_section' => 'marketing', 'form_section' => null, 'label' => 'messages.New advertisement', 'grant' => ['advertisement_management', 'advertisement']],
            'advertisement_list' => ['v1_section' => 'advertisement_management', 'v2_section' => 'marketing', 'form_section' => null, 'label' => 'messages.Advertisement list', 'grant' => ['advertisement_management', 'advertisement_list']],

            // Delivery Man Management is a single grantable permission. Only this
            // unit is offered in the role form; the two below derive their grant
            // from it.
            //
            // Both carry an explicit self_delivery condition: the store flag used
            // to ride on employee_module_permission_check('deliveryman'), and
            // delegating the grant would otherwise drop that capability gate.
            'deliveryman_management' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'deliveryman',
                'label' => 'Delivery Man',
                // Same gate as its two leaves, so can() and applicable() agree and
                // a future module:deliveryman_management guard cannot bypass it.
                'visible' => fn () => self::context()['self_delivery'],
            ],
            'deliveryman' => [
                'v1_section' => 'deliveryman_section',
                'v2_section' => 'ops',
                'form_section' => null,
                'label' => 'messages.New deliveryman',
                'grant' => ['deliveryman_management', 'deliveryman'],
                'visible' => fn () => self::context()['self_delivery'],
            ],
            'deliveryman_list' => [
                'v1_section' => 'deliveryman_section',
                'v2_section' => 'ops',
                'form_section' => null,
                'label' => 'messages.Deliveryman list',
                'grant' => ['deliveryman_management', 'deliveryman_list'],
                'visible' => fn () => self::context()['self_delivery'],
            ],

            // Wallet Management is a single grantable permission covering the
            // wallet and its disbursement methods. Only this unit is offered in the
            // role form; the two below derive their grant from it.
            'wallet_management' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'wallet',
                'label' => 'Wallet',
            ],
            'wallet' => ['v1_section' => 'wallet_management', 'v2_section' => 'finance', 'form_section' => null, 'label' => 'messages.My Wallet', 'grant' => ['wallet_management', 'wallet']],
            'wallet_method' => ['v1_section' => 'wallet_management', 'v2_section' => 'finance', 'form_section' => null, 'label' => 'messages.Wallet method', 'grant' => ['wallet_management', 'wallet_method']],

            // Employee Section is granted by `employee` alone. Employee Role has no
            // separate checkbox -- it derives its grant from employee (the legacy
            // `role` key is still accepted so older roles keep working).
            'employee' => ['v1_section' => 'employee_section', 'v2_section' => 'team', 'form_section' => 'employee', 'label' => 'messages.All employee'],
            'role' => ['v1_section' => 'employee_section', 'v2_section' => 'team', 'form_section' => null, 'label' => 'messages.Employee Role', 'grant' => ['employee', 'role']],

            // Report Section is a single grantable permission covering all four
            // reports. Only this unit is offered in the role form; the four below
            // derive their grant from it.
            'report_section' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'reports',
                'label' => 'messages.Report section',
            ],
            'expense_report' => ['v1_section' => 'report_section', 'v2_section' => 'reports', 'form_section' => null, 'label' => 'messages.Expense report', 'grant' => ['report_section', 'expense_report']],
            'store_earning_report' => ['v1_section' => 'report_section', 'v2_section' => 'reports', 'form_section' => null, 'label' => 'messages.Store earning report', 'grant' => ['report_section', 'store_earning_report']],
            'disbursement_report' => ['v1_section' => 'report_section', 'v2_section' => 'reports', 'form_section' => null, 'label' => 'messages.Disbursement report', 'grant' => ['report_section', 'disbursement_report']],
            'vat_report' => ['v1_section' => 'report_section', 'v2_section' => 'reports', 'form_section' => null, 'label' => 'messages.VAT report', 'grant' => ['report_section', 'vat_report']],

            // Business Section is a single grantable permission covering store
            // config, notifications, my shop and business plan. Only this unit is
            // offered in the role form; the four below derive their grant from it.
            'business_section' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'business',
                'label' => 'messages.Business section',
            ],
            'store_setup' => ['v1_section' => 'business_section', 'v2_section' => 'settings', 'form_section' => null, 'label' => 'messages.Store Setup', 'grant' => ['business_section', 'store_setup']],
            'notification_setup' => ['v1_section' => 'business_section', 'v2_section' => 'settings', 'form_section' => null, 'label' => 'messages.Notification setup', 'grant' => ['business_section', 'notification_setup']],
            'my_shop' => ['v1_section' => 'business_section', 'v2_section' => 'settings', 'form_section' => null, 'label' => 'messages.MY Shop', 'grant' => ['business_section', 'my_shop']],
            'business_plan' => ['v1_section' => 'business_section', 'v2_section' => 'settings', 'form_section' => null, 'label' => 'messages.Business plan', 'grant' => ['business_section', 'business_plan']],

            // Customer Engagement is a single grantable permission covering both
            // Reviews and Chat. It is the only unit offered in the role form; the
            // two entries below are display units that derive their grant from it.
            'customer_engagement' => [
                'v1_section' => null,
                'v2_section' => null,
                'form_section' => 'engagement',
                'label' => 'messages.Customer engagement',
            ],
            'reviews' => [
                'v1_section' => 'customer_engagement',
                'v2_section' => 'engagement',
                'form_section' => null,
                'label' => 'messages.Reviews',
                // Accepts the legacy key too, so roles that were never migrated
                // (or are edited from another panel) keep working.
                'grant' => ['customer_engagement', 'reviews'],
                'visible' => fn () => self::context()['review_section'] && self::context()['store_reviews'],
            ],
            'chat' => [
                'v1_section' => 'customer_engagement',
                'v2_section' => 'engagement',
                'form_section' => null,
                'label' => 'messages.Chat',
                'grant' => ['customer_engagement', 'chat'],
            ],
        ];
    }

    /**
     * Is this permission unit available to the current vendor / employee?
     *
     * Role grant AND the unit's own condition. Unknown permissions fall back to
     * the plain role check so callers outside this registry keep working.
     */
    public static function can(string $permission): bool
    {
        if (array_key_exists($permission, self::$canCache)) {
            return self::$canCache[$permission];
        }

        $unit = self::units()[$permission] ?? null;

        // A unit may draw its grant from another key (Reviews and Chat both come
        // from customer_engagement); any listed key satisfies it.
        $grantKeys = (array) ($unit['grant'] ?? $permission);

        $granted = false;
        foreach ($grantKeys as $key) {
            if (Helpers::employee_module_permission_check($key)) {
                $granted = true;
                break;
            }
        }

        if ($granted) {
            $condition = $unit['visible'] ?? null;
            if ($condition !== null) {
                $granted = (bool) $condition();
            }
        }

        return self::$canCache[$permission] = $granted;
    }

    /**
     * Container visibility, derived: true when at least one child unit is allowed.
     *
     * $variant is 'v1' (L0 header) or 'v2' (L1 rail section).
     */
    public static function sectionVisible(string $section, string $variant = 'v1'): bool
    {
        $key = $variant.'_section';

        foreach (self::units() as $permission => $unit) {
            if (($unit[$key] ?? null) === $section && self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Permission keys owned by this registry.
     */
    public static function permissions(): array
    {
        return array_keys(self::units());
    }

    /**
     * Can this permission be granted at all for the current store?
     *
     * Store capability only -- deliberately independent of who is logged in, so
     * the role form offers the same checkboxes to an owner and to an employee
     * who manages roles. Mirrors the store-flag branch of
     * Helpers::employee_module_permission_check() plus the unit's own condition.
     */
    public static function applicable(string $permission): bool
    {
        $ctx = self::context();
        $store = $ctx['store'];

        $capable = match ($permission) {
            'pos' => (bool) $store?->pos_system,
            'addon' => (bool) (config('module.'.$ctx['module_type'])['add_on'] ?? false),
            'deliveryman', 'deliveryman_list', 'deliveryman_management' => (bool) $store?->self_delivery_system,
            'reviews' => (bool) $store?->reviews_section,
            default => true,
        };

        if (! $capable) {
            return false;
        }

        $condition = self::units()[$permission]['visible'] ?? null;

        return $condition === null ? true : (bool) $condition();
    }

    /**
     * Grantable permissions for one role-form card, as [permission => labelKey].
     *
     * Registry order is preserved, and units that do not apply to this store are
     * dropped -- so the form can never offer a permission the sidebar would
     * refuse to render.
     */
    public static function formPermissions(string $formSection): array
    {
        $out = [];

        foreach (self::units() as $permission => $unit) {
            if (($unit['form_section'] ?? null) === $formSection && self::applicable($permission)) {
                $out[$permission] = $unit['label'];
            }
        }

        return $out;
    }

    /**
     * Test seam: drop memoised state between requests in long-running processes.
     */
    public static function flush(): void
    {
        self::$units = null;
        self::$canCache = [];
        self::$context = null;
    }
}
