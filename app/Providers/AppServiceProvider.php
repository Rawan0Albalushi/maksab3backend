<?php

namespace App\Providers;

use App\Support\Settings\BusinessRules;
use App\CentralLogics\Helpers;
use App\Support\Cache\ApiCache;
use App\Models\AddOn;
use App\Models\AdminFeature;
use App\Models\AdminPromotionalBanner;
use App\Models\AdminSpecialCriteria;
use App\Models\AdminTestimonial;
use App\Models\Allergy;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DMVehicle;
use App\Models\FAQ;
use App\Models\FlutterSpecialCriteria;
use App\Models\GenericName;
use App\Models\Module;
use App\Models\Nutrition;
use App\Models\OrderCancelReason;
use App\Models\ReactPromotionalBanner;
use App\Models\ReactTestimonial;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\WithdrawalMethod;
use App\Navigation\VendorSidebarViewModel;
use App\Services\Admin\AdminSidebarCountService;
use App\Services\NullSafeToastr;
use App\Services\System\DistanceService;
use App\Services\System\BusinessSettingService;
use App\Services\System\MapService;
use App\Services\Vendor\PosCartSummary;
use App\Services\Vendor\VendorChromeService;
use App\Services\Vendor\VendorHeaderService;
use App\Services\Vendor\VendorLayoutService;
use App\Services\Vendor\VendorSidebarCountService;
use App\Traits\System\AddonHelperTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    use AddonHelperTrait;

    private const BUILDER_ADAPTER_PATH = 'Builder';

    private const BUILDER_CONTRACT_NS = 'Modules\\Builder\\Contracts\\';

    private const BUILDER_ADAPTER_NS = 'App\\Builder\\';

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerBuilderBindings();

        $this->app->singleton(VendorSidebarCountService::class);
        $this->app->singleton(VendorChromeService::class);

        // Resolved from inside per-row formatters — store cards, order payloads, fee quotes —
        // so it is a singleton rather than rebuilt on every call.
        $this->app->singleton(DistanceService::class);

        $this->app->bind(MapService::class, fn ($app) => new MapService(
            fn () => $app->make(BusinessSettingService::class)->findValue('map_api_key_server')
        ));
    }

    /**
     * preventLazyLoading only throws outside production, so in production every violation is
     * absorbed silently as an extra query per row. The GET-route sweeps in tests/Feature cannot
     * reach the write routes, so this is the only signal for those paths: log and continue,
     * never throw, because a lazy load must not take a production request down.
     */
    private function reportLazyLoadingInProduction(): void
    {
        if (! $this->app->isProduction()) {
            return;
        }

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::warning('Lazy loading violation', [
                'model' => $model::class,
                'relation' => $relation,
                'route' => request()->route()?->uri(),
                'method' => request()->method(),
            ]);
        });
    }

    private function registerNullSafeToastr(): void
    {
        $this->app->singleton('toastr', function ($app) {
            return new NullSafeToastr($app['session'], $app['config']);
        });
    }

    private function registerBuilderBindings(): void
    {
        if (! \addon_published_status('Builder') || ! $this->builderModuleIsActive()) {
            return;
        }

        $adapterPath = app_path(self::BUILDER_ADAPTER_PATH);

        if (! is_dir($adapterPath)) {
            return;
        }

        foreach (glob($adapterPath.'/*.php') as $file) {
            $class = self::BUILDER_ADAPTER_NS.basename($file, '.php');

            // The adapters ship with core but their contracts ship with the Builder add-on, so an
            // installation whose Builder copy is older than core -- or is being replaced mid-update --
            // has adapters whose interface does not exist yet. Loading such a class is a hard PHP
            // error, and this runs on every request, so it would take the whole panel (the updater
            // included) down. Skip that adapter instead: the module binds its own Null* default for
            // any contract left unbound, and a contract that does not exist has no caller to serve.
            //
            // Decided from the file's own `use Modules\Builder\...` lines, before the class is
            // loaded, rather than by catching the failure afterwards. Catching works only while
            // the missing interface stays a runtime Error: declare the same class from a
            // compile-time-bound context and it is a fatal no catch block sees. It also leaves
            // PHP having half-declared the class, which is what emits the
            // "class_implements(): Class ... does not exist" warning on an otherwise healthy page.
            if (! $this->builderAdapterDependenciesPresent($file)) {
                continue;
            }

            // Kept as a backstop: the pre-flight above reads the import lines, so an adapter whose
            // contract exists but is itself broken still has to fail somewhere.
            try {
                if (! class_exists($class)) {
                    continue;
                }

                $interfaces = class_implements($class) ?: [];
            } catch (\Throwable $exception) {
                continue;
            }

            foreach ($interfaces as $interface) {
                if (str_starts_with($interface, self::BUILDER_CONTRACT_NS)) {
                    $this->app->bind($interface, $class);
                }
            }
        }
    }

    /**
     * Is every Modules\Builder symbol this adapter file imports actually on disk?
     *
     * Read from the source text, never by loading the class: that is the whole point, since
     * loading is what cannot be made safe. Every adapter names its contract in a `use` line --
     * all 30 of them import it aliased, `use Modules\Builder\Contracts\X as XContract` -- so the
     * import list is a complete picture of what the file needs from the add-on, and it covers the
     * ValueObjects and Services a few of them also pull in.
     *
     * A file importing nothing from the module has nothing to rule out, so it falls through to
     * class_exists() and is judged there. An unreadable one is skipped instead: it cannot be
     * vetted, and loading it is the operation this method exists to avoid.
     */
    private function builderAdapterDependenciesPresent(string $file): bool
    {
        $source = @file_get_contents($file);

        if ($source === false) {
            return false;
        }

        // Import lines only, so a class name inside a docblock or a string cannot veto an adapter.
        if (! preg_match_all('/^use\s+(Modules\\\\Builder\\\\[A-Za-z0-9_\\\\]+)\s*(?:as\s+\w+\s*)?;/mi', $source, $matches)) {
            return true;
        }

        foreach (array_unique($matches[1]) as $symbol) {
            // Each of these autoloads, and a miss is a plain false - the contract file declares an
            // interface extending nothing, so resolving it cannot fatal the way the adapter does.
            // Wrapped anyway: a module symbol that exists but is itself broken must not escape here.
            try {
                if (! interface_exists($symbol) && ! class_exists($symbol) && ! enum_exists($symbol) && ! trait_exists($symbol)) {
                    return false;
                }
            } catch (\Throwable $exception) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the module system will actually load Builder this request.
     *
     * The add-on flag in Modules/Builder/Addon/info.php says the client bought it; this file
     * says whether the module is switched on. The updater switches it off for the length of an
     * update -- the update package carries core only, so a client's Builder copy can be older
     * than the core being installed -- and core must not go looking for its contracts while it
     * is off.
     */
    private function builderModuleIsActive(): bool
    {
        $path = base_path('modules_statuses.json');

        if (! is_file($path)) {
            return true;
        }

        $statuses = json_decode((string) file_get_contents($path), true);

        if (! is_array($statuses) || ! array_key_exists('Builder', $statuses)) {
            return true;
        }

        return (bool) $statuses['Builder'];
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Model::preventLazyLoading(! $this->app->isProduction());
        Model::automaticallyEagerLoadRelationships();

        $this->reportLazyLoadingInProduction();

        $this->registerNullSafeToastr();
        $this->forceLocalUrlScheme();

        Request::macro('isAny', function (array $patterns) {
            return collect($patterns)->contains(fn ($pattern) => Request::is($pattern));
        });

        $this->composeProductAddons();
        $this->flushTranslatedListCachesOnTranslationWrite();
        $this->flushCachedReferenceListsOnChange();

        $this->composeAdminSidebarCounts();
        $this->composeVendorSidebarCounts();
        $this->composeVendorChrome();
        $this->composeVendorLayout();
        $this->composeDistanceUnitLabel();
        $this->composeVendorSidebar();
        $this->composeVendorStoreContext();
        $this->composeVendorLanguageLabels();
        $this->composeVendorSettings();

        try {
            Config::set('addon_admin_routes', $this->getAddonAdminRoutes());
            Config::set('get_payment_publish_status', $this->getPaymentPublishStatus());
            Paginator::useBootstrap();
            foreach (Helpers::get_view_keys() as $key => $value) {
                View::share($key, $value);
            }
        } catch (\Throwable $throwable) {
            // Throwable, not Exception: these read the add-on folders and the settings tables on
            // every request, and during an update both can be half-there -- a missing add-on file
            // is an Error, not an Exception, and would otherwise escape this and blank the panel.
        }
    }

    private function forceLocalUrlScheme(): void
    {
        if (! $this->app->environment('local')) {
            return;
        }

        $request = request();

        if ($request->header('x-forwarded-proto') === 'https' || $request->getScheme() === 'https') {
            URL::forceScheme('https');
        }

        if ($request->header('x-forwarded-host')) {
            URL::forceRootUrl('https://'.$request->header('x-forwarded-host'));
        }
    }

    private function composeProductAddons(): void
    {
        View::composer([
            'admin-views.order.partials._quick-view',
            'admin-views.order.partials._quick-view-cart-item',
            'admin-views.pos._item-stock-view',
            'admin-views.pos._quick-view-cart-item',
            'admin-views.pos._quick-view-data',
        ], function ($view) {
            $product = $view->getData()['product'] ?? null;
            $addOnIds = $product?->add_ons ? (json_decode($product->add_ons) ?: []) : [];

            $view->with('product_addons', Helpers::addons_by_ids($addOnIds));
        });
    }

    private function flushTranslatedListCachesOnTranslationWrite(): void
    {
        DB::listen(function ($query) {
            static $flushing = false;

            if ($flushing || ! str_contains($query->sql, 'translations')) {
                return;
            }

            if (! preg_match('/^\s*(insert|update|delete|replace)/i', $query->sql)) {
                return;
            }

            $flushing = true;

            try {
                ApiCache::bust('translation');
            } catch (\Throwable $throwable) {
                // Writing the stamp needs the cache store, which during an update can still be
                // pointing at a table the migrations have not created yet. A cache miss is
                // cheap; letting this bubble would abort the migration that triggered it.
            }

            $flushing = false;
        });
    }

    private function flushCachedReferenceListsOnChange(): void
    {
        $models = [
            Attribute::class,
            Unit::class,
            Nutrition::class,
            Allergy::class,
            GenericName::class,
            DMVehicle::class,
            WithdrawalMethod::class,
            Currency::class,
            OrderCancelReason::class,
            AddOn::class,
            FAQ::class,
            AdminFeature::class,
            AdminTestimonial::class,
            AdminSpecialCriteria::class,
            AdminPromotionalBanner::class,
            FlutterSpecialCriteria::class,
            ReactTestimonial::class,
            ReactPromotionalBanner::class,
        ];

        foreach ($models as $model) {
            $model::saved(function () {
                Helpers::deleteCacheData('ref_list_');
            });
            $model::deleted(function () {
                Helpers::deleteCacheData('ref_list_');
            });
        }

        foreach ([Category::class, Module::class, Tag::class, AddOn::class] as $model) {
            $model::saved(fn () => Helpers::clearReferenceLookupMemo());
            $model::deleted(fn () => Helpers::clearReferenceLookupMemo());
        }
    }

    private function composeAdminSidebarCounts(): void
    {
        View::composer('layouts.admin.partials._sidebar_v2', function ($view) {
            $moduleId = Config::get('module.current_module_id');

            $view->with(AdminSidebarCountService::get(
                moduleId: $moduleId === null ? null : (int) $moduleId,
                isParcel: Config::get('module.current_module_type') === 'parcel'
            ));
        });
    }

    /**
     * The vendor layout shell and header. Both are included on every vendor page,
     * so there is no controller to own their data.
     */
    private function composeVendorLayout(): void
    {
        View::composer('layouts.vendor.app', function ($view) {
            $view->with(app(VendorLayoutService::class)->build());
        });

        View::composer('layouts.vendor.partials._header', function ($view) {
            $view->with(app(VendorHeaderService::class)->build(app(VendorChromeService::class)->storeWallet()));
        });
    }

    /**
     * Both vendor sidebars render together (v1 always, v2 additionally under the
     * v2 chrome), so the view model is built once and shared by both.
     */
    /**
     * Shares `$distanceUnitLabel` with the screens that print a distance unit in static text —
     * a field label, a column header, a placeholder — rather than a measured value.
     *
     * MEASURED values do not come through here. They are read off a model accessor
     * (`Order::$distance_label`, `Trips::$distance_label`, `RideRequest::$estimated_distance_label`)
     * so the number is CONVERTED as well as relabelled; a shared label alone would have shown a
     * kilometre figure with "mi" written after it, which is the exact defect QA case TC_06 looks
     * for.
     *
     * A composer rather than fifteen controller edits, and the blades still only print a
     * variable (rule 8). DistanceService memoises the lookup, so this costs one query per request.
     */
    private function composeDistanceUnitLabel(): void
    {
        View::composer([
            'admin-views.zone.module-setup',
            'rental::admin.vehicle.create',
            'rental::admin.vehicle.edit',
            'rental::provider.vehicle.create',
            'rental::provider.vehicle.edit',
            'rental::admin.trip.details',
            'rental::provider.trip.details',
            'rental::admin.trip.partials._invoice',
            'ride-share::admin.fare-management.trip.create',
            'ride-share::admin.business-management.business-setup.settings',
        ], function ($view) {
            $view->with('distanceUnitLabel', app(\App\Services\System\DistanceService::class)->unitLabel());
        });
    }

    private function composeVendorSidebar(): void
    {
        View::composer([
            'layouts.vendor.partials._sidebar',
            'layouts.vendor.partials._sidebar_v2',
        ], function ($view) {
            $view->with('sidebar', app(VendorSidebarViewModel::class));
        });
    }

    private function composeVendorChrome(): void
    {
        View::composer([
            'layouts.vendor.partials._header',
            'layouts.vendor.partials._header_v2',
        ], function ($view) {
            $chrome = app(VendorChromeService::class);

            $view->with([
                'system_language_setting' => $chrome->systemLanguageSetting(),
                'system_languages' => $chrome->systemLanguages(),
                'unread_message_count' => $chrome->unreadMessageCount(),
                'store_wallet' => $chrome->storeWallet(),
            ]);
        });

        View::composer([
            'vendor-views.wallet.index',
            'vendor-views.wallet.payment_list',
            'vendor-views.wallet.disbursement',
        ], function ($view) {
            $view->with('wallet', app(VendorChromeService::class)->storeWalletOrCreate());
        });

        View::composer('vendor-views.pos._cart', function ($view) {
            $view->with(app(PosCartSummary::class)->build(app(VendorChromeService::class)->posCartModuleZone()));
        });
    }

    /**
     * The authenticated vendor's store, for the blades that used to call
     * Helpers::get_store_data() / get_store_id() inline.
     *
     * These are not extra work: get_store_data() reads the already-hydrated
     * auth('vendor')->user()->stores relation, so this is the same value the blade
     * resolved for itself — it is now resolved once, outside the template.
     *
     * Only views that actually referenced it are listed; a global share would run on
     * every vendor render, including the ones that never needed a store.
     */
    private function composeVendorStoreContext(): void
    {
        View::composer([
            'vendor-views.advertisement.create',
            'vendor-views.advertisement.edit',
            'vendor-views.banner.edit',
            'vendor-views.banner.index',
            'vendor-views.campaign.list',
            'vendor-views.coupon.edit',
            'vendor-views.coupon.index',
            'vendor-views.partials._dashboard-order-stats',
            'vendor-views.pos._single_product_list',
            'vendor-views.pos.index',
            'vendor-views.product.bulk-import',
            'vendor-views.product.edit',
            'vendor-views.product.index',
            'vendor-views.product.list',
            'vendor-views.product.pending_list',
            'vendor-views.product.product_gallery',
            'vendor-views.product.stock_limit_list',
            'vendor-views.product.view',
            'vendor-views.report.expense-report',
            'vendor-views.review.index',
            'vendor-views.shop.edit',
            'vendor-views.shop.shopInfo',
            'vendor-views.subscription.subscriber.vendor-subscription',
            'vendor-views.wallet.disbursement',
            'vendor-views.wallet.index',
            'vendor-views.wallet.payment_list',
        ], function ($view) {
            $store = Helpers::get_store_data();

            $view->with([
                'store_data' => $store,
                'store_id' => $store?->id,
            ]);
        });
    }

    /**
     * Labels for the per-language tab strips ("English(EN)", …).
     *
     * Every one of these blades built the same string inline with
     * Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')'. The language
     * list itself still comes from each controller — only the label map is shared,
     * so a view whose $language is the raw JSON string keeps it.
     */
    private function composeVendorLanguageLabels(): void
    {
        View::composer([
            'vendor-views.addon.edit',
            'vendor-views.addon.index',
            'vendor-views.advertisement.create',
            'vendor-views.advertisement.details',
            'vendor-views.advertisement.edit',
            'vendor-views.coupon.edit',
            'vendor-views.coupon.index',
            'vendor-views.product.requested_product_view',
            'vendor-views.shop.edit',
            'vendor-views.store-category._edit',
            'vendor-views.store-category._form',
        ], function ($view) {
            $labels = [];
            foreach ((array) Helpers::get_business_settings('language') as $lang) {
                $labels[$lang] = Helpers::get_language_name($lang).'('.strtoupper($lang).')';
            }

            $view->with('language_labels', $labels);
        });
    }

    /**
     * Business settings and module labels the vendor blades used to fetch inline.
     *
     * Grouped per setting rather than shared wholesale so a view still only resolves
     * what it actually renders.
     */
    private function composeVendorSettings(): void
    {
        $with = fn (array $views, callable $data) => View::composer($views, fn ($view) => $view->with($data()));

        $with(['vendor-views.dashboard'], fn () => [
            'can_dashboard' => Helpers::employee_module_permission_check('dashboard'),
        ]);

        $with(['vendor-views.auth.register-step-2'], fn () => [
            'commission_check' => Helpers::commission_check(),
        ]);

        $with(['vendor-views.subscription.subscriber.vendor-subscription'], fn () => [
            'commission_check' => Helpers::commission_check(),
            'subscription_check' => Helpers::subscription_check(),
        ]);

        $with(['vendor-views.shop.shopInfo'], fn () => [
            'verified_seller_badge' => Helpers::get_business_settings('verified_seller_badge'),
            'admin_commission' => Helpers::get_business_settings('admin_commission'),
        ]);

        $with(['vendor-views.product.view', 'vendor-views.review.index'], fn () => [
            'store_review_reply' => Helpers::get_business_settings('store_review_reply', false) ?? 0,
        ]);

        $with(['vendor-views.pos.index'], fn () => [
            'map_api_key' => Helpers::get_business_settings('map_api_key', false),
        ]);

        $with(['vendor-views.product.index', 'vendor-views.product.edit'], fn () => [
            'openai_config' => Helpers::get_business_settings('openai_config'),
        ]);

        $with(['vendor-views.wallet.disbursement'], fn () => [
            'store_disbursement_waiting_time' => (int) Helpers::get_business_settings('store_disbursement_waiting_time', false) ?? 0,
        ]);

        $with(['vendor-views.wallet.partials._balance_data'], fn () => [
            'is_wallet_index' => request()->is('vendor-panel/wallet'),
            'is_wallet_payment_list' => request()->is('vendor-panel/wallet/wallet-payment-list'),
            'is_wallet_disbursement' => request()->is('vendor-panel/wallet/disbursement-list'),
            'disbursement_type' => Helpers::get_business_settings('disbursement_type', false) ?? 'manual',
            'min_amount_to_pay_store' => Helpers::get_business_settings('min_amount_to_pay_store', false) ?? 0,
            'digital_payment' => Helpers::get_business_settings('digital_payment'),
        ]);

        $with(['vendor-views.profile.index'], fn () => [
            'loggedin_user' => Helpers::get_loggedin_user(),
        ]);

        $with([
            'vendor-views.advertisement.create',
            'vendor-views.advertisement.edit',
            'vendor-views.advertisement.list',
            'vendor-views.campaign.item_list',
            'vendor-views.review.index',
        ], fn () => [
            'module_item_label' => Helpers::moduleItemLabel(),
            'module_store_label' => Helpers::moduleStoreLabel(),
        ]);

        $with(['vendor-views.advertisement.list'], fn () => [
            'admin_email_address' => Helpers::get_settings('email_address'),
        ]);

        // "Provider" vs "Store" wording. Each of these blades derived it from the store's
        // module_type in its own @php block; the conditions differ slightly per view, so
        // they stay separate rather than collapsing into one shared flag.
        $moduleType = fn () => Helpers::get_store_data()?->module_type;

        $with(['vendor-views.wallet.index'], function () use ($moduleType) {
            $isProvider = ($moduleType() == 'rental' && addon_published_status('Rental')) || $moduleType() == 'service';

            return ['is_provider_module' => $isProvider, 'title' => $isProvider ? 'Provider' : 'Store'];
        });

        $with(['vendor-views.wallet.payment_list', 'vendor-views.wallet.disbursement'], function () use ($moduleType) {
            $isProvider = ($moduleType() == 'rental' && addon_published_status('Rental')) || $moduleType() == 'service';

            return ['is_provider_module' => $isProvider, 'wallet_title_key' => $isProvider ? 'Provider_wallet' : 'store_wallet'];
        });

        $with(['vendor-views.shop.shopInfo'], fn () => [
            'title' => (($moduleType() == 'rental' && addon_published_status('Rental')) || $moduleType() == 'service') ? 'Provider' : 'Store',
        ]);

        $with(['vendor-views.shop.edit'], fn () => [
            'title' => $moduleType() == 'rental' && addon_published_status('Rental') ? 'Provider' : 'Store',
        ]);

        $with(['vendor-views.report.expense-report'], function () use ($moduleType) {
            $vendor = $moduleType();

            return [
                'vendor_module_type' => $vendor,
                'title' => in_array($vendor, ['rental', 'service']) ? 'Provider' : 'Store',
                'order_or_trip' => $vendor == 'rental' ? 'trip' : ($vendor == 'service' ? 'booking' : 'order'),
                'expense_type' => $vendor == 'rental' ? 'vehicle' : ($vendor == 'service' ? 'service' : 'item'),
            ];
        });

        $with(['vendor-views.subscription.subscriber.vendor-subscription'], function () use ($moduleType) {
            $vendor = $moduleType();
            $isService = $vendor == 'service';

            return [
                'vendor_module_type' => $vendor,
                'is_service_module' => $isService,
                'title' => ($vendor == 'rental' || $isService) ? 'Provider' : 'Store',
                'order_or_trip' => $vendor == 'rental' ? 'trip' : ($isService ? 'booking' : 'order'),
            ];
        });

        $with(['vendor-views.review.index'], fn () => [
            'is_service_review' => Helpers::get_store_data()?->module?->module_type === 'service',
        ]);
    }

    private function composeVendorSidebarCounts(): void
    {
        View::composer([
            'layouts.vendor.partials._sidebar',
            'layouts.vendor.partials._sidebar_v2',
        ], function ($view) {
            $store = Helpers::get_store_data();

            $view->with(app(VendorSidebarCountService::class)->counts(
                $store?->id,
                BusinessRules::storeConfirmsOrder() || (bool) $store?->sub_self_delivery
            ));
        });
    }
}
