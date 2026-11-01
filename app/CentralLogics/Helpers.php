<?php

namespace App\CentralLogics;

use App\Models\Item;
use App\Models\Store;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Notification\NotificationDataSetUpTrait;
use App\Traits\Payment\PaymentGatewayTrait;
use DateTime;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\RideShare\Entities\ReviewModule\RideReview;
use Modules\TaxModule\Services\CalculateTaxService;
use Mpdf\Mpdf;
use App\Support\Cache\ApiCache;
use App\Support\Notification\SendNotification;
use App\Services\System\BusinessSettingService;
use App\Services\System\DistanceService;
use App\Services\Order\ExpenseService;
use App\Services\Promotion\StoreDiscountResolver;
use App\Services\Store\StoreConfigService;
use App\Services\Store\StoreCategoryService;
use App\Services\Store\StoreService;
use App\Services\Item\AddonService;
use App\Services\Item\TagService;
use App\Services\Item\CategoryService;
use App\Services\Zone\ZoneService;
use App\Services\DeliveryMan\DmReviewService;
use App\Services\System\FaqService;
use App\Services\System\SocialMediaService;
use App\Services\Marketing\FlashSaleItemService;
use App\Services\Auth\PasswordResetService;
use App\Services\System\NotificationSettingService;
use App\Services\Parcel\ParcelReturnFeeService;
use App\Services\Marketing\ReactPromotionalBannerService;
use Modules\TaxModule\Services\SystemTaxSetupService;
use App\Services\System\TranslationService;
use App\Services\Customer\VisitorLogService;
use App\Services\Payment\SettingService;
use App\Services\System\CurrencyService;
use App\Services\System\DataSettingService;
use App\Services\System\ModuleService;
use App\Services\Payment\StoreSubscriptionService;
use App\Services\Payment\SubscriptionPackageService;
use App\Services\DeliveryMan\DeliveryManService;
use App\Services\DeliveryMan\DeliverymanLoyaltyPointHistoryService;
use App\Services\Customer\UserService;
use App\Support\Notification\NotificationText;
use App\Support\Notification\StoreNotificationSettings;
use App\Support\Notification\Sms;
use App\Support\Storage\FileStorage;

class Helpers
{
    use NotificationDataSetUpTrait, PaymentGatewayTrait;

    /**
     * Read off the authenticated vendor's own store on essentially every vendor panel request.
     * Loaded here rather than through Vendor::$with, which would also apply to admin vendor
     * lists and chunked exports where none of it is needed.
     */
    private const VENDOR_PANEL_STORE_RELATIONS = [
        'stores.module', 'stores.store_sub', 'stores.store_sub.package',
        'stores.store_sub_update_application', 'stores.discount',
        // .dates so HappyHour::isRunningNow() (asked of every approved enrolment while resolving
        // the running window, e.g. Helpers::get_store_discount() pricing a POS product) doesn't
        // query dates() itself per enrolment -- the N+1 Debugbar flags as
        // "HappyHour => HappyHourDate". Loaded once here, cached on the auth guard's user model,
        // rather than repeated with loadMissing() at each vendor-panel call site that needs it.
        'stores.happyHourEnrollments.happyHour.dates',
    ];

    public static function error_processor($validator)
    {
        $err_keeper = [];
        foreach ($validator->errors()->getMessages() as $index => $error) {
            array_push($err_keeper, ['code' => $index, 'message' => translate($error[0])]);
        }

        return $err_keeper;
    }

