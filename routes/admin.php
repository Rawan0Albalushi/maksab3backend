<?php

use App\Http\Controllers\Admin\{
    AccountTransactionController, AddonActivationController,
    AdminEarningReportController, AdminTaxReportController,
    AutomatedMessageController, Banner\BannerController,
    BusinessSettingsController, CampaignController,
    ContactController, ConversationController,
    Coupon\CouponController, CustomerController,
    CustomerWalletController, Customer\WalletBonusController,
    DashboardController, DatabaseBackupController, DatabaseSettingController,
    DeliveryManDisbursementController, DeliverymanEarningReportController,
    DeliveryMan\DeliveryManController,
    Employee\CustomRoleController, Employee\EmployeeController,
    Erp\ErpIntegrationController,
    FileManagerController, FlashSaleController,
    ItemController, Item\AddonCategoryController,
    Item\AddonController, Item\AttributeController,
    Item\BrandController, Item\CategoryController,
    Item\CommonConditionController, Item\StoreCategoryController,
    Item\UnitController, LanguageController,
    LoyaltyPointController, Marketing\AnalyticScriptController,
    Module\ModuleController, Notification\NotificationController,
    OfflinePaymentMethodController, OrderCancelReasonController,
    OrderController, OtherBannerController,
    ParcelCategoryController, ParcelController,
    POSController, ProCustomerController,
    Promotion\AdvertisementController, Promotion\BogoOfferController,
    Promotion\CashBackController, Promotion\HappyHourController,
    ProvideDMEarningController, ReportController,
    SearchRoutingController,
    SmartBannerController, SMSModuleController,
    SocialMediaController, StoreDisbursementController,
    StoreEarningReportController, Subscription\SubscriptionController,
    SystemController,
    System\AddonController as SystemAddonController, VendorController,
    VendorTaxReportController, WithdrawalMethodController,
    Parcel\DimensionController, Parcel\WeightController,
    Zone\FreeDeliveryController,
    Zone\EtaConfigurationController,
    Zone\AdditionalDeliveryChargeController,
    Zone\SurgePriceController,
    Zone\AreaController,
    Zone\DeliveryRuleController,
    Zone\VehicleCategoryController,
    Zone\ZipCodeController,
    Zone\ZoneController, };
use App\Http\Controllers\Admin\Promotion\BundleController;
use Illuminate\Support\Facades\Route;

