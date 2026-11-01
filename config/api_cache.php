<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | The single place that decides where every application cache entry lives.
    | Any store defined in config/cache.php is valid: file, database, redis,
    | memcached, dynamodb, array. Leave null to follow cache.default.
    |
    | Switching this value is safe at any time: invalidation is driven by tag
    | stamps (see below) rather than by scanning a driver-specific key store,
    | so it behaves identically on every driver.
    |
    */

    'store' => env('APP_CACHE_STORE', env('CACHE_DRIVER', 'file')),

    /*
    |--------------------------------------------------------------------------
    | Deep-page Cut-off
    |--------------------------------------------------------------------------
    |
    | Paginated list endpoints only cache the first N pages. Beyond that the
    | request falls through to the database, which keeps the key count bounded
    | instead of growing with every filter/offset combination a client sends.
    |
    */

    'max_cached_page' => env('APP_CACHE_MAX_PAGE', 3),

    /*
    |--------------------------------------------------------------------------
    | Cache Groups
    |--------------------------------------------------------------------------
    |
    | One entry per logical cache. 'ttl' is in seconds; null means forever.
    | 'tags' lists the invalidation tags whose stamps are folded into every key
    | in the group, so busting any one of them retires the whole group at once.
    |
    */

    'groups' => [

        'banners' => ['ttl' => 1200, 'tags' => ['banner', 'module', 'zone', 'translation']],
        'banners_formatted' => ['ttl' => 1200, 'tags' => ['banner', 'store', 'item', 'store_category', 'brand', 'category', 'flash_sale', 'common_condition', 'module', 'zone', 'translation']],
        'banners_store' => ['ttl' => 1200, 'tags' => ['banner', 'module', 'zone', 'translation']],
        'banners_service' => ['ttl' => 1200, 'tags' => ['banner', 'module', 'translation']],
        'banners_service_store' => ['ttl' => 1200, 'tags' => ['banner', 'module', 'translation']],
        'campaigns' => ['ttl' => 1200, 'tags' => ['campaign', 'store', 'module', 'zone', 'translation']],
        'advertisement' => ['ttl' => 1200, 'tags' => ['advertisement', 'store', 'module', 'zone', 'translation']],
        'advertised_store_ids' => ['ttl' => 600, 'tags' => ['advertisement']],
        'advertisement_service' => ['ttl' => 1200, 'tags' => ['advertisement', 'store', 'module', 'translation']],
        'smart_banners' => ['ttl' => 120, 'tags' => ['smart_banner', 'zone', 'translation']],
        'store_cat_items' => ['ttl' => 1200, 'tags' => ['store_category_items', 'item', 'category', 'store_category', 'store', 'module', 'translation']],

        'business_settings' => ['ttl' => null, 'tags' => ['business_setting']],
        'data_settings' => ['ttl' => null, 'tags' => ['data_setting']],
        'landing' => ['ttl' => null, 'tags' => ['landing', 'data_setting', 'translation']],
        'modules' => ['ttl' => null, 'tags' => ['module', 'zone', 'translation']],
        'zones' => ['ttl' => null, 'tags' => ['zone', 'translation']],
        'reference_list' => ['ttl' => null, 'tags' => ['reference', 'translation']],
        'priority_settings' => ['ttl' => null, 'tags' => ['priority_setting']],

        'verified_seller' => ['ttl' => 3600, 'tags' => ['verified_seller', 'store']],
        // HappyHourCatalog::runningHappyHour()'s fallback (non-eager-loaded) enrolment fetch --
        // rows only, never the "running now" verdict, which is still decided fresh in PHP on
        // every call. Busted immediately by HappyHour/HappyHourStore writes (both tag 'store').
        'store_happy_hour_enrollments' => ['ttl' => 300, 'tags' => ['store']],
        'vehicle_price' => ['ttl' => null, 'tags' => ['vehicle']],
        'analytic_script' => ['ttl' => null, 'tags' => ['analytic_script']],

        'admin_sidebar_counts' => ['ttl' => 300, 'tags' => ['sidebar_counts']],
        'notification_matrix' => ['ttl' => null, 'tags' => ['notification_setting']],
        'addon_activation' => ['ttl' => 86400, 'tags' => ['addon_activation']],
        'maintenance' => ['ttl' => 31536000, 'tags' => ['maintenance']],
        'fcm_token' => ['ttl' => 3000, 'tags' => ['fcm']],
        'fcm_form_html' => ['ttl' => 3600, 'tags' => ['notification_setting', 'translation']],
        'route_index' => ['ttl' => 86400, 'tags' => ['route_index']],
        'trending_search' => ['ttl' => 900, 'tags' => ['trending_search']],
        'builder_checkout' => ['ttl' => 21600, 'tags' => ['builder_checkout']],
        'ai_platform_stats' => ['ttl' => 300, 'tags' => ['ai_platform_stats']],
        'map_lookup' => ['ttl' => 604800, 'tags' => ['map_lookup']],
        'demo_throttle' => ['ttl' => null, 'tags' => ['demo_throttle']],

        'api.config' => ['ttl' => 600, 'tags' => ['business_setting', 'data_setting', 'module', 'zone', 'translation']],
        'api.module' => ['ttl' => 600, 'tags' => ['module', 'zone', 'translation']],
        'api.brand' => ['ttl' => 900, 'tags' => ['brand', 'module', 'translation', 'priority_setting', 'business_setting']],
        // 'item'/'store' added because CategoryService::buildCustomerQuery() and
        // ServiceCategoryService::categoryList() now drop a category/sub-category that has no
        // live item or service under it -- so the response depends on item/store status too, not
        // just the category rows themselves.
        'api.categories' => ['ttl' => 900, 'tags' => ['category', 'item', 'store', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.flash_sales' => ['ttl' => 300, 'tags' => ['flash_sale', 'item', 'store', 'module', 'zone', 'translation']],
        'api.campaigns_basic' => ['ttl' => 900, 'tags' => ['campaign', 'store', 'item', 'module', 'zone', 'translation']],
        'api.common_condition' => ['ttl' => 900, 'tags' => ['common_condition', 'module', 'translation', 'priority_setting', 'business_setting']],
        'api.common_condition_items' => ['ttl' => 600, 'tags' => ['common_condition', 'item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],

        'api.items_basic' => ['ttl' => 600, 'tags' => ['item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.items_popular' => ['ttl' => 600, 'tags' => ['item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.items_most_reviewed' => ['ttl' => 600, 'tags' => ['item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.items_discounted' => ['ttl' => 600, 'tags' => ['item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.items_recommended' => ['ttl' => 600, 'tags' => ['item', 'store', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],
        'api.categories_featured_items' => ['ttl' => 600, 'tags' => ['category', 'item', 'store', 'module', 'zone', 'translation', 'priority_setting', 'business_setting']],

        'api.stores_list' => ['ttl' => 600, 'tags' => ['store', 'item', 'category', 'module', 'zone', 'translation', 'priority_setting', 'business_setting', 'bogo', 'bundle', 'coupon']],
        'api.stores_popular' => ['ttl' => 600, 'tags' => ['store', 'item', 'module', 'zone', 'translation', 'priority_setting', 'business_setting', 'bogo', 'bundle', 'coupon']],
        'api.stores_latest' => ['ttl' => 600, 'tags' => ['store', 'item', 'module', 'zone', 'translation', 'priority_setting', 'business_setting', 'bogo', 'bundle', 'coupon']],
        'api.stores_recommended' => ['ttl' => 600, 'tags' => ['store', 'item', 'module', 'zone', 'translation', 'priority_setting', 'business_setting', 'bogo', 'bundle', 'coupon']],
        'api.stores_top_offer' => ['ttl' => 300, 'tags' => ['store', 'item', 'module', 'zone', 'translation', 'priority_setting', 'business_setting', 'bogo', 'bundle', 'coupon']],
        'api.visit_again' => ['ttl' => 300, 'tags' => ['store', 'module', 'zone', 'translation', 'bogo', 'bundle', 'coupon']],

        'api.other_banners_video' => ['ttl' => 900, 'tags' => ['banner', 'module', 'translation']],
        'api.other_banners_why_choose' => ['ttl' => 900, 'tags' => ['banner', 'module', 'translation']],

        'api.reels' => ['ttl' => 300, 'tags' => ['reel', 'store', 'item', 'module', 'zone']],

        'api.rental_top_rated' => ['ttl' => 600, 'tags' => ['vehicle', 'store', 'module', 'zone', 'translation']],
        'api.rental_banners' => ['ttl' => 900, 'tags' => ['banner', 'module', 'zone', 'translation']],
        'api.rental_coupons' => ['ttl' => 600, 'tags' => ['coupon', 'module', 'zone', 'translation']],

        'api.service_popular' => ['ttl' => 600, 'tags' => ['store', 'item', 'category', 'module', 'zone', 'translation', 'bogo', 'bundle', 'coupon']],
        'api.service_recommended' => ['ttl' => 600, 'tags' => ['store', 'item', 'category', 'module', 'zone', 'translation', 'bogo', 'bundle', 'coupon']],
        'api.service_emergency_experts' => ['ttl' => 600, 'tags' => ['store', 'category', 'module', 'zone', 'translation', 'bogo', 'bundle', 'coupon']],
        'api.service_campaigns' => ['ttl' => 900, 'tags' => ['campaign', 'store', 'module', 'zone', 'translation']],
        'api.service_campaign_list' => ['ttl' => 900, 'tags' => ['campaign', 'store', 'module', 'zone', 'translation']],

    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Prefix Map
    |--------------------------------------------------------------------------
    |
    | Helpers::deleteCacheData() is still called from model boot() hooks and
    | admin controllers with a raw key prefix. Each prefix resolves to the tags
    | it should bust. Longest prefix wins; an unmapped prefix falls back to a
    | tag named after the prefix itself, so a new call site fails closed rather
    | than silently invalidating nothing.
    |
    */

    'legacy_prefix_tags' => [
        'advertisement_' => ['advertisement'],
        'analytic_script' => ['analytic_script'],
        'banner_' => ['banner'],
        'banners_' => ['banner'],
        'business_settings_' => ['business_setting'],
        'business_settings_all_data' => ['business_setting'],
        'campaigns_' => ['campaign'],
        'data_settings_' => ['data_setting'],
        'landing_' => ['landing'],
        'landing_meta_data_' => ['landing'],
        'landing_policy_statuses' => ['landing'],
        'module_' => ['module'],
        'modules_list_' => ['module'],
        'priority_settings_all_data' => ['priority_setting'],
        'ref_list_' => ['reference'],
        'smart_banners_' => ['smart_banner'],
        'store_cat_items_' => ['store_category_items'],
        'vehicle_' => ['vehicle'],
        'verified_seller_eligible_providers_' => ['verified_seller'],
        'verified_seller_eligible_stores_' => ['verified_seller'],
        'zones_dropdown_' => ['zone'],
    ],

];