    public static function decodeJsonToArray($value, $default = [])
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode(json_encode($value), true) ?? $default;
        }

        if (! is_string($value) || $value === '') {
            return $default;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function schedule_order()
    {
        return (bool) self::get_business_settings('schedule_order');
    }

    public static function combinations($arrays)
    {
        $result = [[]];
        foreach ($arrays as $property => $property_values) {
            $tmp = [];
            foreach ($result as $result_item) {
                foreach ($property_values as $property_value) {
                    $tmp[] = array_merge($result_item, [$property => $property_value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    public static function variation_price($product, $variation)
    {
        $decoded = json_decode($variation ?? '', true);

        return self::matched_variation($product, is_array($decoded) ? ($decoded[0] ?? []) : []);
    }

    /**
     * The combination `type` a cart/order line's chosen variation resolves to, or null when none
     * was chosen.
     *
     * BOGO lines can freeze a variation in the food-shape {"name":"variation","values":{"label":
     * [...]}} even for non-food items (see HandlesBogoPricing::bogoCartRows()), so this falls back
     * to that shape's label when the flat {"type":...} key isn't present. Shared by every reader
     * of a stored variation match -- price/stock lookup here and the order-placement stock
     * decrement in PlaceNewOrderTrait -- so a line freezing the food-shape resolves to the same
     * type everywhere instead of only where each caller remembered to unwrap it.
     */
    public static function variationType($match): ?string
    {
        $match = is_array($match) ? $match : [];
        $type = $match['type'] ?? implode('-', (array) data_get($match, 'values.label', []));

        return $type === '' ? null : $type;
    }

    private static function matched_variation($product, $match)
    {
        $result = ['price' => 0, 'stock' => 0];

        $type = self::variationType($match);

        if ($type === null) {
            return $result;
        }

        $variations = json_decode($product['variations'] ?? '', true);

        foreach (is_array($variations) ? $variations : [] as $value) {
            if (isset($value['type']) && $value['type'] == $type) {
                $result = ['price' => $value['price'] ?? 0, 'stock' => $value['stock'] ?? 0];
            }
        }

        return $result;
    }

    public static function pos_variation_price($product, $variation)
    {
        $decoded = json_decode($variation ?? '', true);

        return self::matched_variation($product, is_array($decoded) ? $decoded : []);
    }

    private static function runningFlashSaleQuery()
    {
        return app(FlashSaleItemService::class)->runningQuery();
    }

    private static function getRunningFlashSale($itemId)
    {
        // Resolved once per request. This is called per item from
        // product_discount_calculate(), so querying per id made every product
        // listing an N+1.
        static $running_flash_sales = null;

        if ($running_flash_sales === null) {
            $running_flash_sales = self::runningFlashSaleQuery()->get()->keyBy('item_id');
        }

        return $running_flash_sales[$itemId] ?? null;
    }

    public static function serviceProviderVerifiedBadgeStatus(): bool
    {
        return app(DataSettingService::class)->serviceProviderVerifiedBadgeStatus();
    }

    public static function moduleTypeById(?int $moduleId): ?string
    {
        return app(ModuleService::class)->memoizedTypeById($moduleId);
    }

    public static function get_verified_seller_status(?Store $store = null, mixed $storeConfig = null): int
    {
        $storeConfig ??= $store?->storeConfig;

        $enabled = self::moduleTypeById($store?->module_id) === 'service'
            ? self::serviceProviderVerifiedBadgeStatus()
            : (bool) self::get_business_settings('verified_seller_badge');

        return (int) ($enabled && $storeConfig?->verified_seller);
    }

    public static function pro_discount_data($order): array
    {
        $pro = method_exists($order, 'orderProDiscount') ? $order->orderProDiscount : $order->proDiscount;

        return [
            'pro_discount' => (float) ($pro?->amount_saved ?? 0),
            'benefit_type' => $pro?->benefit_type,
            'delivery_fee_reduction_amount' => (float) ($pro?->delivery_fee_reduction_amount ?? 0),
            'delivery_offer_type' => $pro?->delivery_offer_type,
        ];
    }

    public static function deliverymen_list_formatting($data)
    {
        if ($data instanceof EloquentCollection) {
            $data->loadMissing(['storage', 'last_location']);
        }

        $storage = [];
        foreach ($data as $item) {
            $storage_type = 'public';
            if ($item->storage && count($item->storage) > 0) {
                foreach ($item->storage as $value) {
                    if ($value['key'] == 'image') {
                        $storage_type = $value['value'];
                    }
                }
            }
            $storage[] = [
                'id' => $item['id'],
                'name' => $item['f_name'].' '.$item['l_name'],
                'image' => $item['image'],
                'assigned_order_count' => $item['assigned_order_count'],
                'lat' => $item->last_location ? $item->last_location->latitude : false,
                'lng' => $item->last_location ? $item->last_location->longitude : false,
                'location' => $item->last_location ? $item->last_location->location : '',
                'storage' => $storage_type,
                'image_link' => $item['image_full_url'],
                'image_full_url' => $item['image_full_url'],
            ];
        }
        $data = $storage;

        return $data;
    }

 

    public static function clearReferenceLookupMemo(): void
    {
        foreach ([ModuleService::class, AddonService::class, TagService::class, CategoryService::class] as $service) {
            $service::forgetMemo();
        }
    }

    /**
     * module_type is read off Store from shared partials that have no say in how the store was
     * queried, so resolving it through the relation makes every one of those a lazy load. The
     * modules table is small and fixed for the request, so it is resolved from a memoized map.
     */
    public static function module_type_by_id($module_id): ?string
    {
        return app(ModuleService::class)->memoizedTypeById($module_id);
    }

    public static function clearBusinessSettingsCache(): void
    {
        BusinessSettingService::forgetCache();
        BusinessSettingService::forgetModelMemo();
        self::deleteCacheData('business_settings_all_data');

        // The distance unit is memoised per request inside DistanceService; drop it here so a
        // save-then-render request cannot keep quoting the old unit. This method is already
        // called from BusinessSetting::saved(), so every write path is covered.
        app(DistanceService::class)->forgetUnit();
    }

    public static function get_business_settings($key, $json_decode = true, $relations = [])
    {
        return app(BusinessSettingService::class)->value($key, $json_decode, $relations);
    }

    public static function get_business_settings_many(array $keys, $json_decode = false): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = self::get_business_settings($key, $json_decode);
        }

        return $data;
    }

    public static function copyright_text()
    {
        return translate('Copyright').' '.date('Y').' '.self::get_business_settings('business_name', false).'. '.translate('All rights reserved');
    }

    public static function copyright_placeholder()
    {
        return translate('Ex').':'.' '.self::copyright_text();
    }

    public static function get_business_data($name)
    {
        return self::get_business_settings($name);
    }

    public static function toggle_verified_seller(Store $store, ?int $status = null): int
    {
        return app(StoreConfigService::class)->toggleVerifiedSeller($store, $status);
    }

    public static function mark_verified_badge_popup_seen(Store $store): int
    {
        return app(StoreConfigService::class)->markVerifiedBadgePopupSeen($store);
    }

    public static function get_verified_seller_eligible_stores(bool $countOnly = false, ?string $moduleId = null): mixed
    {
        return app(StoreService::class)->getVerifiedSellerEligibleStores($countOnly, $moduleId);
    }

    public static function get_verified_seller_eligible_providers(bool $countOnly = false, ?string $moduleId = null): mixed
    {
        return app(StoreService::class)->getVerifiedSellerEligibleProviders($countOnly, $moduleId);
    }

    public static function currency_code()
    {
        return app(CurrencyService::class)->code();
    }

    public static function currency_symbol()
    {
        return app(CurrencyService::class)->symbol();
    }

    public static function highlight($text)
    {
        if (! $text) {
            return '';
        }

        return preg_replace('/\$(.+?)\$/', '<span class="hl">$1</span>', e($text));
    }

    public static function format_currency($value)
    {
        return app(CurrencyService::class)->format($value);
    }

    public static function dm_rating_count($deliveryman_id, $rating)
    {
        return app(DmReviewService::class)->countByRating($deliveryman_id, $rating);
    }

    public static function rider_rating_count($rider_id, $rating)
    {
        return RideReview::where(['received_by' => $rider_id, 'review_for' => 'driver', 'rating' => $rating])->count();
    }

    public static function tax_calculate($item, $price)
    {
        if ($item['tax_type'] == 'percent') {
            $price_tax = ($price / 100) * $item['tax'];
        } else {
            $price_tax = $item['tax'];
        }

        return $price_tax;
    }

    public static function discount_calculate($product, $price)
    {
        if ($product['store_discount']) {
            $price_discount = ($price / 100) * $product['store_discount'];
        } elseif ($product['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $product['discount'];
        } else {
            $price_discount = $product['discount'];
        }

        return $price_discount;
    }

    public static function get_product_discount($product)
    {
        $store_discount = self::get_store_discount($product->store);
        if ($store_discount) {
            $discount = $store_discount['discount'].' %';
        } elseif ($product['discount_type'] == 'percent') {
            $discount = $product['discount'].' %';
        } else {
            $discount = self::format_currency($product['discount']);
        }

        return $discount;
    }

    public static function product_discount_calculate($product, $price, $store, $check_store_discount = true)
    {
        $discount_percentage = 0;
        $store_discount_percentage = 0;
        $store_discount = null;

        $running_flash_sale = self::getRunningFlashSale($product['id']);

        if ($running_flash_sale) {
            $discount_percentage = $running_flash_sale['discount'];
            if ($running_flash_sale['discount_type'] == 'percent') {
                $price_discount = ($price / 100) * $running_flash_sale['discount'];
            } else {
                $price_discount = $running_flash_sale['discount'];
            }

            return [
                'discount_type' => 'flash_sale',
                'discount_amount' => $price_discount,
                'admin_discount_amount' => ($price_discount * $running_flash_sale->flashSale->admin_discount_percentage) / 100,
                'vendor_discount_amount' => ($price_discount * $running_flash_sale->flashSale->vendor_discount_percentage) / 100,
                'discount_percentage' => $discount_percentage ?? 0,
                'original_discount_type' => $running_flash_sale['discount_type'],
            ];
        }
        return self::item_store_discount_calculate($product, $price, $store, $check_store_discount, 'product_discount');
    }

    private static function item_store_discount_calculate($item, $price, $store, $check_store_discount, string $ownDiscountType)
    {
        $store_discount = null;
        $store_discount_percentage = 0;
        $store_price_discount = 0;

        if ($check_store_discount) {
            $store_discount = self::get_store_discount($store);
            if (isset($store_discount)) {
                $store_price_discount = ($price / 100) * $store_discount['discount'];
                $store_discount_percentage = $store_discount['discount'];
            }
        }

        $discount_percentage = $item['discount'];
        if ($item['discount_type'] == 'percent') {
            $price_discount = ($price / 100) * $item['discount'];
        } else {
            $price_discount = $item['discount'];
        }

        $discount_percentage = isset($store_discount) && $price_discount == $store_price_discount ? $store_discount_percentage : $discount_percentage ?? 0;

        $price_discount = max($store_price_discount, $price_discount);
        $discount_type = isset($store_discount) && $price_discount == $store_price_discount ? 'store_discount' : $ownDiscountType;

        return [
            'discount_type' => $discount_type,
            'discount_amount' => $price_discount,
            'discount_percentage' => $discount_type == 'store_discount' ? $store_discount['discount'] : $item['discount'],
            'original_discount_type' => $discount_type == 'store_discount' ? 'percent' : $item['discount_type'],
        ];
    }

    public static function service_discount_calculate($service, $price, $store, $check_store_discount = true)
    {
        return self::item_store_discount_calculate($service, $price, $store, $check_store_discount, 'service_discount');
    }

    public static function get_price_range($product, $discount = false)
    {
        $lowest_price = $product->price;
        $highest_price = $product->price;
        if ($product->variations && is_array(json_decode($product['variations'], true))) {
            foreach (json_decode($product->variations) as $key => $variation) {
                if ($lowest_price > $variation->price) {
                    $lowest_price = round($variation->price, 2);
                }
                if ($highest_price < $variation->price) {
                    $highest_price = round($variation->price, 2);
                }
            }
        }

        if ($discount) {
            $lowest_price -= self::product_discount_calculate($product, $lowest_price, $product->store)['discount_amount'];
            $highest_price -= self::product_discount_calculate($product, $highest_price, $product->store)['discount_amount'];
        }
        $lowest_price = self::format_currency($lowest_price);
        $highest_price = self::format_currency($highest_price);

        if ($lowest_price == $highest_price) {
            return $lowest_price;
        }

        return $lowest_price.' - '.$highest_price;
    }

    public static function get_food_price_range($product, $discount = false)
    {
        $lowest_price = $product->price;

        if ($discount) {
            $lowest_price -= self::product_discount_calculate($product, $lowest_price, $product->store)['discount_amount'];

        }
        $lowest_price = self::format_currency($lowest_price);

        return $lowest_price;
    }

    /**
     * The store-wide rate in force right now, or null.
     *
     * Both product_discount_calculate() and service_discount_calculate() call this, which is why
     * Happy Hour is resolved HERE rather than in either of them: one insertion point, and the two
     * near-identical folds downstream cannot drift apart on which promotion won.
     *
     * The returned array gains a 'source' key -- 'happy_hour' or 'store_discount'. Additive, so
     * callers reading only discount / min_purchase / max_discount are unaffected, but it is what
     * lets a caller label the discount_type correctly and lets accounting find the right bearer:
     * a happy hour is borne 100% by the vendor under its own expense type, while an ordinary
     * store discount is split with the admin on commission stores.
     */
    public static function get_store_discount($store)
    {
        return app(StoreDiscountResolver::class)->resolve($store);
    }

    public static function remove_dir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        Helpers::remove_dir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    public static function get_store_id()
    {
        return self::get_store_data()?->id;
    }

    public static function get_vendor_id()
    {
        return self::vendor_panel_actor(fn ($vendor) => $vendor->id, fn ($employee) => $employee->vendor_id);
    }

    private static function vendor_panel_actor(callable $fromVendor, callable $fromEmployee)
    {
        if (auth('vendor')->check()) {
            return $fromVendor(auth('vendor')->user());
        }

        if (auth('vendor_employee')->check()) {
            return $fromEmployee(auth('vendor_employee')->user());
        }

        return 0;
    }

    public static function get_vendor_data()
    {
        return self::vendor_panel_actor(fn ($vendor) => $vendor, fn ($employee) => $employee->vendor);
    }

    public static function get_loggedin_user()
    {
        return self::vendor_panel_actor(fn ($vendor) => $vendor, fn ($employee) => $employee);
    }

    public static function get_store_data()
    {
        if (auth('vendor_employee')->check()) {
            return auth('vendor_employee')->user()?->loadMissing('store.module')?->store;
        }

        $store = auth('vendor')->user()?->loadMissing(self::VENDOR_PANEL_STORE_RELATIONS)?->stores[0];

        self::shareSubscriptionPackage($store);

        return $store;
    }

    private static function shareSubscriptionPackage(?Store $store): void
    {
        $application = $store?->store_sub_update_application;

        if (! $application || $application->relationLoaded('package')) {
            return;
        }

        if ($store->store_sub?->getKey() === $application->getKey()) {
            $application->setRelation('package', $store->store_sub->package);
        }
    }

    public static function storeCategoryStatus(): bool
    {
        return (bool) (self::get_business_settings('store_category_status') ?? 0);
    }

    public static function moduleItemLabel(?string $moduleType = null): string
    {
        return self::resolved_module_type($moduleType) === 'service' ? translate('Service') : translate('item');
    }

    private static function resolved_module_type(?string $moduleType = null): ?string
    {
        return $moduleType
            ?? config('module.current_module_type')
            ?? self::get_store_data()?->module?->module_type;
    }

    public static function moduleStoreLabel(?string $moduleType = null): string
    {
        return self::resolved_module_type($moduleType) === 'service' ? translate('messages.Provider') : translate('messages.Store');
    }

    public static function hasAnyStoreCategory(?int $storeId = null): bool
    {
        return self::storeCategoryStatus()
            && app(StoreCategoryService::class)->existsForResolvedStore($storeId);
    }

    public static function serviceProviderCategoryStatus(): bool
    {
        return app(DataSettingService::class)->serviceProviderCategoryStatus();
    }

    public static function serviceProviderHasAnyCategory(?int $storeId = null): bool
    {
        return self::serviceProviderCategoryStatus()
            && app(StoreCategoryService::class)->existsForResolvedStore($storeId);
    }

    public static function vendorCategoryStatus(): bool
    {
        return (self::get_store_data()?->module?->module_type === 'service')
            ? self::serviceProviderCategoryStatus()
            : self::storeCategoryStatus();
    }

    public static function getDisk()
    {
        return FileStorage::getDisk();
    }

    public static function upload(string $dir, string $format, $image = null, ?int $maxSizeMb = null, ?string $allowedExtensions = null)
    {
        return FileStorage::upload($dir, $image, $maxSizeMb, $allowedExtensions);
    }

    public static function update(string $dir, $old_image, string $format, $image = null, ?int $maxSizeMb = null, ?string $allowedExtensions = null)
    {
        return FileStorage::update($dir, $old_image, $image, $maxSizeMb, $allowedExtensions);
    }

    public static function check_and_delete(string $dir, $old_image)
    {
        return FileStorage::delete($dir, $old_image);
    }

    public static function addons_by_ids($ids)
    {
        return app(AddonService::class)->getMemoizedActiveByIds($ids);
    }

    public static function tags_by_ids($ids)
    {
        return app(TagService::class)->getMemoizedByIds($ids);
    }

    public static function store_addons($storeId = null)
    {
        return app(AddonService::class)->getCachedForStore($storeId);
    }

    public static function cached_list(string $model, array $conditions = [], ?string $orderBy = null, array $columns = ['*'])
    {
        // Models with a storage relation append *_full_url attributes that read it, so the
        // cached rows have to arrive with storage already loaded — every caller renders them
        // in a view, and lazy loading throws outside production. The flag is part of the key
        // so entries cached before this eager load are not handed back without the relation.
        $withStorage = in_array(HasStorageTrait::class, class_uses_recursive($model), true)
            && (in_array('*', $columns, true) || in_array('id', $columns, true));

        $context = [$model, $conditions, $orderBy, $columns, $withStorage, app()->getLocale()];

        return ApiCache::remember('reference_list', $context, function () use ($model, $conditions, $orderBy, $columns, $withStorage) {
            $query = $model::query();
            if ($withStorage) {
                $query->withStorage();
            }
            foreach ($conditions as $column => $value) {
                $query->where($column, $value);
            }
            if ($orderBy) {
                $query->orderBy($orderBy);
            }

            return $query->get($columns);
        });
    }

    public static function modules_list()
    {
        return app(ModuleService::class)->getCachedList();
    }

    public static function landing_meta_data()
    {
        return app(DataSettingService::class)->getLandingMetaData();
    }

    public static function faqs_by_user_type(string $userType, int $limit = 5)
    {
        return app(FaqService::class)->getCachedByUserType($userType, $limit);
    }

    public static function landing_policy_statuses()
    {
        return app(DataSettingService::class)->getLandingPolicyStatuses();
    }

    public static function social_media_active()
    {
        return app(SocialMediaService::class)->getActiveCached();
    }

    public static function customers_by_ids($ids)
    {
        return app(UserService::class)->getBasicByIds($ids);
    }

    public static function zones_dropdown($activeOnly = false)
    {
        return app(ZoneService::class)->getDropdown($activeOnly);
    }

    public static function module_permission_check($mod_name)
    {
        if (! auth('admin')->user()->role) {
            return false;
        }

        if ($mod_name == 'zone' && auth('admin')->user()->zone_id) {
            return false;
        }

        $permission = auth('admin')->user()->role->modules;
        if (isset($permission) && in_array($mod_name, (array) json_decode($permission)) == true) {
            return true;
        }

        if (auth('admin')->user()->role_id == 1) {
            return true;
        }

        return false;
    }

    public static function admin_workspace_modules()
    {
        return [
            'module' => ['dashboard', 'pos', 'order', 'item', 'store', 'category', 'addon', 'banner', 'coupon', 'campaign', 'notification', 'reels', 'parcel', 'promotion', 'ride', 'ride_promotion', 'fare', 'trip', 'vehicle', 'provider', 'driver', 'service_booking', 'service_management', 'download_app', 'store_bulk', 'rental_vehicle_setup', 'rental_provider_bulk', 'rental_banners', 'rental_communication'],
            'users' => ['employee_role', 'employee', 'customer_management', 'customer_wallet', 'customer_loyalty_point', 'cashback', 'deliveryman', 'ride_vehicle', 'rider', 'rider_level', 'rider_review', 'service_provider', 'user_overview'],
            'finance' => ['collect_cash', 'disbursement', 'provide_dm_earning', 'withdraw_list', 'withdraw_method', 'report', 'admin_text_module', 'vendor_vat_report'],
            'reports' => ['report', 'sales_report', 'performance_report', 'expense_report', 'disbursement_report', 'earning_report'],
            'dispatch' => ['dispatch'],
            'settings' => ['module', 'settings', 'subscription', 'pro_customer_subscription', 'customer_management', 'system_tax', 'social_media', 'landing_pages', 'business_pages', 'seo', 'gallery', 'login_setup', 'email_setups', 'notification_setup', 'service_settings', 'third_party-ms', 'clean_database', 'database_backup', 'system_config', 'ride_settings', 'service_management'],
        ];
    }

    /**
     * Which top-level workspace a request path belongs to.
     *
     * The header tab strip, the sidebar shell and the breadcrumb all have to
     * agree on this, so the rules live here rather than being restated in each
     * view. Returns one of: module, users, finance, reports, dispatch, settings.
     */
    public static function admin_workspace_for_path($path = null)
    {
        $path = $path ?? request()->path();

        $is = fn ($pattern) => \Illuminate\Support\Str::is($pattern, $path);

        // `delivery-management` is a Settings workspace path that does not start with
        // `business-settings`. Its screens moved out from under /zone/ on 2026-09-09 to get a URL
        // matching the section they were already shown in; without this line they fall through to
        // 'module' below, which puts them on the wrong sidebar, the wrong top-nav tab and a
        // "Grocery > Module overview" breadcrumb.
        if ($is('admin/business-settings*') || $is('admin/delivery-management*')
            || $is('admin/payment/configuration*')
            || $is('admin/sms/configuration*') || $is('taxvat/*') || $is('admin/pro-customer*')) {
            return 'settings';
        }

        if ($is('admin/users*')) {
            return 'users';
        }

        if ($is('admin/dispatch*')) {
            return 'dispatch';
        }

        if (! $is('admin/transactions*')) {
            return 'module';
        }

        // Tax lives with Finance even though it is filed under the report
        // routes, and a module-scoped order/trip detail reached from a
        // transaction stays in the module workspace.
        $is_tax = $is('admin/transactions/report/*tax*')
            || $is('admin/transactions/rental/report/*tax*')
            || $is('admin/transactions/service/report/*tax*')
            || $is('admin/transactions/ride-share/report/*tax*');

        if (! $is_tax && ($is('admin/transactions/report/*') || $is('admin/transactions/rental/report/*')
            || $is('admin/transactions/service/report/*') || $is('admin/transactions/ride-share/*'))) {
            return 'reports';
        }

        if ($is('admin/transactions/parcel/order/details/*') || $is('admin/transactions/rental/trip/details/*')
            || $is('admin/transactions/rental/trip/generate-invoice/*')) {
            return 'module';
        }

        return 'finance';
    }

    /**
     * Landing URL for a workspace, honouring the admin's own permissions.
     */
    public static function workspace_landing_url($workspace)
    {
        switch ($workspace) {
            case 'users':
                return self::users_workspace_landing_url();
            case 'finance':
                return self::finance_workspace_landing_url();
            case 'reports':
                return self::reports_workspace_landing_url();
            case 'dispatch':
                return route('admin.dispatch.dashboard');
            case 'settings':
                return self::settings_workspace_landing_url();
            default:
                return route('admin.dashboard').'?module_id='.Config::get('module.current_module_id');
        }
    }

    public static function admin_can_access_workspace($workspace)
    {
        $admin = auth('admin')->user();
        if (! $admin || ! $admin->role) {
            return false;
        }
        if ($admin->role_id == 1) {
            return true;
        }
        $modules = self::admin_workspace_modules()[$workspace] ?? [];
        foreach ($modules as $module) {
            if (self::module_permission_check($module)) {
                return true;
            }
        }

        return false;
    }

    public static function reports_workspace_landing_url()
    {
        $map = [
            'report' => 'admin.transactions.report.day-wise-report',
            'earning_report' => 'admin.transactions.report.admin-earning-report',
            'disbursement_report' => 'admin.transactions.report.disbursement_report',
            'expense_report' => 'admin.transactions.report.expense-report',
            'sales_report' => 'admin.transactions.report.order-report',
            'performance_report' => 'admin.transactions.report.store-summary-report',
        ];

        foreach ($map as $key => $route) {
            if (self::module_permission_check($key)) {
                return route($route);
            }
        }

        return route('admin.transactions.report.day-wise-report');
    }

    public static function finance_workspace_landing_url()
    {
        $map = [
            'withdraw_list' => 'admin.transactions.store.withdraw_list',
            'disbursement' => 'admin.transactions.store-disbursement.list',
            'collect_cash' => 'admin.transactions.account-transaction.index',
            'provide_dm_earning' => 'admin.transactions.provide-deliveryman-earnings.index',
            'withdraw_method' => 'admin.transactions.withdraw-method.list',
            'admin_text_module' => 'admin.transactions.report.getTaxReport',
            'vendor_vat_report' => 'admin.transactions.report.vendorWiseTaxes',
        ];

        foreach ($map as $key => $route) {
            if (self::module_permission_check($key)) {
                return $key === 'disbursement' ? route($route, ['status' => 'all']) : route($route);
            }
        }

        return route('admin.transactions.store.withdraw_list');
    }

    public static function ride_share_module_id(): ?int
    {
        return app(ModuleService::class)->findRideShareModuleId();
    }

    public static function users_workspace_landing_url()
    {
        $map = [
            'user_overview' => 'admin.users.dashboard',
            'customer_management' => 'admin.users.customer.list',
            'customer_wallet' => 'admin.users.customer.wallet.add-fund',
            'customer_loyalty_point' => 'admin.users.customer.loyalty-point.report',
            'cashback' => 'admin.users.cashback.add-new',
            'deliveryman' => 'admin.users.delivery-man.list',
            'employee' => 'admin.users.employee.list',
        ];

        foreach ($map as $key => $route) {
            if (self::module_permission_check($key)) {
                return route($route);
            }
        }

        return route('admin.users.dashboard');
    }

    public static function settings_workspace_landing_url()
    {
        if (self::module_permission_check('settings')) {
            return route('admin.business-settings.business-setup');
        }
        if (self::module_permission_check('subscription')) {
            return route('admin.business-settings.subscriptionackage.index');
        }
        if (self::module_permission_check('pro_customer_subscription')) {
            return route('admin.pro-customer.benefits-setup');
        }
        if (self::module_permission_check('customer_management')) {
            return route('admin.pro-customer.list');
        }

        return route('admin.business-settings.business-setup');
    }

    public static function admin_landing_url()
    {
        $admin = auth('admin')->user();
        if (! $admin) {
            return null;
        }
        if ($admin->role_id == 1 || self::module_permission_check('dashboard')) {
            return route('admin.dashboard');
        }
        $candidates = [
            ['pos', 'admin.pos.index', []],
            ['order', 'admin.order.list', ['all']],
            ['item', 'admin.item.list', []],
            ['store', 'admin.store.list', []],
            ['employee_role', 'admin.users.custom-role.list', []],
            ['employee', 'admin.users.employee.list', []],
            ['customer_management', 'admin.users.customer.list', []],
            ['cashback', 'admin.users.cashback.add-new', []],
            ['collect_cash', 'admin.transactions.account-transaction.index', []],
            ['provide_dm_earning', 'admin.transactions.provide-deliveryman-earnings.index', []],
            ['disbursement', 'admin.transactions.store-disbursement.list', []],
            ['withdraw_list', 'admin.transactions.store.withdraw_list', []],
            ['report', 'admin.transactions.report.day-wise-report', []],
            ['module', 'admin.business-settings.module.index', []],
            ['zone', 'admin.business-settings.zone.home', []],
            ['settings', 'admin.business-settings.business-setup', []],
        ];
        foreach ($candidates as $candidate) {
            [$module, $routeName, $params] = $candidate;
            if (self::module_permission_check($module) && Route::has($routeName)) {
                return route($routeName, $params);
            }
        }

        return null;
    }

    public static function employee_module_permission_check($mod_name)
    {
        if (auth('vendor')->check()) {
            // The menu asks this once per entry, so the store is loaded once here rather than
            // lazily on whichever entry happens to be checked first. module comes with it for
            // the addon branch below. One query for the signed-in vendor, reused by the rest of
            // the request -- and lazy loading throws outside production.
            auth('vendor')->user()->loadMissing('stores.module');

            if ($mod_name == 'reviews') {
                return auth('vendor')->user()->stores[0]->reviews_section;
            } elseif ($mod_name == 'deliveryman' || $mod_name == 'deliveryman_list') {
                return auth('vendor')->user()->stores[0]->self_delivery_system;
            } elseif ($mod_name == 'pos') {
                return auth('vendor')->user()->stores[0]->pos_system;
            } elseif ($mod_name == 'addon') {
                return config('module.'.auth('vendor')->user()->stores[0]->module->module_type)['add_on'];
            }

            return true;
        } elseif (auth('vendor_employee')->check()) {
            if (! auth('vendor_employee')->user()->role) {
                return false;
            }
            $permission = auth('vendor_employee')->user()->role->modules;
            if (isset($permission) && in_array($mod_name, (array) json_decode($permission)) == true) {
                if ($mod_name == 'reviews') {
                    return auth('vendor_employee')->user()->store->reviews_section;
                } elseif ($mod_name == 'deliveryman' || $mod_name == 'deliveryman_list') {
                    return auth('vendor_employee')->user()->store->self_delivery_system;
                } elseif ($mod_name == 'pos') {
                    return auth('vendor_employee')->user()->store->pos_system;
                } elseif ($mod_name == 'addon') {
                    return config('module.'.auth('vendor_employee')->user()->store->module->module_type)['add_on'];
                }

                return true;
            }
        }

        return false;
    }

    public static function employee_landing_url()
    {
        if (! auth('vendor_employee')->check()) {
            return null;
        }
        if (self::employee_module_permission_check('dashboard')) {
            return null;
        }
        $candidates = [
            ['pos', 'vendor.pos.index', []],
            ['order', 'vendor.order.list', ['all']],
            ['item', 'vendor.item.list', []],
            ['campaign', 'vendor.campaign.list', []],
            ['coupon', 'vendor.coupon.add-new', []],
            ['banner', 'vendor.banner.list', []],
            ['advertisement', 'vendor.advertisement.index', []],
            ['wallet', 'vendor.wallet.index', []],
            ['employee', 'vendor.employee.list', []],
            ['role', 'vendor.custom-role.index', []],
            ['reviews', 'vendor.reviews', []],
            ['my_shop', 'vendor.shop.view', []],
            ['store_setup', 'vendor.store-category.list', []],
            ['chat', 'vendor.message.list', []],
        ];
        foreach ($candidates as $candidate) {
            [$module, $routeName, $params] = $candidate;
            if (! self::employee_module_permission_check($module)) {
                continue;
            }
            if (! Route::has($routeName)) {
                continue;
            }
            if (! self::vendor_route_subscription_ok($routeName, $module)) {
                continue;
            }

            return route($routeName, $params);
        }

        return null;
    }

    public static function vendor_route_subscription_ok($routeName, $module)
    {
        $route = Route::getRoutes()->getByName($routeName);
        $subscription_gated = false;
        if ($route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (is_string($mw) && str_starts_with($mw, 'subscription')) {
                    $subscription_gated = true;
                    break;
                }
            }
        }
        if (! $subscription_gated) {
            return true;
        }
        $store = self::get_store_data();
        if (! $store || $store->store_business_model == 'commission') {
            return true;
        }
        if ($store->store_business_model == 'subscription') {
            $store_sub = $store->store_sub;
            if ($store_sub == null) {
                return false;
            }
            $package = [
                'reviews' => $store_sub->review,
                'pos' => $store_sub->pos,
                'deliveryman' => $store_sub->self_delivery,
                'deliveryman_list' => $store_sub->self_delivery,
                'chat' => $store_sub->chat,
            ];
            if (array_key_exists($module, $package)) {
                return $package[$module] == 1;
            }

            return true;
        }

        return false;
    }

    public static function calculate_addon_price($addons, $add_on_qtys)
    {
        $add_ons_cost = 0;
        $data = [];
        if ($addons) {
            foreach ($addons as $key2 => $addon) {
                if ($add_on_qtys == null) {
                    $add_on_qty = 1;
                } else {
                    $add_on_qty = $add_on_qtys[$key2];
                }
                $data[] = ['id' => $addon->id, 'name' => $addon->name, 'price' => $addon->price, 'quantity' => $add_on_qty, 'category_id' => $addon->addon_category_id];
                $add_ons_cost += $addon['price'] * $add_on_qty;
            }

            return ['addons' => $data, 'total_add_on_price' => $add_ons_cost];
        }

        return null;
    }

    public static function get_settings($name)
    {
        return self::get_business_settings($name);
    }

    public static function setEnvironmentValue($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $oldValue = env($envKey);
        if (strpos($str, $envKey) !== false) {
            $str = str_replace("{$envKey}={$oldValue}", "{$envKey}={$envValue}", $str);
        } else {
            $str .= "{$envKey}={$envValue}\n";
        }
        $fp = fopen($envFile, 'w');
        fwrite($fp, $str);
        fclose($fp);

        return $envValue;
    }

    public static function setEnvironmentValueIfMissing($envKey, $envValue)
    {
        $envFile = app()->environmentFilePath();

        if (! file_exists($envFile) || ! is_writable($envFile)) {
            return null;
        }

        $contents = file_get_contents($envFile);

        if (preg_match('/^\s*'.preg_quote($envKey, '/').'\s*=/m', $contents) === 1) {
            return null;
        }

        file_put_contents($envFile, rtrim($contents, "\r\n")."\n".$envKey.'='.$envValue."\n", LOCK_EX);

        return $envValue;
    }

    public static function system_permission_check(): array
    {
        $permission['curl_enabled'] = function_exists('curl_version');
        $permission['curl'] = function_exists('curl_version');
        $permission['bcmath'] = extension_loaded('bcmath');
        $permission['ctype'] = extension_loaded('ctype');
        $permission['json'] = extension_loaded('json');
        $permission['mbstring'] = extension_loaded('mbstring');
        $permission['openssl'] = extension_loaded('openssl');
        $permission['pdo'] = defined('PDO::ATTR_DRIVER_NAME');
        $permission['tokenizer'] = extension_loaded('tokenizer');
        $permission['xml'] = extension_loaded('xml');
        $permission['zip'] = extension_loaded('zip');
        $permission['fileinfo'] = extension_loaded('fileinfo');
        $permission['gd'] = extension_loaded('gd');
        $permission['sodium'] = extension_loaded('sodium');
        $permission['pdo_mysql'] = extension_loaded('pdo_mysql');
        $permission['db_file_write_perm'] = is_writable(base_path('.env'));
        $permission['config_file_write_perm'] = is_writable(base_path('config/system-addons.php'));
        $permission['routes_file_write_perm'] = is_writable(base_path('app/Providers/RouteServiceProvider.php'));
        // Not a permission, but the same class of blocker. Install and update both leave
        // wizard mode by copying this file over RouteServiceProvider.php; without it the
        // copy has no source and every URL keeps redirecting to the wizard. It is the one
        // non-.php file under app/, so FTP filters and rsync --include=*.php rules drop it.
        // Checked here so the wizard's submit button disables instead of dead-ending.
        $permission['routes_backup_file_exists'] = is_readable(base_path('app/Providers/RouteServiceProvider.txt'));

        return $permission;
    }

    /**
     * Is this file one of the wizard stubs from installation/activate_*_routes.txt?
     *
     * The stubs group routes/install.php or routes/update.php and nothing else; the real
     * provider groups routes/web.php and never mentions either. Used to keep a stub from
     * being snapshotted into - or restored from - RouteServiceProvider.txt, which leaves
     * the wizard restoring itself over itself and the site stuck in wizard mode.
     */
    public static function is_wizard_route_provider(string $path): bool
    {
        if (!is_readable($path)) {
            return false;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        return (str_contains($contents, 'routes/install.php') || str_contains($contents, 'routes/update.php'))
            && !str_contains($contents, 'routes/web.php');
    }

    public static function system_file_checks(): array
    {
        return [
            'db_file_write_perm' => ['label' => '.env File Permission', 'path' => base_path('.env'), 'requirement' => 'Write permission is required for:'],
            'config_file_write_perm' => ['label' => 'config/system-addons.php File Permission', 'path' => base_path('config/system-addons.php'), 'requirement' => 'Write permission is required for:'],
            'routes_file_write_perm' => ['label' => 'RouteServiceProvider.php File Permission', 'path' => base_path('app/Providers/RouteServiceProvider.php'), 'requirement' => 'Write permission is required for:'],
            'routes_backup_file_exists' => ['label' => 'RouteServiceProvider.txt File', 'path' => base_path('app/Providers/RouteServiceProvider.txt'), 'requirement' => 'This file restores normal routing when the wizard finishes. Re-upload it from the package to:'],
        ];
    }

    public static function insert_business_settings_key($key, $value = null)
    {
        return app(BusinessSettingService::class)->insertKeyIfMissing($key, $value);
    }

    public static function insert_data_settings_key($key, $type, $value = null)
    {
        return app(DataSettingService::class)->insertKeyIfMissing($key, $type, $value);
    }

    public static function get_language_name($key)
    {
        return array_key_exists($key, LANGUAGE_NAMES) ? LANGUAGE_NAMES[$key] : $key;
    }

    public static function get_view_keys()
    {
        $data = [];
        foreach (['toggle_veg_non_veg', 'toggle_dm_registration', 'toggle_store_registration'] as $key) {
            $value = self::get_business_settings($key, false);

            if ($value !== null) {
                $data[$key] = (bool) $value;
            }
        }
        $data['MAX_FILE_SIZE'] = self::maxUploadSizeMb();
        $data['PRODUCT_VIDEO_MAX_FILE_SIZE'] = self::productVideoMaxUploadSizeMb();

        return $data;
    }

    private static array $dataSettingsMemo = [];

    /**
     * Memoized per (type, key) for the request: the landing page layout and its `home` child view
     * both read the same setting (e.g. toggle_rider_registration) independently -- the layout's
     * top-level @php runs after the child's @section content is already captured, so the child
     * can't just reuse a variable the layout sets, and re-queries instead. Caching here fixes that
     * without restructuring the Blade inheritance.
     */
    public static function get_data_settings($type, $key)
    {
        $memoKey = $type . ':' . $key;

        if (! array_key_exists($memoKey, self::$dataSettingsMemo)) {
            self::$dataSettingsMemo[$memoKey] = app(DataSettingService::class)->findByTypeAndKey($type, $key);
        }

        return self::$dataSettingsMemo[$memoKey];
    }

    public static function system_default_language()
    {
        return self::system_language_field('code');
    }

    /**
     * The extra-language codes (default excluded) that are both configured for content
     * translation and still active. `business_settings.language` is append-only — disabling a
     * language via LanguageController::update_status() only flips its `status` inside the
     * separate `system_language` key and never removes the code here — so a screen that builds
     * its translation tabs from `language` alone keeps offering a tab for a language an admin has
     * already turned off (TC_57).
     *
     * @return array<int, string>
     */
    public static function active_extra_languages(): array
    {
        $activeCodes = collect(self::get_business_settings('system_language') ?? [])
            ->where('status', 1)
            ->pluck('code')
            ->all();

        return array_values(array_intersect(self::get_business_settings('language') ?? [], $activeCodes));
    }

    private static function system_language_field(string $field)
    {
        $languages = self::get_business_settings('system_language');
        $lang = 'en';

        foreach ($languages as $language) {
            if ($language['default']) {
                $lang = $language[$field];
            }
        }

        return $lang;
    }

    public static function system_default_direction()
    {
        return self::system_language_field('direction');
    }

    public static function generate_referer_code($type = null)
    {
        return self::unique_random_code(10, fn ($code) => self::referer_code_exists($code, $type));
    }

    private static function unique_random_code(int $length, callable $exists): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while ($exists($code));

        return $code;
    }

    public static function referer_code_exists($ref_code, $type = null)
    {
        return $type == 'deliveryman'
            ? app(DeliveryManService::class)->refCodeExists($ref_code)
            : app(UserService::class)->refCodeExists($ref_code);
    }

    public static function generate_reset_password_code()
    {
        return self::unique_random_code(15, fn ($code) => self::reset_password_code_exists($code));
    }

    public static function reset_password_code_exists($code)
    {
        return app(PasswordResetService::class)->tokenExists($code);
    }

    public static function number_format_short($n)
    {
        if ($n < 900) {
            $n = $n;
            $suffix = '';
        } elseif ($n < 900000) {
            $n = $n / 1000;
            $suffix = 'K';
        } elseif ($n < 900000000) {
            $n = $n / 1000000;
            $suffix = 'M';
        } elseif ($n < 900000000000) {
            $n = $n / 1000000000;
            $suffix = 'B';
        } else {
            $n = $n / 1000000000000;
            $suffix = 'T';
        }

        if (! session()->has('currency_symbol_position')) {
            $currency_symbol_position = self::get_business_settings('currency_symbol_position');
            session()->put('currency_symbol_position', $currency_symbol_position);
        }
        $currency_symbol_position = session()->get('currency_symbol_position');

        return $currency_symbol_position == 'right' ? number_format($n, config('round_up_to_digit')).$suffix.' '.self::currency_symbol() : self::currency_symbol().' '.number_format($n, config('round_up_to_digit')).$suffix;
    }

    public static function hex_to_rbg($color)
    {
        [$r, $g, $b] = sscanf($color, '#%02x%02x%02x');
        $output = "$r, $g, $b";

        return $output;
    }

    public static function expenseCreate($amount, $type, $datetime, $created_by, $order_id = null, $store_id = null, $description = '', $delivery_man_id = null, $user_id = null, $ride_id = null)
    {
        return app(ExpenseService::class)->create([
            'amount' => $amount,
            'type' => $type,
            'created_by' => $created_by,
            'order_id' => $order_id,
            'store_id' => $store_id,
            'description' => $description,
            'delivery_man_id' => $delivery_man_id,
            'user_id' => $user_id,
            'ride_id' => $ride_id,
        ]);
    }

    public static function get_varient(array $product_variations, $variations)
    {
        $result = [];
        $variation_price = 0;

        foreach ($variations as $k => $variation) {
            foreach ($product_variations as $product_variation) {
                if (isset($variation['values']) && isset($product_variation['values']) && $product_variation['name'] == $variation['name']) {
                    $result[$k] = $product_variation;
                    $result[$k]['values'] = [];
                    $selected_labels = data_get($variation, 'values.label', []);
                    foreach ($product_variation['values'] as $key => $option) {
                        $label = data_get($option, 'label');
                        if ($label !== null && in_array($label, $selected_labels)) {
                            $result[$k]['values'][] = $option;
                            $variation_price += data_get($option, 'optionPrice', 0);
                        }
                    }
                }
            }
        }

        return ['price' => $variation_price, 'variations' => $result];
    }

    public static function get_edit_varient(array $product_variations, $variations)
    {
        $result = [];
        $variation_price = 0;

        foreach ($variations as $k => $variation) {
            foreach ($product_variations as $product_variation) {
                if (
                    isset($variation['values']) &&
                    isset($product_variation['values']) &&
                    $product_variation['name'] == $variation['name']
                ) {
                    $result[$k] = $product_variation;
                    $result[$k]['values'] = [];

                    foreach ($product_variation['values'] as $option) {
                        foreach ($variation['values'] as $selected) {
                            if (isset($selected['label']) && $option['label'] === $selected['label']) {
                                $result[$k]['values'][] = $option;
                                $variation_price += $option['optionPrice'];
                                break;
                            }
                        }
                    }
                }
            }
        }

        return ['price' => $variation_price, 'variations' => $result];
    }

    public static function food_variation_price($product, $variations)
    {
        $match = $variations;
        $result = 0;
        foreach ($product as $product_variation) {
            foreach ($product_variation['values'] as $option) {
                foreach ($match as $variation) {
                    $label = data_get($option, 'label');
                    if ($product_variation['name'] == $variation['name'] && $label !== null && in_array($label, data_get($variation, 'values.label', []))) {
                        $result += data_get($option, 'optionPrice', 0);
                    }
                }
            }
        }

        return $result;
    }

    public static function gen_mpdf($view, $file_prefix, $file_postfix)
    {
        $mpdf = new Mpdf(['tempDir' => __DIR__.'/../../storage/tmp', 'default_font' => 'Inter', 'mode' => 'utf-8', 'format' => [190, 250]]);
        /* $mpdf->AddPage('XL', '', '', '', '', 10, 10, 10, '10', '270', ''); */
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;

        $mpdf_view = $view;
        $mpdf_view = $mpdf_view->render();
        $mpdf->WriteHTML($mpdf_view);
        $mpdf->Output($file_prefix.$file_postfix.'.pdf', 'D');
    }

    public static function auto_translator($q, $sl, $tl)
    {
        $q = (string) $q;

        if (trim($q) === '' || $sl === $tl) {
            return $q;
        }

        $tokens = [];
        $base = 0;
        $masked = $q;

        if (preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $q, $matches)) {
            $tokens = array_values(array_unique($matches[0]));

            if (preg_match_all('/#(\d+)#/', $q, $existing)) {
                $base = max(array_map('intval', $existing[1])) + 1;
            }

            $mask = [];
            foreach ($tokens as $i => $token) {
                $mask[$token] = '#' . ($base + $i) . '#';
            }

            uksort($mask, fn ($a, $b) => strlen($b) <=> strlen($a));

            $masked = strtr($q, $mask);
        }

        $translated = self::translateRemotely($masked, $sl, $tl);

        if ($translated === null) {
            return $q;
        }

        if (! $tokens) {
            return $translated;
        }

        $restored = preg_replace_callback('/#\s*(\d+)\s*#/', function ($match) use ($tokens, $base) {
            return $tokens[(int) $match[1] - $base] ?? $match[0];
        }, $translated);

        foreach ($tokens as $token) {
            if (! str_contains($restored, $token)) {
                return $q;
            }
        }

        return $restored;
    }

    /**
     * The client ids the endpoint accepts, tried in order.
     *
     * `gtx` is the one this has always used and stays first. Google throttles these per client id
     * per source IP, and it started answering `gtx` with a 429 "Sorry..." page for server traffic
     * — which `auto_translator()` could only report as "unchanged", so the bulk translator saw
     * twenty rows come back in English and gave up with "Translation service did not respond".
     * The other two answer normally and return byte-identical JSON, so falling through to them
     * turns a hard stop back into a translation.
     *
     * Not a retry of the same request: each entry is a different id, so a throttled one is
     * skipped rather than hammered.
     */
    private const TRANSLATE_CLIENTS = ['gtx', 'dict-chrome-ex', 'at'];

    /** Why the last remote call failed, for a caller that wants to say something specific. */
    private static ?string $translateFailure = null;

    /** 'rate_limited' | 'unreachable' | null when the last call succeeded. */
    public static function lastTranslationFailure(): ?string
    {
        return self::$translateFailure;
    }

    /**
     * One phrase through the endpoint, or NULL when no client id could answer.
     *
     * The working client is remembered for the rest of the request: the bulk translator makes
     * twenty calls, and without this every one of them would pay a round trip to a throttled
     * `gtx` before falling through.
     */
    private static function translateRemotely(string $masked, string $sl, string $tl): ?string
    {
        static $preferred = null;

        $options = self::curl_supports_http2() ? ['version' => 2.0] : [];
        $clients = $preferred
            ? array_merge([$preferred], array_diff(self::TRANSLATE_CLIENTS, [$preferred]))
            : self::TRANSLATE_CLIENTS;

        $failure = 'unreachable';

        foreach ($clients as $client) {
            try {
                $response = Http::timeout(10)->withOptions($options)->get('https://translate.googleapis.com/translate_a/single', [
                    'client' => $client,
                    'ie' => 'UTF-8',
                    'oe' => 'UTF-8',
                    'dt' => 't',
                    'sl' => $sl,
                    'tl' => $tl,
                    'q' => $masked,
                ]);
            } catch (\Throwable $exception) {
                continue;
            }

            if (! $response->successful()) {
                // 429 is the throttle, and it is worth telling apart from an outage: the advice
                // is "wait", not "try again now".
                if ($response->status() === 429) {
                    $failure = 'rate_limited';
                }

                continue;
            }

            $data = $response->json();

            if (! isset($data[0]) || ! is_array($data[0])) {
                continue;
            }

            $translated = '';
            foreach ($data[0] as $segment) {
                if (is_array($segment) && isset($segment[0]) && is_string($segment[0])) {
                    $translated .= $segment[0];
                }
            }

            if (trim($translated) === '') {
                continue;
            }

            $preferred = $client;
            self::$translateFailure = null;

            return $translated;
        }

        self::$translateFailure = $failure;

        return null;
    }

    /*
     * Checked rather than retried: a fallback retry would double the wait on
     * every call, and the bulk translator makes 20 of them per request.
     */
    private static function curl_supports_http2(): bool
    {
        static $supported = null;

        if ($supported === null) {
            $supported = function_exists('curl_version')
                && defined('CURL_VERSION_HTTP2')
                && (bool) ((curl_version()['features'] ?? 0) & CURL_VERSION_HTTP2);
        }

        return $supported;
    }

    public static function language_load()
    {
        return app(BusinessSettingService::class)->findSystemLanguage('language_settings');
    }

    public static function vendor_language_load()
    {
        return app(BusinessSettingService::class)->findSystemLanguage('vendor_language_settings');
    }

    public static function landing_language_load()
    {
        return app(BusinessSettingService::class)->findSystemLanguage('landing_language_settings');
    }

    public static function Export_generator($datas)
    {
        foreach ($datas as $data) {
            yield $data;
        }

        return true;
    }

    public static function formatDeliverymanText(?string $value, $deliveryMan = null, bool $includeRiderOption = false): ?string
    {
        return NotificationText::forDeliveryman($value, $deliveryMan, $includeRiderOption);
    }

    public static function get_login_url($type)
    {
        return app(DataSettingService::class)->findLoginUrlKey($type);
    }

    public static function get_zones_name($zones)
    {
        return app(ZoneService::class)->getNamesByIds($zones);
    }

    public static function get_stores_name($stores)
    {
        return app(StoreService::class)->getNamesByIds($stores);
    }

    public static function get_category_name($id)
    {
        $id = json_decode($id, true);

        return self::category_name_by_id(data_get($id, '0.id', 'NA'));
    }

    public static function get_sub_category_name($id)
    {
        $id = json_decode($id, true);

        return self::category_name_by_id(data_get($id, '1.id', 'NA'));
    }

    private static function category_name_by_id($id)
    {
        return app(CategoryService::class)->findMemoizedNameById($id);
    }

    public static function get_attributes($choice_options)
    {
        try {
            $data = [];
            foreach ((array) json_decode($choice_options) as $key => $choice) {
                $data[$choice->title] = $choice->options;
            }

            return self::sanitized_json_text($data, true);
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}", $ex->getMessage()]);

            return 0;
        }
    }

    public static function get_module_name($id)
    {
        return self::modules_list()->firstWhere('id', $id)?->module_name;
    }

    public static function get_food_variations($variations)
    {
        try {
            $data = [];
            $data2 = [];
            foreach ((array) json_decode($variations, true) as $key => $choice) {
                foreach ($choice['values'] as $k => $v) {
                    $data2[$k] = $v['label'];
                }
                $data[$choice['name']] = $data2;
            }

            return self::sanitized_json_text($data);
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}", $ex->getMessage()]);

            return 0;
        }

    }

    public static function get_customer_name($id)
    {
        return app(UserService::class)->findFullName($id);
    }

    public static function get_addon_data($id)
    {
        try {
            $data = [];
            $addon = app(AddonService::class)->getMemoizedNameAndPriceByIds(json_decode($id, true));
            foreach ($addon as $key => $value) {
                $data[$key] = $value['name'].' - '.Helpers::format_currency($value['price']);
            }

            return self::sanitized_json_text($data, false, JSON_UNESCAPED_UNICODE);
        } catch (\Exception $ex) {
            info(["line___{$ex->getLine()}", $ex->getMessage()]);

            return 0;
        }
    }

    public static function add_or_update_translations($request, $key_data, $name_field, $model_name, $data_id, $data_value, $model_class = false)
    {
        return app(TranslationService::class)->addOrUpdate($request, $key_data, $name_field, $model_name, $data_id, $data_value, $model_class);
    }

    /**
     * Row count and id / created_at bounds for a bulk export or import screen, so
     * the form can show the ranges its filters accept. One aggregate query, and
     * the caller passes the already scoped query.
     */
    public static function bulkDataSummary($query): array
    {
        $summary = $query
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('MIN(id) as min_id, MAX(id) as max_id')
            ->selectRaw('MIN(created_at) as first_created_at, MAX(created_at) as last_created_at')
            ->first();

        return [
            'total' => (int) ($summary?->total ?? 0),
            'min_id' => $summary?->min_id,
            'max_id' => $summary?->max_id,
            'first_created_at' => $summary?->first_created_at,
            'last_created_at' => $summary?->last_created_at,
        ];
    }

    public static function time_date_format($data)
    {
        return self::translated_date_format($data, 'd M Y '.self::time_format_pattern());
    }

    private static function time_format_pattern(): string
    {
        return config('timeformat') ?? 'H:i';
    }

    private static function translated_date_format($data, string $format)
    {
        return Carbon::parse($data)->locale(app()->getLocale())->translatedFormat($format);
    }

    public static function date_format($data)
    {
        return self::translated_date_format($data, 'd M Y');
    }

    public static function time_format($data)
    {
        return self::translated_date_format($data, self::time_format_pattern());
    }

    /**
     * How long a BOGO offer runs, in the largest unit that still reads as a duration.
     *
     * Days are counted inclusively -- an offer that starts and ends on the same date runs for one
     * day, not zero -- and anything shorter than a day is given in hours, because a same-day offer
     * shown as "1 Days" told the admin nothing about a two-hour window.
     */
    public static function bogo_offer_duration($offer): string
    {
        if (! $offer?->start_date || ! $offer?->end_date) {
            return 'N/A';
        }

        $hours = (int) round($offer->start_date->diffInHours($offer->end_date));

        if ($hours < 24) {
            return max(1, $hours).' '.translate('messages.Hours');
        }

        return ($offer->start_date->copy()->startOfDay()->diffInDays($offer->end_date->copy()->startOfDay()) + 1)
            .' '.translate('messages.Days');
    }

    public static function get_full_url($path, $data, $type, $placeholder = null)
    {
        if ($data && $type === 's3') {
            try {
                return Storage::disk('s3')->url($path.'/'.$data);
            } catch (\Exception $e) {
            }
        }

        if ($data && Storage::disk('public')->exists($path.'/'.$data)) {
            return asset('storage/app/public').'/'.$path.'/'.$data;
        }

        if (request()->is('api/*')) {
            return null;
        }

        $place_holders = [
            'default' => asset('public/assets/admin/img/100x100/2.jpg'),
            'business' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'contact_us_image' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'profile' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'product' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'order' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'refund' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'delivery-man' => asset('public/assets/admin/img/160x160/img2.jpg'),
            'admin' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'conversation' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'banner' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'campaign' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'notification' => asset('public/assets/admin/img/900x400/img1.jpg'),
            'category' => asset('public/assets/admin/img/100x100/2.jpg'),
            'store' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'vendor' => asset('public/assets/admin/img/160x160/img1.jpg'),
            'brand' => asset('public/assets/admin/img/100x100/2.jpg'),
            'upload_image' => asset('public/assets/admin/img/upload-img.png'),
            'store/cover' => asset('public/assets/admin/img/100x100/2.jpg'),
            'upload_image_4' => asset('/public/assets/admin/img/upload-4.png'),
            'promotional_banner' => asset('public/assets/admin/img/100x100/2.jpg'),
            'admin_feature' => asset('public/assets/admin/img/100x100/2.jpg'),
            'aspect_1' => asset('/public/assets/admin/img/aspect-1.png'),
            'special_criteria' => asset('public/assets/admin/img/100x100/2.jpg'),
            'download_user_app_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'reviewer_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'fixed_header_image' => asset('/public/assets/admin/img/aspect-1.png'),
            'header_icon' => asset('/public/assets/admin/img/aspect-1.png'),
            'available_zone_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'why_choose' => asset('/public/assets/admin/img/aspect-1.png'),
            'header_banner' => asset('/public/assets/admin/img/aspect-1.png'),
            'reviewer_company_image' => asset('public/assets/admin/img/100x100/2.jpg'),
            'module' => asset('public/assets/admin/img/100x100/2.jpg'),
            'parcel_category' => asset('/public/assets/admin/img/400x400/img2.jpg'),
            'favicon' => asset('/public/assets/admin/img/favicon.png'),
            'seller' => asset('public/assets/back-end/img/160x160/img1.jpg'),
            'upload_placeholder' => asset('/public/assets/admin/img/upload-placeholder.png'),
            'payment_modules/gateway_image' => asset('/public/assets/admin/img/payment/placeholder.png'),
            'email_template' => asset('/public/assets/admin/img/blank1.png'),
        ];

        if (isset($placeholder) && array_key_exists($placeholder, $place_holders)) {
            return $place_holders[$placeholder];
        }

        return $place_holders[$path] ?? $place_holders["default"];
    }

    public static function getCusromerFirstOrderDiscount($order_count, $user_creation_date, $refby, $price = null)
    {

        $data = [
            'is_valid' => false,
            'discount_amount' => 0,
            'discount_amount_type' => '',
            'validity' => '',
            'calculated_amount' => 0,
        ];
        if ($order_count > 0 || ! $refby) {
            return $data ?? [];
        }
        $settings = self::get_business_settings_many(['new_customer_discount_status', 'new_customer_discount_amount', 'new_customer_discount_amount_type', 'new_customer_discount_amount_validity', 'new_customer_discount_validity_type']);

        $validity_value = data_get($settings, 'new_customer_discount_amount_validity');
        $validity_unit = data_get($settings, 'new_customer_discount_validity_type');

        if ($validity_unit == 'day') {
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value day");

        } elseif ($validity_unit == 'month') {
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value month");

        } elseif ($validity_unit == 'year') {
            $validity_end_date = (new DateTime($user_creation_date))->modify("+$validity_value year");
        } else {
            $validity_end_date = (new DateTime($user_creation_date))->modify('-1 day');
        }

        $is_valid = false;
        $current_date = new DateTime;
        if ($validity_end_date >= $current_date) {
            $is_valid = true;
        }

        if ($order_count == 0 && $is_valid && data_get($settings, 'new_customer_discount_status') == 1 && data_get($settings, 'new_customer_discount_amount') > 0) {
            $calculated_amount = 0;
            if (data_get($settings, 'new_customer_discount_amount_type') == 'percentage' && isset($price)) {
                $calculated_amount = ($price / 100) * data_get($settings, 'new_customer_discount_amount');
            } else {
                $calculated_amount = data_get($settings, 'new_customer_discount_amount');
            }

            $data = [
                'is_valid' => $is_valid,
                'discount_amount' => data_get($settings, 'new_customer_discount_amount'),
                'discount_amount_type' => data_get($settings, 'new_customer_discount_amount_type'),
                'validity' => data_get($settings, 'new_customer_discount_amount_validity').' '.translate(Str::plural((data_get($settings, 'new_customer_discount_validity_type') ?? 'day'), data_get($settings, 'new_customer_discount_amount_validity'))),
                'calculated_amount' => round($calculated_amount, config('round_up_to_digit')),
            ];
        }

        return $data ?? [];
    }

    public static function subscriptionPackageType($store)
    {
        return app(SubscriptionPackageService::class)->typeFor($store);
    }

    public static function subscriptionConditionsCheck($store_id, $package_id)
    {
        return app(StoreSubscriptionService::class)->conditionsCheck($store_id, $package_id);
    }

    public static function subscription_plan_chosen($store_id, $package_id, $payment_method, $discount = 0, $pending_bill = 0, $reference = null, $type = null)
    {
        return app(StoreSubscriptionService::class)->applyPlan($store_id, $package_id, $payment_method, $discount, $pending_bill, $reference, $type);
    }

    public static function subscriptionPayment($store_id, $package_id, $payment_gateway, $url, $pending_bill = 0, $type = 'payment', $payment_platform = 'web')
    {
        return app(StoreSubscriptionService::class)->generatePaymentLink($store_id, $package_id, $payment_gateway, $url, $pending_bill, $type, $payment_platform);
    }

    public static function subscription_check()
    {
        return self::business_model_flag('subscription_business_model');
    }

    private static function business_model_flag(string $key)
    {
        $value = self::get_business_settings($key);
        if ($value == null) {
            self::insert_business_settings_key($key, '1');
            $value = self::get_business_settings($key);
        }

        return $value ?? 1;
    }

    public static function commission_check()
    {
        return self::business_model_flag('commission_business_model');
    }

    public static function calculateSubscriptionRefundAmount($store, $return_data = null)
    {
        return app(StoreSubscriptionService::class)->calculateRefundAmount($store, $return_data);
    }

    public static function increment_order_count($store)
    {
        $store_sub = $store->store_sub;
        if ($store->store_business_model == 'subscription' && isset($store_sub) && $store_sub->max_order != 'unlimited') {
            $store_sub->increment('max_order', 1);
        }

        return true;
    }

    public static function notificationDataSetup()
    {
        return app(NotificationSettingService::class)->upsertAdminSetupData(self::getAdminNotificationSetupData());
    }

    public static function storeNotificationDataSetup($id)
    {
        return StoreNotificationSettings::install($id);
    }

    public static function storeRentalNotificationDataSetup($id)
    {
        return StoreNotificationSettings::install($id, 'rental');
    }

    public static function storeServiceNotificationDataSetup($id)
    {
        return StoreNotificationSettings::install($id, 'service');
    }

    public static function updateAdminNotificationSetupDataSetup()
    {
        self::updateAdminNotificationSetupData();

        return true;
    }

    public static function addNewAdminNotificationSetupDataSetup()
    {
        self::addNewAdminNotificationSetupData();

        return true;
    }

    public static function add_fund_push_notification($user_id)
    {
        return app(UserService::class)->notifyFundAdded($user_id);
    }

    public static function getActivePaymentGateways()
    {
        return app(SettingService::class)->getActiveGateways();
    }

    public static function checkCurrency($data, $type = null)
    {

        $digital_payment = self::get_business_settings('digital_payment');

        if ($digital_payment && $digital_payment['status'] == 1) {
            if ($type === null) {
                if (is_array(self::getActivePaymentGateways())) {
                    foreach (self::getActivePaymentGateways() as $payment_gateway) {

                        if (! empty(self::getPaymentGatewaySupportedCurrencies($payment_gateway['gateway'])) && ! array_key_exists($data, self::getPaymentGatewaySupportedCurrencies($payment_gateway['gateway']))) {
                            return $payment_gateway['gateway'];
                        }
                    }
                }
            } elseif ($type == 'payment_gateway') {
                $currency = self::get_business_settings('currency');
                if (! empty(self::getPaymentGatewaySupportedCurrencies($data)) && ! array_key_exists($currency, self::getPaymentGatewaySupportedCurrencies($data))) {
                    return $data;
                }
            }
        }

        return true;
    }

    public static function updateStorageTable($dataType, $dataId, $image)
    {
        FileStorage::updateStorageTable($dataType, $dataId, $image);
    }

    public static function getNextOpeningTime($schedule)
    {
        $currentTime = now()->format('H:i');
        if ($schedule) {
            foreach ($schedule as $entry) {
                if ($entry['day'] == now()->format('w')) {
                    if ($currentTime >= $entry['opening_time'] && $currentTime <= $entry['closing_time']) {
                        return $entry['opening_time'];
                    } elseif ($currentTime < $entry['opening_time']) {
                        return $entry['opening_time'];
                    }
                }
            }
        }

        return 'closed';
    }

    public static function businessUpdateOrInsert($key, $value)
    {
        app(BusinessSettingService::class)->saveValue($key['key'], $value['value']);
    }

    public static function businessInsert($data)
    {
        app(BusinessSettingService::class)->saveValue($data['key'], $data['value']);
    }

    public static function dataUpdateOrInsert($key, $value)
    {
        app(DataSettingService::class)->saveValue($key['key'], $key['type'], $value['value']);
    }

    public static function getSettingsDataFromConfig($settings, $relations = [])
    {
        return app(BusinessSettingService::class)->findModelByKey($settings, $relations);
    }

    public static function sendTripPaymentNotificationCustomerMain($trip)
    {
        if (is_dir('Modules/Rental') && file_exists('Modules/Rental/Traits/RentalPushNotification.php')) {
            $traitUser = 'Modules\\Rental\\Traits\\RentalPushNotification';
            if (trait_exists($traitUser)) {
                return \Modules\Rental\Support\RentalNotifier::sendTripPaymentNotificationCustomer($trip);
            }
        }

        return null;
    }

    public static function createTransactionForTrip($trip, $received_by = false, $status = null)
    {
        if (is_dir('Modules/Rental') && file_exists('Modules/Rental/Services/TripTransactionService.php')) {
            try {
                $serviceClass = 'Modules\Rental\Services\TripTransactionService';
                if (class_exists($serviceClass)) {
                    return (new $serviceClass)->createTransaction($trip, $received_by, $status);
                }
            } catch (\Exception $e) {
                info(['error_creating_trip_transaction', $e->getMessage()]);
            }
        }

        return null;
    }

    public static function deleteCacheData($prefix)
    {
        ApiCache::bustPrefix((string) $prefix);
    }

    public static function minDiscountCheck($productPrice, $discount)
    {
        $discountApplied = min($productPrice, $discount);
        $finalPrice = max(0, $productPrice - $discountApplied);

        return ['final_price' => $finalPrice, 'discount_applied' => $discountApplied];
    }

    public static function checkAdminDiscount($price, $discount, $max_discount, $min_purchase, $item_wise_price = null)
    {
        // Nothing to discount, so nothing is discounted. Without this the method falls straight
        // through to the return, where $discount still holds the *percentage* it arrived as --
        // so a caller asking what 20% of nothing comes to was told 20, and booked it as twenty
        // currency off. Reachable as soon as an order has no discountable amount in it at all,
        // which is any order that is nothing but a BOGO bundle.
        if ($price <= 0 || $discount <= 0) {
            return 0;
        }

        $discount = ($price * $discount) / 100;
        // null means no cap -- what a happy hour carries, since it has no ceiling. A 0 still
        // clamps to 0, so vendor discounts configured that way keep behaving exactly as before;
        // only an explicitly uncapped rate skips the clamp.
        $discount = ($max_discount !== null && $discount > $max_discount) ? $max_discount : $discount;
        $discount = $price >= $min_purchase ? $discount : 0;

        // null means the caller wants the order-wide figure, so leave it whole. An explicit 0 is
        // a real line that costs nothing -- a BOGO free item -- and its share of the discount is
        // 0, not the entire order's. Testing for > 0 handed such a line the whole amount.
        if ($discount > 0 && $item_wise_price !== null) {
            $discount = ($item_wise_price / $price) * $discount;
        }

        return $discount ?? 0;
    }

    public static function posCartSubtotal(): float
    {
        $subtotal = 0.0;
        foreach ((array) session()->get('cart', []) as $cartItem) {
            if (! is_array($cartItem)) {
                continue;
            }
            $unit = (float) ($cartItem['price'] ?? 0);
            $quantity = (int) ($cartItem['quantity'] ?? 0);
            $addon = (float) ($cartItem['addon_price'] ?? 0);
            $discount = (float) ($cartItem['discount'] ?? 0);
            $subtotal += ($unit * $quantity) + $addon - ($discount * $quantity);
        }

        return (float) max($subtotal, 0);
    }

    public static function getFinalCalculatedTax($details_data, $additionalCharges, $totalDiscount, $price, $storeId, $storeData = true)
    {
        $addonIds = [];
        $products = [];
        $tempList = [];
        $taxData = [];

        $productDiscountTotal = 0;
        $addonDiscountTotal = 0;
        $totalAfterOwnDiscounts = 0;
        if (addon_published_status('TaxModule')) {

            foreach ($details_data as $item) {
                $item_id = $item['item_id'] ?? $item['item_campaign_id'];
                // Always times the quantity, whatever set the rate. discount_on_item is a UNIT
                // discount for every discount_type now; it used to be written per line by the
                // store-wide branch alone, and this conditional was what kept the two straight.
                // Leaving it in place after that was made uniform taxed a store-wide discount on
                // any line of two or more as though only one unit had been discounted.
                $itemWiseDiscount = $item['discount_on_item'] * $item['quantity'];
                $productDiscountTotal += $itemWiseDiscount;

                $itemTotal = $item['price'] * $item['quantity'];
                $itemFinal = $itemTotal - $itemWiseDiscount;

                $tempList[] = [
                    'type' => 'product',
                    'id' => $item_id,
                    'original_price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'category_id' => $item['category_id'],
                    'discount' => $item['discount_on_item'],
                    'discount_type' => $item['discount_type'],
                    'base_final' => $itemFinal,
                    'is_campaign_item' => $item['item_campaign_id'] ? true : false,
                ];

                $totalAfterOwnDiscounts += $itemFinal;

                $addons = json_decode($item['add_ons'], true) ?? [];
                $addonDiscount = $item['addon_discount'] ?? 0;
                $addonTotalPrice = ($item['total_add_on_price'] ?? 0) > 0 ? $item['total_add_on_price'] : 1;

                $addonDiscountTotal += $addonDiscount;

                foreach ($addons as $addon) {
                    $addonPrice = $addon['price'] * $addon['quantity'];
                    $discountPart = $addonDiscount * ($addonPrice / $addonTotalPrice);
                    $addonFinal = $addonPrice - $discountPart;

                    $tempList[] = [
                        'type' => 'addon',
                        'addon_id' => $addon['id'],
                        'item_id' => $item_id,
                        'quantity' => $addon['quantity'],
                        'category_id' => $addon['category_id'] ?? null,
                        'original_price' => $addon['price'],
                        'base_final' => $addonFinal,
                        'total_addon_addon_price' => $addonTotalPrice,
                        'total_addon_discount' => $addonDiscount,
                    ];

                    $totalAfterOwnDiscounts += $addonFinal;
                }
            }

            $otherDiscounts = $totalDiscount - ($productDiscountTotal + $addonDiscountTotal);

            foreach ($tempList as $entry) {
                $share = $totalAfterOwnDiscounts > 0 ? ($entry['base_final'] / $totalAfterOwnDiscounts) * $otherDiscounts : 0;
                $finalPrice = $entry['base_final'] - $share;

                if ($entry['type'] === 'product') {
                    $products[] = [
                        'id' => $entry['id'],
                        'original_price' => $entry['original_price'],
                        'quantity' => $entry['quantity'],
                        'category_id' => $entry['category_id'],
                        'discount' => $entry['discount'],
                        'discount_type' => $entry['discount_type'],
                        'after_discount_final_price' => $finalPrice,
                        'is_campaign_item' => $entry['is_campaign_item'],
                    ];
                } else {
                    $addonIds[] = [
                        'addon_id' => $entry['addon_id'],
                        'item_id' => $entry['item_id'],
                        'quantity' => $entry['quantity'],
                        'category_id' => $entry['category_id'],
                        'original_price' => $entry['original_price'],
                        'after_discount_final_price' => $finalPrice,
                        'total_addon_addon_price' => $entry['total_addon_addon_price'],
                        'total_addon_discount' => $entry['total_addon_discount'],
                    ];
                }
            }

            $taxData = CalculateTaxService::getCalculatedTax(
                amount: $price,
                productIds: $products,
                taxPayer: 'vendor',
                storeData: $storeData,
                additionalCharges: $additionalCharges,
                addonIds: $addonIds,
                orderId: null,
                storeId: $storeId
            );
            $tax_amount = $taxData['totalTaxamount'];
            $tax_included = $taxData['include'];
            $tax_status = $tax_included ? 'included' : 'excluded';

            foreach ($taxData['productWiseData'] ?? [] as $key => $item) {
                $taxMap[$key] = $item;
            }
        }

        return [
            'tax_amount' => $tax_amount ?? 0,
            'tax_included' => $tax_included ?? null,
            'tax_status' => $tax_status ?? 'excluded',
            'taxMap' => $taxMap ?? [],
            'taxType' => data_get($taxData, 'taxType'),
            'taxData' => $taxData ?? [],
        ];
    }

    public static function getTaxSystemType($getTaxVatList = true, $tax_payer = 'vendor')
    {
        return app(SystemTaxSetupService::class)->getTaxSystemType($getTaxVatList, $tax_payer);
    }

    public static function sendOrderDeliveryVerificationOtp($order)
    {
        if (SendNotification::channelEnabled('customer', 'customer_delivery_verification_otp', 'sms_status')) {
            $address = json_decode($order->delivery_address, true);
            $phone = $order->is_guest ? data_get($address, 'contact_person_number') : $order?->customer?->phone;

            $response = Sms::deliver($phone, $order->otp);
        }

        return $response ?? null;
    }

    private static function brandAssetSetting(string $key)
    {
        return app(BusinessSettingService::class)->findBrandAsset($key);
    }

    public static function logoFullUrl()
    {
        return self::brandAssetUrl('logo');
    }

    private static function brandAssetUrl(string $key)
    {
        $asset = self::brandAssetSetting($key);

        return self::get_full_url('business', $asset?->value ?? '', $asset?->storage[0]?->value ?? 'public', 'favicon');
    }

    public static function iconFullUrl()
    {
        return self::brandAssetUrl('icon');
    }

    public static function highlightWords($text, $colorClass = 'text-base-clr')
    {
        $escapedText = e($text);

        return preg_replace(
            '/\$(.*?)\$/',
            '<span class="'.htmlspecialchars($colorClass, ENT_QUOTES, 'UTF-8').'">$1</span>',
            $escapedText
        );
    }

    public static function promotionalImage()
    {
        return app(ReactPromotionalBannerService::class)->migrateFromLegacyDataSetting();
    }

    public static function getCoordinatesZone($lat, $lng)
    {
        return app(ZoneService::class)->findActiveContaining($lat, $lng);
    }

    public static function deliverymanLoyaltyPointHistory($deliveryManId, $amount, $transactionType, $pointConversionType = 'credit', $reference = null)
    {
        return app(DeliverymanLoyaltyPointHistoryService::class)->recordDeliverymanLoyaltyPoint(
            $deliveryManId, $amount, $transactionType, $pointConversionType, $reference
        );
    }

    public static function generate_transaction_id($model, $column = 'transaction_id')
    {
        $id_val = $model->id ?? rand(1000, 9999);
        $randomLength = 10 - strlen($id_val);
        $random = Str::upper(Str::random($randomLength));
        $id = $id_val.$random;

        if ($model->where($column, $id)->exists()) {
            return self::generate_transaction_id($model, $column);
        }

        return $id;
    }

    public static function deliverymanReferralNotification($referal_user)
    {
        app(DeliveryManService::class)->notifyReferralUsed($referal_user);
    }

    public static function validateFile($image, ?int $maxSizeMb = null, ?string $allowedExtensionsString = null)
    {
        return FileStorage::validateFile($image, $maxSizeMb, $allowedExtensionsString);
    }

    public static function extensionFromMimeType(string $mimeType)
    {
        return FileStorage::extensionFromMimeType($mimeType);
    }

    public static function reel_matches_product(?int $reelId, ?string $productType, ?int $productId): bool
    {
        if (! $reelId || ! $productType || ! $productId || ! Schema::hasTable('reels') || ! Schema::hasColumn('reels', 'productable_id')) {
            return false;
        }

        return DB::table('reels')
            ->where('id', $reelId)
            ->where('productable_type', $productType)
            ->where('productable_id', $productId)
            ->exists();
    }

    public static function resolve_reel_id_for_product(?int $reelId, ?string $productType, ?int $productId): ?int
    {
        return self::reel_matches_product($reelId, $productType, $productId) ? $reelId : null;
    }

    public static function resolve_reel_id(?int $reelId, ?int $itemId): ?int
    {
        return self::resolve_reel_id_for_product($reelId, Item::class, $itemId);
    }

    public static function resolve_reel_vehicle_id(?int $reelId, ?int $vehicleId): ?int
    {
        return self::resolve_reel_id_for_product($reelId, 'Modules\\Rental\\Entities\\Vehicle', $vehicleId);
    }

    public static function resolve_reel_id_for_service(?int $reelId, ?int $serviceId): ?int
    {
        return self::resolve_reel_id_for_product($reelId, 'Modules\\Service\\Entities\\Service', $serviceId);
    }

    public static function seoPageList()
    {
        return [
            'home_page', 'top_offers_page', 'brands_page', 'search_page', 'vehicle_search_page', 'about_us_page', 'contact_us_page', 'store_join_page', 'deliveryman_join_page', 'terms_and_conditions_page', 'privacy_policy_page', 'refund_policy_page', 'cancellation_policy_page', 'shipping_policy_page', 'latest_store_page', 'flash_sales', 'popular_store_page', 'coupons_page', 'best_sellers_page', 'top_rated_page', 'organic_page', 'recently_viewed_page', 'recently_ordered_page', 'wishlist_page', 'basic_medicine_page', 'common_conditions_page', 'store_near_you_page', 'restaurant_near_you_page', 'recommended_store_page',
        ];
    }

    public static function formatMetaData(array $input, $oldMeta = [])
    {
        $meta = $oldMeta ?? [];
        $meta['meta_index'] = ($input['meta_index'] ?? 1);
        $meta['meta_no_follow'] = $input['meta_no_follow'] ?? null;
        $meta['meta_no_image_index'] = $input['meta_no_image_index'] ?? null;
        $meta['meta_no_archive'] = $input['meta_no_archive'] ?? null;
        $meta['meta_no_snippet'] = $input['meta_no_snippet'] ?? null;
        $meta['meta_max_snippet'] = (int) ($input['meta_max_snippet'] ?? 0);
        $meta['meta_max_snippet_value'] = isset($input['meta_max_snippet_value']) ? (int) $input['meta_max_snippet_value'] : null;
        $meta['meta_max_video_preview'] = (int) ($input['meta_max_video_preview'] ?? 0);
        $meta['meta_max_video_preview_value'] = isset($input['meta_max_video_preview_value']) ? (int) $input['meta_max_video_preview_value'] : null;
        $meta['meta_max_image_preview'] = (int) ($input['meta_max_image_preview'] ?? 0);
        $meta['meta_max_image_preview_value'] = $input['meta_max_image_preview_value'] ?? null;

        return $meta;
    }

    public static function getDecimalPlaces()
    {
        $decimalPlaces = (int) config('round_up_to_digit') ?? 2;

        return number_format(pow(10, -$decimalPlaces), $decimalPlaces, '.', '');

    }

    public static function getLanguages()
    {
        return LANGUAGES;
    }

    public static function getCountries()
    {
        return COUNTIRES;
    }

    public static function addPreviousParcelReturnFees()
    {
        app(ParcelReturnFeeService::class)->backfillFromPaidCancellations();
    }

    public static function maxUploadSizeMb(int $configuredLimit = MAX_FILE_SIZE): int
    {
        try {
            $serverLimit = self::sizeToMb(ini_get('post_max_size'));

            return min($configuredLimit, $serverLimit);
        } catch (\Throwable $e) {
            return $configuredLimit;
        }
    }

    public static function productVideoMaxUploadSizeMb(): int
    {
        return self::maxUploadSizeMb(PRODUCT_VIDEO_MAX_FILE_SIZE);
    }

    public static function host_base_domain(string $host): string
    {
        $host = strtolower(trim($host));

        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        $labels = explode('.', $host);
        if (count($labels) <= 2) {
            return $host;
        }

        $secondLevelLabels = ['co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'go', 'or', 'ne', 'me', 'gen'];
        $take = in_array($labels[count($labels) - 2], $secondLevelLabels, true) ? 3 : 2;

        return implode('.', array_slice($labels, -$take));
    }

    public static function getStorageDiskByKey($model, string $key, string $default = 'public')
    {
        return FileStorage::getStorageDiskByKey($model, $key, $default);
    }

    public static function copyStorageFile(string $dir, ?string $fileName, string $sourceDisk = 'public')
    {
        return FileStorage::copyStorageFile($dir, $fileName, $sourceDisk);
    }

    public static function duplicateProductVideoData($product): array
    {
        if (! $product) {
            return [
                'video' => null,
                'video_link' => null,
            ];
        }

        if ($product?->video) {
            return [
                'video' => self::copyStorageFile('product/', $product->video, self::getStorageDiskByKey($product, 'video', 'public')),
                'video_link' => null,
            ];
        }

        return [
            'video' => null,
            'video_link' => $product?->video_link ?: null,
        ];
    }

    public static function sizeToMb($value): int
    {
        $value = trim((string) $value);
        $unit = strtolower(substr($value, -1));
        $num = (int) $value;

        switch ($unit) {
            case 'g':
                return $num * 1024;
            case 'm':
                return $num;
            case 'k':
                return (int) ceil($num / 1024);
            default:
                return $num;
        }
    }

    public static function getStoreLabelByModuleType(?string $moduleType = null, bool $lowercase = false): string
    {
        $resolvedModuleType = $moduleType
            ?? config('module.current_module_type')
            ?? self::get_store_data()?->module_type
            ?? self::get_store_data()?->module?->module_type;

        $label = match ($resolvedModuleType) {
            'food' => translate('messages.Restaurant'),
            'rental' => translate('messages.Provider'),
            'service' => translate('messages.Provider'),
            default => translate('messages.Store'),
        };

        return $lowercase ? strtolower($label) : ucfirst($label);
    }

    public static function visitor_log($model, $user_id, $visitor_log_id, $order_count = false)
    {
        app(VisitorLogService::class)->record($model, $user_id, $visitor_log_id, (bool) $order_count);
    }

    public static function check_website_builder_status()
    {
        if (! addon_published_status('Builder')) {
            return false;
        }

        $store = self::get_store_data();
        if (($store?->module_type ?? null) === 'rental') {
            return false;
        }

        $admin_website_builder_status = self::get_business_settings('admin_website_builder_status');
        $store?->loadMissing('storeConfig');
        $vendor_website_builder_status = $store?->storeConfig?->website_builder_status;

        return $vendor_website_builder_status == 1 && $admin_website_builder_status == 1;
    }

    public static function is_vendor_panel_maintenance_active(): bool
    {
        if (! ApiCache::has('maintenance')) {
            return false;
        }

        $maintenance = ApiCache::get('maintenance');

        if (empty($maintenance['vendor_panel'])) {
            return false;
        }

        if (($maintenance['maintenance_duration'] ?? null) === 'until_change') {
            return true;
        }

        if (! empty($maintenance['start_date']) && ! empty($maintenance['end_date'])) {
            return Carbon::now()->between(Carbon::parse($maintenance['start_date']), Carbon::parse($maintenance['end_date']));
        }

        return true;
    }

    private static function sanitized_json_text($data, bool $stripSemicolon = false, int $flags = 0): string
    {
        $chars = ['\'', '"', '{', '}', '[', ']', '<', '>', '?'];
        if ($stripSemicolon) {
            $chars[] = ';';
        }

        return str_ireplace($chars, ' ', json_encode($data, $flags));
    }
}
