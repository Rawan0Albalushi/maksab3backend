<?php

namespace App\Navigation;

use App\Support\Settings\BusinessRules;
use App\CentralLogics\Helpers;
use Illuminate\Support\Str;

/**
 * View data for the vendor sidebars.
 *
 * Replaces the @php blocks that used to open
 * layouts/vendor/partials/_sidebar.blade.php and _sidebar_v2.blade.php --
 * store lookups, the active-section resolution and the request-path matcher.
 *
 * Bound to both sidebars by VendorViewComposerServiceProvider, because they are
 * layout partials included on every vendor page rather than views owned by a
 * single controller.
 */
class VendorSidebarViewModel
{
    /**
     * Request path prefix -> v2 rail section. First match wins, so the
     * flash-sale and reviews/message entries must precede the broader ones.
     */
    private const SECTION_PATTERNS = [
        'sales' => ['vendor-panel/pos*', 'vendor-panel/order*'],
        'marketing' => ['vendor-panel/item/flash-sale*', 'vendor-panel/campaign*', 'vendor-panel/coupon*', 'vendor-panel/bogo-offer*', 'vendor-panel/happy-hour*', 'vendor-panel/bundle*', 'vendor-panel/banner*', 'vendor-panel/advertisement*', 'vendor-panel/reels*'],
        'catalog' => ['vendor-panel/item*', 'vendor-panel/addon*', 'vendor-panel/category*', 'vendor-panel/store-category*'],
        'ops' => ['vendor-panel/delivery-man*'],
        'finance' => ['vendor-panel/wallet*', 'vendor-panel/withdraw-method*', 'vendor-panel/wallet-method*'],
        'team' => ['vendor-panel/custom-role*', 'vendor-panel/employee*'],
        'reports' => ['vendor-panel/report*', 'vendor-panel/expense*'],
        'engagement' => ['vendor-panel/reviews*', 'vendor-panel/message*'],
        'settings' => ['vendor-panel/business-settings*', 'vendor-panel/store/*', 'vendor-panel/subscription*'],
    ];

    public readonly array $nav;

    public readonly ?object $store;

    public readonly string $moduleType;

    public readonly bool $isFood;

    public readonly ?object $user;

    public readonly bool $reelsEnabled;

    public readonly string $activeSection;

    public readonly bool $marketingVisible;

    /**
     * The pending-orders item is labelled "take away" unless the store confirms
     * its own orders or does its own delivery.
     */
    public readonly bool $showTakeAwayLabel;

    private readonly string $path;

    public function __construct()
    {
        $this->nav = VendorNav::context();
        $this->store = $this->nav['store'];
        $this->moduleType = $this->nav['module_type'];
        $this->isFood = $this->nav['is_food'];
        $this->user = Helpers::get_loggedin_user() ?: null;
        $this->path = request()->path();

        // External module -- keeps its own gate, outside the level-scoped registry.
        $this->reelsEnabled = (bool) (addon_published_status('ReelsModule')
            && Helpers::get_business_settings('vendor_can_upload_reels')
            && Helpers::employee_module_permission_check('reels')
            && \Modules\ReelsModule\Support\ReelModuleConfig::isAllowedType($this->moduleType));

        $this->showTakeAwayLabel = ! (BusinessRules::storeConfirmsOrder()
            || (bool) ($this->store?->sub_self_delivery));

        $this->activeSection = $this->resolveActiveSection();
        $this->marketingVisible = VendorNav::sectionVisible('marketing', 'v2') || $this->reelsEnabled;
    }

    /**
     * Does the current request path match this pattern?
     */
    public function is(string $pattern): bool
    {
        return Str::is($pattern, $this->path);
    }

    /**
     * Does the current request match any of these patterns?
     */
    public function isAny(array $patterns): bool
    {
        return Str::is($patterns, $this->path);
    }

    /**
     * Permission proxies, so the blades never name a class directly.
     */
    public function can(string $permission): bool
    {
        return VendorNav::can($permission);
    }

    public function sectionVisible(string $section, string $variant = 'v1'): bool
    {
        return VendorNav::sectionVisible($section, $variant);
    }

    /**
     * Advertisement leaves: the list and the pending ("Ad Requests") view share
     * one route, separated only by ?type=pending.
     */
    public function adPendingActive(): bool
    {
        return $this->is('vendor-panel/advertisement*') && request()->input('type') === 'pending';
    }

    public function adListActive(): bool
    {
        return $this->is('vendor-panel/advertisement*')
            && ! $this->adPendingActive()
            && ! $this->is('vendor-panel/advertisement/create*');
    }

    public function reelsCreateActive(): bool
    {
        return $this->is('vendor-panel/reels/create*');
    }

    public function reelsListActive(): bool
    {
        return $this->is('vendor-panel/reels*') && ! $this->reelsCreateActive();
    }

    private function resolveActiveSection(): string
    {
        foreach (self::SECTION_PATTERNS as $section => $patterns) {
            // Catalog must not claim the flash-sale page, which lives under
            // vendor-panel/item/* but belongs to Marketing.
            if ($section === 'catalog' && $this->is('vendor-panel/item/flash-sale*')) {
                continue;
            }

            if ($this->isAny($patterns)) {
                return $section;
            }
        }

        return 'dashboard';
    }
}
