<?php

use App\Http\Controllers\Vendor\Promotion;
use App\Http\Controllers\Vendor\{
    AddOnController,
    AdvertisementController,
    BannerController,
    BusinessSettingsController,
    CampaignController,
    CategoryController,
    ConversationController,
    CouponController,
    CustomRoleController,
    DashboardController,
    DeliveryManController,
    EmployeeController,
    ItemController,
    LanguageController,
    OrderController,
    POSController,
    ProfileController,
    ReportController,
    RestaurantController,
    ReviewController,
    SearchRoutingController,
    StoreCategoryController,
    StoreEarningReportController,
    SubscriptionController,
    VendorTaxReportController,
    WalletController,
    WalletMethodController,
};
use Illuminate\Support\Facades\Route;


Route::name('vendor.')->group(function () {
    Route::middleware(['vendor', 'maintenance', 'actch:admin_panel'])->group(function () {
        Route::controller(SearchRoutingController::class)->group(function () {
            Route::post('search-routing', 'index')->name('search.routing');
            Route::get('recent-search', 'recentSearch')->name('recent.search');
            Route::post('store-clicked-route', 'storeClickedRoute')->name('store.clicked.route');
        });
        Route::controller(LanguageController::class)->group(function () {
            Route::get('lang/{locale}', [LanguageController::class, 'lang'])->name('lang');
        });
        Route::controller(DashboardController::class)->group(function () {
            Route::get('/', 'dashboard')->name('dashboard');
            Route::get('/get-store-data', 'store_data')->name('get-store-data');
            Route::post('/store-token', 'updateDeviceToken')->name('store.token');
            Route::post('/verified-badge-popup-seen', 'verifiedBadgePopupSeen')->name('verified-badge-popup-seen');
        });
        Route::middleware(['module:reviews' ,'subscription:reviews'])->group(function () {
            Route::controller(ReviewController::class)->group(function () {
                Route::get('/reviews', 'index')->name('reviews');
                Route::get('/reviews_export', 'reviewsExport')->name('reviewsExport');
                Route::post('/store-reply/{id}', 'update_reply')->name('review-reply');
            });
        });
        Route::controller(BusinessSettingsController::class)->group(function () {
            Route::get('site_direction', 'site_direction_vendor')->name('site_direction');
        });
        Route::prefix('pos')->name('pos.')->group(function () {
            Route::middleware(['module:pos','subscription:pos' ])->group(function () {
                Route::controller(POSController::class)->group(function () {
                    Route::post('variant_price', 'variant_price')->name('variant_price');
                    Route::get('/', 'index')->name('index');
                    Route::get('quick-view', 'quick_view')->name('quick-view');
                    Route::post('item-stock-view', 'item_stock_view')->name('item_stock_view');
                    Route::post('item-stock-view-update', 'item_stock_view_update')->name('item_stock_view_update');
                    Route::get('quick-view-cart-item', 'quick_view_card_item')->name('quick-view-cart-item');
                    Route::post('add-to-cart', 'addToCart')->name('add-to-cart');
                    Route::post('add-delivery-info', 'addDeliveryInfo')->name('add-delivery-info');
                    Route::post('remove-from-cart', 'removeFromCart')->name('remove-from-cart');
                    Route::post('cart-items', 'cart_items')->name('cart_items');
                    Route::post('single-items', 'single_items')->name('single_items');
                    Route::post('update-quantity', 'updateQuantity')->name('updateQuantity');
                    Route::post('empty-cart', 'emptyCart')->name('emptyCart');
                    Route::post('tax', 'update_tax')->name('tax');
                    Route::post('paid', 'update_paid')->name('paid');
                    Route::post('discount', 'update_discount')->name('discount');
                    Route::get('customers', 'get_customers')->name('customers');
                    Route::post('order', 'place_order')->name('order');
                    Route::post('customer-store', 'customer_store')->name('customer-store');
                    Route::get('data', 'extra_charge')->name('extra_charge');
                    Route::get('delivery-coverage', 'getDeliveryCoverage')->name('delivery_coverage');
                    Route::get('get-user-data', 'getUserData')->name('getUserData');
                });
                Route::prefix('delivery-type')->name('delivery_type.')->group(function () {
                    Route::controller(POSController::class)->group(function () {
                        Route::get('get', 'getDeliveryTypes')->name('get');
                        Route::post('set', 'setDeliveryType')->name('set');
                    });
                });
            });
        });
        Route::middleware(['module:business_plan'])->prefix('subscription')->name('subscriptionackage.')->group(function () {
            Route::controller(SubscriptionController::class)->group(function () {
                Route::get('/subscriber-detail', 'subscriberDetail')->name('subscriberDetail');
                Route::get('/invoice/{id}', 'invoice')->name('invoice');
                Route::post('/cancel-subscription/{id}', 'cancelSubscription')->name('cancelSubscription');
                Route::post('/switch-to-commission/{id}', 'switchToCommission')->name('switchToCommission');
                Route::get('/package-view/{id}/{store_id}', 'packageView')->name('packageView');
                Route::get('/subscriber-transactions/{id}', 'subscriberTransactions')->name('subscriberTransactions');
                Route::get('/subscriber-transaction-export', 'subscriberTransactionExport')->name('subscriberTransactionExport');
                Route::get('/subscriber-wallet-transactions', 'subscriberWalletTransactions')->name('subscriberWalletTransactions');
                Route::post('/package-buy', 'packageBuy')->name('packageBuy');
                Route::post('/add-to-session', 'addToSession')->name('addToSession');
            });
        });
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::controller(DashboardController::class)->group(function () {
                Route::post('order-stats', 'order_stats')->name('order-stats');
            });
        });
        Route::middleware(['module:category','subscription:category'])->prefix('category')->name('category.')->group(function () {
            Route::controller(CategoryController::class)->group(function () {
                Route::get('get-all', 'get_all')->name('get-all');
                Route::get('list', 'index')->name('add');
                Route::get('sub-category-list', 'sub_index')->name('add-sub-category');
                Route::get('export-categories', 'export_categories')->name('export-categories');
                Route::get('export-sub-categories', 'export_sub_categories')->name('export-sub-categories');
            });
        });
        Route::middleware(['module:my_category'])->prefix('store-category')->name('store-category.')->group(function () {
            Route::controller(StoreCategoryController::class)->group(function () {
                Route::get('list', 'index')->name('list');
                Route::get('create', 'create')->name('create');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'getUpdateView')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('status', 'updateStatus')->name('status');
                Route::get('priority/{id}', 'updatePriority')->name('priority');
                Route::delete('delete', 'delete')->name('delete');
                Route::get('get-all', 'getAll')->name('get-all');
                Route::get('export', 'exportList')->name('export');
                Route::get('items/{id}', 'assignItemsView')->name('items');
                Route::get('items/{id}/search', 'searchAssignableItems')->name('items.search');
                Route::post('items/{id}/assign', 'storeAssignedItems')->name('items.assign');
            });
        });
        Route::middleware(['module:role' ,'subscription:role'])->prefix('custom-role')->name('custom-role.')->group(function () {
            Route::controller(CustomRoleController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('create', 'create')->name('create');
                Route::post('create', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('update/{id}', 'update')->name('update-role');
                Route::delete('delete/{id}', 'distroy')->name('delete');
                Route::get('view/{id}', [CustomRoleController::class, 'view'])->name('view');
            });
        });
        Route::prefix('delivery-man')->name('delivery-man.')->group(function () {
            Route::middleware(['module:deliveryman' ,'subscription:deliveryman'])->group(function () {
                Route::controller(DeliveryManController::class)->group(function () {
                    Route::get('add', 'index')->name('add');
                    Route::post('store', 'store')->name('store');
                });
            });
            Route::middleware(['module:deliveryman_list' ,'subscription:deliveryman_list'])->group(function () {
                Route::controller(DeliveryManController::class)->group(function () {
                    Route::get('preview/{id}/{tab?}', 'preview')->name('preview');
                    Route::get('list', 'list')->name('list');
                });
                Route::prefix('reviews')->name('reviews.')->group(function () {
                    Route::controller(DeliveryManController::class)->group(function () {
                        Route::get('list', 'reviews_list')->name('list');
                    });
                });
                Route::controller(DeliveryManController::class)->group(function () {
                    Route::get('status/{id}/{status}', 'status')->name('status');
                    Route::get('earning/{id}/{status}', 'earning')->name('earning');
                    Route::get('edit/{id}', 'edit')->name('edit');
                    Route::post('update/{id}', 'update')->name('update');
                    Route::delete('delete/{id}', 'delete')->name('delete');
                    Route::get('get-deliverymen', 'get_deliverymen')->name('get-deliverymen');
                    Route::post('transation/search', 'transaction_search')->name('transaction-search');
                });
            });
        });
        Route::middleware(['module:employee' ,'subscription:employee'])->prefix('employee')->name('employee.')->group(function () {
            Route::controller(EmployeeController::class)->group(function () {
                Route::get('add-new', 'add_new')->name('add-new');
                Route::post('add-new', 'store');
                Route::get('list', 'list')->name('list');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'distroy')->name('delete');
                Route::get('list-export', 'list_export')->name('export-employee');
            });
        });
        Route::middleware(['module:item' ,'subscription:item'])->prefix('item')->name('item.')->group(function () {
            Route::controller(ItemController::class)->group(function () {
                Route::get('add-new', 'index')->name('add-new');
                Route::post('variant-combination', 'variant_combination')->name('variant-combination');
                Route::post('variant-price', 'variant_price')->name('variant-price');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::get('list', 'list')->name('list');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('status/{id}/{status}', 'status')->name('status');
                Route::post('search', 'search')->name('search');
                Route::get('view/{id}', [ItemController::class, 'view'])->name('view');
                Route::get('remove-image', 'remove_image')->name('remove-image');
                Route::get('get-categories', 'get_categories')->name('get-categories');
                Route::get('recommended/{id}/{status}', 'recommended')->name('recommended');
                Route::get('pending/item/list', 'pending_item_list')->name('pending_item_list');
                Route::get('requested/item/view/{id}', 'requested_item_view')->name('requested_item_view');
                Route::get('product-gallery', 'product_gallery')->name('product_gallery');
                Route::get('item-view/{id}', 'gallery_item_view')->name('item-view');
                Route::get('get-variations', 'get_variations')->name('get-variations');
                Route::get('stock-limit-list', 'stock_limit_list')->name('stock-limit-list');
                Route::get('get-stock', 'get_stock')->name('get_stock');
                Route::post('stock-update', 'stock_update')->name('stock-update');
                Route::post('food-variation-generate', 'food_variation_generator')->name('food-variation-generate');
                Route::post('variation-generate', 'variation_generator')->name('variation-generate');
                Route::get('bulk-import', 'bulk_import_index')->name('bulk-import');
                Route::post('bulk-import', 'bulk_import_data');
                Route::get('bulk-export', 'bulk_export_index')->name('bulk-export-index');
                Route::post('bulk-export', 'bulk_export_data')->name('bulk-export');
                Route::get('get-brand-list', 'getBrandList')->name('getBrandList');
            });
        });
        // Flash sale is its own permission unit (grocery/ecommerce only) -- see App\Navigation\VendorNav
        Route::middleware(['module:flash_sale' ,'subscription:item'])->prefix('item')->name('item.')->group(function () {
            Route::controller(ItemController::class)->group(function () {
                Route::get('flash-sale', 'flash_sale')->name('flash_sale');
            });
        });
        Route::middleware(['module:banner','subscription:banner'])->prefix('banner')->name('banner.')->group(function () {
            Route::controller(BannerController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{banner}', 'edit')->name('edit');
                Route::post('update/{banner}', 'update')->name('update');
                Route::get('status/{id}/{status}', 'status_update')->name('status_update');
                Route::delete('delete/{banner}', 'delete')->name('delete');
                Route::get('join_campaign/{id}/{status}', 'status')->name('status');
            });
        });
        Route::middleware(['module:campaign','subscription:campaign'])->prefix('campaign')->name('campaign.')->group(function () {
            Route::controller(CampaignController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::get('item/list', 'itemlist')->name('itemlist');
                Route::get('remove-store/{campaign}/{store}', 'remove_store')->name('remove-store');
                Route::get('add-store/{campaign}/{store}', 'addstore')->name('add-store');
                Route::post('search-item', 'searchItem')->name('searchItem');
            });
        });
        Route::middleware(['module:wallet' ,'subscription:wallet'])->prefix('wallet')->name('wallet.')->group(function () {
            Route::controller(WalletController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('request', 'w_request')->name('withdraw-request');
                Route::delete('close/{id}', 'close_request')->name('close-request');
                Route::get('method-list', 'method_list')->name('method-list');
                Route::post('make-collected-cash-payment', 'make_payment')->name('wallet_make_payment');
                Route::post('make-wallet-adjustment', 'make_wallet_adjustment')->name('make_wallet_adjustment');
                Route::get('wallet-payment-list', 'wallet_payment_list')->name('wallet_payment_list');
                Route::get('disbursement-list', 'getDisbursementList')->name('getDisbursementList');
                Route::get('export', 'getDisbursementExport')->name('export');
            });
        });
        Route::middleware(['module:wallet_method' ,'subscription:wallet_method' ])->prefix('withdraw-method')->name('wallet-method.')->group(function () {
            Route::controller(WalletMethodController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('store/', 'store')->name('store');
                Route::get('default/{id}/{default}', 'default')->name('default');
                Route::delete('delete/{id}', 'delete')->name('delete');
            });
        });
        // Gated on 'coupon' like the admin sidebar entries -- adding a permission key would leave
        // every existing role without it -- plus the module-type capability, since neither feature
        // exists outside grocery, food, pharmacy and ecommerce.
        Route::middleware(['module:coupon', 'promotion-module'])->prefix('bogo-offer')->name('bogo-offer.')
            ->controller(Promotion\BogoOfferController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('detail/{id}', 'getDetailView')->name('detail');
                Route::get('store-items', 'getStoreItems')->name('store-items');
                Route::post('join/{id}', 'join')->name('join');
                Route::post('resubmit/{id}', 'resubmit')->name('resubmit');
                Route::post('respond/{id}/{status}', 'respond')->name('respond');
                Route::delete('leave/{id}', 'leave')->name('leave');
            });

        Route::middleware(['module:coupon'])->prefix('bundle')->name('bundle.')->controller(Promotion\BundleController::class)->group(function () {
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

        Route::middleware(['module:coupon', 'promotion-module'])->prefix('happy-hour')->name('happy-hour.')
            ->controller(Promotion\HappyHourController::class)->group(function () {
                Route::get('/', 'index')->name('list');
                Route::get('detail/{id}', 'getDetailView')->name('detail');
                Route::post('join/{id}', 'join')->name('join');
                Route::post('respond/{id}/{status}', 'respond')->name('respond');
                Route::delete('leave/{id}', 'leave')->name('leave');
            });

        Route::middleware(['module:coupon','subscription:coupon'])->prefix('coupon')->name('coupon.')->group(function () {
            Route::controller(CouponController::class)->group(function () {
                Route::get('add-new', 'add_new')->name('add-new');
                Route::post('store', 'store')->name('store');
                Route::get('update/{id}', 'edit')->name('update');
                Route::post('update/{id}', 'update');
                Route::get('status/{id}/{status}', 'status')->name('status');
                Route::delete('delete/{id}', 'delete')->name('delete');
                Route::get('view/{id}', 'viewCoupon')->name('viewCoupon');
                Route::get('generate-check-code', 'generateCheckCode')->name('generate-check-code');
            });
        });
        Route::prefix('advertisement')->name('advertisement.')->group(function () {
            Route::middleware(['module:advertisement' ,'subscription:advertisement'])->group(function () {
                Route::controller(AdvertisementController::class)->group(function () {
                    Route::get('create/', 'create')->name('create');
                    Route::get('/copy-advertisement/{advertisement}', 'copyAdd')->name('copyAdd');
                    Route::post('/copy-add-post/{advertisement}', 'copyAddPost')->name('copyAddPost');
                    Route::post('store', 'store')->name('store');
                });
            });
            Route::middleware(['module:advertisement_list' ,'subscription:advertisement_list'])->group(function () {
                Route::controller(AdvertisementController::class)->group(function () {
                    Route::get('/', 'index')->name('index');
                    Route::get('details/{advertisement}', 'show')->name('show');
                    Route::get('{advertisement}/edit', 'edit')->name('edit');
                    Route::put('update/{advertisement}', 'update')->name('update');
                    Route::delete('delete/{id}', 'destroy')->name('destroy');
                    Route::get('/status', 'status')->name('status');
                });
            });
        });
        Route::middleware(['module:addon','subscription:addon'])->prefix('addon')->name('addon.')->group(function () {
            Route::controller(AddOnController::class)->group(function () {
                Route::get('add-new', 'index')->name('add-new');
                Route::post('store', 'store')->name('store');
                Route::get('edit/{id}', 'edit')->name('edit');
                Route::post('update/{id}', 'update')->name('update');
                Route::delete('delete/{id}', 'delete')->name('delete');
            });
        });
        Route::middleware(['module:order'])->prefix('order')->name('order.')->group(function () {
            Route::controller(OrderController::class)->group(function () {
                Route::get('list/{status}', 'list')->name('list');
                Route::put('status-update/{id}', 'status')->name('status-update');
                Route::post('add-to-cart', 'add_to_cart')->name('add-to-cart');
                Route::post('remove-from-cart', 'remove_from_cart')->name('remove-from-cart');
                Route::post('update-cart-quantity', 'update_cart_quantity')->name('update-cart-quantity');
                Route::get('cart-list', 'cart_list')->name('cart-list');
                Route::get('search-items', 'search_items')->name('search-items');
                Route::post('update/{order}', 'update')->name('update');
                Route::get('edit-order/{order}', 'edit')->name('edit');
                Route::get('details/{id}', 'details')->name('details');
                Route::get('status', 'status')->name('status');
                Route::get('quick-view', 'quick_view')->name('quick-view');
                Route::get('quick-view-cart-item', 'quick_view_cart_item')->name('quick-view-cart-item');
                Route::get('generate-invoice/{id}', 'generate_invoice')->name('generate-invoice');
                Route::post('add-payment-ref-code/{id}', 'add_payment_ref_code')->name('add-payment-ref-code');
                Route::post('update-order-amount', 'edit_order_amount')->name('update-order-amount');
                Route::post('update-discount-amount', 'edit_discount_amount')->name('update-discount-amount');
                Route::post('add-order-proof/{id}', 'add_order_proof')->name('add-order-proof');
                Route::get('remove-proof-image', 'remove_proof_image')->name('remove-proof-image');
                Route::get('export-orders/{file_type}/{status}/{type}', 'export_orders')->name('export');
            });
        });
        Route::prefix('business-settings')->name('business-settings.')->group(function () {
            Route::middleware(['module:store_setup' ,'subscription:store_setup'])->group(function () {
                Route::controller(BusinessSettingsController::class)->group(function () {
                    Route::get('store-setup', 'store_index')->name('store-setup');
                    Route::post('add-schedule', 'add_schedule')->name('add-schedule');
                    Route::get('remove-schedule/{store_schedule}', 'remove_schedule')->name('remove-schedule');
                    Route::get('update-active-status', 'active_status')->name('update-active-status');
                    Route::post('update-setup/{store}', 'store_setup')->name('update-setup');
                    Route::post('update-stock-setup/{store}', 'stock_setup')->name('update-stock-setup');
                    Route::post('update-meta-data/{store}', 'updateStoreMetaData')->name('update-meta-data');
                    Route::get('toggle-settings-status/{store}/{status}/{menu}', 'store_status')->name('toggle-settings');
                    Route::get('website-builder-status/{store}/{status}', 'website_builder_status')->name('website-builder-status');
                });
            });
            Route::middleware(['module:notification_setup' ,'subscription:notification_setup'])->group(function () {
                Route::controller(BusinessSettingsController::class)->group(function () {
                    Route::get('notification-setup', 'notification_index')->name('notification-setup');
                    Route::get('notification-status-change/{key}/{type}', 'notification_status_change')->name('notification_status_change');
                });
            });
        });
        Route::middleware(['module:profile' ,'subscription:profile'])->prefix('profile')->name('profile.')->group(function () {
            Route::controller(ProfileController::class)->group(function () {
                Route::get('view', [ProfileController::class, 'view'])->name('view');
                Route::post('update', 'update')->name('update');
                Route::post('settings-password', 'settings_password_update')->name('settings-password');
            });
        });
        Route::middleware(['module:my_shop' ,'subscription:my_shop'])->prefix('store')->name('shop.')->group(function () {
            Route::controller(RestaurantController::class)->group(function () {
                Route::get('view', [RestaurantController::class, 'view'])->name('view');
                Route::get('edit', 'edit')->name('edit');
                Route::post('update', 'update')->name('update');
                Route::post('update-message', 'update_message')->name('update-message');
            });
        });
        Route::middleware(['module:chat','subscription:chat'])->prefix('message')->name('message.')->group(function () {
            Route::controller(ConversationController::class)->group(function () {
                Route::get('list', 'list')->name('list');
                Route::post('store/{user_id}/{user_type}', 'store')->name('store');
                Route::get('view/{conversation_id}/{user_id}', [ConversationController::class, 'view'])->name('view');
            });
        });
        Route::prefix('report')->name('report.')->group(function () {
            Route::controller(ReportController::class)->group(function () {
                Route::post('set-date', 'set_date')->name('set-date');
            });
            Route::middleware(['module:store_earning_report' ,'subscription:expense_report'])->group(function () {
                Route::controller(StoreEarningReportController::class)->group(function () {
                    Route::get('store-earning-report', 'getStoreEarningReport')->name('store-earning-report');
                    Route::get('store-earning-summary', 'getStoreEarningSummary')->name('store-earning-summary');
                    Route::get('store-earning-breakdown', 'getStoreEarningBreakdown')->name('store-earning-breakdown');
                    Route::get('store-expense-breakdown', 'getStoreExpenseBreakdown')->name('store-expense-breakdown');
                    Route::get('store-earning-trend', 'getStoreEarningTrend')->name('store-earning-trend');
                    Route::get('store-earning-transactions', 'getStoreEarningTransactions')->name('store-earning-transactions');
                    Route::get('store-earning-export', 'exportStoreEarningTransactions')->name('store-earning-export');
                });
            });
            Route::middleware(['module:expense_report' ,'subscription:expense_report'])->group(function () {
                Route::controller(ReportController::class)->group(function () {
                    Route::get('expense-report', 'expense_report')->name('expense-report');
                    Route::get('expense-export', 'expense_export')->name('expense-export');
                });
            });
            Route::middleware(['module:disbursement_report' ,'subscription:disbursement_report'])->group(function () {
                Route::controller(ReportController::class)->group(function () {
                    Route::get('disbursement-report', 'disbursement_report')->name('disbursement-report');
                    Route::get('disbursement-report-export/{type}', 'disbursement_report_export')->name('disbursement-report-export');
                });
            });
            Route::middleware(['module:vat_report' ,'subscription:vat_report'])->group(function () {
                Route::controller(VendorTaxReportController::class)->group(function () {
                    Route::get('vendor-tax-report', 'vendorTax')->name('vendorTax');
                    Route::get('vendor-tax-export', 'vendorTaxExport')->name('vendorTaxExport');
                });
            });
        });
    });
});
