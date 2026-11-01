<?php

use App\Http\Controllers\{
    BkashPaymentController,
    DeliveryManController,
    FirebaseController,
    FlutterwaveV3Controller,
    HomeController,
    LiqPayController,
    LoginController,
    MercadoPagoController,
    NewsletterController,
    PaymentController,
    PaymobController,
    PaypalPaymentController,
    PaystackController,
    PaytabsController,
    PaytmController,
    RazorPayController,
    RiderRegistrationController,
    SenangPayController,
    SslCommerzPaymentController,
    StripePaymentController,
    VendorController,
};
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::controller(FirebaseController::class)->group(function () {
    Route::post('/subscribeToTopic', 'subscribeToTopic')->middleware(['auth:admin,vendor,vendor_employee', 'throttle:10,1']);
});
Route::controller(HomeController::class)->group(function () {
    Route::get('/', 'index')->name('home');
    Route::get('maintenance-mode', 'maintenanceMode')->name('maintenance_mode');
    Route::get('lang/{locale}', [HomeController::class, 'lang'])->name('lang');
    Route::get('terms-and-conditions', 'terms_and_conditions')->name('terms-and-conditions');
    Route::get('about-us', 'about_us')->name('about-us');
    Route::get('contact-us', 'contact_us')->name('contact-us');
    Route::post('send-message', 'send_message')->name('send-message');
    Route::get('privacy-policy', 'privacy_policy')->name('privacy-policy');
    Route::get('cancelation', 'cancelation')->name('cancelation');
    Route::get('refund', 'refund_policy')->name('refund');
    Route::get('shipping-policy', 'shipping_policy')->name('shipping-policy');
    Route::get('subscription-invoice/{id}', 'subscription_invoice')->name('subscription_invoice');
    Route::get('order-invoice/{id}', 'order_invoice')->name('order_invoice');
    Route::get('deliveryman-earning-report-invoice/{id}', 'earningReportInvoice')->name('delivery_earning_invoice')->middleware('localization');
    Route::get('activation-check', 'getActivationCheckView')->name('system.activation-check');
    Route::post('activation-check', 'activationCheck');
});
Route::controller(NewsletterController::class)->group(function () {
    Route::post('newsletter/subscribe', 'newsLetterSubscribe')->name('newsletter.subscribe');
});
Route::controller(LoginController::class)->group(function () {
    Route::get('login/{tab}', 'login')->name('login');
    Route::post('login_submit', 'submit')->name('login_post')->middleware('actch');
    Route::get('logout', 'logout')->name('logout');
    Route::get('/reload-captcha', 'reloadCaptcha')->name('reload-captcha');
    Route::post('/reset-password', 'reset_password_request')->name('reset-password')->middleware('throttle:3,60');
    Route::post('/vendor-reset-password', 'vendor_reset_password_request')->name('vendor-reset-password')->middleware('throttle:3,60');
    Route::get('/password-reset', 'reset_password')->name('change-password');
    Route::post('verify-otp', 'verify_token')->name('verify-otp');
    Route::post('reset-password-submit', 'reset_password_submit')->name('reset-password-submit');
    Route::get('otp-resent', 'otp_resent')->name('otp_resent');
});
Route::get('authentication-failed', function () {
$errors = [];
array_push($errors, ['code' => 'auth-001', 'message' => 'Unauthenticated.']);
return response()->json([
'errors' => $errors,
], 401);
})->name('authentication-failed');
Route::prefix('payment-mobile')->group(function () {
    Route::controller(PaymentController::class)->group(function () {
        Route::get('/', 'payment')->name('payment-mobile');
        Route::get('set-payment-method/{name}', 'set_payment_method')->name('set-payment-method');
    });
});
Route::controller(PaymentController::class)->group(function () {
    Route::get('payment-success', 'success')->name('payment-success');
    Route::get('payment-fail', 'fail')->name('payment-fail');
    Route::get('payment-cancel', 'cancel')->name('payment-cancel');
});
$is_published = addon_published_status('Gateways');
if (!$is_published) {
Route::prefix('payment')->group(function () {
    Route::prefix('sslcommerz')->name('sslcommerz.')->group(function () {
        Route::controller(SslCommerzPaymentController::class)->group(function () {
            Route::get('pay', 'index')->name('pay');
            Route::post('success', 'success')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('failed', 'failed')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('canceled', 'canceled')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('stripe')->name('stripe.')->group(function () {
        Route::controller(StripePaymentController::class)->group(function () {
            Route::get('pay', 'index')->name('pay');
            Route::get('token', 'payment_process_3d')->name('token');
            Route::get('success', 'success')->name('success');
            Route::get('canceled', 'canceled')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('razor-pay')->name('razor-pay.')->group(function () {
        Route::controller(RazorPayController::class)->group(function () {
            Route::get('pay', 'index');
            Route::post('payment', 'payment')->name('payment')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::post('callback', 'callback')->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('cancel', 'cancel')->name('cancel')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('create-order', 'createOrder')->name('create-order')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('verify-payment', 'verifyPayment')->name('verify-payment')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('paypal')->name('paypal.')->group(function () {
        Route::controller(PaypalPaymentController::class)->group(function () {
            Route::get('pay', 'payment');
            Route::any('success', 'success')->name('success')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('cancel', 'cancel')->name('cancel')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('senang-pay')->name('senang-pay.')->group(function () {
        Route::controller(SenangPayController::class)->group(function () {
            Route::get('pay', 'index');
            Route::any('callback', 'return_senang_pay');
        });
    });
    Route::prefix('paytm')->name('paytm.')->group(function () {
        Route::controller(PaytmController::class)->group(function () {
            Route::get('pay', 'payment');
            Route::any('response', 'callback')->name('response')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('flutterwave-v3')->name('flutterwave-v3.')->group(function () {
        Route::controller(FlutterwaveV3Controller::class)->group(function () {
            Route::get('pay', 'initialize')->name('pay');
            Route::get('callback', 'callback')->name('callback');
        });
    });
    Route::prefix('paystack')->name('paystack.')->group(function () {
        Route::controller(PaystackController::class)->group(function () {
            Route::get('pay', 'index')->name('pay');
            Route::get('callback', 'handleGatewayCallback')->name('callback');
            Route::get('cancel', 'cancel')->name('cancel');
        });
    });
    Route::prefix('bkash')->name('bkash.')->group(function () {
        Route::controller(BkashPaymentController::class)->group(function () {
            Route::get('make-payment', 'make_tokenize_payment')->name('make-payment');
            Route::any('callback', 'callback')->name('callback');
        });
    });
    Route::prefix('liqpay')->name('liqpay.')->group(function () {
        Route::controller(LiqPayController::class)->group(function () {
            Route::get('payment', 'payment')->name('payment');
            Route::any('callback', 'callback')->name('callback');
        });
    });
    Route::prefix('mercadopago')->name('mercadopago.')->group(function () {
        Route::controller(MercadoPagoController::class)->group(function () {
            Route::get('pay', 'index')->name('index');
            Route::post('make-payment', 'make_payment')->name('make_payment')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
            Route::any('callback', 'callback')->name('callback')->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
        });
    });
    Route::prefix('paymob')->name('paymob.')->group(function () {
        Route::controller(PaymobController::class)->group(function () {
            Route::any('pay', 'credit')->name('pay');
            Route::any('callback', 'callback')->name('callback');
        });
    });
    Route::prefix('paytabs')->name('paytabs.')->group(function () {
        Route::controller(PaytabsController::class)->group(function () {
            Route::any('pay', 'payment')->name('pay');
            Route::any('callback', 'callback')->name('callback');
            Route::any('response', [PaytabsController::class, 'response'])->name('response');
        });
    });
});
}
Route::get('/test', function () {
Artisan::call('optimize:clear');
$homeRoute = app('router')->getRoutes()->getByName('home');
});
Route::get('module-test', function () {
});
Route::prefix('vendor')->name('restaurant.')->group(function () {
    Route::controller(VendorController::class)->group(function () {
        Route::get('apply', 'create')->name('create');
        Route::post('apply', 'store')->name('store');
        Route::get('get-all-modules', 'get_all_modules')->name('get-all-modules');
        Route::get('get-module-type', 'get_modules_type')->name('get-module-type');
        Route::get('check-module-type', 'check_module_type')->name('check-module-type');
        Route::get('back', 'back')->name('back');
        Route::post('business-plan', 'business_plan')->name('business_plan');
        Route::get('business-plan', 'secondStep')->name('secondStep');
        Route::post('payment', 'payment')->name('payment');
        Route::get('final-step', 'final_step')->name('final_step');
    });
});
Route::prefix('rider')->name('rider.')->group(function () {
    Route::controller(RiderRegistrationController::class)->group(function () {
        Route::get('apply', 'create')->name('create');
        Route::post('apply', 'store')->name('store');
    });
});
Route::prefix('deliveryman')->name('deliveryman.')->group(function () {
    Route::controller(DeliveryManController::class)->group(function () {
        Route::get('apply', 'create')->name('create');
        Route::post('apply', 'store')->name('store');
    });
});
Route::get('/image-proxy', function () {
$url = request('url');
if (!$url) {
abort(400, 'Missing url parameter');
}
$response = Http::withHeaders([
'User-Agent' => 'Laravel-Image-Proxy'
])->get($url);
return response($response->body(), $response->status())
->header('Content-Type', $response->header('Content-Type'))
->header('Access-Control-Allow-Origin', '*');
});