Route::name('admin.')->controller(ZoneController::class)->group(function () {
    Route::get('zone/get-coordinates/{id}', 'getCoordinates')->name('zone.get-coordinates');
    Route::get('zone/get-zone', 'get_zone')->name('zone.get-zone');
    Route::get('zone/check-location', 'checkLocation')->name('zone.check-location');
    Route::get('get-all-zone-coordinates/{id?}', 'getAllZoneCoordinates')->name('zone.zoneCoordinates');

    Route::middleware(['admin', 'current-module', 'actch:admin_panel'])->controller(VendorController::class)->group(function () {
        Route::controller(VendorController::class)->group(function () {
            Route::get('get-all-stores', 'get_all_stores')->name('get_all_stores');
        });
        Route::controller(LanguageController::class)->group(function () {
            Route::get('lang/{locale}', [LanguageController::class, 'lang'])->name('lang');
        });
        Route::middleware(['module:profile'])->controller(SystemController::class)->group(function () {
            Route::get('settings', 'settings')->name('settings');
            Route::post('settings', 'settings_update');
            Route::post('settings-password', 'settings_password_update')->name('settings-password');
        });
        Route::controller(SystemController::class)->group(function () {
            Route::get('get-store-data', 'store_data')->name('get-store-data');
        });
        Route::controller(BusinessSettingsController::class)->group(function () {
            Route::post('remove_image', 'remove_image')->name('remove_image');
        });
        Route::controller(SystemController::class)->group(function () {
            Route::get('system-currency', 'system_currency')->name('system_currency');
        });
        Route::controller(DashboardController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
        });

        Route::controller(SystemController::class)->group(function () {
            Route::post('maintenance-mode', 'maintenance_mode')->name('maintenance-mode');
            Route::get('landing-page', 'landing_page')->name('landing-page')->middleware('module:landing_pages');
        });

        Route::middleware(['module:parcel'])->prefix('parcel')->name('parcel.')->controller(ParcelController::class)->group(function () {
            Route::controller(ParcelCategoryController::class)->group(function () {
                Route::get('category/status/{id}/{status}', 'status')->name('category.status');
            });
            Route::resource('category', ParcelCategoryController::class);
            Route::get('orders/{status}', 'orders')->name('orders');
            Route::get('orders/export/{status}/{file_type}', 'parcel_orders_export')->name('parcel_orders_export');
            Route::get('details/{id}', 'order_details')->name('order.details');
            Route::get('settings', 'settings')->name('settings');
            Route::post('settings', 'update_settings')->name('update.settings');
            Route::get('dispatch/{status}', 'dispatch_list')->name('list');
            Route::post('instruction', 'instruction')->name('instruction');
            Route::get('instruction/{id}/{status}', 'instruction_status')->name('instruction_status');
            Route::put('instruction_edit', 'instruction_edit')->name('instruction_edit');
            Route::delete('instruction_delete/{id}', 'instruction_delete')->name('instruction_delete');

            Route::get('cancellation-settings', 'cancellationSettings')->name('cancellationSettings');
            Route::get('cancellation-settings-status', 'cancellationSettingsStatus')->name('cancellationSettingsStatus');
            Route::put('cancellation-settings-update', 'cancellationSettingsUpdate')->name('cancellationSettingsUpdate');
            Route::post('cancellation-reason', 'cancellationReason')->name('cancellationReason');
            Route::get('cancellation-reason-status/{reason}', 'cancellationReasonStatus')->name('cancellationReasonStatus');
            Route::get('cancellation-reason-edit/{reason}', 'cancellationReasonEdit')->name('cancellationReasonEdit');
            Route::put('cancellation-reason-update/{reason}', 'cancellationReasonUpdate')->name('cancellationReasonUpdate');
            Route::delete('cancellation-reason-delete/{reason}', 'cancellationReasonDelete')->name('cancellationReasonDelete');
            Route::get('cancellation-reason-export', 'cancellationReasonExport')->name('cancellationReasonExport');
        });

        Route::prefix('dashboard-stats')->name('dashboard-stats.')->controller(DashboardController::class)->group(function () {
            Route::post('order', 'order')->name('order');
            Route::post('zone', 'zone')->name('zone');
            Route::post('user-overview', 'user_overview')->name('user-overview');
            Route::post('commission-overview', 'commission_overview')->name('commission-overview');
        });

        Route::controller(ItemController::class)->group(function () {
            Route::post('item/variant-price', 'variant_price')->name('item.variant-price');
        });

        Route::middleware(['module:item'])->prefix('item')->name('item.')->controller(ItemController::class)->group(function () {
            Route::get('add-new', 'index')->name('add-new');
            Route::get('item-view/{id}', 'gallery_item_view')->name('item-view');
            Route::post('variant-combination', 'variant_combination')->name('variant-combination');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::get('list', 'list')->name('list');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'status')->name('status');
            Route::get('review-status/{id}/{status}', 'reviews_status')->name('reviews.status');
            Route::post('search', 'search')->name('search');
            Route::post('store/{store_id}/search', 'search_store')->name('store-search');
            Route::get('reviews', 'review_list')->name('reviews');
            Route::get('remove-image', 'remove_image')->name('remove-image');
            Route::get('view/{id}', [ItemController::class, 'view'])->name('view');
            Route::get('store-item-export', 'store_item_export')->name('store-item-export');
            Route::get('reviews-export', 'reviews_export')->name('reviews_export');
            Route::get('item-wise-reviews-export', 'item_wise_reviews_export')->name('item_wise_reviews_export');

            Route::get('new/item/list', 'approval_list')->name('approval_list');
            Route::get('approved', 'approved')->name('approved');
            Route::get('product_denied', 'deny')->name('deny');
            Route::get('requested/item/view/{id}', 'requested_item_view')->name('requested_item_view');
            Route::get('product-gallery', 'product_gallery')->name('product_gallery');

            Route::get('get-categories', 'get_categories')->name('get-categories');
            Route::get('get-items', 'get_items')->name('getitems');
            Route::get('get-items-flashsale', 'get_items_flashsale')->name('getitems-flashsale');
            Route::post('food-variation-generate', 'food_variation_generator')->name('food-variation-generate');
            Route::post('variation-generate', 'variation_generator')->name('variation-generate');

            Route::get('export', 'export')->name('export');

            Route::get('get-variations', 'get_variations')->name('get-variations');
            Route::get('get-stock', 'get_stock')->name('get_stock');
            Route::post('stock-update', 'stock_update')->name('stock-update');

            Route::get('bulk-import', 'bulk_import_index')->name('bulk-import');
            Route::post('bulk-import', 'bulk_import_data');
            Route::get('bulk-export', 'bulk_export_index')->name('bulk-export-index');
            Route::post('bulk-export', 'bulk_export_data')->name('bulk-export');
        });

        Route::middleware(['module:banner'])->prefix('promotional-banner')->name('promotional-banner.')->controller(OtherBannerController::class)->group(function () {
            Route::get('add-new', 'promotional_index')->name('add-new');
            Route::get('add-video', 'promotional_video')->name('add-video');
            Route::post('store', 'promotional_store')->name('store');
            Route::get('edit/{id}', 'promotional_edit')->name('edit');
            Route::post('update/{id}', 'promotional_update')->name('update');
            Route::get('update-status/{id}/{status}', 'promotional_status')->name('update-status');
            Route::delete('delete/{banner}', 'promotional_destroy')->name('delete');
            Route::get('add-why-choose', 'promotional_why_choose')->name('add-why-choose');
            Route::post('why-choose/store', 'why_choose_store')->name('why-choose-store');
            Route::get('why-choose/edit/{id}', 'why_choose_edit')->name('why-choose-edit');
            Route::post('why-choose/update/{id}', 'why_choose_update')->name('why-choose-update');
            Route::get('why-choose/update-status/{id}/{status}', 'why_choose_status')->name('why-choose-status-update');
            Route::delete('why-choose/delete/{banner}', 'why_choose_destroy')->name('why-choose-delete');
            Route::post('video-content/store', 'video_content_store')->name('video-content-store');
            Route::post('video-image/store', 'video_image_store')->name('video-image-store');
        });

        Route::middleware(['module:campaign'])->prefix('campaign')->name('campaign.')->where(['type' => 'basic|item'])->controller(CampaignController::class)->group(function () {
            Route::get('{type}/add-new', 'index')->name('add-new');
            Route::post('store/basic', 'storeBasic')->name('store-basic');
            Route::post('store/item', 'storeItem')->name('store-item');
            Route::get('{type}/edit/{campaign}', 'edit')->name('edit');
            Route::get('{type}/view/{campaign}', [CampaignController::class, 'view'])->name('view');
            Route::post('basic/update/{campaign}', 'update')->name('update-basic');
            Route::post('item/update/{campaign}', 'updateItem')->name('update-item');
            Route::get('remove-store/{campaign}/{store}', 'remove_store')->name('remove-store');
            Route::post('add-store/{campaign}', 'addstore')->name('addstore');
            Route::get('{type}/list', 'list')->name('list');
            Route::get('status/{type}/{id}/{status}', 'status')->name('status');
            Route::delete('delete/{campaign}', 'delete')->name('delete');
            Route::delete('item/delete/{campaign}', 'delete_item')->name('delete-item');
            Route::get('store-confirmation/{campaign}/{id}/{status}', 'store_confirmation')->name('store_confirmation');
            Route::get('basic-campaign-export', 'basic_campaign_export')->name('basic_campaign_export');
            Route::get('basic-campaign-store-export', 'basic_campaign_store_export')->name('basic_campaign_store_export');
            Route::get('item-campaign-export', 'item_campaign_export')->name('item_campaign_export');
        });

        Route::middleware(['module:campaign'])->prefix('flash-sale')->name('flash-sale.')->controller(FlashSaleController::class)->group(function () {
            Route::get('add-new', 'index')->name('add-new');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::get('publish/{id}/{publish}', 'publish')->name('publish');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('add-product/{id}', 'add_product')->name('add-product');
            Route::post('store-product', 'store_product')->name('store-product');
            Route::delete('delete-product/{id}', 'delete_product')->name('delete-product');
            Route::get('status/{id}/{status}', 'status_product')->name('status-product');
        });

        Route::middleware(['module:customer_management'])->prefix('message')->name('message.')->controller(ConversationController::class)->group(function () {
            Route::get('list', 'list')->name('list');
            Route::post('store/{user_id}', 'store')->name('store');
            Route::get('view/{conversation_id}/{user_id}', [ConversationController::class, 'view'])->name('view');
        });

        Route::prefix('zone')->name('zone.')->controller(ZoneController::class)->group(function () {
            Route::get('get-zones', 'get_zones')->name('get-zones');
        });

        Route::prefix('store')->name('store.')->controller(VendorController::class)->group(function () {
            Route::get('get-stores-data/{store}', 'get_store_data')->name('get-stores-data');
            Route::get('store-filter/{id}', 'store_filter')->name('store-filter');
            Route::get('get-account-data/{store}', 'get_account_data')->name('get-account-data');
            Route::get('get-stores', 'get_stores')->name('get-stores');
            Route::get('get-providers', 'get_providers')->name('get-providers');
            Route::get('get-addons', 'get_addons')->name('get_addons');
            Route::middleware(['module:store'])->group(function () {
                Route::get('update-application/{id}/{status}', 'update_application')->name('application');
                Route::get('add', 'index')->name('add');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('update/{store}', 'update')->name('update');
                Route::post('discount/{store}', 'discountSetup')->name('discount');
                Route::post('update-settings/{store}', 'updateStoreSettings')->name('update-settings');
                Route::post('update-meta-data/{store}', 'updateStoreMetaData')->name('update-meta-data');
                Route::delete('delete/{store}', 'destroy')->name('delete');
                Route::delete('clear-discount/{store}', 'cleardiscount')->name('clear-discount');
                Route::get('disbursement-export/{id}/{type}', 'disbursement_export')->name('disbursement-export');
                Route::get('view/{store}/{tab?}/{sub_tab?}', [VendorController::class, 'view'])->name('view');
                Route::get('list', 'list')->name('list');
                Route::get('pending-requests', 'pending_requests')->name('pending-requests');
                Route::get('deny-requests', 'deny_requests')->name('deny-requests');
                Route::post('search', 'search')->name('search');
                Route::get('export', 'export')->name('export');
                Route::get('store-wise-reviwe-export', 'store_wise_reviwe_export')->name('store_wise_reviwe_export');
                Route::get('export/cash/{type}/{store_id}', 'cash_export')->name('cash_export');
                Route::get('export/order/{type}/{store_id}', 'order_export')->name('order_export');
                Route::get('export/withdraw/{type}/{store_id}', 'withdraw_trans_export')->name('withdraw_trans_export');
                Route::get('status/{store}/{status}', 'status')->name('status');
                Route::get('verified-seller/{store}', 'verifiedSeller')->name('verified-seller');
                Route::get('verified-seller-all', 'verifiedSellerAll')->name('verified-seller-all');
                Route::get('featured/{store}/{status}', 'featured')->name('featured');
                Route::get('toggle-settings-status/{store}/{status}/{menu}', 'store_status')->name('toggle-settings');
                Route::get('website-builder-status/{store}/{status}', 'website_builder_status')->name('website-builder-status');

                Route::get('recommended-store', 'recommended_store')->name('recommended_store');
                Route::get('recommended-store-add', 'recommended_store_add')->name('recommended_store_add');
                Route::get('recommended-store-status/{id}/{status}', 'recommended_store_status')->name('recommended_store_status');
                Route::delete('recommended-store-remove/{id}', 'recommended_store_remove')->name('recommended_store_remove');
                Route::get('shuffle-recommended-store/{status}', 'shuffle_recommended_store')->name('shuffle_recommended_store');

                Route::get('selected-stores', 'selected_stores')->name('selected_stores');

                Route::post('add-schedule', 'add_schedule')->name('add-schedule');
                Route::get('remove-schedule/{store_schedule}', 'remove_schedule')->name('remove-schedule');
            });

            Route::middleware(['module:store_bulk'])->group(function () {
                Route::get('bulk-import', 'bulk_import_index')->name('bulk-import');
                Route::post('bulk-import', 'bulk_import_data');
                Route::get('bulk-export', 'bulk_export_index')->name('bulk-export-index');
                Route::post('bulk-export', 'bulk_export_data')->name('bulk-export');
            });

            Route::middleware(['module:customer_management'])->group(function () {
                Route::get('message/{conversation_id}/{user_id}', 'conversation_view')->name('message-view');
                Route::get('message/list', 'conversation_list')->name('message-list');
            });
        });

        Route::middleware(['module:order'])->controller(OrderController::class)->group(function () {
            Route::get('order/generate-invoice/{id}', 'generate_invoice')->name('order.generate-invoice');
            Route::get('order/print-invoice/{id}', 'print_invoice')->name('order.print-invoice');
            Route::get('order/status', 'status')->name('order.status');
            Route::get('order/offline-payment', 'offline_payment')->name('order.offline_payment');
        });
        Route::middleware(['module:order'])->prefix('order')->name('order.')->controller(OrderController::class)->group(function () {
            Route::get('list/{status}', 'list')->name('list');
            Route::get('details/{id}', 'details')->name('details');
            Route::get('all-details/{id}', 'all_details')->name('all-details');

            Route::post('update-shipping/{order}', 'update_shipping')->name('update-shipping');

            Route::get('add-delivery-man/{order_id}/{delivery_man_id}', 'add_delivery_man')->name('add-delivery-man');

            Route::post('add-payment-ref-code/{id}', 'add_payment_ref_code')->name('add-payment-ref-code');
            Route::post('add-order-proof/{id}', 'add_order_proof')->name('add-order-proof');
            Route::get('remove-proof-image', 'remove_proof_image')->name('remove-proof-image');
            Route::get('store-filter/{store_id}', 'restaurnt_filter')->name('store-filter');
            Route::get('filter/reset', 'filter_reset');
            Route::post('filter', 'filter')->name('filter');
            Route::get('search', 'search')->name('search');
            Route::post('store/search', 'store_order_search')->name('store-search');
            Route::get('store/export', 'store_order_export')->name('store-export');
            Route::post('add-to-cart', 'add_to_cart')->name('add-to-cart');
            Route::post('remove-from-cart', 'remove_from_cart')->name('remove-from-cart');
            Route::post('update-cart-quantity', 'update_cart_quantity')->name('update-cart-quantity');
            Route::post('update/{order}', 'update')->name('update');
            Route::get('edit-order/{order}', 'edit')->name('edit');
            Route::get('quick-view', 'quick_view')->name('quick-view');
            Route::get('quick-view-cart-item', 'quick_view_cart_item')->name('quick-view-cart-item');
            Route::get('search-items', 'search_items')->name('search-items');
            Route::get('cart-list', 'cart_list')->name('cart-list');
            Route::get('get-searched-foods', 'getSearchedFoods')->name('get-searched-foods');
            Route::post('get-single-food-price', 'getSingleFoodPrice')->name('get-single-food-price');
            Route::get('export-orders/{file_type}/{status}/{type}', 'export_orders')->name('export');
            Route::post('switch-to-cod/{order}', 'switch_to_cod')->name('switch_to_cod');

            Route::get('offline/payment/list/{status}', 'offline_verification_list')->name('offline_verification_list');
            Route::get('parcel-cancelation-reasons', 'parcelCancellationReason')->name('parcelCancellationReason');
            Route::put('cancel-parcel', 'CancelParcel')->name('CancelParcel');
            Route::put('parcel-refund', 'parcelRefund')->name('parcelRefund');
            Route::get('parcel-return', 'parcelReturn')->name('parcelReturn');
        });
        Route::middleware(['module:order'])->prefix('refund')->name('refund.')->controller(OrderController::class)->group(function () {
            Route::get('refund_mode', 'refund_mode')->name('refund_mode');
            Route::post('refund_reason', 'refund_reason')->name('refund_reason');
            Route::get('status/{id}/{status}', 'reason_status')->name('reason_status');
            Route::put('reason-update', 'reasonUpdate')->name('reason-update');
            Route::get('reason-edit/{id}', 'reasonEdit')->name('reason-edit');
            Route::delete('reason_delete/{id}', 'reason_delete')->name('reason_delete');
            Route::put('order_refund_rejection', 'order_refund_rejection')->name('order_refund_rejection');
            Route::get('{status}', 'list')->name('refund_attr');
        });

        /*
        |--------------------------------------------------------------------------
        | Delivery Management
        |--------------------------------------------------------------------------
        |
        | Its own section in the side navigation, and now its own URL to match:
        | `admin/delivery-management/...` rather than `admin/business-settings/zone/...`.
        |
        | These screens used to sit inside the zone group purely to inherit its
        | `module:settings` permission, which left every one of them reading as a
        | sub-page of Zone Setup. The permission is declared here instead, so the
        | grouping is a statement about access rather than an accident of nesting.
        |
        | ROUTE NAMES ARE DELIBERATELY UNCHANGED — still `admin.business-settings.zone.*`.
        | Every `route()` call, the navigation map's `route` keys and the custom-role
        | permission rows key on those names, and renaming them is a separate change with
        | a much larger blast radius than moving a URL. The `name()` prefix below is what
        | keeps them stable now that the group has moved.
        |
        | Zone Setup itself, and the zone-scoped Smart Banner, stay under
        | `business-settings/zone` — they are Business Setup screens, not delivery ones.
        */
        /*
        | The old home of the screens below. Every in-app link is generated from a route name and
        | already points at the new prefix, but bookmarks, emailed links and anything a tenant has
        | written down still say `business-settings/zone/...`. A 301 to the same path under the new
        | prefix keeps all of them working, and `where()` limits it to the ten sections that moved
        | so Zone Setup and Smart Banner — which did NOT move — are untouched.
        |
        | Safe to drop once the old URLs stop appearing in the access log.
        */
        // The SOURCE picks up the group's `admin` prefix; the DESTINATION does not — it is
        // resolved from the site root — so it carries `admin/` explicitly.
        Route::permanentRedirect(
            'business-settings/zone/{section}/{rest?}',
            'admin/delivery-management/{section}/{rest?}'
        )->where([
            'section' => 'delivery-rule|area|zip-code|weight|dimension|vehicle-category'
                .'|free-delivery|additional-delivery-charge|eta-configuration|surge-price',
            'rest' => '.*',
        ])->name('business-settings.zone.legacy-redirect');

        Route::middleware(['module:settings'])->prefix('delivery-management')->name('business-settings.zone.')->group(function () {
            Route::prefix('delivery-rule')->name('delivery-rule.')->controller(DeliveryRuleController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('zone-coverage/{zoneId}', 'getCoverage')->name('coverage');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                // POST, not GET: switching a rule on or off writes, and the turn-off case
                // carries the replacement the admin nominated.
                Route::post('status/{id}', 'updateStatus')->name('status');
                Route::get('zone-rules/{zoneId}', 'zoneRules')->name('zone-rules');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
                // Last: a bare {id} would otherwise swallow 'create' and 'coverage'.
                Route::get('{id}', 'show')->name('show');
            });

            // Areas and ZIP codes sit inside the zone group, so they inherit its
            // `module:settings` permission — the same one the zone screens use. No new
            // role-matrix key: adding one would create a permission nothing checks.
            Route::prefix('area')->name('area.')->controller(AreaController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            Route::prefix('zip-code')->name('zip-code.')->controller(ZipCodeController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            // Weight and Dimension Setup — the parcel tier's SETTINGS. Placed in the zone
            // group with area and zip-code so they inherit its `module:settings` permission;
            // adding a new role-matrix key would create a permission nothing checks.
            //
            // Global, not zone-scoped, so there is no {zone} segment: a weight band and a
            // size class describe the package, not the geography.
            Route::prefix('weight')->name('weight.')->controller(WeightController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            Route::prefix('dimension')->name('dimension.')->controller(DimensionController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            // Vehicles Category — moved out of `admin/users/delivery-man/vehicle` on
            // 2026-09-08. It sits in the zone group with its Delivery Management siblings so
            // it inherits the same `module:settings` permission; adding a role-matrix key of
            // its own would create a permission nothing checks.
            //
            // Global, not zone-scoped: a coverage band and a weight limit describe the
            // vehicle, not the geography.
            Route::prefix('vehicle-category')->name('vehicle-category.')->controller(VehicleCategoryController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            Route::prefix('free-delivery')->name('free-delivery.')->controller(FreeDeliveryController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('zone-modules/{zoneId}', 'getModules')->name('zone-modules');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            // Additional Delivery Charge — the express and slightly-delayed offers, per
            // (zone, module). Replaces the inline editor that lived on Module Setup.
            Route::prefix('additional-delivery-charge')->name('additional-delivery-charge.')->controller(AdditionalDeliveryChargeController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('zone-modules/{zoneId}', 'getModules')->name('zone-modules');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            Route::prefix('eta-configuration')->name('eta-configuration.')->controller(EtaConfigurationController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('zone-modules/{zoneId}', 'getModules')->name('zone-modules');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });

            // The screen this replaces was reached as `surge-price/{zone_id}` and could only
            // ever show one zone. Surge Price is a Delivery Management screen of its own now,
            // shaped like Delivery Rule and Free Delivery — so the zone segment is gone and
            // `list` and `create` take no argument.
            Route::prefix('surge-price')->name('surge-price.')->controller(SurgePriceController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
            });
        });

        Route::prefix('business-settings')->name('business-settings.')->group(function () {
            Route::middleware(['module:settings'])->controller(AutomatedMessageController::class)->group(function () {
                Route::controller(BusinessSettingsController::class)->group(function () {
                    Route::get('business-setup/{tab?}', 'business_index')->name('business-setup');
                    Route::post('update-setup', 'business_setup')->name('update-setup');
                    Route::post('update-payment-setup', 'updatePaymentSetup')->name('update-payment-setup');
                    Route::post('update-dm', 'update_dm')->name('update-dm');
                    Route::post('update-disbursement', 'update_disbursement')->name('update-disbursement');
                    Route::post('update-store', 'update_store')->name('update-store');
                    Route::post('update-order', 'update_order')->name('update-order');
                    Route::post('update-priority', 'update_priority')->name('update-priority');
                    Route::get('toggle-settings/{key}/{value}', 'toggle_settings')->name('toggle-settings');
                    Route::get('site_direction', 'site_direction')->name('site_direction');
                });

                Route::controller(OfflinePaymentMethodController::class)->group(function () {
                    Route::get('offline-payment', 'index')->name('offline');
                    Route::get('offline-payment/new', 'create')->name('offline.new');
                    Route::post('offline-payment/store', 'store')->name('offline.store');
                    Route::get('offline-payment/edit/{id}', 'edit')->name('offline.edit');
                    Route::post('offline-payment/update', 'update')->name('offline.update');
                    Route::post('offline-payment/delete', 'delete')->name('offline.delete');
                    Route::get('offline-payment/status/{id}', 'status')->name('offline.status');
                });

                Route::controller(OrderCancelReasonController::class)->group(function () {
                    Route::get('order-cancel-reasons/status/{id}/{status}', 'status')->name('order-cancel-reasons.status');
                    Route::get('order-cancel-reasons/edit/{id}', 'edit')->name('order-cancel-reasons.edit');
                    Route::post('order-cancel-reasons/store', 'store')->name('order-cancel-reasons.store');
                    Route::put('order-cancel-reasons/update', 'update')->name('order-cancel-reasons.update');
                    Route::delete('order-cancel-reasons/destroy/{id}', 'destroy')->name('order-cancel-reasons.destroy');
                });

                Route::post('automated-message/store', 'store')->name('automated_message.store');
                Route::put('automated-message/update', 'update')->name('automated_message.update');
                Route::get('automated-message/status/{id}/{status}', 'status')->name('automated_message.status');
                Route::delete('automated-message/destroy/{id}', 'destroy')->name('automated_message.destroy');
                Route::get('automated-message/edit/{id}', 'edit')->name('automated_message.edit');
            });

            Route::middleware(['module:system_config'])->controller(BusinessSettingsController::class)->group(function () {
                Route::get('app-settings', 'app_settings')->name('app-settings');
                Route::post('app-settings', 'update_app_settings')->name('app-settings-update');
            });

            Route::middleware(['module:system_config'])->controller(BusinessSettingsController::class)->group(function () {
                Route::get('websocket', 'websocket')->name('websocket');
                Route::post('update-websocket', 'update_websocket')->name('update-websocket');

                Route::prefix('addon-activation')->name('addon-activation.')->controller(AddonActivationController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('activation', 'activation')->name('activation');
                });

                Route::prefix('language')->name('language.')->controller(LanguageController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::post('add-new', 'store')->name('add-new');
                    Route::get('update-status', 'update_status')->name('update-status');
                    Route::get('update-default-status', 'update_default_status')->name('update-default-status');
                    Route::post('update', 'update')->name('update');
                    Route::get('translate/{lang}', 'translate')->name('translate');
                    Route::post('translate-submit/{lang}', 'translate_submit')->name('translate-submit');
                    // Saves every edited row on the page in a single file write.
                    Route::post('translate-bulk-submit/{lang}', 'translate_bulk_submit')->name('translate-bulk-submit');
                    Route::post('remove-key/{lang}', 'translate_key_remove')->name('remove-key');
                    Route::get('delete/{lang}', 'delete')->name('delete');
                    // POST, not ANY: GET bypasses CSRF verification and this
                    // endpoint writes to the language file.
                    Route::post('auto-translate/{lang}', 'auto_translate')->name('auto-translate');
                    Route::get('auto-translate-all/{lang}', 'auto_translate_all')->name('auto_translate_all');
                });
            });

            Route::middleware(['module:login_setup'])->controller(BusinessSettingsController::class)->group(function () {
                Route::prefix('login-settings')->name('login-settings.')->group(function () {
                    Route::get('login-setup', 'login_settings')->name('index');
                    Route::post('login-setup/update', 'login_settings_update')->name('update');
                });

                Route::get('login-url-setup', 'login_url_page')->name('login_url_page');
                Route::post('login-url-setup/update', 'login_url_page_update')->name('login_url_update');
            });

            Route::middleware(['module:email_setups'])->controller(BusinessSettingsController::class)->group(function () {
                Route::get('email-setup/{type}/{tab?}', 'email_index')->name('email-setup');
                Route::post('email-setup/{type}/{tab?}', 'update_email_index')->name('email-setup-update');
                Route::get('email-status/{type}/{tab}/{status}', 'update_email_status')->name('email-status');
            });

            Route::middleware(['module:notification_setup'])->controller(BusinessSettingsController::class)->group(function () {
                Route::get('fcm-index', 'fcm_index')->name('fcm-index');
                Route::get('fcm-config', 'fcm_config')->name('fcm-config');
                Route::post('update-fcm', 'update_fcm')->name('update-fcm');

                Route::post('update-fcm-messages', 'update_fcm_messages')->name('update-fcm-messages');
                Route::post('update-fcm-messages-rental', 'update_fcm_messages_rental')->name('update-fcm-messages-rental');
                Route::post('update-fcm-messages-service', 'update_fcm_messages_service')->name('update-fcm-messages-service');
                Route::post('update-fcm-messages-ride-share', 'update_fcm_messages_ride_share')->name('update-fcm-messages-ride-share');

                Route::get('notification-setup', 'notification_setup')->name('notification_setup');
                Route::get('notification-status-change/{key}/{user_type}/{type}', 'notification_status_change')->name('notification_status_change');
            });

            Route::middleware(['module:landing_pages'])->controller(BusinessSettingsController::class)->group(function () {
                Route::post('update-landing-setup', 'landing_page_settings_update')->name('update-landing-setup');
                Route::delete('delete-custom-landing-page', 'delete_custom_landing_page')->name('delete-custom-landing-page');

                Route::get('pages/admin-landing-page-settings/{tab?}', 'admin_landing_page_settings')->name('admin-landing-page-settings');
                Route::post('pages/admin-landing-page-settings/{tab}', 'update_admin_landing_page_settings')->name('admin-landing-page-settings-update');
                Route::get('promotional-status/{id}/{status}', 'promotional_status')->name('promotional-status');
                Route::get('pages/admin-landing-page-settings/promotional-section/edit/{id}', 'promotional_edit')->name('promotional-edit');
                Route::post('promotional-section/update/{id}', 'promotional_update')->name('promotional-update');
                Route::delete('banner/delete/{banner}', 'promotional_destroy')->name('promotional-delete');
                Route::get('feature-status/{id}/{status}', 'feature_status')->name('feature-status');
                Route::get('pages/admin-landing-page-settings/feature-list/edit/{id}', 'feature_edit')->name('feature-edit');
                Route::post('feature-section/update/{id}', 'feature_update')->name('feature-update');
                Route::delete('feature/delete/{feature}', 'feature_destroy')->name('feature-delete');
                Route::get('criteria-status/{id}/{status}', 'criteria_status')->name('criteria-status');
                Route::get('pages/admin-landing-page-settings/why-choose-us/criteria-list/edit/{id}', 'criteria_edit')->name('criteria-edit');
                Route::post('criteria-section/update/{id}', 'criteria_update')->name('criteria-update');
                Route::delete('admin/criteria/delete/{criteria}', 'criteria_destroy')->name('criteria-delete');
                Route::get('review-status/{id}/{status}', 'review_status')->name('review-status');
                Route::get('pages/admin-landing-page-settings/testimonials/review-list/edit/{id}', 'review_edit')->name('review-edit');
                Route::post('review-section/update/{id}', 'review_update')->name('review-update');
                Route::delete('review/delete/{review}', 'review_destroy')->name('review-delete');
                Route::get('pages/react-landing-page-settings/{tab?}', 'react_landing_page_settings')->name('react-landing-page-settings');
                Route::post('pages/react-landing-page-settings/{tab?}', 'update_react_landing_page_settings')->name('react-landing-page-settings-update');
                Route::delete('react-landing-page-settings/{tab}/{key}', 'delete_react_landing_page_settings')->name('react-landing-page-settings-delete');
                Route::get('pages/react-ride-share-page-settings/{tab?}', 'react_ride_share_page_settings')->name('react-ride-share-page-settings');
                Route::post('pages/react-ride-share-page-settings/{tab?}', 'update_react_ride_share_page_settings')->name('react-ride-share-page-settings-update');
                Route::delete('react-ride-share-page-settings/{tab}/{key}', 'delete_react_ride_share_page_settings')->name('react-ride-share-page-settings-delete');
                Route::get('review-react-status/{id}/{status}', 'review_react_status')->name('review-react-status');
                Route::get('pages/react-landing-page-settings/testimonials/review-react-list/edit/{id}', 'review_react_edit')->name('review-react-edit');
                Route::get('status-update/{type}/{key}', 'statusUpdate')->name('statusUpdate');

                Route::post('pages/react-landing-page-settings/faq-store', 'reactFaqStore')->name('reactFaqStore');
                Route::get('pages/react-landing-page-settings/faq-status/{id}/{status}', 'reactfaqStatus')->name('reactfaqStatus');
                Route::get('pages/react-landing-page-settings/faq/edit/{id}', 'reactfaqEdit')->name('reactfaqEdit');
                Route::post('pages/react-landing-page-settings/faq-data/update/{id}', 'reactFaqUpdate')->name('reactFaqUpdate');
                Route::delete('pages/react-landing-page-settings/faq/delete/{faq}', 'reactfaqDestroy')->name('reactfaqDestroy');

                Route::post('promotional-banner-store', 'react_promotional_banner_store')->name('promotional-banner-store');
                Route::get('promotional-banner-status/{id}/{status}', 'react_promotional_banner_status')->name('promotional-banner-status');
                Route::post('promotional-banner/update/{id}', 'react_promotional_banner_update')->name('promotional-banner-update');
                Route::delete('promotional-banner/delete/{react_promotional_banner}', 'react_promotional_banner_destroy')->name('promotional-banner-delete');

                Route::post('review-react-section/update/{id}', 'review_react_update')->name('review-react-update');
                Route::delete('review-react/delete/{review}', 'review_react_destroy')->name('review-react-delete');
                Route::get('pages/flutter-landing-page-settings/{tab?}', 'flutter_landing_page_settings')->name('flutter-landing-page-settings');
                Route::post('pages/flutter-landing-page-settings/{tab}', 'update_flutter_landing_page_settings')->name('flutter-landing-page-settings-update');
                Route::get('flutter-criteria-status/{id}/{status}', 'flutter_criteria_status')->name('flutter-criteria-status');
                Route::get('pages/flutter-landing-page-settings/special-criteria/edit/{id}', 'flutter_criteria_edit')->name('flutter-criteria-edit');
                Route::post('flutter-criteria-section/update/{id}', 'flutter_criteria_update')->name('flutter-criteria-update');
                Route::delete('flutter/criteria/delete/{criteria}', 'flutter_criteria_destroy')->name('flutter-criteria-delete');
            });

            Route::middleware(['module:seo'])->prefix('seo-settings')->name('seo-settings.')->controller(BusinessSettingsController::class)->group(function () {
                Route::get('page-meta-data', 'pageMetaData')->name('pageMetaData');
                Route::post('page-meta-data-update', 'pageMetaDataUpdate')->name('pageMetaDataUpdate');
            });

            Route::middleware(['module:business_pages'])->controller(BusinessSettingsController::class)->group(function () {
                Route::get('pages/business-page/terms-and-conditions', 'terms_and_conditions')->name('terms-and-conditions');
                Route::post('pages/business-page/terms-and-conditions', 'terms_and_conditions_update');

                Route::get('pages/business-page/privacy-policy', 'privacy_policy')->name('privacy-policy');
                Route::post('pages/business-page/privacy-policy', 'privacy_policy_update');

                Route::get('pages/business-page/about-us', 'about_us')->name('about-us');
                Route::post('pages/business-page/about-us', 'about_us_update');

                Route::get('pages/business-page/refund', 'refund_policy')->name('refund');
                Route::post('pages/business-page/refund', 'refund_update');
                Route::get('pages/refund-policy/{status}', 'refund_policy_status')->name('refund-policy-status');

                Route::get('pages/business-page/cancelation', 'cancellation_policy')->name('cancelation');
                Route::post('pages/business-page/cancelation', 'cancellation_policy_update');
                Route::get('pages/cancellation-policy/{status}', 'cancellation_policy_status')->name('cancellation-policy-status');

                Route::get('pages/business-page/shipping-policy', 'shipping_policy')->name('shipping-policy');
                Route::post('pages/business-page/shipping-policy', 'shipping_policy_update');
                Route::get('pages/shipping-policy/{status}', 'shipping_policy_status')->name('shipping-policy-status');
            });

            Route::middleware(['module:social_media'])->controller(SocialMediaController::class)->group(function () {
                Route::get('social-media/fetch', 'fetch')->name('social-media.fetch');
                Route::get('social-media/status-update', 'social_media_status_update')->name('social-media.status-update');
                Route::resource('pages/social-media', SocialMediaController::class);
            });

            Route::middleware(['module:third_party-ms'])->controller(BusinessSettingsController::class)->group(function () {
                Route::prefix('marketing')->name('marketing.')->controller(AnalyticScriptController::class)->group(function () {
                    Route::get('analytic-setup', 'analyticSetup')->name('analytic');
                    Route::post('analytic-setup-update', 'analyticUpdate')->name('analyticUpdate');
                    Route::get('analytic-status', 'analyticStatus')->name('analyticStatus');
                });

                Route::get('open-ai', [BusinessSettingsController::class, 'openAI'])->name('openAI');
                Route::get('open-ai-settings', 'openAISettings')->name('openAISettings');
                Route::put('open-ai-settings-update', 'openAISettingsUpdate')->name('openAISettingsUpdate');
                Route::get('open-ai-config-status', 'openAIConfigStatus')->name('openAIConfigStatus');
                Route::post('openai-update', 'openAIConfigUpdate')->name('openAIConfigUpdate');

                Route::prefix('third-party')->name('third-party.')->controller(BusinessSettingsController::class)->group(function () {
                    Route::controller(SMSModuleController::class)->group(function () {
                        Route::get('sms-module', 'sms_index')->name('sms-module');
                        Route::post('sms-module-update/{sms_module}', 'sms_update')->name('sms-module-update');
                    });
                    Route::get('payment-method', 'payment_index')->name('payment-method');

                    Route::post('payment-method-update', 'payment_config_update')->name('payment-method-update');
                    Route::get('config-setup', 'config_setup')->name('config-setup');
                    Route::post('config-update', 'config_update')->name('config-update');
                    Route::get('mail-config', 'mail_index')->name('mail-config');
                    Route::get('test-mail', 'test_mail')->name('test');
                    Route::post('mail-config', 'mail_config');
                    Route::post('mail-config-status', 'mail_config_status')->name('mail-config-status');
                    Route::get('send-mail', 'send_mail')->name('mail.send');
                    Route::prefix('social-login')->name('social-login.')->controller(BusinessSettingsController::class)->group(function () {
                        Route::get('view', 'viewSocialLogin')->name('view');
                    });
                    Route::get('recaptcha', 'recaptcha_index')->name('recaptcha_index');
                    Route::post('recaptcha-update', 'recaptcha_update')->name('recaptcha_update');
                    Route::get('firebase-otp', 'firebase_otp_index')->name('firebase_otp_index');
                    Route::post('firebase-otp-update', 'firebase_otp_update')->name('firebase_otp_update');
                    Route::get('storage-connection', 'storage_connection_index')->name('storage_connection_index');
                    Route::post('storage-connection-update/{name}', 'storage_connection_update')->name('storage_connection_update');
                    // erp integration
                    Route::controller(ErpIntegrationController::class)->group(function () {
                        Route::get('integration', 'index')->name('integration');
                        Route::post('integration/store', 'store')->name('integration.store');
                        Route::post('integration/webhook/{token}', 'updateWebhook')->name('integration.update-webhook');
                        Route::post('integration/revoke/{token}', 'revoke')->name('integration.revoke');
                        Route::delete('integration/destroy/{token}', 'destroy')->name('integration.destroy');
                    });
                });
            });

            Route::middleware(['module:gallery'])->controller(FileManagerController::class)->group(function () {
                Route::prefix('file-manager')->name('file-manager.')->group(function () {
                    Route::get('download/{file_name}/{storage?}', 'download')->name('download');
                    Route::get('index/{folder_path?}/{storage?}', 'index')->name('index');
                    Route::post('image-upload', 'upload')->name('image-upload');
                    Route::delete('delete/{file_path}', 'destroy')->name('destroy');
                });
            });

            Route::middleware(['module:clean_database'])->controller(DatabaseSettingController::class)->group(function () {
                Route::get('db-index', 'db_index')->name('db-index');
                Route::post('db-clean', 'clean_db')->name('clean-db');
            });

            Route::middleware(['module:database_backup'])->prefix('database')->name('database.')->controller(DatabaseBackupController::class)->group(function () {
                Route::get('backup', 'backupIndex')->name('backup');
                Route::post('backup', 'store')->name('backup.store');
                Route::get('backup/download/{file}', 'download')->name('backup.download');
                Route::delete('backup/{file}', 'destroy')->name('backup.destroy');

                Route::get('restore', 'restoreIndex')->name('restore');
                Route::post('restore/upload', 'upload')->name('restore.upload');
                Route::post('restore/run', 'restore')->name('restore.run');
            });

            Route::middleware(['module:system_config'])->prefix('system-addon')->name('system-addon.')->controller(SystemAddonController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('publish', 'publish')->name('publish');
                Route::post('activation', 'activation')->name('activation');
                Route::post('upload', 'upload')->name('upload');
                Route::post('delete', 'delete_theme')->name('delete');
            });
        });

        Route::prefix('pos')->name('pos.')->controller(POSController::class)->group(function () {
            Route::post('variant_price', 'variant_price')->name('variant_price');
            Route::middleware(['module:pos'])->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('quick-view', 'quick_view')->name('quick-view');
                Route::post('item-stock-view', 'item_stock_view')->name('item_stock_view');
                Route::post('item-stock-view-update', 'item_stock_view_update')->name('item_stock_view_update');
                Route::get('quick-view-cart-item', 'quick_view_card_item')->name('quick-view-cart-item');
                Route::post('add-to-cart', 'addToCart')->name('add-to-cart');
                Route::post('remove-from-cart', 'removeFromCart')->name('remove-from-cart');
                Route::post('cart-items', 'cart_items')->name('cart_items');
                Route::post('single-items', 'single_items')->name('single_items');
                Route::post('update-quantity', 'updateQuantity')->name('updateQuantity');
                Route::post('empty-cart', 'emptyCart')->name('emptyCart');
                Route::post('tax', 'update_tax')->name('tax');
                Route::post('discount', 'update_discount')->name('discount');
                Route::get('customers', 'get_customers')->name('customers');
                Route::post('order', 'place_order')->name('order');
                Route::get('invoice/{id}', 'generate_invoice');
                Route::post('customer-store', 'customer_store')->name('customer-store');
                Route::post('add-delivery-address', 'addDeliveryInfo')->name('add-delivery-address');
                Route::get('data', 'extra_charge')->name('extra_charge');
                Route::get('delivery-coverage', 'getDeliveryCoverage')->name('delivery_coverage');
                Route::get('get-user-data', 'getUserData')->name('getUserData');
                Route::prefix('delivery-type')->name('delivery_type.')->group(function () {
                    Route::get('get', 'getDeliveryTypes')->name('get');
                    Route::post('set', 'setDeliveryType')->name('set');
                });
            });
        });

        Route::middleware(['module:report'])->prefix('report')->name('report.')->controller(ReportController::class)->group(function () {
            Route::get('stock-report', 'stock_report')->name('stock-report');
            Route::get('generate-statement/{id}', 'generate_statement')->name('generate-statement');
        });

        Route::middleware(['module:settings'])->prefix('social-login')->name('social-login.')->controller(BusinessSettingsController::class)->group(function () {
            Route::post('update/{service}', 'updateSocialLogin')->name('update');
        });
        Route::prefix('apple-login')->name('apple-login.')->controller(BusinessSettingsController::class)->group(function () {
            Route::post('update/{service}', 'updateAppleLogin')->name('update');
        });
        Route::prefix('dispatch')->name('dispatch.')->group(function () {
            Route::middleware(['module:dispatch'])->controller(OrderController::class)->group(function () {
                Route::controller(DashboardController::class)->group(function () {
                    Route::get('/', 'dispatch_dashboard')->name('dashboard');
                });
                Route::controller(OrderController::class)->group(function () {
                    Route::get('list/{module?}/{status?}', 'dispatch_list')->name('list');
                });
                Route::controller(ParcelController::class)->group(function () {
                    Route::get('parcel/list/{module?}/{status?}', 'parcel_dispatch_list')->name('parcel.list');
                });
                Route::get('order/generate-invoice/{id}', 'generate_invoice')->name('order.generate-invoice');
            });
        });

        Route::prefix('users')->name('users.')->controller(CustomerController::class)->group(function () {
            Route::controller(DashboardController::class)->group(function () {
                Route::get('/', 'user_dashboard')->name('dashboard')->middleware('module:user_overview');
            });

            Route::prefix('customer')->name('customer.')->group(function () {
                Route::middleware(['module:customer_wallet'])->prefix('wallet')->name('wallet.')->controller(CustomerWalletController::class)->group(function () {
                    Route::get('add-fund', 'add_fund_view')->name('add-fund');
                    Route::post('add-fund', 'add_fund');
                    Route::post('set-date', 'set_date')->name('set-date');
                    Route::get('report', 'report')->name('report');
                    Route::get('export', 'export')->name('export');
                    Route::get('get-user-wallet', 'getUserWallet')->name('getUserWallet');
                });

                Route::middleware(['module:customer_management'])->controller(CustomerController::class)->group(function () {
                    Route::get('subscribed', 'subscribedCustomers')->name('subscribed');
                    Route::get('subscriber-search', 'subscribed_customer_export')->name('subscriber-export');
                });
                Route::middleware(['module:customer_loyalty_point'])->controller(LoyaltyPointController::class)->group(function () {
                    Route::get('loyalty-point/report', 'report')->name('loyalty-point.report');
                    Route::get('loyalty-point/export', 'export')->name('loyalty-point.export');
                    Route::post('loyalty-point/set-date', 'set_date')->name('loyalty-point.set-date');
                });
                Route::middleware(['module:customer_management'])->controller(CustomerController::class)->group(function () {
                    Route::get('settings', 'settings')->name('settings');
                    Route::post('update-settings', 'update_settings')->name('update-settings');
                    Route::get('export', 'export')->name('export');
                    Route::get('order-export', 'customer_order_export')->name('order-export');
                    Route::get('trip-export', 'customer_trip_export')->name('trip-export');
                });
            });
            Route::get('customer/select-list', 'get_customers')->name('customer.select-list');

            Route::middleware(['module:customer_management'])->prefix('customer')->name('customer.')->controller(CustomerController::class)->group(function () {
                Route::controller(CustomerController::class)->group(function () {
                    Route::get('list', 'customer_list')->name('list');
                    Route::get('rental-view/{user_id}', 'rentalView')->name('rental.view');
                    Route::get('view/{user_id}', [CustomerController::class, 'view'])->name('view');
                });
                Route::controller(ProCustomerController::class)->group(function () {
                    Route::get('subscription-plan/{user_id}', 'subscriptionPlanView')->name('subscription-plan');
                });
                Route::post('search', 'search')->name('search');
                Route::post('status/{customer}', 'status')->name('status');
            });
            Route::middleware(['module:customer_management'])->prefix('contact')->name('contact.')->controller(ContactController::class)->group(function () {
                Route::get('contact-list', 'list')->name('contact-list');
                Route::get('contact-list-export', 'exportList')->name('exportList');
                Route::delete('contact-delete/{id}', 'destroy')->name('contact-delete');
                Route::get('contact-view/{id}', [ContactController::class, 'view'])->name('contact-view');
                Route::post('contact-update/{id}', 'update')->name('contact-update');
                Route::post('contact-send-mail/{id}', 'send_mail')->name('contact-send-mail');
            });
        });
        Route::prefix('transactions')->name('transactions.')->controller(ProvideDMEarningController::class)->group(function () {
            Route::controller(DashboardController::class)->group(function () {
                Route::get('/', 'transaction_dashboard')->name('dashboard');
            });
            Route::middleware(['module:order'])->controller(ParcelController::class)->group(function () {
                Route::get('parcel/order/details/{id}', 'order_details')->name('parcel.order.details');
            });
            Route::prefix('report')->name('report.')->group(function () {
                Route::middleware(['module:report'])->controller(ReportController::class)->group(function () {
                    Route::get('day-wise-report', 'day_wise_report')->name('day-wise-report');
                    Route::get('day-wise-report-export', 'day_wise_export')->name('day-wise-report-export');
                    Route::get('parcel-transaction-report', 'parcel_transaction_report')->name('parcel-transaction-report');
                    Route::get('parcel-transaction-report-export', 'parcel_transaction_export')->name('parcel-transaction-report-export');
                    Route::post('set-date', 'set_date')->name('set-date');
                    Route::get('stock-wise-report-search', 'stock_wise_export')->name('stock-wise-report-export');
                    Route::get('low-stock-report', 'low_stock_report')->name('low-stock-report');
                    Route::post('low-stock-report', 'low_stock_search')->name('low-stock-search');
                    Route::get('low-stock-wise-report-search', 'low_stock_wise_export')->name('low-stock-wise-report-export');
                });

                Route::middleware(['module:sales_report'])->controller(ReportController::class)->group(function () {
                    Route::get('order-report', 'order_report')->name('order-report');
                    Route::get('order-report-export', 'order_report_export')->name('order-report-export');
                    Route::get('parcel-report', 'parcel_report')->name('parcel-report');
                    Route::get('parcel-report-export', 'parcel_report_export')->name('parcel-report-export');
                });

                Route::middleware(['module:earning_report'])->controller(DeliverymanEarningReportController::class)->group(function () {
                    Route::controller(AdminEarningReportController::class)->group(function () {
                        Route::get('admin-earning-report', 'getAdminEarningReport')->name('admin-earning-report');
                        Route::get('admin-earning-summary', 'getAdminEarningSummary')->name('admin-earning-summary');
                        Route::get('admin-earning-breakdown', 'getAdminEarningBreakdown')->name('admin-earning-breakdown');
                        Route::get('admin-expense-breakdown', 'getAdminExpenseBreakdown')->name('admin-expense-breakdown');
                        Route::get('admin-monthly-earnings', 'getMonthlyEarningsReport')->name('admin-monthly-earnings');
                        Route::get('admin-zone-wise-earnings', 'getZoneWiseEarnings')->name('admin-zone-wise-earnings');
                        Route::get('admin-top-earning-stores', 'getTopEarningStores')->name('admin-top-earning-stores');
                        Route::get('admin-earning-transactions', 'getEarningTransactions')->name('admin-earning-transactions');
                        Route::get('admin-earning-export', 'exportEarningTransactions')->name('admin-earning-export');
                        Route::get('admin-deliveryman-earning-transactions', 'getDeliverymanEarningTransactions')->name('admin-deliveryman-earning-transactions');
                        Route::get('admin-deliveryman-earning-export', 'exportDeliverymanEarningTransactions')->name('admin-deliveryman-earning-export');
                    });
                    Route::controller(StoreEarningReportController::class)->group(function () {
                        Route::get('store-earning-report', 'getStoreEarningReport')->name('store-earning-report');
                        Route::get('store-earning-summary', 'getStoreEarningSummary')->name('store-earning-summary');
                        Route::get('store-earning-breakdown', 'getStoreEarningBreakdown')->name('store-earning-breakdown');
                        Route::get('store-expense-breakdown', 'getStoreExpenseBreakdown')->name('store-expense-breakdown');
                        Route::get('store-earning-trend', 'getStoreEarningTrend')->name('store-earning-trend');
                        Route::get('store-earning-transactions', 'getStoreEarningTransactions')->name('store-earning-transactions');
                        Route::get('store-earning-export', 'exportStoreEarningTransactions')->name('store-earning-export');
                    });
                    Route::get('deliveryman-earning-report', 'getDeliverymanEarningReport')->name('deliveryman-earning-report');
                    Route::get('deliveryman-earning-summary', 'getDeliverymanEarningSummary')->name('deliveryman-earning-summary');
                    Route::get('deliveryman-earning-breakdown', 'getDeliverymanEarningBreakdown')->name('deliveryman-earning-breakdown');
                    Route::get('deliveryman-expense-breakdown', 'getDeliverymanExpenseBreakdown')->name('deliveryman-expense-breakdown');
                    Route::get('deliveryman-earning-trend', 'getDeliverymanEarningTrend')->name('deliveryman-earning-trend');
                });

                Route::middleware(['module:performance_report'])->controller(ReportController::class)->group(function () {
                    Route::get('item-wise-report', 'item_wise_report')->name('item-wise-report');
                    Route::get('item-wise-export', 'item_wise_export')->name('item-wise-export');
                    Route::post('item-wise-report-search', 'item_search')->name('item-wise-report-search');
                    Route::get('store-wise-report', 'store_summary_report')->name('store-summary-report');
                    Route::post('store-summary-report-search', 'store_summary_search')->name('store-summary-report-search');
                    Route::get('store-summary-report-export', 'store_summary_export')->name('store-summary-report-export');
                    Route::get('store-wise-sales-report', 'store_sales_report')->name('store-sales-report');
                    Route::get('store-wise-sales-report-export', 'store_sales_export')->name('store-sales-report-export');
                    Route::get('store-wise-order-report', 'store_order_report')->name('store-order-report');
                    Route::post('store-wise-order-report-search', 'store_order_search')->name('store-order-report-search');
                    Route::get('store-wise-order-report-export', 'store_order_export')->name('store-order-report-export');
                });

                Route::middleware(['module:expense_report'])->controller(ReportController::class)->group(function () {
                    Route::get('expense-report', 'expense_report')->name('expense-report');
                    Route::get('expense-export', 'expense_export')->name('expense-export');
                    Route::post('expense-report-search', 'expense_search')->name('expense-report-search');
                    Route::get('parcel-expense-report', 'parcel_expense_report')->name('parcel-expense-report');
                    Route::get('parcel-expense-export', 'parcel_expense_export')->name('parcel-expense-export');
                    Route::get('rental-expense-report', 'rental_expense_report')->name('rental-expense-report');
                    Route::get('rental-expense-export', 'rental_expense_export')->name('rental-expense-export');
                    Route::get('rideshare-expense-report', 'rideshare_expense_report')->name('rideshare-expense-report');
                    Route::get('rideshare-expense-export', 'rideshare_expense_export')->name('rideshare-expense-export');
                    Route::get('service-expense-report', 'service_expense_report')->name('service-expense-report');
                    Route::get('service-expense-export', 'service_expense_export')->name('service-expense-export');
                    Route::get('other-expense-report', 'other_expense_report')->name('other-expense-report');
                    Route::get('other-expense-export', 'other_expense_export')->name('other-expense-export');
                });

                Route::middleware(['module:disbursement_report'])->controller(ReportController::class)->group(function () {
                    Route::get('disbursement-report/{tab?}', 'disbursement_report')->name('disbursement_report');
                    Route::get('disbursement-report-export/{type}/{tab?}', 'disbursement_report_export')->name('disbursement_report_export');
                });

                Route::middleware(['module:vendor_vat_report'])->controller(VendorTaxReportController::class)->group(function () {
                    Route::get('vendor-wise-taxes', 'vendorWiseTaxes')->name('vendorWiseTaxes');
                    Route::get('vendor-wise-taxes-export', 'vendorWiseTaxExport')->name('vendorWiseTaxExport');
                    Route::get('vendor-tax-report', 'vendorTax')->name('vendorTax');
                    Route::get('vendor-tax-export', 'vendorTaxExport')->name('vendorTaxExport');
                });

                Route::middleware(['module:admin_text_module'])->controller(AdminTaxReportController::class)->group(function () {
                    Route::get('get-tax-export', 'getTaxReport')->name('getTaxReport');
                    Route::get('get-tax-list', 'getTaxList')->name('getTaxList');
                    Route::get('get-tax-details', 'getTaxDetails')->name('getTaxDetails');
                    Route::get('tax-details-report-export', 'adminTaxDetailsExport')->name('getTaxDetailsExport');
                    Route::get('admin-tax-report-export', 'adminTaxReportExport')->name('adminTaxReportExport');
                    Route::get('parcel-wise-taxes', 'parcelWiseTaxes')->name('parcel-wise-taxes');
                    Route::get('parcel-wise-taxes-export', 'parcelWiseTaxExport')->name('parcel-wise-tax-export');
                });
            });

            Route::middleware(['module:collect_cash'])->prefix('account-transaction')->name('account-transaction.')->controller(AccountTransactionController::class)->group(function () {
                Route::get('list', 'index')->name('index');
                Route::post('store', 'store')->name('store');
                Route::get('details/{id}', 'show')->name('view');
                Route::get('export', 'export_account_transaction')->name('export');
                Route::post('search', 'search_account_transaction')->name('search');
            });

            Route::resource('provide-deliveryman-earnings', ProvideDMEarningController::class)->middleware('module:provide_dm_earning');
            Route::get('export-deliveryman-earnings', 'dm_earning_list_export')->name('export-deliveryman-earning')->middleware('module:provide_dm_earning');
            Route::post('deliveryman-earnings-search', 'search_deliveryman_earning')->name('search-deliveryman-earning');

            Route::prefix('store')->name('store.')->controller(VendorController::class)->group(function () {
                Route::post('status-filter', 'status_filter')->name('status-filter');
                Route::middleware(['module:withdraw_list'])->group(function () {
                    Route::post('withdraw-status/{id}', 'withdrawStatus')->name('withdraw_status');
                    Route::get('withdraw_list', 'withdraw')->name('withdraw_list');
                    Route::get('withdraw_export', 'withdraw_export')->name('withdraw_export');
                    Route::get('withdraw-view/{withdraw_id}/{seller_id}', 'withdraw_view')->name('withdraw_view');
                    Route::get('get-Withdraw-Details', 'getWithdrawDetails')->name('getWithdrawDetails');
                });
            });

            Route::middleware(['module:withdraw_list'])->prefix('delivery-man')->name('delivery-man.')->controller(DeliveryManController::class)->group(function () {
                Route::post('status-filter', 'status_filter')->name('status-filter');
                Route::post('withdraw-status/{id}', 'withdrawStatus')->name('withdraw_status');
                Route::get('withdraw_list', 'withdraw_list')->name('withdraw_list');
                Route::post('withdraw_search', 'withdraw_search')->name('withdraw_search');
                Route::get('withdraw_export', 'withdraw_export')->name('withdraw_export');
                Route::get('withdraw-view/{withdraw_id}/{seller_id}', 'withdraw_view')->name('withdraw_view');
                Route::get('get-Withdraw-Details', 'getWithdrawDetails')->name('getWithdrawDetails');
            });

            Route::middleware(['module:withdraw_method'])->prefix('withdraw-method')->name('withdraw-method.')->controller(WithdrawalMethodController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::put('update', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::post('status-update', 'status_update')->name('status-update');
                Route::post('default-status-update', 'default_status_update')->name('default-status-update');
                Route::get('get-method-info', 'getMethodInfo')->name('getMethodInfo');
            });

            Route::middleware(['module:disbursement'])->prefix('store-disbursement')->name('store-disbursement.')->controller(StoreDisbursementController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::get('details/{id}', [StoreDisbursementController::class, 'view'])->name('view');
                Route::get('status', 'status')->name('status');
                Route::get('change-status/{id}/{status}', 'statusById')->name('change-status');
                Route::get('export/{id}/{type?}', 'export')->name('export');
            });
            Route::middleware(['module:disbursement'])->prefix('dm-disbursement')->name('dm-disbursement.')->controller(DeliveryManDisbursementController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::get('details/{id}', [DeliveryManDisbursementController::class, 'view'])->name('view');
                Route::get('export/{id}/{type?}', 'export')->name('export');
                Route::get('status', 'status')->name('status');
                Route::get('change-status/{id}/{status}', 'statusById')->name('change-status');
            });
        });

        Route::prefix('pro-customer')->name('pro-customer.')->controller(ProCustomerController::class)->group(function () {
            Route::middleware(['module:customer_management'])->group(function () {
                Route::get('list', 'customerList')->name('list');
                Route::get('export', 'customerExport')->name('export');
                Route::post('subscription/cancel/{id}', 'subscriptionCancel')->name('subscription.cancel');
                Route::post('subscription/start/{userId}', 'subscriptionStart')->name('subscription.start');
                Route::post('subscription/renew/{id}', 'subscriptionRenew')->name('subscription.renew');
                Route::post('subscription/shift/{id}', 'subscriptionShift')->name('subscription.shift');
            });

            Route::middleware(['module:pro_customer_subscription'])->group(function () {
                Route::get('benefits-setup', 'benefitsSetup')->name('benefits-setup');
                Route::post('benefits-setup/update', 'benefitsSetupUpdate')->name('benefits-setup.update');

                Route::get('price-setup', 'priceSetup')->name('price-setup');
                Route::post('plan/store', 'planStore')->name('plan.store');
                Route::get('plan/edit/{id}', 'planEdit')->name('plan.edit');
                Route::put('plan/update/{id}', 'planUpdate')->name('plan.update');
                Route::get('plan/status/{id}/{status}', 'planStatus')->name('plan.status');
                Route::delete('plan/delete/{id}', 'planDestroy')->name('plan.delete');

                Route::get('transactions', 'transactions')->name('transactions');
                Route::get('transaction/export', 'transactionExport')->name('transaction.export');

                Route::get('additional-setup', 'additionalSetup')->name('additional-setup');
                Route::post('faq/store', 'faqStore')->name('faq.store');
                Route::get('faq/edit/{id}', 'faqEdit')->name('faq.edit');
                Route::put('faq/update/{id}', 'faqUpdate')->name('faq.update');
                Route::get('faq/status/{id}/{status}', 'faqStatus')->name('faq.status');
                Route::delete('faq/delete/{id}', 'faqDestroy')->name('faq.delete');

                Route::get('terms-and-conditions', 'termsSetup')->name('terms-and-conditions');
                Route::post('terms-and-conditions/update', 'termsUpdate')->name('terms-and-conditions.update');
            });
        });

        Route::controller(SearchRoutingController::class)->group(function () {
            Route::post('search-routing', 'index')->name('search.routing');
            Route::get('recent-search', 'recentSearch')->name('recent.search');
            Route::post('store-clicked-route', 'storeClickedRoute')->name('store.clicked.route');
        });

        Route::get('store/get-store-ratings', 'get_store_ratings')->name('store.get-store-ratings');
        Route::prefix('category')->name('category.')->controller(CategoryController::class)->group(function () {
            Route::get('get-all', 'getNameList')->name('get-all');
            Route::middleware(['module:category'])->group(function () {
                Route::get('add', 'index')->name('add');
                Route::get('update/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('update-priority/{category}', 'updatePriority')->name('priority');
                Route::post('add/{position?}', 'add')->name('store');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::get('featured/{id}/{featured}', 'updateFeatured')->name('featured');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export-categories', 'exportList')->name('export-categories');

                Route::get('bulk-import', 'getBulkImportView')->name('bulk-import');
                Route::post('bulk-import', 'importBulkData');
                Route::post('bulk-update', 'updateBulkData')->name('bulk-update');
                Route::get('bulk-export', 'getBulkExportView')->name('bulk-export-index');
                Route::post('bulk-export', 'exportBulkData')->name('bulk-export');
            });
        });

        Route::middleware(['module:category'])->prefix('store-category')->name('store-category.')->controller(StoreCategoryController::class)->group(function () {
            Route::get('list', 'index')->name('list');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::get('status', 'updateStatus')->name('status');
            Route::get('priority/{id}', 'updatePriority')->name('priority');
            Route::delete('delete', 'delete')->name('delete');
            Route::get('by-store', 'getByStore')->name('by-store');
            Route::get('export', 'exportList')->name('export');
        });

        Route::middleware(['module:category'])->prefix('attribute')->name('attribute.')->controller(AttributeController::class)->group(function () {
            Route::get('/', 'index')->name('add-new');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('export', 'exportList')->name('export-attributes');
        });

        Route::middleware(['module:category'])->prefix('unit')->name('unit.')->controller(UnitController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::put('edit/{id}', 'update')->name('update');
            Route::post('search', 'search')->name('search');
            Route::delete('delete/{id}', 'delete')->name('destroy');
            Route::get('export/{type}', 'exportList')->name('export');
        });

        Route::middleware(['module:addon'])->prefix('addon')->name('addon.')->controller(AddonController::class)->group(function () {
            Route::controller(AddonCategoryController::class)->group(function () {
                Route::get('addon-category', 'index')->name('addon-category');
                Route::get('addon-status/{id}', 'status')->name('addon-category-status');
                Route::get('addon-edit/{id}', 'edit')->name('addon-category-edit');
                Route::put('addon-update/{id}', 'update')->name('addon-category-update');
                Route::delete('addon-category/{id}', 'delete')->name('addon-category-delete');
                Route::post('addon-category-store', 'store')->name('addon-category-store');
                Route::get('addon-category-export', 'exportAddonCategories')->name('addon-category-export');
            });

            Route::get('/', 'index')->name('add-new');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('export', 'exportList')->name('export');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');

            Route::get('bulk-import', 'getBulkImportView')->name('bulk-import');
            Route::post('bulk-import', 'importBulkData');
            Route::post('bulk-update', 'updateBulkData')->name('bulk-update');
            Route::get('bulk-export', 'getBulkExportView')->name('bulk-export-index');
            Route::post('bulk-export', 'exportBulkData')->name('bulk-export');
        });

        Route::middleware(['module:banner'])->prefix('banner')->name('banner.')->controller(BannerController::class)->group(function () {
            Route::get('/', 'index')->name('add-new');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get('featured/{id}/{status}', 'updateFeatured')->name('featured');
            Route::post('search', 'getSearchList')->name('search');
        });

        Route::middleware(['module:coupon'])->prefix('coupon')->name('coupon.')->controller(CouponController::class)->group(function () {
            Route::get('/', 'index')->name('add-new');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get('export', 'exportList')->name('coupon_export');
            Route::get('view/{id}', 'viewCoupon')->name('viewCoupon');
            Route::get('generate-check-code', 'generateCheckCode')->name('generate-check-code');
        });

        Route::middleware(['module:coupon'])->prefix('bundle')->name('bundle.')->controller(BundleController::class)->group(function () {
            Route::get('list', 'index')->name('list');
            Route::get('create', 'create')->name('create');
            Route::get('items', 'items')->name('items');
            Route::get('export', 'exportList')->name('export');
            Route::post('store', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::get('view/{id}', 'show')->name('view');
            Route::patch('status/{id}/{status}', 'updateStatus')->name('status');
            Route::delete('delete/{id}', 'destroy')->name('delete');
        });

        Route::middleware(['module:coupon', 'promotion-module'])->prefix('bogo-offer')->name('bogo-offer.')->controller(BogoOfferController::class)->group(function () {
            // Create and list are separate pages, unlike coupon and cashback where the form sits
            // above its own table: a BOGO offer's form is a full page on its own.
            Route::get('/', 'index')->name('add-new');
            Route::get('list', 'getListView')->name('list');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::get('view/{id}', [BogoOfferController::class, 'view'])->name('view');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get('export', 'exportList')->name('export');

            // The enrolment conversation between the admin and a store. add-store and
            // update-enrollment answer JSON: the Add Store drawer submits beside the table and
            // the page never navigates.
            Route::get('store-items', 'getStoreItems')->name('store-items');
            Route::post('add-store/{id}', 'addStore')->name('add-store');
            Route::get('enrollment/{id}/{enrollment}', 'getEnrollmentView')->name('enrollment-detail');
            Route::post('enrollment/{id}/{enrollment}', 'updateEnrollment')->name('update-enrollment');
            Route::post('confirmation/{id}/{enrollment}/{status}', 'updateEnrollmentStatus')->name('store-confirmation');
            Route::delete('remove-store/{id}/{enrollment}', 'removeStore')->name('remove-store');
            Route::get('store-export/{id}', 'exportEnrollmentList')->name('store-export');
        });

        Route::middleware(['module:coupon', 'promotion-module'])->prefix('happy-hour')->name('happy-hour.')->controller(HappyHourController::class)->group(function () {
            Route::get('/', 'index')->name('add-new');
            Route::get('list', 'getListView')->name('list');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::get('view/{id}', [HappyHourController::class, 'view'])->name('view');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get('export', 'exportList')->name('export');

            // The enrolment conversation. Unlike BOGO, joining needs no per-store setup, so the
            // picker is multi-select and add-store takes a batch.
            Route::post('add-store/{id}', 'addStore')->name('add-store');
            Route::post('confirmation/{id}/{enrollment}/{status}', 'updateEnrollmentStatus')->name('store-confirmation');
            Route::delete('remove-store/{id}/{enrollment}', 'removeStore')->name('remove-store');
            Route::get('store-export/{id}', 'exportEnrollmentList')->name('store-export');
        });

        Route::middleware(['module:notification'])->prefix('notification')->name('notification.')->controller(NotificationController::class)->group(function () {
            Route::get('/', 'index')->name('add-new');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get('export', 'exportList')->name('export');
        });

        Route::middleware(['module:category'])->prefix('common-condition')->name('common-condition.')->controller(CommonConditionController::class)->group(function () {
            Route::get('get-all', 'getDropdownList')->name('get-all');
            Route::get('/', 'index')->name('add');
            Route::post('store', 'add')->name('store');
            Route::get('edit/{id}', 'getUpdateView')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::get( 'view/{id}', 'getDetailsView')->name('view');
        });

        Route::middleware(['module:category'])->prefix('brand')->name('brand.')->controller(BrandController::class)->group(function () {
            Route::get('get-all', 'getDropdownList')->name('get-all');
            Route::get('/', 'index')->name('add');
            Route::post('store', 'add')->name('store');
            Route::post('edit/{id}', 'update')->name('update');
            Route::delete('delete/{id}', 'delete')->name('delete');
            Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            Route::post('module-upadte', 'moduleUpadte')->name('moduleUpadte');
            Route::get('get-brand-data', 'getBrandData')->name('getBrandData');
        });

        Route::middleware(['module:coupon'])->prefix('advertisement')->name('advertisement.')->controller(AdvertisementController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('create', 'create')->name('create');
            Route::get('details/{advertisement}', 'show')->name('show');
            Route::get('{advertisement}/edit', 'edit')->name('edit');
            Route::post('store', 'store')->name('store');
            Route::put('update/{advertisement}', 'update')->name('update');
            Route::delete('delete/{id}', 'destroy')->name('destroy');

            Route::get('status', 'status')->name('status');
            Route::get('paidStatus', 'paidStatus')->name('paidStatus');
            Route::get('priority', 'priority')->name('priority');
            Route::get('requests', 'requestList')->name('requestList');
            Route::get('copy-advertisement/{advertisement}', 'copyAdd')->name('copyAdd');
            Route::get('updateDate/{advertisement}', 'updateDate')->name('updateDate');
            Route::post('copy-add-post/{advertisement}', 'copyAddPost')->name('copyAddPost');
        });

        Route::prefix('business-settings')->name('business-settings.')->group(function () {
            Route::middleware(['module:subscription'])->prefix('subscription')->controller(SubscriptionController::class)->group(function () {
                Route::get('settings', 'settings')->name('subscriptionackage.settings');
                Route::post('setting-update', 'settingUpdate')->name('subscriptionackage.settingUpdate');
            });

            Route::middleware(['module:subscription'])->prefix('subscription')->controller(SubscriptionController::class)->group(function () {
                Route::resource('subscriptionackage', SubscriptionController::class);
                Route::get('status/{subscriptionackage}', 'statusChange')->name('subscriptionackage.status');
                Route::get('overView/{subscriptionackage}', 'overView')->name('subscriptionackage.overView');
                Route::get('transaction/{subscriptionackage}', 'transaction')->name('subscriptionackage.transaction');
                Route::get('trial-status', 'trialStatus')->name('subscriptionackage.trialStatus');
                Route::get('invoice/{id}', 'invoice')->name('subscriptionackage.invoice');
                Route::post('switch-plan', 'switchPlan')->name('subscriptionackage.switchPlan');
                Route::get('package-export', 'packageExport')->name('subscriptionackage.packageExport');
                Route::get('transaction-export', 'TransactionExport')->name('subscriptionackage.TransactionExport');

                Route::get('subscriber-list', 'subscriberList')->name('subscriptionackage.subscriberList');
                Route::get('subscriber-list-export', 'subscriberListExport')->name('subscriptionackage.subscriberListExport');
                Route::get('subscriber-transaction-export', 'subscriberTransactionExport')->name('subscriptionackage.subscriberTransactionExport');
                Route::post('cancel-subscription/{id}', 'cancelSubscription')->name('subscriptionackage.cancelSubscription');
                Route::post('switch-to-commission/{id}', 'switchToCommission')->name('subscriptionackage.switchToCommission');
                Route::get('subscriber-detail/{id}', 'subscriberDetail')->name('subscriptionackage.subscriberDetail');
                Route::get('package-view/{id}/{store_id}', 'packageView')->name('subscriptionackage.packageView');
                Route::get('subscriber-transactions/{id}', 'subscriberTransactions')->name('subscriptionackage.subscriberTransactions');
                Route::get('subscriber-wallet-transactions/{id}', 'subscriberWalletTransactions')->name('subscriptionackage.subscriberWalletTransactions');

                Route::post('package-buy', 'packageBuy')->name('subscriptionackage.packageBuy');
            });

            Route::middleware(['module:settings'])->prefix('zone')->name('zone.')->controller(ZoneController::class)->group(function () {
                Route::get('/', 'index')->name('home');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('edit/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('export/{type}', 'exportList')->name('export');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                // Fresh readiness for one zone, read right before the status toggle decides which
                // dialog to show — the row's own data-* attributes are a snapshot from whenever it
                // was last rendered, and can go stale sitting in an open tab while a delivery rule
                // or ETA configuration is added elsewhere. This is the same computation the page
                // load and updateStatus() both already trust; nothing here can say something they
                // wouldn't.
                Route::get('readiness/{id}', 'readiness')->name('readiness');
                Route::get('zone-filter/{id}', 'zoneFilter')->name('zone-filter');
                // Connect Module — the drawer the zone list opens. GET returns its markup, POST
                // saves. The full-page `module-setup` below stays for the delivery options it is
                // still the only home for; the drawer deliberately carries no pricing.
                Route::get('connect-module/{id}', 'connectModuleView')->name('connect-module');
                Route::post('connect-module/{id}', 'connectModule')->name('connect-module.save');
                Route::get('module-setup', 'getLatestModuleSetupView')->name('go-module-setup');
                Route::get('module-setup/{id?}', 'getModuleSetupView')->name('module-setup');
                Route::post('module-update/{id}', 'updateModuleSetup')->name('module-update');
                Route::get('instruction', 'getInstruction')->name('instruction');
                Route::get('digital-payment/{id}/{digital_payment}', 'updateDigitalPayment')->name('digital-payment');
                Route::get('cash-on-delivery/{id}/{cash_on_delivery}', 'updateCashOnDelivery')->name('cash-on-delivery');
                Route::get('offline-payment/{id}/{offline_payment}', 'updateOfflinePayment')->name('offline-payment');
                Route::get('default-status/{id}', 'defaultStatus')->name('default-status');

                Route::middleware(['module:settings'])->prefix('smart-banner')->name('smart-banner.')->controller(SmartBannerController::class)->group(function () {
                    Route::get('categories/{module_id}', 'categoriesByModule')->name('categories');
                    Route::get('stores/{module_id}/{zone_id}', 'storesByModuleZone')->name('stores');
                    Route::get('view/{id}', [SmartBannerController::class, 'view'])->name('view');
                    Route::get('edit/{id}', 'edit')->name('edit');
                    Route::post('store/{zone_id}', 'store')->name('store');
                    Route::post('update/{id}', 'update')->name('update');
                    Route::get('status/{id}/{status}', 'status')->name('status');
                    Route::delete('delete/{id}', 'destroy')->name('delete');
                    Route::get('{zone_id}', 'index')->name('list');
                });
            });

            Route::middleware(['module:module'])->prefix('module')->name('module.')->controller(ModuleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('store', 'getAddView')->name('create');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::put('edit/{id}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                Route::get('type', 'getType')->name('type');
                Route::post('search', 'search')->name('search');
                Route::get('export', 'exportList')->name('export');
                Route::get('show/{id}', 'show')->name('show')->withoutMiddleware('module:module');
            });
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::middleware(['module:employee'])->prefix('custom-role')->name('custom-role.')->controller(CustomRoleController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('create', 'getAddView')->name('create');
                Route::post('create', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::post('search', 'search')->name('search');
                Route::get('view/{id}', [CustomRoleController::class, 'view'])->name('view');
            });

            Route::middleware(['module:employee'])->prefix('employee')->name('employee.')->controller(EmployeeController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('store', 'getAddView')->name('add-new');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('edit/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::post('search', 'getSearchList')->name('search');
                Route::get('export', 'exportList')->name('export');
            });

            Route::prefix('customer')->name('customer.')->controller(WalletBonusController::class)->group(function () {
                Route::middleware(['module:customer_wallet'])->prefix('wallet')->name('wallet.')->group(function () {
                    Route::prefix('bonus')->name('bonus.')->group(function () {
                        Route::get('/', 'index')->name('add-new');
                        Route::post('store', 'add')->name('store');
                        Route::get('edit/{id}', 'getUpdateView')->name('edit');
                        Route::post('edit/{id}', 'update')->name('update');
                        Route::delete('delete/{id}', 'delete')->name('delete');
                        Route::post('status/{id}', 'updateStatus')->name('status');
                    });
                });
            });

            Route::middleware(['module:cashback'])->prefix('cashback')->name('cashback.')->controller(CashBackController::class)->group(function () {
                Route::get('/', 'index')->name('add-new');
                Route::post('store', 'add')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('edit/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('status/{id}/{status}', 'updateStatus')->name('status');
            });


            Route::prefix('delivery-man')->name('delivery-man.')->controller(DeliveryManController::class)->group(function () {
                Route::get('get-deliverymen', 'getDropdownList')->name('get-deliverymen');
                Route::get('get-account-data/{id}', 'getAccountData')->name('store-filter');

                Route::middleware(['module:deliveryman'])->controller(DeliveryManController::class)->group(function () {
                    Route::get('add', 'getAddView')->name('add');
                    Route::post('add', 'add')->name('store');
                    Route::get('/', 'index')->name('list');
                    Route::get('new', 'getNewDeliveryManView')->name('new');
                    Route::get('deny', 'getDeniedDeliveryManView')->name('deny');
                    Route::get('preview/{id}/{tab?}', 'getPreview')->name('preview');
                    Route::get('status/{id}/{status}', 'updateStatus')->name('status');
                    Route::get('earning/{id}/{status}', 'updateEarning')->name('earning');
                    Route::get('update-application/{id}/{status}', 'updateApplication')->name('application');
                    Route::get('edit/{id}', 'getUpdateView')->name('edit');
                    Route::post('edit/{id}', 'update')->name('update');
                    Route::delete('delete/{id}', 'delete')->name('delete');
                    Route::post('search', 'getSearchList')->name('search');
                    Route::post('active-search', 'getActiveSearchList')->name('active-search');
                    Route::get('export', 'exportList')->name('export');
                    Route::get('earning-export', 'getEarningListExport')->name('earning-export');
                    Route::get('review-export', 'getReviewExportList')->name('review-export');
                    Route::get('loyalty-point-export', 'getLoyaltyPointExportList')->name('loyalty-point-export');
                    Route::get('referral-export', 'getReferralEarnExportList')->name('referral-export');
                    Route::get('disbursement-export/{id}/{type}', 'disbursement_export')->name('disbursement-export');

                    Route::get('message/{conversation_id}/{user_id}', 'getConversationView')->name('message-view');
                    Route::get('message/details', 'getConversationList')->name('message-list-search');
                });

                Route::middleware(['module:deliveryman'])->prefix('reviews')->name('reviews.')->controller(DeliveryManController::class)->group(function () {
                    Route::get('/', 'getReviewListView')->name('list');
                    Route::post('search', 'getReviewSearchList')->name('search');
                    Route::get('status/{id}/{status}', 'updateReviewStatus')->name('status');
                    Route::get('export', 'getAllReviewExportList')->name('export');
                });

                // Vehicles Category moved to Delivery Management on 2026-09-08, and to
                // admin/delivery-management/vehicle-category on 2026-09-09. Nothing is left here.
            });
        });
    });
});
