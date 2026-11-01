<?php

use App\Http\Controllers\Api\V1\Common\Delivery\CoverageController as DeliveryCoverageController;
use App\Http\Controllers\Api\V1\Customer\DeliveryMan\ReviewController as DeliveryManReviewController;
use App\Http\Controllers\Api\V1\Erp\ErpDeliveryManController;
use App\Http\Controllers\Api\V1\Erp\ErpRefundController;
use App\Http\Controllers\Api\V1\Erp\ErpStoreController;
use App\Http\Controllers\Api\V1\Vendor\DeliveryMan\DeliveryManController as VendorDeliveryManController;
use App\Http\Controllers\Api\V1\DeliveryMan\Disbursement\{
    DisbursementController,
    DisbursementMethodController,
    WithdrawRequestController,
};
use App\Http\Controllers\Api\V1\DeliveryMan\Notification\NotificationController as DeliveryManNotificationController;
use App\Http\Controllers\Api\V1\DeliveryMan\Order\OrderController as DeliveryManOrderController;
use App\Http\Controllers\Api\V1\DeliveryMan\Profile\{
    LocationController,
    ProfileController as DeliveryManProfileController,
};
use App\Http\Controllers\Api\V1\DeliveryMan\Report\EarningReportController;
use App\Http\Controllers\Api\V1\DeliveryMan\Wallet\WalletController as DeliveryManWalletController;
use App\Http\Controllers\Api\V1\DeliveryMan\Auth\PasswordResetController as DeliveryManPasswordResetController;
use App\Http\Controllers\Api\V1\Customer\Auth\PasswordResetController;
use App\Http\Controllers\Api\V1\Vendor\Auth\PasswordResetController as VendorPasswordResetController;
use App\Http\Controllers\Api\V1\DeliveryMan\Auth\LoginController as DeliveryManLoginController;
use App\Http\Controllers\Api\V1\Vendor\Auth\LoginController as VendorLoginController;
use App\Http\Controllers\Api\V1\Common\DeliveryMan\VehicleController;
use App\Http\Controllers\Api\V1\Common\Item\{
    AddonCategoryController,
    BrandController,
    CommonConditionController,
    NameListController as ItemNameListController,
    ReviewController as ItemReviewController,
};
use App\Http\Controllers\Api\V1\Common\Marketing\LandingPageController;
use App\Http\Controllers\Api\V1\Common\Module\ModuleController;
use App\Http\Controllers\Api\V1\Common\ProCustomer\{
    FaqController as ProCustomerFaqController,
    PlanController as ProCustomerPlanController,
    TermsController as ProCustomerTermsController,
};
use App\Http\Controllers\Api\V1\Common\Parcel\{
    CancellationReasonController,
    DimensionController as ParcelDimensionController,
    ParcelCategoryController,
    WeightController as ParcelWeightController,
};
use App\Http\Controllers\Api\V1\Common\System\{
    ConfigController as SystemConfigController,
    HomeController,
    MapController,
    PaymentMethodController,
};
use App\Http\Controllers\Api\V1\Common\Zone\ZoneController;
use App\Http\Controllers\Api\V1\Customer\Auth\{
    AuthController as CustomerAuthController,
    GuestController as CustomerGuestController,
    OtpController as CustomerOtpController,
};
use App\Http\Controllers\Api\V1\Customer\Promotion\BundleController as CustomerBundleController;
use App\Http\Controllers\Api\V1\Customer\Cart\CartController;
use App\Http\Controllers\Api\V1\Customer\Chat\{
    AutomatedMessageController,
    ConversationController,
};
use App\Http\Controllers\Api\V1\Customer\Search\SearchController;
use App\Http\Controllers\Api\V1\Customer\Store\StoreController;
use App\Http\Controllers\Api\V1\Customer\Item\{
    CategoryController,
    ItemController,
    StoreCategoryController,
    SuggestedItemController,
};
use App\Http\Controllers\Api\V1\Customer\LoyaltyPoint\TransactionController as LoyaltyPointTransactionController;
use App\Http\Controllers\Api\V1\Customer\Notification\NotificationController;
use App\Http\Controllers\Api\V1\Customer\Order\{
    CancellationReasonController as OrderCancellationReasonController,
    MonthlySubscriptionController,
    OrderController,
    OrderPaymentController,
    OrderPlacementController,
    ParcelInstructionController,
    PaymentFailedController,
    RefundController,
    ReorderController,
    ReviewReminderController,
};
use App\Http\Controllers\Api\V1\Customer\ProCustomer\SubscriptionController as ProCustomerSubscriptionController;
use App\Http\Controllers\Api\V1\Customer\Profile\{
    AddressController,
    ProfileController,
    SavedFileController,
};
use App\Http\Controllers\Api\V1\Customer\Promotion\{
    AdvertisementController,
    BannerController,
    BogoOfferController,
    CampaignController,
    CashBackController,
    CouponController,
    FlashSaleController,
    HappyHourController,
    ItemCampaignController,
    ModuleBannerController,
    SmartBannerController,
    WhyChooseController,
};
use App\Http\Controllers\Api\V1\Customer\Wallet\{
    BonusController as WalletBonusController,
    FundController as WalletFundController,
    TransactionController as WalletTransactionController,
};
use App\Http\Controllers\Api\V1\Customer\Wishlist\WishlistController;
use App\Http\Controllers\Api\V1\DeliveryMan\Chat\ConversationController as DmConversationController;
use App\Http\Controllers\Api\V1\Vendor\Disbursement\WithdrawRequestController as VendorWithdrawRequestController;
use App\Http\Controllers\Api\V1\Vendor\Notification\NotificationController as VendorNotificationController;
use App\Http\Controllers\Api\V1\Vendor\Order\OrderController as VendorOrderController;
use App\Http\Controllers\Api\V1\Vendor\Profile\ProfileController as VendorProfileController;
use App\Http\Controllers\Api\V1\Vendor\Promotion\CampaignController as VendorCampaignController;
use App\Http\Controllers\Api\V1\Vendor\Wallet\WalletController as VendorWalletController;
use App\Http\Controllers\Api\V1\Vendor\Chat\ConversationController as VendorConversationController;
use App\Http\Controllers\Api\V1\Vendor\Disbursement\WithdrawMethodController;
use App\Http\Controllers\Api\V1\Vendor\Order\OrderEditController as VendorOrderEditController;
use App\Http\Controllers\Api\V1\Vendor\Item\{
    AddonController as VendorAddonController,
    AttributeController as VendorAttributeController,
    UnitController as VendorUnitController,
    CategoryController as VendorCategoryController,
    ItemController as VendorItemController,
    ItemReviewController as VendorItemReviewController,
    ItemStockController as VendorItemStockController,
    PendingItemController as VendorPendingItemController,
    StoreCategoryController as VendorStoreCategoryController,
    StoreCategoryItemController as VendorStoreCategoryItemController,
};
use App\Http\Controllers\Api\V1\Vendor\Report\{
    DisbursementReportController as VendorDisbursementReportController,
    EarningReportController as VendorEarningReportController,
    ExpenseReportController as VendorExpenseReportController,
    TaxReportController as VendorTaxReportController,
};
use App\Http\Controllers\Api\V1\Vendor\Store\{
    ScheduleController as VendorScheduleController,
    SettingsController as VendorStoreSettingsController,
};
use App\Http\Controllers\Api\V1\Vendor\Subscription\{
    PackageController as VendorPackageController,
    SubscriptionController as VendorSubscriptionController,
    TransactionController as VendorSubscriptionTransactionController,
};
use App\Http\Controllers\Api\V1\Vendor\Promotion\{
    AdvertisementController as VendorAdvertisementController,
    BannerController as VendorBannerController,
    BogoOfferController as VendorBogoOfferController,
    CouponController as VendorCouponController,
    HappyHourController as VendorHappyHourController,
};
use App\Http\Controllers\Api\V1\Vendor\Promotion\BundleController as VendorBundleController;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('localization')->group(function () {
    Route::controller(HomeController::class)->group(function () {
        Route::get('/terms-and-conditions', 'termsAndConditions');
        Route::get('/about-us', 'aboutUs');
        Route::get('/privacy-policy', 'privacyPolicy');
        Route::get('/refund-policy', 'refundPolicy');
        Route::get('/shipping-policy', 'shippingPolicy');
        Route::get('/cancelation', 'cancellation');
    });
    Route::controller(ZoneController::class)->group(function () {
        Route::get('zone/list', 'index');
        Route::get('zone/check', 'check');
    });

    // §14.3. Named as the port source names it — `delivery-charge/coverage-list` — so a client
    // written against StackFood reaches the same lookup here without a path of its own.
    Route::controller(DeliveryCoverageController::class)->group(function () {
        Route::get('delivery-charge/coverage-list', 'index');
    });
    Route::prefix('config')->group(function () {
        Route::controller(ZoneController::class)->group(function () {
            Route::get('/get-zone-id', 'resolve');
        });
    });
    Route::controller(AddonCategoryController::class)->group(function () {
        Route::get('addon-category/list', 'index');
    });
    Route::controller(PaymentMethodController::class)->group(function () {
        Route::get('offline_payment_method_list', 'index');
    });
    Route::prefix('auth')->group(function () {
        Route::controller(CustomerAuthController::class)->group(function () {
            Route::post('sign-up', 'register');
            Route::post('login', 'login');
            Route::post('update-info', 'updateInfo');
        });
        Route::controller(CustomerOtpController::class)->group(function () {
            Route::post('verify-phone', 'verify');
            Route::post('firebase-verify-token', 'firebaseVerify');
        });
        Route::controller(CustomerGuestController::class)->group(function () {
            Route::post('guest/request', 'store');
        });
        Route::controller(PasswordResetController::class)->group(function () {
            Route::post('forgot-password', 'sendOtp');
            Route::post('verify-token', 'verifyOtp');
            Route::put('reset-password', 'resetPassword');
            Route::put('firebase-reset-password', 'verifyFirebaseOtp');
        });
        Route::middleware('actch:deliveryman_app')->prefix('delivery-man')->group(function () {
            Route::controller(DeliveryManLoginController::class)->group(function () {
                Route::post('login', 'login');
                Route::post('store', 'store');
            });
            Route::controller(DeliveryManPasswordResetController::class)->group(function () {
                Route::post('forgot-password', 'sendOtp');
                Route::post('verify-token', 'verifyOtp');
                Route::post('firebase-verify-token', 'verifyFirebaseOtp');
                Route::put('reset-password', 'resetPassword');
            });
        });
        Route::middleware('actch:vendor_app')->prefix('vendor')->group(function () {
            Route::controller(VendorLoginController::class)->group(function () {
                Route::post('login', 'login');
                Route::post('register', 'register')->withoutMiddleware('actch:vendor_app');
            });
            Route::controller(VendorPasswordResetController::class)->group(function () {
                Route::post('forgot-password', 'sendOtp');
                Route::post('verify-token', 'verifyOtp');
                Route::put('reset-password', 'resetPassword');
            });
        });
    });
    Route::prefix('vendor')->group(function () {
        Route::controller(VendorPackageController::class)->group(function () {
            Route::get('package-view', 'index');
        });
    });
    Route::controller(ModuleController::class)->group(function () {
        Route::get('module', 'index');
        Route::get('module/top-offer', 'topOffer');
    });
    Route::controller(HomeController::class)->group(function () {
        Route::post('newsletter/subscribe', 'subscribeNewsletter');
    });
    Route::controller(LandingPageController::class)->group(function () {
        Route::get('react-landing-page', 'react')->middleware('actch:react_web');
        Route::get('flutter-landing-page', 'flutter');
        Route::get('app-download-section', 'appDownload');
    });
    Route::middleware('actch:deliveryman_app')->prefix('delivery-man')->group(function () {
        Route::controller(LocationController::class)->group(function () {
            Route::get('last-location', 'lastLocation');
        });
        Route::middleware(['auth:api'])->prefix('reviews')->group(function () {
            Route::controller(DeliveryManReviewController::class)->group(function () {
                Route::get('/{delivery_man_id}', 'index');
                Route::get('rating/{delivery_man_id}', 'rating');
                Route::post('/submit', 'store');
            });
        });
        Route::middleware(['dm.api'])->group(function () {
            Route::controller(DeliveryManProfileController::class)->group(function () {
                Route::get('profile', 'show');
                Route::put('update-profile', 'update');
                Route::post('update-active-status', 'updateActiveStatus');
                Route::put('update-fcm-token', 'updateFcmToken');
                Route::delete('remove-account', 'destroy');
            });
            Route::controller(LocationController::class)->group(function () {
                Route::post('record-location-data', 'store');
                Route::get('order-delivery-history', 'orderHistory');
            });
            Route::controller(DeliveryManNotificationController::class)->group(function () {
                Route::get('notifications', 'index');
            });
            Route::controller(DeliveryManOrderController::class)->group(function () {
                Route::get('current-orders', 'index');
                Route::get('orders-count', 'statusStatistics');
                Route::get('latest-orders', 'latest');
                Route::get('all-orders', 'history');
                Route::put('accept-order', 'accept');
                Route::put('update-order-status', 'updateStatus');
                Route::put('update-payment-status', 'updatePaymentStatus');
                Route::get('order-details', 'details');
                Route::get('order', 'show');
                Route::put('send-order-otp', 'sendOtp');
                Route::post('parcel-return', 'returnParcel');
                Route::post('add-return-date', 'addReturnDate');
            });
            Route::controller(DeliveryManWalletController::class)->group(function () {
                Route::get('convert-loyalty-points', 'convertLoyaltyPoints');
            });
            Route::controller(EarningReportController::class)->group(function () {
                Route::get('income-statement', 'incomeStatement');
                Route::get('earning-report', 'index');
                Route::get('loyalty-report', 'loyalty');
                Route::get('referral-report', 'referral');
                Route::get('loyalty-point-list', 'loyaltyPoints');
                Route::get('referral-earning-list', 'referralEarnings');
                Route::get('parcel-return-earning-list', 'parcelReturnEarnings');
                Route::get('new-earning-report', 'summary');
            });
            Route::controller(DisbursementMethodController::class)->group(function () {
                Route::get('get-withdraw-method-list', 'withdrawalMethods');
            });
            Route::controller(DisbursementController::class)->group(function () {
                Route::get('get-disbursement-report', 'index');
            });
            Route::prefix('withdraw-method')->group(function () {
                Route::controller(DisbursementMethodController::class)->group(function () {
                    Route::get('list', 'index');
                    Route::post('store', 'store');
                    Route::post('make-default', 'makeDefault');
                    Route::delete('delete', 'destroy');
                });
            });
            Route::controller(WithdrawRequestController::class)->group(function () {
                Route::get('get-withdraw-list', 'index');
                Route::post('request-withdraw', 'store');
            });
            Route::controller(DeliveryManWalletController::class)->group(function () {
                Route::post('make-collected-cash-payment', 'collectCashPayment')->name('deliveryman_make_payment');
                Route::post('make-wallet-adjustment', 'adjust')->name('deliveryman_make_wallet_adjustment');
                Route::get('wallet-payment-list', 'payments')->name('deliveryman_wallet_payment_list');
                Route::get('wallet-provided-earning-list', 'providedEarnings')->name('wallet_provided_earning_list');
            });
            Route::prefix('message')->group(function () {
                Route::controller(DmConversationController::class)->group(function () {
                    Route::get('list', 'index');
                    Route::get('search-list', 'search');
                    Route::get('details', 'show');
                    Route::post('send', 'store');
                    Route::post('question/send', 'storeAutoMessage');
                });
            });
        });
    });
    Route::middleware(['vendor.api','actch:vendor_app'])->prefix('vendor')->group(function () {
        Route::controller(VendorNotificationController::class)->group(function () {
            Route::get('notifications', 'index');
        });
        Route::controller(VendorProfileController::class)->group(function () {
            Route::get('profile', 'show');
            Route::post('update-active-status', 'updateActiveStatus');
            Route::get('earning-info', 'earnings');
            Route::put('update-profile', 'update');
            Route::put('update-announcment', 'updateAnnouncement');
            Route::put('update-fcm-token', 'updateFcmToken');
        });
        Route::controller(VendorOrderController::class)->group(function () {
            Route::get('current-orders', 'current');
            Route::get('completed-orders', 'completed');
            Route::get('canceled-orders', 'canceled');
            Route::get('all-orders', 'index');
            Route::put('update-order-status', 'updateStatus');
            Route::put('update-order-amount', 'updateAmount');
            Route::get('order-details', 'details');
            Route::get('order', 'show');
            Route::put('send-order-otp', 'sendOtp');
        });
        Route::controller(VendorCampaignController::class)->group(function () {
            Route::get('get-basic-campaigns', 'index');
            Route::put('campaign-leave', 'leave');
            Route::put('campaign-join', 'join');
        });
        Route::controller(VendorWithdrawRequestController::class)->group(function () {
            Route::get('get-withdraw-list', 'index');
            Route::post('request-withdraw', 'store');
        });
        Route::controller(VendorItemController::class)->group(function () {
            Route::get('get-items-list', 'index');
        });
        Route::controller(VendorWalletController::class)->group(function () {
            Route::post('make-collected-cash-payment', 'collectCashPayment')->name('vendor_make_payment');
            Route::post('make-wallet-adjustment', 'adjust')->name('vendor_make_wallet_adjustment');
            Route::get('wallet-payment-list', 'payments')->name('vendor_wallet_payment_list');
        });
        Route::controller(VendorOrderEditController::class)->group(function () {
            Route::put('update-order', 'update');
            Route::get('get-searched-food', 'searchItems');
            Route::get('order-edit-log', 'logs');
        });
        Route::controller(VendorEarningReportController::class)->group(function () {
            Route::get('earning-report', 'index');
        });
        Route::controller(WithdrawMethodController::class)->group(function () {
            Route::get('get-withdraw-method-list', 'withdrawalMethods');
        });
        Route::prefix('withdraw-method')->group(function () {
            Route::controller(WithdrawMethodController::class)->group(function () {
                Route::get('list', 'index');
                Route::post('store', 'store');
                Route::post('make-default', 'makeDefault');
                Route::delete('delete', 'destroy');
            });
        });
        Route::controller(VendorExpenseReportController::class)->group(function () {
            Route::get('get-expense', 'index');
        });
        Route::controller(VendorTaxReportController::class)->group(function () {
            Route::get('get-tax-report', 'index');
        });
        Route::controller(VendorDisbursementReportController::class)->group(function () {
            Route::get('get-disbursement-report', 'index');
        });
        Route::controller(VendorSubscriptionTransactionController::class)->group(function () {
            Route::get('subscription-transaction', 'index');
        });
        Route::controller(VendorSubscriptionController::class)->group(function () {
            Route::post('business_plan', 'businessPlan')->withoutMiddleware('vendor.api');
            Route::post('cancel-subscription', 'cancel');
            Route::get('check-product-limits', 'checkProductLimits');
        });
        Route::controller(VendorProfileController::class)->group(function () {
            Route::delete('remove-account', 'destroy');
        });
        Route::controller(VendorUnitController::class)->group(function () {
            Route::get('unit', 'index');
        });
        Route::controller(VendorStoreSettingsController::class)->group(function () {
            Route::put('update-basic-info', 'updateBasicInfo');
            Route::put('update-business-setup', 'updateSetup');
        });
        Route::controller(VendorScheduleController::class)->group(function () {
            Route::post('schedule/store', 'store');
            Route::delete('schedule/{store_schedule}', 'destroy');
        });
        Route::controller(VendorAttributeController::class)->group(function () {
            Route::get('attributes', 'index');
        });
        Route::prefix('coupon')->group(function () {
            Route::controller(VendorCouponController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('view', 'show');
                Route::get('view-without-translate', 'show');
                Route::post('store', 'store')->name('store');
                Route::post('update', 'update');
                Route::post('status', 'updateStatus')->name('status');
                Route::post('delete', 'destroy')->name('delete');
                Route::post('search', 'search')->name('search');
            });
        });
        Route::prefix('advertisement')->name('advertisement.')->group(function () {
            Route::controller(VendorAdvertisementController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('details/{id}', 'show');
                Route::delete('delete/{id}', 'destroy');
                Route::post('store', 'store');
                Route::post('update/{id}', 'update');
                Route::put('/status', 'updateStatus')->name('status');
                Route::post('copy-add-post', 'duplicate');
            });
        });
        Route::prefix('addon')->group(function () {
            Route::controller(VendorAddonController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('store', 'store');
                Route::put('update', 'update');
                Route::get('status', 'updateStatus');
                Route::delete('delete', 'destroy');
            });
        });
        // BOGO -- the store joins an offer the admin published with its own buy/get selection,
        // answers one the admin assigned it, reworks a rejected one, or leaves.
        Route::middleware('promotion-module')->prefix('bogo-offer')->group(function () {
            Route::controller(VendorBogoOfferController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('items', 'items');
                Route::get('details/{id}', 'show');
                Route::post('join/{id}', 'join');
                Route::post('resubmit/{id}', 'resubmit');
                Route::post('respond/{id}', 'respond');
                Route::delete('leave/{id}', 'destroy');
            });
        });
        Route::prefix('bundle')->group(function () {
            Route::controller(VendorBundleController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('items', 'items');
                Route::get('details/{id}', 'show');
                Route::post('store', 'store');
                Route::post('update/{id}', 'update');
                Route::post('status/{id}', 'status');
                Route::delete('delete/{id}', 'destroy');
            });
        });
        // Happy Hour -- no item selection, so no resubmit: a denied store cancels and joins again.
        Route::middleware('promotion-module')->prefix('happy-hour')->group(function () {
            Route::controller(VendorHappyHourController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('details/{id}', 'show');
                Route::post('join/{id}', 'join');
                Route::post('respond/{id}', 'respond');
                Route::delete('leave/{id}', 'destroy');
            });
        });
        Route::prefix('banner')->group(function () {
            Route::controller(VendorBannerController::class)->group(function () {
                Route::get('/', 'index');
                Route::post('store', 'store');
                Route::put('update', 'update');
                Route::get('status', 'updateStatus');
                Route::delete('delete', 'destroy');
                Route::get('edit/{id}', 'show');
            });
        });
        Route::prefix('categories')->group(function () {
            Route::controller(VendorCategoryController::class)->group(function () {
                Route::get('/', 'index');
                Route::get('childes/{category_id}', 'childes');
                Route::get('category-wise-products/{id}', 'items');
            });
        });
        Route::prefix('delivery-man')->group(function () {
            Route::controller(VendorDeliveryManController::class)->group(function () {
                Route::post('store', 'store');
                Route::get('list', 'index');
                Route::get('preview', 'show');
                Route::get('status', 'updateStatus');
                Route::post('update/{id}', 'update');
                Route::delete('delete', 'destroy');
                Route::post('search', 'search');
            });
        });
        Route::prefix('item')->group(function () {
            Route::controller(VendorItemController::class)->group(function () {
                Route::post('store', 'store');
                Route::put('update', 'update');
                Route::delete('delete', 'destroy');
                Route::get('status', 'updateStatus');
                Route::get('details/{id}', 'show');
                Route::post('search', 'search');
                Route::get('recommended', 'updateRecommended');
                Route::get('organic', 'updateOrganic');
            });
            Route::controller(VendorItemReviewController::class)->group(function () {
                Route::get('reviews', 'index');
                Route::put('reply-update', 'updateReply');
            });
            Route::controller(VendorPendingItemController::class)->group(function () {
                Route::get('pending/item/list', 'index');
                Route::get('requested/item/view/{id}', 'show');
            });
            Route::controller(VendorItemStockController::class)->group(function () {
                Route::put('stock-update', 'update');
                Route::get('stock-limit-list', 'index');
            });
        });
        Route::prefix('store-category')->group(function () {
            Route::controller(VendorStoreCategoryController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('details/{id}', 'show');
                Route::post('store', 'store');
                Route::post('update/{id}', 'update');
                Route::post('status', 'updateStatus');
                Route::post('priority', 'updatePriority');
                Route::delete('delete', 'destroy');
            });
            Route::controller(VendorStoreCategoryItemController::class)->group(function () {
                Route::get('items/{id}', 'index');
                Route::get('assignable-items/{id}', 'assignable');
                Route::post('assign-items', 'assign');
            });
        });
        Route::prefix('message')->group(function () {
            Route::controller(VendorConversationController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('search-list', 'search');
                Route::get('details', 'show');
                Route::post('send', 'store');
            });
        });
    });
    Route::prefix('config')->group(function () {
        Route::controller(SystemConfigController::class)->group(function () {
            Route::get('/', 'index');
        });
        Route::controller(SystemConfigController::class)->group(function () {
            Route::get('get-analytic-scripts', 'analyticScripts');
        });
        Route::controller(MapController::class)->group(function () {
            Route::get('place-api-autocomplete', 'placeAutocomplete');
            Route::get('distance-api', 'distance');
            Route::get('direction-api', 'direction');
            Route::get('place-api-details', 'placeDetails');
            Route::get('geocode-api', 'geocode');
        });
    });
    Route::controller(OrderCancellationReasonController::class)->group(function () {
        Route::get('customer/order/cancellation-reasons', 'index');
    });
    Route::controller(AutomatedMessageController::class)->group(function () {
        Route::get('customer/automated-message', 'index');
    });
    Route::controller(ParcelInstructionController::class)->group(function () {
        Route::get('customer/order/parcel-instructions', 'index');
    });
    Route::controller(OrderController::class)->group(function () {
        Route::get('customer/order/last', 'lastOrders');
        Route::get('most-tips', 'mostTips');
    });
    Route::controller(ItemNameListController::class)->group(function () {
        Route::get('item/get-generic-name-list', 'generics');
        Route::get('item/get-allergy-name-list', 'allergies');
        Route::get('item/get-nutrition-name-list', 'nutritions');
    });
    Route::controller(StoreController::class)->group(function () {
        Route::get('stores/details/{id}', 'show');
    });
    Route::prefix('pro-customer')->group(function () {
        Route::controller(ProCustomerPlanController::class)->group(function () {
            Route::get('plans', 'index');
        });
        Route::controller(ProCustomerFaqController::class)->group(function () {
            Route::get('faqs', 'index');
        });
        Route::controller(ProCustomerTermsController::class)->group(function () {
            Route::get('terms-and-conditions', 'show');
        });
    });
    Route::middleware(['module-check'])->group(function () {
        Route::middleware('auth:api')->prefix('customer')->group(function () {
            Route::controller(SavedFileController::class)->group(function () {
                Route::get('saved-files', 'index');
                Route::post('saved-files/store', 'store');
                Route::delete('saved-files/delete-all', 'destroyAll');
            });
            Route::controller(ProfileController::class)->group(function () {
                Route::get('info', 'show');
                Route::get('update-zone', 'updateZone');
                Route::post('update-profile', 'update');
                Route::post('update-interest', 'updateInterest');
                Route::put('cm-firebase-token', 'updateFirebaseToken');
                Route::delete('remove-account', 'destroy');
            });
            Route::controller(SuggestedItemController::class)->group(function () {
                Route::get('suggested-items', 'index');
            });
            Route::controller(NotificationController::class)->group(function () {
                Route::get('notifications', 'index');
            });
            Route::prefix('address')->group(function () {
                Route::controller(AddressController::class)->group(function () {
                    Route::get('list', 'index');
                    Route::post('add', 'store');
                    Route::put('update/{id}', 'update');
                    Route::delete('delete', 'destroy');
                });
            });
            Route::prefix('message')->group(function () {
                Route::controller(ConversationController::class)->group(function () {
                    Route::get('list', 'index');
                    Route::get('search-list', 'search');
                    Route::get('details', 'show');
                    Route::post('send', 'store');
                });
            });
            Route::prefix('wish-list')->group(function () {
                Route::controller(WishlistController::class)->group(function () {
                    Route::get('/', 'index');
                    Route::post('add', 'store');
                    Route::delete('remove', 'destroy');
                });
            });
            Route::prefix('loyalty-point')->group(function () {
                Route::controller(LoyaltyPointTransactionController::class)->group(function () {
                    Route::post('point-transfer', 'transfer');
                    Route::get('transactions', 'index');
                });
            });
            Route::prefix('wallet')->group(function () {
                Route::controller(WalletTransactionController::class)->group(function () {
                    Route::get('transactions', 'index');
                });
                Route::controller(WalletBonusController::class)->group(function () {
                    Route::get('bonuses', 'index');
                });
                Route::controller(WalletFundController::class)->group(function () {
                    Route::post('add-fund', 'store');
                });
            });
            Route::controller(StoreController::class)->group(function () {
                Route::get('visit-again', 'visitAgain');
            });
            Route::controller(ItemController::class)->group(function () {
                Route::get('recent-ordered-items', 'recentOrdered');
            });
            Route::controller(ReorderController::class)->group(function () {
                Route::post('order-again/reorder', 'store');
                Route::post('monthly-order/reorder', 'storeFromReminder');
            });
            Route::controller(MonthlySubscriptionController::class)->group(function () {
                Route::get('monthly-order/list', 'index');
                Route::get('monthly-order/details', 'show');
                Route::delete('monthly-order/remove', 'destroy');
            });
            Route::controller(ReviewReminderController::class)->group(function () {
                Route::get('review-reminder', 'show');
                Route::get('review-reminder-cancel', 'cancel');
            });
            Route::prefix('pro-customer')->group(function () {
            Route::controller(ProCustomerSubscriptionController::class)->group(function () {
                Route::post('subscribe', 'store');
                Route::post('cancel', 'cancel');
                Route::get('active-offer', 'activeOffer');
            });
        });
    });
    Route::middleware('apiGuestCheck')->prefix('customer')->group(function () {
        Route::prefix('order')->group(function () {
            Route::controller(OrderController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('running-orders', 'runningOrders');
                Route::get('all-running-orders', 'allRunningOrders');
                Route::get('details', 'show');
                Route::put('cancel', 'cancel');
                Route::delete('delete', 'destroy');
                Route::get('track', 'track')->withoutMiddleware('auth:apiGuestCheck');
                Route::post('parcel-return', 'parcelReturn');
            });
            Route::controller(OrderPlacementController::class)->group(function () {
                Route::post('place', 'store');
                // §14.4 — everything the checkout page shows, in one call. `get-Tax` and
                // `get-surge-price` stay for shipped clients.
                Route::post('checkout-summary', 'checkoutSummary');
                Route::post('get-Tax', 'tax');
                Route::post('prescription/place', 'storePrescription');
                Route::post('get-surge-price', 'surgePrice');
            });
            Route::controller(RefundController::class)->group(function () {
                Route::post('refund-request', 'store');
                Route::get('refund-reasons', 'reasons');
            });
            Route::controller(OrderPaymentController::class)->group(function () {
                Route::put('payment-method', 'update');
                Route::put('offline-payment', 'storeOffline');
                Route::put('offline-payment-update', 'updateOffline');
                Route::post('wallet-payment', 'walletPayment');
            });
            Route::controller(PaymentFailedController::class)->group(function () {
                Route::get('payment-failed', 'show');
            });
        });
        Route::prefix('cart')->group(function () {
            Route::controller(CartController::class)->group(function () {
                Route::get('list', 'index');
                Route::get('get-all', 'groupedByStore');
                // What a store-wide discount would come to on this cart, and how much more the
                // basket needs. Separate from `list`, which answers with rows rather than one
                // verdict about the basket.
                Route::get('discount-eligibility', 'discountEligibility');
                Route::post('add', 'store');
                Route::post('add-multiple', 'storeMultiple');
                Route::post('update', 'update');
                Route::delete('remove-item', 'destroy');
                Route::delete('remove', 'destroyAll');
                // A BOGO bundle is atomic -- added, re-quantified and removed whole, never edited
                // line by line -- so it gets its own three verbs rather than sharing the ones
                // above, which address a single item row by id.
                Route::post('bogo/add', 'storeBundle');
                Route::post('bogo/update', 'updateBundle');
                Route::delete('bogo/remove', 'destroyBundle');
                Route::post('bundle/add', 'storeBundlePackage');
                Route::post('bundle/update', 'updateBundlePackage');
                Route::delete('bundle/remove', 'destroyBundlePackage');
            });
        });
    });
    Route::prefix('items')->group(function () {
        Route::controller(ItemController::class)->group(function () {
            Route::get('latest', 'latest');
            Route::get('new-arrival', 'newArrivals');
            Route::get('popular', 'popular');
            Route::get('most-reviewed', 'mostReviewed');
            Route::get('top-rated', 'topRated');
            Route::get('recently-viewed', 'recentlyViewed');
            Route::get('organic', 'organic');
            Route::get('discounted', 'discounted');
            Route::get('set-menu', 'setMenus');
            Route::get('search', 'search');
            Route::get('search-suggestion', 'searchSuggestions');
            Route::get('details/{id}', 'show');
            Route::get('related-items/{item_id}', 'related');
            Route::get('related-store-items/{item_id}', 'relatedStoreItems');
            Route::get('recommended', 'recommended');
            Route::get('basic', 'basic');
            Route::get('suggested', 'suggested');
            Route::get('item-or-store-search', 'itemOrStoreSearch')->withoutMiddleware(['module-check']);
            Route::get('common-conditions', 'commonConditions');
            Route::get('get-products', 'index');
        });
        Route::controller(ItemReviewController::class)->group(function () {
            Route::get('reviews/{item_id}', 'index');
            Route::get('rating/{item_id}', 'rating');
            Route::post('reviews/submit', 'store')->middleware('auth:api');
        });
    });
    Route::prefix('stores')->group(function () {
        Route::controller(StoreController::class)->group(function () {
            Route::get('get-stores/{filter_data}', 'index');
            Route::get('verified', 'verified');
            Route::get('latest', 'latest');
            Route::get('distance', 'distanceWise');
            Route::get('popular', 'popular');
            Route::get('recommended', 'recommended');
            Route::get('discounted', 'discounted');
            Route::get('top-rated', 'topRated');
            Route::get('popular-items/{id}', 'popularItems');
            Route::get('reviews', 'reviews');
            Route::get('search', 'search');
            Route::get('top-offer-near-me', 'topOfferNearMe');
            Route::get('quick-delivery', 'quickDelivery');
            Route::get('exclusive-deals', 'exclusiveDeals');
        });
        Route::controller(SearchController::class)->group(function () {
            Route::get('get-data', 'combinedData');
        });
    });
    Route::controller(SearchController::class)->group(function () {
        Route::get('get-combined-data', 'combinedData');
        Route::get('trending-searches', 'trending')->withoutMiddleware(['module-check']);
    });
    Route::prefix('banners')->group(function () {
        Route::controller(BannerController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('{store_id}/', 'listForStore');
        });
    });
    Route::prefix('smart-banners')->group(function () {
        Route::controller(SmartBannerController::class)->group(function () {
            Route::get('/', 'index')->withoutMiddleware(['module-check']);
        });
    });
    Route::prefix('other-banners')->group(function () {
        Route::controller(ModuleBannerController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('video-content', 'videoContent');
        });
        Route::controller(WhyChooseController::class)->group(function () {
            Route::get('why-choose', 'index');
        });
    });
    Route::prefix('categories')->group(function () {
        Route::controller(CategoryController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('childes/{category_id}', 'childes');
            Route::get('items/list', 'itemsByCategories');
            Route::get('stores/list', 'storesByCategories');
            Route::get('items/{category_id}', 'items');
            Route::get('items/{category_id}/all', 'allItems');
            Route::get('stores/{category_id}', 'stores');
            Route::get('featured/items', 'featuredItems');
            Route::get('popular', 'popular');
            Route::get('top', 'top')->withoutMiddleware(['module-check']);
        });
    });
    Route::prefix('common-condition')->group(function () {
        Route::controller(CommonConditionController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/list', 'options');
            Route::get('items/{condition_id}', 'items');
        });
    });
    Route::prefix('brand')->group(function () {
        Route::controller(BrandController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('items/{brand_id}', 'items');
        });
    });
    Route::prefix('campaigns')->group(function () {
        Route::controller(CampaignController::class)->group(function () {
            Route::get('basic', 'index');
            Route::get('basic-campaign-details', 'show');
        });
        Route::controller(ItemCampaignController::class)->group(function () {
            Route::get('item', 'index');
        });
    });
    Route::prefix('flash-sales')->group(function () {
        Route::controller(FlashSaleController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('/items', 'items');
        });
    });
    // Happy Hour and BOGO both need a line-item cart with priced products, so promotion-module
    // refuses them outside a module type that can run one -- see PromotionModuleCheckMiddleware.
    Route::middleware('promotion-module')->group(function () {
        Route::prefix('happy-hour')->group(function () {
            Route::controller(HappyHourController::class)->group(function () {
                Route::get('stores', 'index');
                // The home banner: the one window running here, or none.
                Route::get('running', 'running');
            });
        });
        Route::prefix('bogo')->group(function () {
            Route::controller(BogoOfferController::class)->group(function () {
                Route::get('home', 'home');
                Route::get('offers', 'index');
                Route::get('store-offers', 'storeOffers');
                // Last, so `home`, `offers` and `store-offers` are not swallowed by {id}.
                Route::get('offers/{id}', 'show');
            });
        });
    });

    Route::prefix('bundle')->group(function () {
        Route::controller(CustomerBundleController::class)->group(function () {
            Route::get('home', 'home');
            Route::get('list', 'index');
            Route::get('store-bundles', 'storeBundles');
            Route::get('{id}', 'show');
        });
    });
    Route::controller(CouponController::class)->group(function () {
        Route::get('coupon/list/all', 'index');
    });
    Route::middleware('auth:api')->prefix('coupon')->group(function () {
        Route::controller(CouponController::class)->group(function () {
            Route::get('list', 'index');
            Route::get('apply', 'apply');
        });
    });
    Route::middleware('auth:api')->prefix('cashback')->group(function () {
        Route::controller(CashBackController::class)->group(function () {
            Route::get('list', 'index');
            Route::get('getCashback', 'calculate');
        });
    });
    Route::controller(ParcelCategoryController::class)->group(function () {
        Route::get('parcel-category', 'index');
    });
    // The two ADDITIVE parcel tiers, beside the category the checkout already asks for — the
    // three lists one parcel screen needs. Both take `zone_id` and answer an empty `data` when
    // the (zone, module)'s active delivery rule does not price by that tier.
    Route::controller(ParcelWeightController::class)->group(function () {
        Route::get('parcel-weight', 'index');
    });
    Route::controller(ParcelDimensionController::class)->group(function () {
        Route::get('parcel-dimension', 'index');
    });
    Route::controller(AdvertisementController::class)->group(function () {
        Route::get('advertisement/list', 'index');
    });
    Route::prefix('store-categories')->group(function () {
        Route::controller(StoreCategoryController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('store/{storeId}', 'byStore');
            Route::get('items', 'items');
        });
    });
    });
    Route::prefix('offers')->group(function () {
        Route::controller(ItemController::class)->group(function () {
            Route::get('items', 'offerItems');
            Route::get('stores', 'offerStores');
        });
    });
    Route::controller(SystemConfigController::class)->group(function () {
        Route::get('get-page-meta-data', 'pageMetaData');
    });
    Route::controller(VehicleController::class)->group(function () {
        Route::get('vehicle/extra_charge', 'extraCharge');
        Route::get('get-vehicles', 'index');
    });
    Route::controller(CancellationReasonController::class)->group(function () {
        Route::get('get-parcel-cancellation-reasons', 'index');
    });
});

Route::prefix('erp')->middleware('erp.api')->group(function () {
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

    Route::get('/vendors-count', [ErpStoreController::class, 'count']);
    Route::get('/vendors', [ErpStoreController::class, 'index']);
    Route::get('/vendors/{id}', [ErpStoreController::class, 'show']);

    Route::get('/delivery-men-count', [ErpDeliveryManController::class, 'count']);
    Route::get('/delivery-men', [ErpDeliveryManController::class, 'index']);
    Route::get('/delivery-men/{id}', [ErpDeliveryManController::class, 'show']);

    Route::get('/refunds-count', [ErpRefundController::class, 'count']);
    Route::get('/refunds', [ErpRefundController::class, 'index']);
    Route::get('/refunds/{id}', [ErpRefundController::class, 'show']);
});
