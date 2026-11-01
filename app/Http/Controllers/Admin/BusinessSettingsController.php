<?php

namespace App\Http\Controllers\Admin;

use App\Rules\EmailAddress;
use App\Support\Notification\Fcm\WebPushServiceWorker;
use App\Rules\ImageFile;
use App\CentralLogics\Helpers;
use App\Services\System\DistanceService;
use App\Services\System\MeasurementUnitService;
use App\Http\Controllers\Controller;
use App\Models\AdminFeature;
use App\Models\AdminPromotionalBanner;
use App\Models\AdminSpecialCriteria;
use App\Models\AdminTestimonial;
use App\Models\AutomatedMessage;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use App\Models\FAQ;
use App\Models\EcommerceItemDetails;
use App\Models\EmailTemplate;
use App\Models\FlutterSpecialCriteria;
use App\Models\Item;
use App\Models\PageSeoData;
use App\Models\NotificationMessage;
use App\Models\NotificationSetting;
use App\Models\OrderCancelReason;
use App\Models\PharmacyItemDetails;
use App\Models\PriorityList;
use App\Models\ReactPromotionalBanner;
use App\Models\ReactTestimonial;
use App\Models\RefundReason;
use App\Models\Setting;
use App\Services\Payment\SettingService;
use App\Services\System\DataSettingService;
use App\Models\Store;
use App\Models\StoreSubscription;
use App\Models\TempProduct;
use App\Models\Translation;
use App\Traits\System\ProcessorTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use App\Support\Notification\SendNotification;
use App\Support\Promotion\BundleSettings;
use Illuminate\Support\Facades\Log;

class BusinessSettingsController extends Controller
{
    use ProcessorTrait;

    public function business_index(Request $request, string $tab = 'business')
{
    if (!Helpers::module_permission_check('settings')) {
        Toastr::error(translate('messages.Access denied'));
        return back();
    }


    $language = getWebConfig('language');
    $type = $request->input('type');
    $search = $request->input('search');

    switch ($tab) {
        case 'business':
            // The three measurement units. Read-only here — the form saves them with the rest
            // of Business Info, and nothing is converted, so there is no preview to prepare.
            $distanceUnit = app(DistanceService::class)->unit();
            $measurementService = app(MeasurementUnitService::class);
            $weightUnit = $measurementService->weightUnit();
            $dimensionUnit = $measurementService->dimensionUnit();

            return view(
                'admin-views.business-settings.settings.business-index',
                compact('distanceUnit', 'weightUnit', 'dimensionUnit')
            );

        case 'customer':
            $keys = [
                'guest_checkout_status',
                'toggle_veg_non_veg',
                'wallet_status',
                'wallet_add_refund',
                'add_fund_status',
                'loyalty_point_status',
                'loyalty_point_exchange_rate',
                'loyalty_point_item_purchase_point',
                'loyalty_point_minimum_point',
                'ref_earning_status',
                'ref_earning_exchange_rate',
                'new_customer_discount_status',
                'new_customer_discount_amount_type',
                'new_customer_discount_validity_type',
                'new_customer_discount_amount',
                'new_customer_discount_amount_validity',
                'pro_member_status',
                'customer_personalization_status',
            ];

            $data = Helpers::get_business_settings_many($keys);

            return view('admin-views.business-settings.settings.customer-index', compact('data'));

        case 'payment':
            $digital_payment_methods_count = Setting::whereIn('settings_type', ['payment_config'])
                ->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago'])
                ->where('is_active', 1)
                ->count();
            $offline_payment_methods_count = \App\Models\OfflinePaymentMethod::where('status', 1)->count();
            $cash_on_delivery_status = optional(
                json_decode(
                    Helpers::get_business_settings('cash_on_delivery', false),
                    true
                )
            )['status'] ?? null;
            $digital_payment_status = optional(
                json_decode(
                    Helpers::get_business_settings('digital_payment', false),
                    true
                )
            )['status'] ?? null;
            $offline_payment_status = Helpers::get_business_settings('offline_payment_status', false);
            return view('admin-views.business-settings.settings.payment-index', compact('digital_payment_methods_count', 'offline_payment_methods_count', 'cash_on_delivery_status', 'digital_payment_status', 'offline_payment_status'));

        case 'deliveryman':
            return view('admin-views.business-settings.settings.deliveryman-index');

        case 'order':
            $reasons = OrderCancelReason::when(
                $type && $type !== 'all',
                fn ($query) => $query->where('user_type', $type)
            )
            ->latest()
            ->paginate(config('default_pagination'));

            $bundleModuleTypes = BundleSettings::moduleTypes();
            $bundleStatus = BundleSettings::enabled();
            $bundleSelectedModules = BundleSettings::selectedModules();

            return view(
                'admin-views.business-settings.settings.order-index',
                compact('reasons', 'type', 'language', 'bundleModuleTypes', 'bundleStatus', 'bundleSelectedModules')
            );

        case 'store':
            $keys = [
                'canceled_by_store',
                'toggle_store_registration',
                'product_gallery',
                'access_all_products',
                'store_review_reply',
                'review_section',
                'verified_seller_badge',
                'vendor_can_set_low_stock',
                'store_category_status',
                'can_vendor_edit_order',
                'admin_website_builder_status',
                'product_approval',
                'cash_in_hand_overflow_store',
                'cash_in_hand_overflow_store_amount',
                'min_amount_to_pay_store',
            ];

            $data = Helpers::get_business_settings_many($keys);

            $data['product_approval_datas'] = json_decode(
                Helpers::get_business_settings('product_approval_datas', false) ?? '',
                true
            );

            return view('admin-views.business-settings.settings.store-index', compact('data'));

        case 'refund-settings':
            $refund_active_status = Helpers::get_business_settings('refund_active_status');
            $keywords = $search ? explode(' ', $search ?? '') : [];

            $reasons = RefundReason::latest()
                ->when($keywords, function ($query) use ($keywords) {
                    foreach ($keywords as $word) {
                        $query->where('reason', 'like', "%{$word}%");
                    }
                })
                ->paginate(config('default_pagination'));

            return view(
                'admin-views.business-settings.settings.refund-index',
                compact('refund_active_status', 'reasons', 'language')
            );

        case 'landing-page':
            BusinessSetting::firstOrCreate(
                ['key' => 'landing_page'],
                ['value' => '1']
            );

            BusinessSetting::firstOrCreate(
                ['key' => 'landing_integration_type'],
                ['value' => 'none']
            );

            return view('admin-views.business-settings.landing-index');

        case 'disbursement':
            return view('admin-views.business-settings.settings.disbursement-index');

        case 'priority':
            return view('admin-views.business-settings.settings.priority-index');

        case 'automated-message':
            $keywords = $search ? explode(' ', $search ?? '') : [];

            $messages = AutomatedMessage::latest()
                ->when($keywords, function ($query) use ($keywords) {
                    foreach ($keywords as $word) {
                        $query->where('message', 'like', "%{$word}%");
                    }
                })
                ->paginate(config('default_pagination'));

            return view(
                'admin-views.business-settings.settings.automated-message',
                compact('messages', 'language')
            );

        default:
            abort(404);
    }
}


    public function update_priority(Request $request)
    {
        $list = ['category_list', 'popular_store', 'recommended_store', 'special_offer', 'popular_item', 'best_reviewed_item', 'item_campaign', 'latest_items', 'all_stores', 'category_sub_category_item', 'product_search', 'basic_medicine', 'common_condition', 'brand', 'brand_item', 'latest_stores', 'top_offer_near_me_stores'];
        $types = ['general', 'unavailable', 'temp_closed', 'rating'];

        // Only the sections the page actually rendered are touched — otherwise a section with
        // no form field (e.g. latest_items) would have its status overwritten with 0 on save.
        $submitted = array_values(array_filter($list, fn ($item) => $request->has($item . '_default_status')));

        // Validate everything up front: writing as we go left the sections before the failing
        // one already saved while the admin was sent back with an error.
        foreach ($submitted as $item) {
            if ($request[$item . '_default_status'] == '0' && !$request[$item . '_sort_by_general']) {
                Toastr::error(translate('You must select an option for') . ' ' . translate($item));

                return back();
            }
        }

        foreach ($submitted as $item) {
            Helpers::businessUpdateOrInsert(['key' => $item . '_default_status'], [
                'value' => $request[$item . '_default_status'] ?? 0,
            ]);

            if ($request[$item . '_default_status'] != '0') {
                continue;
            }

            foreach ($types as $type) {
                if ($request[$item . '_sort_by_' . $type]) {
                    PriorityList::updateOrCreate(['name' => $item . '_sort_by_' . $type, 'type' => $type], [
                        'value' => $request[$item . '_sort_by_' . $type],
                    ]);
                }
            }
        }

        Toastr::success(translate('messages.Successfully updated to changes restart app'));

        return back();
    }

    public function update_dm(Request $request)
    {
        if (getEnvMode() === 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        $keys = [
            'min_amount_to_pay_dm',
            'cash_in_hand_overflow_delivery_man',
            'dm_max_cash_in_hand',
            'dm_tips_status',
            'dm_maximum_orders',
            'canceled_by_deliveryman',
            'show_dm_earning',
            'dm_picture_upload_status',
            'dm_loyality_point_status',
            'dm_loyality_point_per_order',
            'dm_loyality_point_conversion_rate',
            'dm_min_loyality_point_to_convert',
            'dm_referal_status',
            'dm_referal_amount',
            'dm_referal_bonus',
            'toggle_dm_registration',
        ];


        foreach ($keys as $key) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $request->$key ?? 0,
            ]);
        }

        Toastr::success(translate('messages.Successfully updated to changes restart app'));
        return back();
    }


    public function update_store(Request $request)
    {
        if ($request['cash_in_hand_overflow_store']) {
            $request->validate([
                'min_amount_to_pay_store' => 'required|numeric|min:0',
                'cash_in_hand_overflow_store_amount' => 'required|numeric|min:0|gt:min_amount_to_pay_store',
            ], [
                'cash_in_hand_overflow_store_amount.gt' => translate('Amount must be greater than the minimum payable amount'),
            ]);
        }

        $reelsSettingsController = $this->getReelsBusinessSettingsController();
        if ($reelsSettingsController) {
            $reelsSettingsController->validateStoreSettings($request);
        }

        if ($request['product_approval'] == null) {
            $this->product_approval_all();
        }
        if ($request['product_approval'] == 1) {
            if (!($request->Update_product_price || $request->Add_new_product || $request->Update_product_variation || $request->Update_anything_in_product_details)) {
                Helpers::businessUpdateOrInsert(['key' => 'product_approval'], [
                    'value' => 0,
                ]);
                Toastr::error(translate('messages.Select at least one criterion for product approval'));

                return back();
            }
        }
        $storeSettings = [
            'cash_in_hand_overflow_store' => $request['cash_in_hand_overflow_store'] ?? 0,
            'cash_in_hand_overflow_store_amount' => $request['cash_in_hand_overflow_store_amount'],
            'min_amount_to_pay_store' => $request['min_amount_to_pay_store'],
            'store_review_reply' => $request['store_review_reply'] ?? 0,
            'review_section' => $request['review_section'] ?? 0,
            'verified_seller_badge' => $request['verified_seller_badge'] ?? 0,
            'vendor_can_set_low_stock' => $request['vendor_can_set_low_stock'] ?? 0,
            'canceled_by_store' => $request['canceled_by_store'] ?? 0,
            'toggle_store_registration' => $request['store_self_registration'] ?? 0,
            'product_approval' => $request['product_approval'] ?? 0,
            'access_all_products' => $request['access_all_products'] ?? 0,
            'product_gallery' => $request['product_gallery'] ?? 0,
            'admin_website_builder_status' => $request['admin_website_builder_status'] ?? 0,
            'store_category_status' => $request['store_category_status'] ?? 0,
            'can_vendor_edit_order' => $request['can_vendor_edit_order'] ?? 0
        ];

        foreach ($storeSettings as $key => $value) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $value,
            ]);
        }

        $values = [
            'Update_product_price' => $request->update_existing_products ? ($request->Update_product_price ?? 0) : 0,
            'Add_new_product' => $request->Add_new_product ?? 0,
            'Update_product_variation' => $request->update_existing_products ? ($request->Update_product_variation ?? 0) : 0,
            'Update_anything_in_product_details' => $request->update_existing_products ? ($request->Update_anything_in_product_details ?? 0) : 0,
        ];

        Helpers::businessUpdateOrInsert(['key' => 'product_approval_datas'], [
            'value' => json_encode($values),
        ]);

        if ($reelsSettingsController) {
            $reelsSettingsController->persistStoreSettings($request);
        }



        Toastr::success(translate('messages.Successfully updated to changes restart app'));

        return back();
    }

    private function getReelsBusinessSettingsController(): ?object
    {
        $controllerClass = 'Modules\\ReelsModule\\Http\\Controllers\\ReelsBusinessSettingsController';

        if (!addon_published_status('ReelsModule')) {
            return null;
        }

        if (!class_exists($controllerClass)) {
            return null;
        }

        return app($controllerClass);
    }

    public function update_order(Request $request)
    {
        $request->validate([
            'home_delivery_status' => 'required_without:takeaway_status',
            'takeaway_status' => 'required_without:home_delivery_status',
        ]);
        $key_datas = [
            'order_cancelation_rate_limit_status' => 'order_cancelation_rate_limit_status',
            'order_cancelation_rate_block_limit' => 'order_cancelation_rate_block_limit',
            'order_cancelation_rate_warning_limit' => 'order_cancelation_rate_warning_limit',
            'order_delivery_verification' => 'odc',
            'schedule_order' => 'schedule_order',
            'prescription_order_status' => 'prescription_order_status',
            'home_delivery_status' => 'home_delivery_status',
            'takeaway_status' => 'takeaway_status',
            'schedule_order_slot_duration_time_format' => 'schedule_order_slot_duration_time_format',
            'extra_packaging_charge_status' => 'extra_packaging_charge_status',
            'repeat_order_option' => 'repeat_order_option',
            'monthly_order_reminder' => 'monthly_order_reminder',
            'product_bundle_status' => 'product_bundle_status',
        ];

        if ($request->order_cancelation_rate_limit_status && $request->order_cancelation_rate_warning_limit > $request->order_cancelation_rate_block_limit) {
            Toastr::error(translate('Providers will be blocked with out warning. Warning rate must be smaller.'));

            return back();
        }
        foreach ($key_datas as $key => $request_key) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $request->{$request_key} ?? 0,
            ]);
        }

        $time = $request['schedule_order_slot_duration'];
        if ($request['schedule_order_slot_duration_time_format'] == 'hour') {
            $time = $request['schedule_order_slot_duration'] * 60;
        }
        Helpers::businessUpdateOrInsert(['key' => 'schedule_order_slot_duration'], [
            'value' => $time,
        ]);
        $values = [];
        foreach (config('module.module_type') as $key => $value) {
            $values[$value] = $request[$value] ?? 0;
        }

        Helpers::businessUpdateOrInsert(['key' => 'extra_packaging_data'], [
            'value' => json_encode($values),
        ]);

        $bundleModules = [];
        foreach (BundleSettings::moduleTypes() as $moduleType) {
            $bundleModules[$moduleType] = $request->input('product_bundle_modules.'.$moduleType) ? 1 : 0;
        }

        Helpers::businessUpdateOrInsert(['key' => BundleSettings::MODULES_KEY], [
            'value' => json_encode($bundleModules),
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'order_confirmation_model'], [
            'value' => $request['order_confirmation_model'],
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'admin_order_notification'], [
            'value' => $request['admin_order_notification'],
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'order_notification_type'], [
            'value' => $request['order_notification_type'],
        ]);

        // Free delivery moved to a per-(zone, module) setup in S6 and these three keys were
        // deprecated with it — nothing reads them any more. The writes are gone so the screen
        // cannot leave an admin believing a setting still does something; the rows are left in
        // `business_settings` rather than deleted, so a rollback loses no data.

        Toastr::success(translate('messages.Successfully updated to changes restart app'));

        return back();
    }

    public function update_disbursement(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        $keys = [
            'disbursement_type',
            'store_disbursement_time_period',
            'store_disbursement_week_start',
            'store_disbursement_waiting_time',
            'store_disbursement_create_time',
            'store_disbursement_min_amount',
            'dm_disbursement_time_period',
            'dm_disbursement_week_start',
            'dm_disbursement_waiting_time',
            'dm_disbursement_create_time',
            'dm_disbursement_min_amount',
        ];


        foreach ($keys as $key) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $request->$key ?? 0,
            ]);
        }

        Toastr::success(translate('messages.Successfully updated disbursement functionality'));

        return back();
    }

    public function business_setup(Request $request)
    {
        if (getEnvMode() == 'demo')
        {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        foreach (['admin_commission', 'delivery_charge_comission', 'digit_after_decimal_point', 'additional_charge'] as $nonNegativeKey) {
            if ($request->filled($nonNegativeKey) && is_numeric($request->input($nonNegativeKey)) && $request->input($nonNegativeKey) < 0) {
                $request->merge([$nonNegativeKey => 0]);
            }
        }

        // TC_02 — a blank or unknown unit must be BLOCKED with a message. Without a rule the
        // fallbacks above would quietly rewrite it to km and report a successful save.
        $validator = Validator::make($request->all(), [
            'distance_unit' => 'required|in:km,mi',
            'weight_unit' => 'required|in:kg,lb',
            'dimension_unit' => 'required|in:cm,in',
        ], [
            'distance_unit.required' => translate('messages.Please_select_a_distance_unit'),
            'distance_unit.in' => translate('messages.Please_select_a_distance_unit'),
            'weight_unit.required' => translate('messages.Please_select_a_weight_unit'),
            'weight_unit.in' => translate('messages.Please_select_a_weight_unit'),
            'dimension_unit.required' => translate('messages.Please_select_a_dimension_unit'),
            'dimension_unit.in' => translate('messages.Please_select_a_dimension_unit'),
        ]);

        if ($validator->fails()) {
            Toastr::error($validator->errors()->first());

            return back()->withInput();
        }

        $this->updateBasicSettings($request);
        $this->updateImages($request);
        // Business Info does NOT write partial payment settings. Those fields live only on the
        // Payment tab and are saved by updatePaymentSetup(), which validates them. Writing them
        // from here read them off a request that never carries them, so every Business Info save
        // blanked partial_payment_status and partial_payment_method.
        $this->updateLocationSettings($request);
        $this->updateAdditionalChargeSettings($request);
        $this->updateBusinessModelSettings($request);

        Toastr::success(translate('messages.Successfully updated to changes restart app'));
        return back();
    }

    public function mail_index()
    {
        return view('admin-views.business-settings.mail-index');
    }

    public function test_mail()
    {
        return view('admin-views.business-settings.send-mail-index');
    }

    public function mail_config(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        Helpers::businessUpdateOrInsert(
            ['key' => 'mail_config'],
            [
                'value' => json_encode([
                    'status' => $request['status'] ?? 0,
                    'name' => $request['name'],
                    'host' => $request['host'],
                    'driver' => $request['driver'],
                    'port' => $request['port'],
                    'username' => $request['username'],
                    'email_id' => $request['email'],
                    'encryption' => $request['encryption'],
                    'password' => $request['password'],
                ]),
                'updated_at' => now(),
            ]
        );
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function mail_config_status(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        $config = BusinessSetting::where(['key' => 'mail_config'])->first();

        $data = $config ? json_decode($config['value'], true) : null;

        Helpers::businessUpdateOrInsert(
            ['key' => 'mail_config'],
            [
                'value' => json_encode([
                    'status' => $request['status'] ?? 0,
                    'name' => $data['name'] ?? '',
                    'host' => $data['host'] ?? '',
                    'driver' => $data['driver'] ?? '',
                    'port' => $data['port'] ?? '',
                    'username' => $data['username'] ?? '',
                    'email_id' => $data['email_id'] ?? '',
                    'encryption' => $data['encryption'] ?? '',
                    'password' => $data['password'] ?? '',
                ]),
                'updated_at' => now(),
            ]
        );
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function payment_index(Request $request)
    {
        $published_status = 0;
        $payment_published_status = config('get_payment_publish_status');
        if (isset($payment_published_status[0]['is_published'])) {
            $published_status = $payment_published_status[0]['is_published'];
        }

        $routes = config('addon_admin_routes');
        $desiredName = 'payment_setup';
        $payment_url = '';

        foreach ($routes as $routeArray) {
            foreach ($routeArray as $route) {
                if ($route['name'] === $desiredName) {
                    $payment_url = $route['url'];
                    break 2;
                }
            }
        }
        $data_values = Setting::whereIn('settings_type', ['payment_config'])
            ->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where('key_name', 'like', "%{$request->search}%");
            })
            ->paginate(config('default_pagination'));

        return view('admin-views.business-settings.payment-index', compact('published_status', 'payment_url', 'data_values'));
    }


    public function canTogglePaymentMethod($method, $newStatus)
    {
        if ($newStatus == 1) {
            return true;
        }

        $allMethods = Helpers::get_business_settings_many([
            'offline_payment_status',
            'cash_on_delivery',
            'digital_payment',
        ]);

        $activeCount = 0;

        foreach ($allMethods as $key => $value) {
            if ($key === $method) {
                continue;
            }

            if ($key === 'offline_payment_status') {
                $status = (int) $value;
            } else {
                $decoded = json_decode($value ?? '', true);
                $status = $decoded['status'] ?? 0;
            }

            if (is_array($status) && in_array(1, $status)) {
                $activeCount++;
            } elseif ($status == 1) {
                $activeCount++;
            }
        }

        return $activeCount > 0;
    }

    public function payment_config_update(Request $request)
    {
        if ($request->toggle_type) {
            if (!$this->canTogglePaymentMethod($request->toggle_type, $request->status)) {
                Toastr::error(translate('messages.Atleast one method must be active'));
                return back();
            }
            Helpers::businessUpdateOrInsert(['key' => $request->toggle_type], [
                'value' => $request->toggle_type == 'offline_payment_status' ? $request?->status : json_encode(['status' => $request?->status]),
                'updated_at' => now(),
            ]);
            Toastr::success(translate('messages.Payment settings updated'));

            return back();
        }

        if($request->payment_method_status){
            return $this->paymentMethodStatusUpdate($request);
        }

        $request['status'] = $request->status ?? 0;

        $validation = [
            'gateway' => 'required|in:ssl_commerz,paypal,stripe,razor_pay,senang_pay,paytabs,paystack,paymob_accept,paytm,flutterwave,liqpay,bkash,mercadopago',
            'mode' => 'required|in:live,test',
        ];

        $settings = Setting::where('key_name', $request['gateway'])->where('settings_type', 'payment_config')->first();
        $additional_data_image = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;
        $validator_image_rule = 'required';

        if ($additional_data_image != null && isset($additional_data_image->gateway_image)) {
            $validator_image_rule = 'nullable';
        }


        $additional_data = [];
        $validation_messages = [];
        $maxFileSizeInMB = MAX_FILE_SIZE * 1024;

        if ($request['gateway'] == 'ssl_commerz') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'store_id' => 'required_if:status,1',
                'store_password' => 'required_if:status,1',
            ];
            $validation_messages = [
                'store_id.required_if' => translate('Store ID is required when payment status is ON'),
                'store_password.required_if' => translate('Store password is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'paypal') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'client_id' => 'required_if:status,1',
                'client_secret' => 'required_if:status,1',
            ];
            $validation_messages = [
                'client_id.required_if' => translate('Client ID is required when payment status is ON'),
                'client_secret.required_if' => translate('Client secret is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'stripe') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'api_key' => 'required_if:status,1',
                'published_key' => 'required_if:status,1',
            ];
            $validation_messages = [
                'api_key.required_if' => translate('API key is required when payment status is ON'),
                'published_key.required_if' => translate('Published key is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'razor_pay') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'api_key' => 'required_if:status,1',
                'api_secret' => 'required_if:status,1',
            ];
            $validation_messages = [
                'api_key.required_if' => translate('API key is required when payment status is ON'),
                'api_secret.required_if' => translate('API secret is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'senang_pay') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'callback_url' => 'required_if:status,1',
                'secret_key' => 'required_if:status,1',
                'merchant_id' => 'required_if:status,1',
            ];
            $validation_messages = [
                'callback_url.required_if' => translate('Callback URL is required when payment status is ON'),
                'secret_key.required_if' => translate('Secret key is required when payment status is ON'),
                'merchant_id.required_if' => translate('Merchant ID is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'paytabs') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'profile_id' => 'required_if:status,1',
                'server_key' => 'required_if:status,1',
                'base_url' => 'required_if:status,1',
            ];
            $validation_messages = [
                'profile_id.required_if' => translate('Profile ID is required when payment status is ON'),
                'server_key.required_if' => translate('Server key is required when payment status is ON'),
                'base_url.required_if' => translate('Base URL is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'paystack') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'public_key' => 'required_if:status,1',
                'secret_key' => 'required_if:status,1',
                'merchant_email' => EmailAddress::rules('required_if:status,1'),
            ];
            $validation_messages = [
                'public_key.required_if' => translate('Public key is required when payment status is ON'),
                'secret_key.required_if' => translate('Secret key is required when payment status is ON'),
                'merchant_email.required_if' => translate('Merchant email is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'paymob_accept') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'callback_url' => 'required_if:status,1',
                'api_key' => 'required_if:status,1',
                'iframe_id' => 'required_if:status,1',
                'integration_id' => 'required_if:status,1',
                'hmac' => 'required_if:status,1',
            ];
            $validation_messages = [
                'callback_url.required_if' => translate('Callback URL is required when payment status is ON'),
                'api_key.required_if' => translate('API key is required when payment status is ON'),
                'iframe_id.required_if' => translate('Iframe ID is required when payment status is ON'),
                'integration_id.required_if' => translate('Integration ID is required when payment status is ON'),
                'hmac.required_if' => translate('HMAC is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'mercadopago') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'access_token' => 'required_if:status,1',
                'public_key' => 'required_if:status,1',
                'supported_country' => 'required_if:status,1',
            ];
            $validation_messages = [
                'access_token.required_if' => translate('Access token is required when payment status is ON'),
                'public_key.required_if' => translate('Public key is required when payment status is ON'),
                'supported_country.required_if' => translate('Supported country is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'liqpay') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'private_key' => 'required_if:status,1',
                'public_key' => 'required_if:status,1',
            ];
            $validation_messages = [
                'private_key.required_if' => translate('Private key is required when payment status is ON'),
                'public_key.required_if' => translate('Public key is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'flutterwave') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'secret_key' => 'required_if:status,1',
                'public_key' => 'required_if:status,1',
                'hash' => 'required_if:status,1',
            ];
            $validation_messages = [
                'secret_key.required_if' => translate('Secret key is required when payment status is ON'),
                'public_key.required_if' => translate('Public key is required when payment status is ON'),
                'hash.required_if' => translate('Hash is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'paytm') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'merchant_key' => 'required_if:status,1',
                'merchant_id' => 'required_if:status,1',
                'merchant_website_link' => 'required_if:status,1',
            ];
            $validation_messages = [
                'merchant_key.required_if' => translate('Merchant key is required when payment status is ON'),
                'merchant_id.required_if' => translate('Merchant ID is required when payment status is ON'),
                'merchant_website_link.required_if' => translate('Merchant website link is required when payment status is ON'),
            ];
        } elseif ($request['gateway'] == 'bkash') {
            $additional_data = [
                'gateway_image' => ImageFile::rules($validator_image_rule),
                'status' => 'required|in:1,0',
                'app_key' => 'required_if:status,1',
                'app_secret' => 'required_if:status,1',
                'username' => 'required_if:status,1',
                'password' => 'required_if:status,1',
            ];
            $validation_messages = [
                'app_key.required_if' => translate('App key is required when payment status is ON'),
                'app_secret.required_if' => translate('App secret is required when payment status is ON'),
                'username.required_if' => translate('Username is required when payment status is ON'),
                'password.required_if' => translate('Password is required when payment status is ON'),
            ];
        }

        $request->validate(array_merge($validation, $additional_data), $validation_messages);

        $settings = Setting::where('key_name', $request['gateway'])->where('settings_type', 'payment_config')->first();

        $additional_data_image = $settings['additional_data'] != null ? json_decode($settings['additional_data']) : null;

        if ($request->has('gateway_image')) {
            $gateway_image = $this->uploadFile('payment_modules/gateway_image/', 'png', $request['gateway_image'], $additional_data_image != null ? $additional_data_image->gateway_image : '');
        } else {
            $gateway_image = $additional_data_image != null ? $additional_data_image->gateway_image : '';
        }

        $payment_additional_data = [
            'gateway_title' => $request['gateway_title'],
            'gateway_image' => $gateway_image,
            'storage' => self::getDisk(),
        ];

        $validator = Validator::make($request->all(), array_merge($validation, $additional_data));

        $settings = Setting::firstOrNew(['key_name' => $request['gateway'], 'settings_type' => 'payment_config']);
        $settings->live_values = $validator->validate();
        $settings->test_values = $validator->validate();
        $settings->mode = $request['mode'];
        $settings->is_active = $request['status'];
        $settings->additional_data = json_encode($payment_additional_data);
        $settings->save();

        Toastr::success(GATEWAYS_DEFAULT_UPDATE_200['message']);

        return back();
    }

    public function app_settings()
    {
        return view('admin-views.business-settings.app-settings');
    }

    public function update_app_settings(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        if ($request->type == 'download_section') {
            $request->validate([
                'download_user_app_title.0' => 'required',
            ], [
                'download_user_app_title.0.required' => translate('messages.Default title is required'),
            ]);

            $this->getAddLandingPageData($request, 'app_settings', 'download_user_app_title', true);
            $this->getAddLandingPageData($request, 'app_settings', 'download_user_app_section_status', false);

            Toastr::success(translate('messages.Download section settings updated'));
            return back();
        }

        $appSettingsConfig = $this->getAppSettingsConfig();

        if (isset($appSettingsConfig[$request->type])) {
            $this->saveAppSettings($request, $appSettingsConfig[$request->type]);

            Toastr::success(translate($appSettingsConfig[$request->type]['message']));

            return back();
        }

        return back();
    }

    private function getAppSettingsConfig(): array
    {
        return [
            'user_app' => [
                'message' => 'messages.User_app_settings_updated',
                'fields' => [
                    'app_minimum_version_android',
                    'app_minimum_version_ios',
                    'app_url_android',
                    'app_url_ios',
                ],
                'sync_download_links' => false,
            ],
            'store_app' => [
                'message' => 'messages.Store_app_settings_updated',
                'fields' => [
                    'app_minimum_version_android_store',
                    'app_url_android_store',
                    'app_minimum_version_ios_store',
                    'app_url_ios_store',
                ],
                'sync_download_links' => true,
            ],
            'deliveryman_app' => [
                'message' => 'messages.Delivery_app_settings_updated',
                'fields' => [
                    'app_minimum_version_android_deliveryman',
                    'app_url_android_deliveryman',
                    'app_minimum_version_ios_deliveryman',
                    'app_url_ios_deliveryman',
                ],
                'sync_download_links' => true,
            ],
            'rider_app' => [
                'message' => 'messages.Rider_app_settings_updated',
                'fields' => [
                    'app_minimum_version_android_rider',
                    'app_url_android_rider',
                    'app_minimum_version_ios_rider',
                    'app_url_ios_rider',
                ],
                'sync_download_links' => true,
            ],
            'serviceman_app' => [
                'message' => 'messages.Serviceman_app_settings_updated',
                'fields' => [
                    'app_minimum_version_android_serviceman',
                    'app_url_android_serviceman',
                    'app_minimum_version_ios_serviceman',
                    'app_url_ios_serviceman',
                ],
                'sync_download_links' => true,
            ],
        ];
    }

    private function saveAppSettings(Request $request, array $config): void
    {
        foreach ($config['fields'] as $field) {
            Helpers::businessUpdateOrInsert(['key' => $field], [
                'value' => $request[$field],
            ]);
        }

        if (!empty($config['sync_download_links'])) {
            $this->syncCentralizedAppDownloadLinks();
        }
    }


    public function currency_index()
    {
        return view('admin-views.business-settings.currency-index');
    }

    private function syncCentralizedAppDownloadLinks(): void
    {
        $this->syncDownloadLinkSetting('admin_landing_page', 'seller_app_earning_links', [
            'playstore_url' => 'app_url_android_store',
            'apple_store_url' => 'app_url_ios_store',
        ]);

        $this->syncDownloadLinkSetting('admin_landing_page', 'dm_app_earning_links', [
            'playstore_url' => 'app_url_android_deliveryman',
            'apple_store_url' => 'app_url_ios_deliveryman',
        ]);

        $this->syncDownloadLinkSetting('admin_landing_page', 'rider_app_earning_links', [
            'playstore_url' => 'app_url_android_rider',
            'apple_store_url' => 'app_url_ios_rider',
        ]);

        $this->syncDownloadLinkSetting('admin_landing_page', 'serviceman_app_earning_links', [
            'playstore_url' => 'app_url_android_serviceman',
            'apple_store_url' => 'app_url_ios_serviceman',
        ]);

        $this->syncDownloadLinkSetting('react_landing_page', 'download_seller_app_links', [
            'playstore_url' => 'app_url_android_store',
            'apple_store_url' => 'app_url_ios_store',
        ]);

        $this->syncDownloadLinkSetting('react_landing_page', 'download_dm_app_links', [
            'playstore_url' => 'app_url_android_deliveryman',
            'apple_store_url' => 'app_url_ios_deliveryman',
        ]);

        $this->syncDownloadLinkSetting('react_landing_page', 'download_rider_app_links', [
            'playstore_url' => 'app_url_android_rider',
            'apple_store_url' => 'app_url_ios_rider',
        ]);

        $this->syncDownloadLinkSetting('react_landing_page', 'download_serviceman_app_links', [
            'playstore_url' => 'app_url_android_serviceman',
            'apple_store_url' => 'app_url_ios_serviceman',
        ]);
    }

    private function syncDownloadLinkSetting(string $type, string $key, array $businessKeyMap): void
    {
        $setting = DataSetting::firstOrNew(['type' => $type, 'key' => $key]);
        $links = json_decode($setting->getRawOriginal('value') ?? '[]', true);
        $links = is_array($links) ? $links : [];

        foreach ($businessKeyMap as $linkKey => $businessKey) {
            $links[$linkKey] = BusinessSetting::where('key', $businessKey)->value('value') ?? null;
        }

        $setting->value = json_encode($links);
        $setting->save();
    }

    private function update_data($request, $key_data)
    {
        $data = DataSetting::firstOrNew(
            [
                'key' => $key_data,
                'type' => 'admin_landing_page',
            ],
        );

        $data->value = $request->{$key_data}[array_search('default', $request->lang)];
        $data->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->{$key_data}[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\DataSetting',
                            'translationable_id' => $data->id,
                            'locale' => $key,
                            'key' => $key_data,
                        ],
                        ['value' => $data->getRawOriginal('value')]
                    );
                }
            } else {
                if ($request->{$key_data}[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\DataSetting',
                            'translationable_id' => $data->id,
                            'locale' => $key,
                            'key' => $key_data,
                        ],
                        ['value' => $request->{$key_data}[$index]]
                    );
                }
            }
        }

        return true;
    }

    private function policy_status_update($key_data, $status)
    {
        $data = DataSetting::firstOrNew(
            [
                'key' => $key_data,
                'type' => 'admin_landing_page',
            ],
        );
        $data->value = $status;
        $data->save();

        return true;
    }

    public function terms_and_conditions()
    {
        $terms_and_conditions = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'terms_and_conditions')->first();

        return view('admin-views.business-settings.terms-and-conditions', compact('terms_and_conditions'));
    }

    public function terms_and_conditions_update(Request $request)
    {
        $this->update_data($request, 'terms_and_conditions');
        Toastr::success(translate('messages.Terms and condition updated'));

        return back();
    }

    public function privacy_policy()
    {
        $privacy_policy = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'privacy_policy')->first();

        return view('admin-views.business-settings.privacy-policy', compact('privacy_policy'));
    }

    public function privacy_policy_update(Request $request)
    {
        $this->update_data($request, 'privacy_policy');
        Toastr::success(translate('messages.privacy_policy_updated'));

        return back();
    }

    public function refund_policy()
    {
        $refund_policy = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'refund_policy')->first();
        $refund_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'refund_policy_status')->first();

        return view('admin-views.business-settings.refund_policy', compact('refund_policy', 'refund_policy_status'));
    }

    public function refund_update(Request $request)
    {
        $this->update_data($request, 'refund_policy');
        Toastr::success(translate('messages.refund_policy_updated'));

        return back();
    }

    public function refund_policy_status($status)
    {
        $this->policy_status_update('refund_policy_status', $status);

        return response()->json(['status' => 'changed']);
    }

    public function shipping_policy()
    {

        $shipping_policy = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'shipping_policy')->first();
        $shipping_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'shipping_policy_status')->first();

        return view('admin-views.business-settings.shipping_policy', compact('shipping_policy', 'shipping_policy_status'));
    }

    public function shipping_policy_update(Request $request)
    {
        $this->update_data($request, 'shipping_policy');
        Toastr::success(translate('messages.shipping_policy_updated'));

        return back();
    }

    public function shipping_policy_status($status)
    {
        $this->policy_status_update('shipping_policy_status', $status);

        return response()->json(['status' => 'changed']);
    }

    public function cancellation_policy()
    {
        $cancellation_policy = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'cancellation_policy')->first();
        $cancellation_policy_status = DataSetting::where('type', 'admin_landing_page')->where('key', 'cancellation_policy_status')->first();

        return view('admin-views.business-settings.cancelation_policy', compact('cancellation_policy', 'cancellation_policy_status'));
    }

    public function cancellation_policy_update(Request $request)
    {
        $this->update_data($request, 'cancellation_policy');
        Toastr::success(translate('messages.cancellation_policy_updated'));

        return back();
    }

    public function cancellation_policy_status($status)
    {
        $this->policy_status_update('cancellation_policy_status', $status);

        return response()->json(['status' => 'changed']);
    }

    public function about_us()
    {
        $about_us = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'about_us')->first();
        $about_title = DataSetting::withoutGlobalScope('translate')->with('translations')->where('type', 'admin_landing_page')->where('key', 'about_title')->first();

        return view('admin-views.business-settings.about-us', compact('about_us', 'about_title'));
    }

    public function about_us_update(Request $request)
    {
        $this->update_data($request, 'about_us');
        $this->update_data($request, 'about_title');
        Toastr::success(translate('messages.About us updated'));

        return back();
    }

    public function fcm_index(Request $request)
    {
        abort_if($request?->module_type == 'rental' && !addon_published_status('Rental'), 404);
        abort_if($request?->module_type == 'ride-share' && !addon_published_status('RideShare'), 404);
        abort_if($request?->module_type == 'service' && !addon_published_status('Service'), 404);

        $moduleType = $request->module_type ?? 'grocery';
        if ($moduleType == 'ride-share' && addon_published_status('RideShare')) {

            $language = Helpers::get_business_settings('language', false);
            $langs = json_decode($language);
            $defaultLang = $langs[0];
            $cacheKey = 'fcm_notification_form_html_' . $moduleType . '_' . implode('_', $langs);

            $formHtml = ApiCache::remember('fcm_form_html', $cacheKey, function () use ($langs, $defaultLang, $moduleType) {
                $notificationMessages = NotificationMessage::with('translations')
                    ->where('module_type', $moduleType)
                    ->get()
                    ->keyBy('key');

                return view('admin-views.business-settings.partials.fcm-ride-share-form', [
                    'language' => $langs,
                    'defaultLang' => $defaultLang,
                    'mod_type' => $moduleType,
                    'notificationMessages' => $notificationMessages,
                ])->render();
            });

            return view('admin-views.business-settings.fcm-index-ride-share', compact('formHtml', 'langs', 'defaultLang','language'));
        }
        if($moduleType == 'rental' && addon_published_status('Rental')) {
            return view('admin-views.business-settings.fcm-index-rental');
        }

        $subscription_reminder_before_time = DataSetting::where(['key' => 'subscription_reminder_before_time', 'type' => 'notification_settings'])->first()?->value ?? 0;
        $subscription_reminder_before = DataSetting::where(['key' => 'subscription_reminder_before', 'type' => 'notification_settings'])->first()?->value ?? 'days';

        $subscription_reminder_enabled = NotificationMessage::where('key', 'subscription_expire_reminder')
            ->where('status', 1)
            ->exists();

        if ($moduleType == 'service' && addon_published_status('Service')) {
            return view('admin-views.business-settings.fcm-index-service', compact(
                'subscription_reminder_before_time',
                'subscription_reminder_before',
                'subscription_reminder_enabled'
            ));
        }

        $monthly_order_reminder = NotificationMessage::where('key', 'monthly_order_reminder')->first();

        return view('admin-views.business-settings.fcm-index', compact(
            'subscription_reminder_before_time',
            'subscription_reminder_before',
            'subscription_reminder_enabled',
            'monthly_order_reminder'
        ));
    }

    public function fcm_config()
    {
        $fcm_credentials = Helpers::get_business_settings('fcm_credentials');

        return view('admin-views.business-settings.fcm-config', compact('fcm_credentials'));
    }

    public function update_fcm(Request $request)
    {
        Helpers::businessUpdateOrInsert(['key' => 'push_notification_service_file_content'], [
            'value' => $request['push_notification_service_file_content'],
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'fcm_project_id'], [
            'value' => $request['projectId'],
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'fcm_credentials'], [
            'value' => json_encode([
                'apiKey' => $request->apiKey,
                'authDomain' => $request->authDomain,
                'projectId' => $request->projectId,
                'storageBucket' => $request->storageBucket,
                'messagingSenderId' => $request->messagingSenderId,
                'appId' => $request->appId,
                'measurementId' => $request->measurementId,
                'vapidKey' => $request->vapidKey,
            ]),
        ]);
        WebPushServiceWorker::generate();
        Toastr::success(translate('messages.Settings updated'));

        return back();
    }


    public function update_fcm_messages(Request $request)
    {
        $moduleType = $request->module_type;
        $languages = $request->lang ?? [];
        $enIndex = array_search('en', $languages);

        $messages = [
            ['pending_message',                'pending_status',                      'order_pending_message',          true],
            ['confirm_message',                'confirm_status',                      'order_confirmation_msg',         true],
            ['processing_message',             'processing_status',                   'order_processing_message',       false],
            ['order_handover_message',         'order_handover_message_status',       'order_handover_message',         false],
            ['order_refunded_message',         'order_refunded_message_status',       'order_refunded_message',         false],
            ['refund_request_canceled',        'refund_request_canceled_status',      'refund_request_canceled',        false],
            ['out_for_delivery_message',       'out_for_delivery_status',             'out_for_delivery_message',       true],
            ['delivered_message',              'delivered_status',                    'order_delivered_message',        true],
            ['delivery_boy_assign_message',    'delivery_boy_assign_status',          'delivery_boy_assign_message',    true],
            ['delivery_boy_delivered_message', 'delivery_boy_delivered_status',       'delivery_boy_delivered_message', true],
            ['order_cancled_message',          'order_cancled_message_status',        'order_cancled_message',          true],
            ['offline_order_accept_message',   'offline_order_accept_message_status', 'offline_order_accept_message',   true],
            ['offline_order_deny_message',     'offline_order_deny_message_status',   'offline_order_deny_message',     true],
        ];

        foreach ($messages as [$requestKey, $statusKey, $dbKey, $parcelSupported]) {
            if (!$parcelSupported && $moduleType == 'parcel') {
                continue;
            }

            $notification = NotificationMessage::where('module_type', $moduleType)
                ->where('key', $dbKey)
                ->first() ?? new NotificationMessage;
            $notification->key = $dbKey;
            $notification->module_type = $moduleType;
            $this->writeNotificationFields($notification, $request, $requestKey, $statusKey, $languages, $enIndex);
        }

        if ($request->has('monthly_order_reminder')) {
            $monthlyReminder = NotificationMessage::where('key', 'monthly_order_reminder')->first() ?? new NotificationMessage;
            $monthlyReminder->key = 'monthly_order_reminder';
            $monthlyReminder->module_type = null;
            $this->writeNotificationFields($monthlyReminder, $request, 'monthly_order_reminder', 'monthly_order_reminder_status', $languages, $enIndex);

            Helpers::businessUpdateOrInsert(['key' => 'monthly_order_reminder_days_before'], [
                'value' => $request->monthly_order_reminder_days_before ?? 3,
            ]);
            Helpers::businessUpdateOrInsert(['key' => 'monthly_order_reminder_before_unit'], [
                'value' => $request->monthly_order_reminder_before_unit ?? 'day',
            ]);
        }

        if (Helpers::get_business_settings('pro_member_status') == 1) {
            if ($request->has('subscription_reminder_before_time')) {
                DataSetting::updateOrInsert(
                    ['key' => 'subscription_reminder_before_time', 'type' => 'notification_settings'],
                    ['value' => $request->subscription_reminder_before_time]
                );
            }
            if ($request->has('subscription_reminder_before')) {
                DataSetting::updateOrInsert(
                    ['key' => 'subscription_reminder_before', 'type' => 'notification_settings'],
                    ['value' => $request->subscription_reminder_before]
                );
            }

            $subscriptionKeys = [
                'subscription_expire_reminder' => 'subscription_expire_reminder_status',
                'subscription_activated'       => 'subscription_activated_status',
                'subscription_expired'         => 'subscription_expired_status',
                'subscription_canceled'        => 'subscription_canceled_status',
            ];

            foreach ($subscriptionKeys as $msgKey => $statusKey) {
                if (!$request->has($msgKey)) {
                    continue;
                }

                $notification = NotificationMessage::where('key', $msgKey)->first() ?? new NotificationMessage;
                $notification->key = $msgKey;
                $notification->module_type = null;
                $this->writeNotificationFields($notification, $request, $msgKey, $statusKey, $languages, $enIndex);
            }
        }

        Toastr::success(translate('messages.Message updated'));

        return back();
    }

    private function writeNotificationFields(NotificationMessage $notification, Request $request, string $requestKey, string $statusKey, array $languages, $enIndex): void
    {
        $messages = $request->input($requestKey, []);
        $notification->message = $messages[$enIndex] ?? '';
        $notification->status = $request->input($statusKey) == 1 ? 1 : 0;
        $notification->save();

        foreach ($languages as $index => $locale) {
            if (!empty($messages[$index])) {
                Translation::updateOrInsert(
                    [
                        'translationable_type' => 'App\Models\NotificationMessage',
                        'translationable_id' => $notification->id,
                        'locale' => $locale,
                        'key' => $notification->key,
                    ],
                    ['value' => $messages[$index]]
                );
            }
        }
    }

    public function update_fcm_messages_rental(Request $request)
    {
        $messageKeys = [
            'trip_pending_message' => 'trip_pending_message',
            'trip_confirm_message' => 'trip_confirm_message',
            'trip_ongoing_message' => 'trip_ongoing_message',
            'trip_complete_message' => 'trip_complete_message',
            'trip_cancel_message' => 'trip_cancel_message',
        ];

        foreach ($messageKeys as $requestKey => $notificationKey) {

            $notification = NotificationMessage::firstOrNew([
                'module_type' => 'rental',
                'key' => $notificationKey,
            ]);

            $notification->message = $request[$requestKey][array_search('en', $request->lang)];
            $notification->status = isset($request[$requestKey . '_status']) && $request[$requestKey . '_status'] == 1 ? 1 : 0;
            $notification->save();

            foreach ($request->lang as $index => $locale) {
                if (!empty($request[$requestKey][$index])) {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => NotificationMessage::class,
                            'translationable_id' => $notification->id,
                            'locale' => $locale,
                            'key' => $notificationKey,
                        ],
                        ['value' => $request[$requestKey][$index]]
                    );
                }
            }
        }

        Toastr::success(translate('messages.Message updated'));

        return back();
    }

    public function update_fcm_messages_service(Request $request)
    {
        $languages = $request->lang ?? [];
        $enIndex = array_search('en', $languages);

        $messageKeys = [
            'booking_place_message',
            'booking_accepted_message',
            'booking_ongoing_message',
            'serviceman_assign_message',
            'booking_complete_message',
            'booking_cancel_message',
            'schedule_booking_time_change_message',
            'booking_service_location_change_message',
            'customized_booking_request_message',
            'customized_booking_delete_message',
            'provider_bid_offer_message',
            'provider_bid_withdraw_message',
            'booking_edit_service_add_message',
            'booking_edit_service_remove_message',
        ];

        foreach ($messageKeys as $key) {
            $notification = NotificationMessage::where('module_type', 'service')->where('key', $key)->first() ?? new NotificationMessage;
            $notification->key = $key;
            $notification->module_type = 'service';
            $this->writeNotificationFields($notification, $request, $key, $key . '_status', $languages, $enIndex);
        }

        if (Helpers::get_business_settings('pro_member_status') == 1) {
            if ($request->has('subscription_reminder_before_time')) {
                DataSetting::updateOrInsert(
                    ['key' => 'subscription_reminder_before_time', 'type' => 'notification_settings'],
                    ['value' => $request->subscription_reminder_before_time]
                );
            }
            if ($request->has('subscription_reminder_before')) {
                DataSetting::updateOrInsert(
                    ['key' => 'subscription_reminder_before', 'type' => 'notification_settings'],
                    ['value' => $request->subscription_reminder_before]
                );
            }

            $subscriptionKeys = [
                'subscription_expire_reminder' => 'subscription_expire_reminder_status',
                'subscription_activated'       => 'subscription_activated_status',
                'subscription_expired'         => 'subscription_expired_status',
                'subscription_canceled'        => 'subscription_canceled_status',
            ];

            foreach ($subscriptionKeys as $msgKey => $statusKey) {
                if (!$request->has($msgKey)) {
                    continue;
                }

                $notification = NotificationMessage::where('key', $msgKey)->first() ?? new NotificationMessage;
                $notification->key = $msgKey;
                $notification->module_type = null;
                $this->writeNotificationFields($notification, $request, $msgKey, $statusKey, $languages, $enIndex);
            }
        }

        Toastr::success(translate('messages.Message updated'));

        return back();
    }

    public function update_fcm_messages_ride_share(Request $request)
    {
        $request->validate([
            'module_type' => 'required|string',
            'lang' => 'required|array',
            'lang.*' => 'required|string',
        ]);

        $moduleType = $request->module_type;
        $activeLanguages = $request->lang;

        $defaultLangIndex = array_search('en', $activeLanguages);
        if ($defaultLangIndex === false && !empty($activeLanguages)) {
            $defaultLangIndex = 0;
        } else if (empty($activeLanguages)) {
            Toastr::error(translate('No data found'));
            return back();
        }

        $notificationUserTypes = [
            'customer' => NOTIFICATION_FOR_RIDE_SHARE_CUSTOMER,
            'driver' => NOTIFICATION_FOR_RIDE_SHARE_DRIVER,
            'driver_registration' => NOTIFICATION_FOR_RIDE_SHARE_DRIVER_REGISTRATION,
            'other' => NOTIFICATION_FOR_RIDE_SHARE_OTHERS,
        ];

        DB::beginTransaction();

        try {
            foreach ($notificationUserTypes as $userType => $notificationsArray) {
                foreach ($notificationsArray as $notificationConfig) {
                    $baseNotificationKey = $notificationConfig['key'];
                    $dbNotificationKey = $userType . '_' . $baseNotificationKey;
                    $messageInputName = $userType . '_' . $baseNotificationKey . '_message';
                    $statusInputName = $userType . '_' . $baseNotificationKey . '_status';

                    $baseMessageContent = $request->input($messageInputName)[$defaultLangIndex] ?? null;
                    $status = $request->has($statusInputName) ? 1 : 0;

                    $notification = NotificationMessage::updateOrCreate(
                        [
                            'module_type' => $moduleType,
                            'key' => $dbNotificationKey,
                        ],
                        [
                            'message' => $baseMessageContent,
                            'status' => $status,
                        ]
                    );

                    $translationsData = [];
                    foreach ($activeLanguages as $langIndex => $locale) {
                        $translatedMessage = $request->input($messageInputName)[$langIndex] ?? '';

                        if ($translatedMessage !== '') {
                            $translationsData[] = [
                                'translationable_type' => 'App\Models\NotificationMessage',
                                'translationable_id' => $notification->id,
                                'locale' => $locale,
                                'key' => $dbNotificationKey,
                                'value' => $translatedMessage,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        Translation::where('translationable_type', 'App\Models\NotificationMessage')
                            ->where('translationable_id', $notification->id)
                            ->where('locale', $locale)
                            ->where('key', $dbNotificationKey)
                            ->delete();
                    }

                    if (!empty($translationsData)) {
                        Translation::upsert(
                            $translationsData,
                            ['translationable_type', 'translationable_id', 'locale', 'key'],
                            ['value', 'updated_at']
                        );
                    }
                }
            }

            DB::commit();

            $cacheKey = 'fcm_notification_form_html_' . $moduleType . '_' . implode('_', $activeLanguages);
            ApiCache::forget('fcm_form_html', $cacheKey);

            Toastr::success(translate('Updated successfully'));
            return back();

        } catch (Throwable $e) {
            DB::rollBack();
            \Log::error('error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            Toastr::error(translate('Notification update failed'));
            return back();
        }
    }

    public function location_setup(Request $request)
    {
        $store = Helpers::get_store_id();
        $store->latitude = $request['latitude'];
        $store->longitude = $request['longitude'];
        $store->save();

        Toastr::success(translate('messages.Settings updated'));

        return back();
    }

    public function config_setup()
    {
        return view('admin-views.business-settings.config');
    }

    public function config_update(Request $request)
    {
        Helpers::businessUpdateOrInsert(['key' => 'map_api_key'], [
            'value' => $request['map_api_key'],
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'map_api_key_server'], [
            'value' => $request['map_api_key_server'],
        ]);

        Toastr::success(translate('messages.Config data updated'));

        return back();
    }

    public function toggle_settings($key, $value)
    {
        Helpers::businessUpdateOrInsert(['key' => $key], [
            'value' => $value,
        ]);

        Toastr::success(translate('messages.App settings updated'));

        return back();
    }

    public function viewSocialLogin()
    {
        $data = BusinessSetting::where('key', 'social_login')->first();
        if (!$data) {
            Helpers::insert_business_settings_key('social_login', '[{"login_medium":"google","client_id":"","client_secret":"","status":"0"},{"login_medium":"facebook","client_id":"","client_secret":"","status":""}]');
            $data = BusinessSetting::where('key', 'social_login')->first();
        }
        $apple = BusinessSetting::where('key', 'apple_login')->first();
        if (!$apple) {
            Helpers::insert_business_settings_key('apple_login', '[{"login_medium":"apple","client_id":"","client_secret":"","team_id":"","key_id":"","service_file":"","redirect_url":"","status":""}]');
            $apple = BusinessSetting::where('key', 'apple_login')->first();
        }
        $appleLoginServices = json_decode($apple->value, true);
        $socialLoginServices = json_decode($data->value, true);

        return view('admin-views.business-settings.social-login.view', compact('socialLoginServices', 'appleLoginServices'));
    }

    public function updateSocialLogin($service, Request $request)
    {
        $login_setup_status = Helpers::get_business_settings($service . '_login_status') ?? 0;
        if ($login_setup_status && ($request['status'] == 0)) {
            Toastr::warning(translate($service . '_login_status_is_enabled_in_login_setup._First_disable_from_login_setup.'));

            return redirect()->back();
        }
        $socialLogin = BusinessSetting::where('key', 'social_login')->first();
        $credential_array = [];
        foreach (json_decode($socialLogin['value'], true) as $key => $data) {
            if ($data['login_medium'] == $service) {
                $cred = [
                    'login_medium' => $service,
                    'client_id' => $request['client_id'],
                    'client_secret' => $request['client_secret'],
                    'status' => $request['status'],
                ];
                array_push($credential_array, $cred);
            } else {
                array_push($credential_array, $data);
            }
        }

        Helpers::businessUpdateOrInsert(['key' => 'social_login'], [
            'value' => $credential_array,
        ]);

        Toastr::success(translate('messages.Credential updated'));

        return redirect()->back();
    }

    public function updateAppleLogin($service, Request $request)
    {
        $appleLogin = BusinessSetting::where('key', 'apple_login')->firstOrNew(['key' => 'apple_login']);
        $credential_array = [];
        if ($request->hasfile('service_file')) {
            $fileName = Helpers::upload('apple-login/', 'p8', $request->file('service_file'));
        }
        foreach (json_decode($appleLogin['value'], true) as $key => $data) {
            if ($data['login_medium'] == $service) {
                $cred = [
                    'login_medium' => $service,
                    'client_id' => $request['client_id'],
                    'client_id_app' => $request['client_id_app'],
                    'client_secret' => $request['client_secret'],
                    'status' => $request['status'],
                    'team_id' => $request['team_id'],
                    'key_id' => $request['key_id'],
                    'service_file' => isset($fileName) ? $fileName : $data['service_file'],
                    'redirect_url_flutter' => $request['redirect_url_flutter'],
                    'redirect_url_react' => $request['redirect_url_react'],
                ];
                array_push($credential_array, $cred);
            } else {
                array_push($credential_array, $data);
            }
        }
        $appleLogin->value = $credential_array;

        $appleLogin->save();

        Toastr::success(translate('messages.Credential updated'));

        return redirect()->back();
    }

    public function login_settings()
    {
        $data = Helpers::get_business_settings_many([
            'manual_login_status',
            'otp_login_status',
            'social_login_status',
            'google_login_status',
            'facebook_login_status',
            'apple_login_status',
            'email_verification_status',
            'phone_verification_status',
            'send_otp_via',
        ]);

        $social_login = [];
        foreach (['social_login', 'apple_login'] as $login_key) {
            foreach (Helpers::get_business_settings($login_key) ?? [] as $social) {
                $social_login[$social['login_medium']] = (bool) $social['status'];
            }
        }

        $google_login_status = (bool) ($social_login['google'] ?? false);
        $facebook_login_status = (bool) ($social_login['facebook'] ?? false);
        $apple_login_status = (bool) ($social_login['apple'] ?? false);
        $is_firebase_active = (bool) (Helpers::get_business_settings('firebase_otp_verification') ?? 0);
        $is_sms_active = app(SettingService::class)->hasActiveSmsGateway();
        $is_mail_active = (bool) config('mail.status');

        return view('admin-views.login-setup.login_page', compact(
            'data',
            'google_login_status',
            'facebook_login_status',
            'apple_login_status',
            'is_firebase_active',
            'is_sms_active',
            'is_mail_active'
        ));
    }

    public function login_settings_update(Request $request)
    {
        $social_login = [];
        foreach (['social_login', 'apple_login'] as $login_key) {
            foreach (Helpers::get_business_settings($login_key) ?? [] as $social) {
                $social_login[$social['login_medium']] = (bool) $social['status'];
            }
        }

        $manual_login_status = (bool) $request['manual_login_status'];
        $otp_login_status = (bool) $request['otp_login_status'];
        $social_login_status = (bool) $request['social_login_status'];
        $google_login_status = (bool) $request['google_login_status'];
        $facebook_login_status = (bool) $request['facebook_login_status'];
        $apple_login_status = (bool) $request['apple_login_status'];
        $email_verification_status = (bool) $request['email_verification_status'];
        $phone_verification_status = (bool) $request['phone_verification_status'];

        $is_firebase_active = (bool) (Helpers::get_business_settings('firebase_otp_verification') ?? 0);
        $is_sms_active = app(SettingService::class)->hasActiveSmsGateway();
        $is_mail_active = (bool) config('mail.status');

        $selected_socials = [
            'google' => $google_login_status,
            'facebook' => $facebook_login_status,
            'apple' => $apple_login_status,
        ];
        $is_misconfigured = fn (string $medium) => $social_login_status
            && $selected_socials[$medium]
            && empty($social_login[$medium]);

        $response = match (true) {
            ! $manual_login_status && ! $otp_login_status && ! $social_login_status => (function () {
                Session::flash('select-one-method', true);

                return back();
            })(),
            $otp_login_status && ! $is_sms_active && ! $is_firebase_active => (function () {
                Session::flash('sms-config', true);

                return back();
            })(),
            $otp_login_status && ! $phone_verification_status => (function () {
                Toastr::error(translate('messages.Phone verification is required when OTP login is enabled.'));

                return back()->withInput();
            })(),
            ! $manual_login_status && ! $otp_login_status && $social_login_status && ! $google_login_status && ! $facebook_login_status => (function () {
                Session::flash('select-one-method-android', true);

                return back();
            })(),
            $social_login_status && ! $google_login_status && ! $facebook_login_status && ! $apple_login_status => (function () {
                Session::flash('select-one-method-social-login', true);

                return back();
            })(),
            $is_misconfigured('google') => (function () {
                Session::flash('setup-google', true);

                return back();
            })(),
            $is_misconfigured('facebook') => (function () {
                Session::flash('setup-facebook', true);

                return back();
            })(),
            $is_misconfigured('apple') => (function () {
                Session::flash('setup-apple', true);

                return back();
            })(),
            $phone_verification_status && ! $is_sms_active && ! $is_firebase_active => (function () {
                Session::flash('sms-config-verification', true);

                return back();
            })(),
            $email_verification_status && ! $is_mail_active => (function () {
                Session::flash('mail-config-verification', true);

                return back();
            })(),
            default => null,
        };

        if ($response) {
            return $response;
        }

        $settings = [
            'manual_login_status' => $manual_login_status ? 1 : 0,
            'otp_login_status' => $otp_login_status ? 1 : 0,
            'social_login_status' => $social_login_status ? 1 : 0,
            'google_login_status' => $social_login_status && $google_login_status ? 1 : 0,
            'facebook_login_status' => $social_login_status && $facebook_login_status ? 1 : 0,
            'apple_login_status' => $social_login_status && $apple_login_status ? 1 : 0,
            'email_verification_status' => $email_verification_status ? 1 : 0,
            'phone_verification_status' => $phone_verification_status ? 1 : 0,
            'send_otp_via' => $request['send_otp_via'] ?? 'sms',
        ];

        foreach ($settings as $key => $value) {
            Helpers::businessUpdateOrInsert(['key' => $key], ['value' => $value]);
        }

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function recaptcha_index(Request $request)
    {
        return view('admin-views.business-settings.recaptcha-index');
    }

    public function recaptcha_update(Request $request)
    {
        Helpers::businessUpdateOrInsert(['key' => 'recaptcha'], [
            'key' => 'recaptcha',
            'value' => json_encode([
                'status' => $request['status'],
                'site_key' => $request['site_key'],
                'secret_key' => $request['secret_key'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Toastr::success(translate('messages.Updated successfully'));

        return back();
    }


    public function firebase_otp_index(Request $request)
    {
        $is_sms_active = app(SettingService::class)->hasActiveSmsGateway();
        $is_mail_active = config('mail.status');

        return view('admin-views.business-settings.firebase-otp-index', compact('is_sms_active', 'is_mail_active'));
    }

    public function firebase_otp_update(Request $request)
    {
        $login_setup_status = Helpers::get_business_settings('otp_login_status') ?? 0;
        $phone_verification_status = Helpers::get_business_settings('phone_verification_status') ?? 0;
        $is_sms_active = app(SettingService::class)->hasActiveSmsGateway();
        if (!$is_sms_active && $login_setup_status && ($request['firebase_otp_verification'] == 0)) {
            Toastr::warning(translate('Otp login status is enabled in login setup. First disable from login setup.'));

            return redirect()->back();
        }
        if (!$is_sms_active && $phone_verification_status && ($request['firebase_otp_verification'] == 0)) {
            Toastr::warning(translate('Phone verification status is enabled in login setup. First disable from login setup.'));

            return redirect()->back();
        }
        Helpers::businessUpdateOrInsert(['key' => 'firebase_otp_verification'], [
            'value' => $request['firebase_otp_verification'] ?? 0,
        ]);
        Helpers::businessUpdateOrInsert(['key' => 'firebase_web_api_key'], [
            'value' => $request['firebase_web_api_key'],
        ]);

        Toastr::success(translate('messages.Updated successfully'));

        return back();
    }

    public function storage_connection_index(Request $request)
    {
        return view('admin-views.business-settings.storage-connection-index');
    }

    public function storage_connection_update(Request $request, $name)
    {
        if ($name == 'local_storage') {
            Helpers::businessUpdateOrInsert(['key' => 'local_storage'], [
                'key' => 'local_storage',
                'value' => $request->status ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Helpers::businessUpdateOrInsert(['key' => '3rd_party_storage'], [
                'key' => '3rd_party_storage',
                'value' => $request->status == '1' ? 0 : 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($name == '3rd_party_storage') {
            Helpers::businessUpdateOrInsert(['key' => '3rd_party_storage'], [
                'key' => '3rd_party_storage',
                'value' => $request->status ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Helpers::businessUpdateOrInsert(['key' => 'local_storage'], [
                'key' => 'local_storage',
                'value' => $request->status == '1' ? 0 : 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        if ($name == 'storage_connection') {
            Helpers::businessUpdateOrInsert(['key' => 's3_credential'], [
                'key' => 's3_credential',
                'value' => json_encode([
                    'key' => $request['key'],
                    'secret' => $request['secret'],
                    'region' => $request['region'],
                    'bucket' => $request['bucket'],
                    'url' => $request['url'],
                    'end_point' => $request['end_point'],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Toastr::success(translate('messages.Updated successfully'));

        return back();
    }

    public function send_mail(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        $response_flag = 0;
        try {
            SendNotification::mail($request->email, new \App\Mail\TestEmailSender);
            $response_flag = 1;
        } catch (\Exception $exception) {
            Log::error('admin.business_settings_controller.send_mail_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
            $response_flag = 2;
        }

        return response()->json(['success' => $response_flag]);
    }

    public function site_direction(Request $request)
    {
        if (getEnvMode() == 'demo') {
            session()->put('site_direction', ($request->status == 1 ? 'ltr' : 'rtl'));

            return response()->json();
        }
        if ($request->status == 1) {
            Helpers::businessUpdateOrInsert(['key' => 'site_direction'], [
                'value' => 'ltr',
            ]);
        } else {
            Helpers::businessUpdateOrInsert(['key' => 'site_direction'], [
                'value' => 'rtl',
            ]);
        }

    }

    public function admin_landing_page_settings($tab)
    {
        $landingData = [];
        $language = [];
        $base = 'admin-views.business-settings.landing-page-settings.';

        $landings = [
            'why-choose-us' => 'admin-landing-why-choose',
            'available-zone' => 'admin-landing-available-zone',
            'download-apps' => 'admin-landing-download-apps',
            'testimonials' => 'admin-landing-testimonial',
            'contact-us' => 'admin-landing-contact',
            'background-color' => 'admin-landing-background-color',
        ];

        $view = $landings[$tab] ?? 'admin-' . str_replace('_', '-', $tab);

        if (!view()->exists($base . $view)) {
            abort(404);
        }
        if ($tab == 'meta-data') {
            $landingData = DataSetting::withoutGlobalScope('translate')->withStorage()->with('translations')->where('type', 'admin_landing_page')->whereIn('key', ['meta_title', 'meta_description', 'meta_image','meta_data'])->get()->keyBy('key') ?? [];
            $language = Helpers::get_business_settings('language');
        }

        return view($base . $view, compact('landingData', 'language'));

    }

    public function update_admin_landing_page_settings(Request $request, $tab)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        if ($tab == 'fixed-data') {
            $fixed_header_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_title')->first();
            if ($fixed_header_title == null) {
                $fixed_header_title = new DataSetting;
            }

            $fixed_header_title->key = 'fixed_header_title';
            $fixed_header_title->type = 'admin_landing_page';
            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
            $fixed_header_title->save();

            $fixed_header_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_header_sub_title')->first();
            if ($fixed_header_sub_title == null) {
                $fixed_header_sub_title = new DataSetting;
            }

            $fixed_header_sub_title->key = 'fixed_header_sub_title';
            $fixed_header_sub_title->type = 'admin_landing_page';
            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
            $fixed_header_sub_title->save();

            $fixed_module_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_title')->first();
            if ($fixed_module_title == null) {
                $fixed_module_title = new DataSetting;
            }

            $fixed_module_title->key = 'fixed_module_title';
            $fixed_module_title->type = 'admin_landing_page';
            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
            $fixed_module_title->save();

            $fixed_module_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_module_sub_title')->first();
            if ($fixed_module_sub_title == null) {
                $fixed_module_sub_title = new DataSetting;
            }

            $fixed_module_sub_title->key = 'fixed_module_sub_title';
            $fixed_module_sub_title->type = 'admin_landing_page';
            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
            $fixed_module_sub_title->save();

            $fixed_referal_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_referal_title')->first();
            if ($fixed_referal_title == null) {
                $fixed_referal_title = new DataSetting;
            }

            $fixed_referal_title->key = 'fixed_referal_title';
            $fixed_referal_title->type = 'admin_landing_page';
            $fixed_referal_title->value = $request->fixed_referal_title[array_search('default', $request->lang)];
            $fixed_referal_title->save();



            $fixed_newsletter_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_title')->first();
            if ($fixed_newsletter_title == null) {
                $fixed_newsletter_title = new DataSetting;
            }

            $fixed_newsletter_title->key = 'fixed_newsletter_title';
            $fixed_newsletter_title->type = 'admin_landing_page';
            $fixed_newsletter_title->value = $request->fixed_newsletter_title[array_search('default', $request->lang)];
            $fixed_newsletter_title->save();

            $fixed_newsletter_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_newsletter_sub_title')->first();
            if ($fixed_newsletter_sub_title == null) {
                $fixed_newsletter_sub_title = new DataSetting;
            }

            $fixed_newsletter_sub_title->key = 'fixed_newsletter_sub_title';
            $fixed_newsletter_sub_title->type = 'admin_landing_page';
            $fixed_newsletter_sub_title->value = $request->fixed_newsletter_sub_title[array_search('default', $request->lang)];
            $fixed_newsletter_sub_title->save();

            $fixed_footer_article_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'fixed_footer_article_title')->first();
            if ($fixed_footer_article_title == null) {
                $fixed_footer_article_title = new DataSetting;
            }

            $fixed_footer_article_title->key = 'fixed_footer_article_title';
            $fixed_footer_article_title->type = 'admin_landing_page';
            $fixed_footer_article_title->value = $request->fixed_footer_article_title[array_search('default', $request->lang)];
            $fixed_footer_article_title->save();

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title',
                            ],
                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title',
                            ],
                            ['value' => $request->fixed_header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title',
                            ],
                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title',
                            ],
                            ['value' => $request->fixed_header_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title',
                            ],
                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title',
                            ],
                            ['value' => $request->fixed_module_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title',
                            ],
                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title',
                            ],
                            ['value' => $request->fixed_module_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_referal_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title',
                            ],
                            ['value' => $fixed_referal_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_referal_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_referal_title->id,
                                'locale' => $key,
                                'key' => 'fixed_referal_title',
                            ],
                            ['value' => $request->fixed_referal_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title',
                            ],
                            ['value' => $fixed_newsletter_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_title',
                            ],
                            ['value' => $request->fixed_newsletter_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_newsletter_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title',
                            ],
                            ['value' => $fixed_newsletter_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_newsletter_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_newsletter_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_newsletter_sub_title',
                            ],
                            ['value' => $request->fixed_newsletter_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_footer_article_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title',
                            ],
                            ['value' => $fixed_footer_article_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_footer_article_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_footer_article_title->id,
                                'locale' => $key,
                                'key' => 'fixed_footer_article_title',
                            ],
                            ['value' => $request->fixed_footer_article_title[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['key' => 'fixed_link', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'web_app_url_status' => $request['web_app_url_status'],
                    'web_app_url' => $request['web_app_url'],
                ]),
            ]);
            Toastr::success(translate('messages.Landing page text updated'));
        } elseif ($tab == 'promotional-section') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('Default data is required'));

                return back();
            }
            $banner = new AdminPromotionalBanner;
            $banner->title = $request->title[array_search('default', $request->lang)];
            $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $banner->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                'translationable_id' => $banner->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $banner->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                'translationable_id' => $banner->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                'translationable_id' => $banner->id,
                                'locale' => $key,
                                'key' => 'sub_title',
                            ],
                            ['value' => $banner->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminPromotionalBanner',
                                'translationable_id' => $banner->id,
                                'locale' => $key,
                                'key' => 'sub_title',
                            ],
                            ['value' => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('Added successfully'));

            return back();
        } elseif ($tab == 'feature-list') {
            $request->validate([
                'title' => 'required',
                'sub_title' => 'required',
                'image' => ImageFile::rules('required'),
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('Default data is required'));

                return back();
            }
            $feature = new AdminFeature;
            $feature->title = $request->title[array_search('default', $request->lang)];
            $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
            $feature->image = Helpers::upload('admin_feature/', 'png', $request->file('image'));
            $feature->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminFeature',
                                'translationable_id' => $feature->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $feature->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminFeature',
                                'translationable_id' => $feature->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $request->title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminFeature',
                                'translationable_id' => $feature->id,
                                'locale' => $key,
                                'key' => 'sub_title',
                            ],
                            ['value' => $feature->sub_title]
                        );
                    }
                } else {

                    if ($request->sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminFeature',
                                'translationable_id' => $feature->id,
                                'locale' => $key,
                                'key' => 'sub_title',
                            ],
                            ['value' => $request->sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('Added successfully'));
        } elseif ($tab == 'feature-title') {
            $feature_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_title')->first();
            if ($feature_title == null) {
                $feature_title = new DataSetting;
            }

            $feature_title->key = 'feature_title';
            $feature_title->type = 'admin_landing_page';
            $feature_title->value = $request->feature_title[array_search('default', $request->lang)];
            $feature_title->save();

            $feature_short_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'feature_short_description')->first();
            if ($feature_short_description == null) {
                $feature_short_description = new DataSetting;
            }

            $feature_short_description->key = 'feature_short_description';
            $feature_short_description->type = 'admin_landing_page';
            $feature_short_description->value = $request->feature_short_description[array_search('default', $request->lang)];
            $feature_short_description->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->feature_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title',
                            ],
                            ['value' => $feature_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_title->id,
                                'locale' => $key,
                                'key' => 'feature_title',
                            ],
                            ['value' => $request->feature_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->feature_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description',
                            ],
                            ['value' => $feature_short_description->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->feature_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $feature_short_description->id,
                                'locale' => $key,
                                'key' => 'feature_short_description',
                            ],
                            ['value' => $request->feature_short_description[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Feature section updated'));
        } elseif ($tab == 'earning-title') {
            $earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_title')->first();
            if ($earning_title == null) {
                $earning_title = new DataSetting;
            }

            $earning_title->key = 'earning_title';
            $earning_title->type = 'admin_landing_page';
            $earning_title->value = $request->earning_title[array_search('default', $request->lang)];
            $earning_title->save();

            $earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'earning_sub_title')->first();
            if ($earning_sub_title == null) {
                $earning_sub_title = new DataSetting;
            }

            $earning_sub_title->key = 'earning_sub_title';
            $earning_sub_title->type = 'admin_landing_page';
            $earning_sub_title->value = $request->earning_sub_title[array_search('default', $request->lang)];
            $earning_sub_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title',
                            ],
                            ['value' => $earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_title->id,
                                'locale' => $key,
                                'key' => 'earning_title',
                            ],
                            ['value' => $request->earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title',
                            ],
                            ['value' => $earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'earning_sub_title',
                            ],
                            ['value' => $request->earning_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.earning_section_updated'));
        } elseif ($tab == 'earning-seller-link') {
            $request->validate([
                'seller_app_earning_title.0' => 'required',
                'seller_app_earning_sub_title.0' => 'required',
                'image' => ImageFile::rules('nullable'),
            ]);
            $seller_app_earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_app_earning_title')->first();
            if ($seller_app_earning_title == null) {
                $seller_app_earning_title = new DataSetting;
            }

            $seller_app_earning_title->key = 'seller_app_earning_title';
            $seller_app_earning_title->type = 'admin_landing_page';
            $seller_app_earning_title->value = $request->seller_app_earning_title[array_search('default', $request->lang)];
            $seller_app_earning_title->save();

            $seller_app_earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_app_earning_sub_title')->first();
            if ($seller_app_earning_sub_title == null) {
                $seller_app_earning_sub_title = new DataSetting;
            }

            $seller_app_earning_sub_title->key = 'seller_app_earning_sub_title';
            $seller_app_earning_sub_title->type = 'admin_landing_page';
            $seller_app_earning_sub_title->value = $request->seller_app_earning_sub_title[array_search('default', $request->lang)];
            $seller_app_earning_sub_title->save();

            $seller_app_earning_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'seller_app_earning_image')->first();
            if ($seller_app_earning_image == null) {
                $seller_app_earning_image = new DataSetting;
            }
            $seller_app_earning_image->key = 'seller_app_earning_image';
            $seller_app_earning_image->type = 'admin_landing_page';
            $seller_app_earning_image->value = $request->has('image') ? Helpers::update('seller_app_earning_image/', $seller_app_earning_image->value, 'png', $request->file('image')) : $seller_app_earning_image->value;
            $seller_app_earning_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->seller_app_earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $seller_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'seller_app_earning_title',
                            ],
                            ['value' => $seller_app_earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->seller_app_earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $seller_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'seller_app_earning_title',
                            ],
                            ['value' => $request->seller_app_earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->seller_app_earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $seller_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'seller_app_earning_sub_title',
                            ],
                            ['value' => $seller_app_earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->seller_app_earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $seller_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'seller_app_earning_sub_title',
                            ],
                            ['value' => $request->seller_app_earning_sub_title[$index]]
                        );
                    }
                }
            }
            Helpers::dataUpdateOrInsert(['key' => 'seller_app_earning_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => Helpers::get_business_settings('app_url_android_store'),
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => Helpers::get_business_settings('app_url_ios_store'),
                ]),
            ]);
            Toastr::success(translate('messages.Seller links updated'));
        } elseif ($tab == 'earning-dm-link') {
            $request->validate([
                'dm_app_earning_title.0' => 'required',
                'dm_app_earning_sub_title.0' => 'required',
                'image' => ImageFile::rules('nullable'),
            ]);
            $dm_app_earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'dm_app_earning_title')->first();
            if ($dm_app_earning_title == null) {
                $dm_app_earning_title = new DataSetting;
            }

            $dm_app_earning_title->key = 'dm_app_earning_title';
            $dm_app_earning_title->type = 'admin_landing_page';
            $dm_app_earning_title->value = $request->dm_app_earning_title[array_search('default', $request->lang)];
            $dm_app_earning_title->save();

            $dm_app_earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'dm_app_earning_sub_title')->first();
            if ($dm_app_earning_sub_title == null) {
                $dm_app_earning_sub_title = new DataSetting;
            }

            $dm_app_earning_sub_title->key = 'dm_app_earning_sub_title';
            $dm_app_earning_sub_title->type = 'admin_landing_page';
            $dm_app_earning_sub_title->value = $request->dm_app_earning_sub_title[array_search('default', $request->lang)];
            $dm_app_earning_sub_title->save();

            $dm_app_earning_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'dm_app_earning_image')->first();
            if ($dm_app_earning_image == null) {
                $dm_app_earning_image = new DataSetting;
            }
            $dm_app_earning_image->key = 'dm_app_earning_image';
            $dm_app_earning_image->type = 'admin_landing_page';
            $dm_app_earning_image->value = $request->has('image') ? Helpers::update('dm_app_earning_image/', $dm_app_earning_image->value, 'png', $request->file('image')) : $dm_app_earning_image->value;
            $dm_app_earning_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->dm_app_earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $dm_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'dm_app_earning_title',
                            ],
                            ['value' => $dm_app_earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->dm_app_earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $dm_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'dm_app_earning_title',
                            ],
                            ['value' => $request->dm_app_earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->dm_app_earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $dm_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'dm_app_earning_sub_title',
                            ],
                            ['value' => $dm_app_earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->dm_app_earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $dm_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'dm_app_earning_sub_title',
                            ],
                            ['value' => $request->dm_app_earning_sub_title[$index]]
                        );
                    }
                }
            }
            Helpers::dataUpdateOrInsert(['key' => 'dm_app_earning_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => Helpers::get_business_settings('app_url_android_deliveryman'),
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => Helpers::get_business_settings('app_url_ios_deliveryman'),
                ]),
            ]);
            Toastr::success(translate('messages.Delivery man links updated'));
        } elseif ($tab == 'earning-rider-link') {
            $request->validate([
                'rider_app_earning_title.0' => 'required',
                'rider_app_earning_sub_title.0' => 'required',
                'image' => ImageFile::rules('nullable'),
            ]);
            $rider_app_earning_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'rider_app_earning_title')->first();
            if ($rider_app_earning_title == null) {
                $rider_app_earning_title = new DataSetting;
            }

            $rider_app_earning_title->key = 'rider_app_earning_title';
            $rider_app_earning_title->type = 'admin_landing_page';
            $rider_app_earning_title->value = $request->rider_app_earning_title[array_search('default', $request->lang)];
            $rider_app_earning_title->save();

            $rider_app_earning_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'rider_app_earning_sub_title')->first();
            if ($rider_app_earning_sub_title == null) {
                $rider_app_earning_sub_title = new DataSetting;
            }

            $rider_app_earning_sub_title->key = 'rider_app_earning_sub_title';
            $rider_app_earning_sub_title->type = 'admin_landing_page';
            $rider_app_earning_sub_title->value = $request->rider_app_earning_sub_title[array_search('default', $request->lang)];
            $rider_app_earning_sub_title->save();

            $rider_app_earning_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'rider_app_earning_image')->first();
            if ($rider_app_earning_image == null) {
                $rider_app_earning_image = new DataSetting;
            }
            $rider_app_earning_image->key = 'rider_app_earning_image';
            $rider_app_earning_image->type = 'admin_landing_page';
            $rider_app_earning_image->value = $request->has('image') ? Helpers::update('rider_app_earning_image/', $rider_app_earning_image->value, 'png', $request->file('image')) : $rider_app_earning_image->value;
            $rider_app_earning_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->rider_app_earning_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $rider_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'rider_app_earning_title',
                            ],
                            ['value' => $rider_app_earning_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->rider_app_earning_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $rider_app_earning_title->id,
                                'locale' => $key,
                                'key' => 'rider_app_earning_title',
                            ],
                            ['value' => $request->rider_app_earning_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->rider_app_earning_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $rider_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'rider_app_earning_sub_title',
                            ],
                            ['value' => $rider_app_earning_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->rider_app_earning_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $rider_app_earning_sub_title->id,
                                'locale' => $key,
                                'key' => 'rider_app_earning_sub_title',
                            ],
                            ['value' => $request->rider_app_earning_sub_title[$index]]
                        );
                    }
                }
            }
            Helpers::dataUpdateOrInsert(['key' => 'rider_app_earning_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => Helpers::get_business_settings('app_url_android_rider'),
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => Helpers::get_business_settings('app_url_ios_rider'),
                ]),
            ]);
            Toastr::success(translate('messages.Rider links updated'));
        } elseif ($tab == 'why-choose-title') {
            $why_choose_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'why_choose_title')->first();
            if ($why_choose_title == null) {
                $why_choose_title = new DataSetting;
            }

            $why_choose_title->key = 'why_choose_title';
            $why_choose_title->type = 'admin_landing_page';
            $why_choose_title->value = $request->why_choose_title[array_search('default', $request->lang)];
            $why_choose_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->why_choose_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title',
                            ],
                            ['value' => $why_choose_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->why_choose_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $why_choose_title->id,
                                'locale' => $key,
                                'key' => 'why_choose_title',
                            ],
                            ['value' => $request->why_choose_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Why choose section updated'));
        } elseif ($tab == 'special-criteria-list') {
            $request->validate([
                'title' => 'required',
                'image' => ImageFile::rules('required'),
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('Default data is required'));

                return back();
            }
            $criteria = new AdminSpecialCriteria;
            $criteria->title = $request->title[array_search('default', $request->lang)];
            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
            $criteria->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                'translationable_id' => $criteria->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $criteria->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\AdminSpecialCriteria',
                                'translationable_id' => $criteria->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $request->title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('Added successfully'));
        } elseif ($tab == 'download-app-section') {
            $request->validate([
                'download_user_app_title.0' => 'required',
                'download_user_app_sub_title.0' => 'required',
                'image' => ImageFile::rules('nullable'),
            ]);
            $download_user_app_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting;
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'admin_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting;
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'admin_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            $download_user_app_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'download_user_app_image')->first();
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting;
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'admin_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url'],
                ]),
            ]);

            Toastr::success(translate('messages.Download app section updated'));
        } elseif ($tab == 'download-counter-section') {
            Helpers::dataUpdateOrInsert(['key' => 'counter_section', 'type' => 'admin_landing_page'], [
                'value' => json_encode([
                    'app_download_count_numbers' => $request['app_download_count_numbers'],
                    'seller_count_numbers' => $request['seller_count_numbers'],
                    'deliveryman_count_numbers' => $request['deliveryman_count_numbers'],
                    'rider_count_numbers' => $request['rider_count_numbers'] ?? 0,
                    'customer_count_numbers' => $request['customer_count_numbers'],
                    'status' => $request['status'],
                ]),
            ]);

            Toastr::success(translate('messages.Landing page counter section updated'));
        } elseif ($tab == 'testimonial-title') {
            $testimonial_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'testimonial_title')->first();
            if ($testimonial_title == null) {
                $testimonial_title = new DataSetting;
            }

            $testimonial_title->key = 'testimonial_title';
            $testimonial_title->type = 'admin_landing_page';
            $testimonial_title->value = $request->testimonial_title[array_search('default', $request->lang)];
            $testimonial_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->testimonial_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title',
                            ],
                            ['value' => $testimonial_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->testimonial_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $testimonial_title->id,
                                'locale' => $key,
                                'key' => 'testimonial_title',
                            ],
                            ['value' => $request->testimonial_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Testimonial section updated'));
        } elseif ($tab == 'testimonial-list') {
            $request->validate([
                'name' => 'required|max:30',
                'designation' => 'required|max:30',
                'review' => 'required',
                'reviewer_image' => ImageFile::rules('required'),
                'company_image' => ImageFile::rules('required'),
            ]);

            $testimonial = new AdminTestimonial;
            $testimonial->name = $request->name;
            $testimonial->designation = $request->designation;
            $testimonial->review = $request->review;
            $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
            $testimonial->company_image = Helpers::upload('reviewer_company_image/', 'png', $request->file('company_image'));
            $testimonial->save();
            Toastr::success(translate('Added successfully'));
        } elseif ($tab == 'contact-us-section') {





            $data = [];

            Helpers::businessUpdateOrInsert(['key' => 'opening_time'], [
                'value' => $request['opening_time'],
            ]);

            Helpers::businessUpdateOrInsert(['key' => 'closing_time'], [
                'value' => $request['closing_time'],
            ]);

            if ($request->opening_day == $request->closing_day) {
                Toastr::error(translate('messages.The start day and end day is same'));
            } else {
                Helpers::businessUpdateOrInsert(['key' => 'opening_day'], [
                    'value' => $request['opening_day'],
                ]);

                Helpers::businessUpdateOrInsert(['key' => 'closing_day'], [
                    'value' => $request['closing_day'],
                ]);
            }

            Toastr::success(translate('messages.Contact section updated'));
        } elseif ($tab == 'available-zone-section') {
            if ($request['available_zone_status']) {
                $request->validate([
                    'available_zone_title.0' => 'required',

                ], [
                    'available_zone_title.0.required' => translate('Default title is required'),
                ]);
            }
            $available_zone_title = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_title')->first();
            if ($available_zone_title == null) {
                $available_zone_title = new DataSetting;
            }

            $available_zone_title->key = 'available_zone_title';
            $available_zone_title->type = 'admin_landing_page';
            $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
            $available_zone_title->save();

            $available_zone_short_description = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_short_description')->first();
            if ($available_zone_short_description == null) {
                $available_zone_short_description = new DataSetting;
            }

            $available_zone_short_description->key = 'available_zone_short_description';
            $available_zone_short_description->type = 'admin_landing_page';
            $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
            $available_zone_short_description->save();

            $available_zone_image = DataSetting::where('type', 'admin_landing_page')->where('key', 'available_zone_image')->first();
            if ($available_zone_image == null) {
                $request->validate([
                    'image' => ImageFile::rules('required'),
                ]);
                $available_zone_image = new DataSetting;
            }
            $available_zone_image->key = 'available_zone_image';
            $available_zone_image->type = 'admin_landing_page';
            $available_zone_image->value = $request->has('image') ? Helpers::update('available_zone_image/', $available_zone_image->value, 'png', $request->file('image')) : $available_zone_image->value;
            $available_zone_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->available_zone_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $available_zone_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $request->available_zone_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $available_zone_short_description?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $request->available_zone_short_description[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['type' => 'admin_landing_page', 'key' => 'available_zone_status'], [
                'value' => $request['available_zone_status'],
            ]);

            Toastr::success(translate('messages.Available zone section updated'));
        } elseif ($tab == 'background-color') {
            Helpers::businessUpdateOrInsert(['key' => 'backgroundChange'], [
                'value' => json_encode([
                    'primary_1_hex' => $request['header-bg'],
                    'primary_1_rgb' => Helpers::hex_to_rbg($request['header-bg']),
                    'primary_2_hex' => $request['footer-bg'],
                    'primary_2_rgb' => Helpers::hex_to_rbg($request['footer-bg']),
                ]),
            ]);
            Toastr::success(translate('messages.Background updated'));
        } elseif ($tab == 'meta-data') {
            $this->landingPageMetaDataUpdate($request, 'admin');
            Toastr::success(translate('Updated successfully'));

            return back();
        }

        return back();
    }

    private function landingPageMetaDataUpdate($request, $type = 'admin')
    {

        $meta_title = DataSetting::firstOrNew([
            'type' => $type . '_landing_page',
            'key' => 'meta_title',
        ]);


        $meta_title->value = $request->meta_title;
        $meta_title->save();

        $meta_description = DataSetting::firstOrNew([
            'type' => $type . '_landing_page',
            'key' => 'meta_description',
        ]);

        $meta_description->value = $request->meta_description;
        $meta_description->save();

        $meta_image = DataSetting::firstOrNew([
            'type' => $type . '_landing_page',
            'key' => 'meta_image',
        ]);

        if ($request->has('meta_image_deleted') && $request->meta_image_deleted == 1) {
            Helpers::check_and_delete('landing/meta_image/', $meta_image?->value);
            $meta_image->value = null;
        }

        $meta_image->value = $request->has('meta_image') ? Helpers::update('landing/meta_image/', $meta_image?->value, 'png', $request->file('meta_image')) : $meta_image?->value;
        $meta_image->save();

        $meta_data = DataSetting::firstOrNew([
            'type' => $type . '_landing_page',
            'key' => 'meta_data',
        ]);
        $old_value = $meta_data->exists ? $meta_data->getRawOriginal('value') : null;
        $old_meta = $old_value ? json_decode($old_value, true) : [];
        $meta_data->value = json_encode(Helpers::formatMetaData($request->all(), $old_meta));
        $meta_data->save();

        return true;

    }

    public function promotional_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this banner!');

            return back();
        }
        $banner = AdminPromotionalBanner::findOrFail($request->id);
        $banner->status = $request->status;
        $banner->save();
        Toastr::success(translate('messages.Banner status updated'));

        return back();
    }

    public function promotional_edit($id)
    {
        $banner = AdminPromotionalBanner::withoutGlobalScope('translate')->withStorage()->with('translations')->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.admin-promotional-section-edit', compact('banner'));
    }

    public function promotional_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'sub_title' => 'required',
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('Default data is required'));

            return back();
        }
        $banner = AdminPromotionalBanner::withStorage()->find($id);
        $banner->title = $request->title[array_search('default', $request->lang)];
        $banner->sub_title = $request->sub_title[array_search('default', $request->lang)];
        $banner->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminPromotionalBanner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $banner->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminPromotionalBanner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $request->title[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->sub_title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminPromotionalBanner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'sub_title',
                        ],
                        ['value' => $banner->sub_title]
                    );
                }
            } else {

                if ($request->sub_title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminPromotionalBanner',
                            'translationable_id' => $banner->id,
                            'locale' => $key,
                            'key' => 'sub_title',
                        ],
                        ['value' => $request->sub_title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function promotional_destroy(AdminPromotionalBanner $banner)
    {
        if (getEnvMode() == 'demo' && $banner->id == 1) {
            Toastr::warning(translate('messages.You can not delete this banner please add a new banner to delete'));

            return back();
        }
        $banner->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function feature_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this feature!');

            return back();
        }
        $feature = AdminFeature::findOrFail($request->id);
        $feature->status = $request->status;
        $feature->save();
        Toastr::success(translate('messages.Feature status updated'));

        return back();
    }

    public function feature_edit($id)
    {
        $feature = AdminFeature::withoutGlobalScope('translate')->withStorage()->with('translations')->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.admin-feature-list-edit', compact('feature'));
    }

    public function feature_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
            'sub_title' => 'required',
            'image' => ImageFile::rules('required'),
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('Default data is required'));

            return back();
        }
        $feature = AdminFeature::withStorage()->find($id);
        $feature->title = $request->title[array_search('default', $request->lang)];
        $feature->sub_title = $request->sub_title[array_search('default', $request->lang)];
        $feature->image = $request->has('image') ? Helpers::update('admin_feature/', $feature->image, 'png', $request->file('image')) : $feature->image;
        $feature->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminFeature',
                            'translationable_id' => $feature->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $feature->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminFeature',
                            'translationable_id' => $feature->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $request->title[$index]]
                    );
                }
            }
            if ($default_lang == $key && !($request->sub_title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminFeature',
                            'translationable_id' => $feature->id,
                            'locale' => $key,
                            'key' => 'sub_title',
                        ],
                        ['value' => $feature->sub_title]
                    );
                }
            } else {

                if ($request->sub_title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminFeature',
                            'translationable_id' => $feature->id,
                            'locale' => $key,
                            'key' => 'sub_title',
                        ],
                        ['value' => $request->sub_title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function feature_destroy(AdminFeature $feature)
    {
        if (getEnvMode() == 'demo' && $feature->id == 1) {
            Toastr::warning(translate('messages.You can not delete this feature please add a new feature to delete'));

            return back();
        }
        $feature->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function criteria_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this criteria!');

            return back();
        }
        $criteria = AdminSpecialCriteria::findOrFail($request->id);
        $criteria->status = $request->status;
        $criteria->save();
        Toastr::success(translate('messages.Criteria status updated'));

        return back();
    }

    public function criteria_edit($id)
    {
        $criteria = AdminSpecialCriteria::withoutGlobalScope('translate')->withStorage()->with('translations')->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.admin-landing-why-choose-edit', compact('criteria'));
    }

    public function criteria_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required',
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('Default data is required'));

            return back();
        }
        $criteria = AdminSpecialCriteria::withStorage()->find($id);
        $criteria->title = $request->title[array_search('default', $request->lang)];
        if ($criteria->image == null) {
            $request->validate([
                'image' => ImageFile::rules('nullable'),
            ]);
        }
        $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
        $criteria->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminSpecialCriteria',
                            'translationable_id' => $criteria->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $criteria->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\AdminSpecialCriteria',
                            'translationable_id' => $criteria->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $request->title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function criteria_destroy(AdminSpecialCriteria $criteria)
    {
        if (getEnvMode() == 'demo' && $criteria->id == 1) {
            Toastr::warning(translate('messages.You can not delete this criteria please add a new criteria to delete'));

            return back();
        }
        $criteria->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function review_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this review!');

            return back();
        }
        $review = AdminTestimonial::findOrFail($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.Review status updated'));

        return back();
    }

    public function review_edit($id)
    {
        $review = AdminTestimonial::withoutGlobalScope('translate')->withStorage()->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.admin-landing-testimonial-test', compact('review'));
    }

    public function review_update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:30',
            'designation' => 'required|max:30',
            'review' => 'required',
        ]);

        $review = AdminTestimonial::withStorage()->findOrFail($id);
        $review->name = $request->name;
        $review->designation = $request->designation;
        $review->review = $request->review;
        if ($review->reviewer_image == null) {
            $request->validate([
                'reviewer_image' => ImageFile::rules('required'),
            ]);
        }
        if ($review->company_image == null) {
            $request->validate([
                'company_image' => ImageFile::rules('required'),
            ]);
        }

        $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
        $review->company_image = $request->has('company_image') ? Helpers::update('reviewer_company_image/', $review->company_image, 'png', $request->file('company_image')) : $review->company_image;
        $review->save();

        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function review_destroy(AdminTestimonial $review)
    {
        if (getEnvMode() == 'demo' && $review->id == 1) {
            Toastr::warning(translate('messages.You can not delete this review please add a new review to delete'));

            return back();
        }
        $review->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function react_landing_page_settings($tab)
    {
        $landingData = [];
        $language = [];
        $base = 'admin-views.business-settings.landing-page-settings.';
        $views = [
            'header' => 'react-landing-page-header',
            'trust-section' => 'react-landing-page-trust-section',
            'available-zone' => 'react-landing-available-zone',
            'promotion-banner' => 'react-landing-promotion-banners',
            'download-user-app' => 'react-landing-download-apps',
            'popular-clients' => 'react-landing-page-popular-clients',
            'download-seller-app' => 'react-landing-page-download-seller-app',
            'download-deliveryman-app' => 'react-landing-page-download-deliveryman-app',
            'download-rider-app' => 'react-landing-page-download-rider-app',
            'banner-section' => 'react-landing-page-banner-section',
            'testimonials' => 'react-landing-testimonial',
            'gallery' => 'react-landing-page-gallery',
            'highlight-section' => 'react-landing-page-highlight-section',
            'faq' => 'react-landing-page-faq',
            'footer' => 'react-landing-page-footer',
            'meta-data' => 'react-landing-meta-data',
        ];

        if (!isset($views[$tab])) {
            abort(404);
        }
        if(($tab == 'download-rider-app') && (addon_published_status('RideShare') != 1)){
            abort(404);
        }

        if ($tab == 'meta-data') {
            $landingData = DataSetting::withoutGlobalScope('translate')->withStorage()->with('translations')->where('type', 'react_landing_page')->whereIn('key', ['meta_title', 'meta_description', 'meta_image','meta_data'])->get()->keyBy('key') ?? [];
            $language = Helpers::get_business_settings('language');
        }

        return view($base . $views[$tab], compact('landingData', 'language'));
    }

    public function update_react_landing_page_settings(Request $request, $tab)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        if ($tab == 'gallery-section') {
            $request->validate([
                'gallery_content_title.0' => 'required',
                'gallery_content_sub_title.0' => 'required',

            ], [
                'gallery_content_title.0.required' => translate('messages.Default title is required'),
                'gallery_content_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);
            $this->getAddLandingPageData($request, 'react_landing_page', 'gallery_content_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'gallery_content_sub_title', true);
            Toastr::success(translate('messages.Gallery content section updated'));
            return back();
        } elseif ($tab == 'popular-client-section') {
            $request->validate([
                'popular_client_title.0' => 'required',
                'popular_client_sub_title.0' => 'required',

            ], [
                'popular_client_title.0.required' => translate('messages.Default title is required'),
                'popular_client_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);
            $this->getAddLandingPageData($request, 'react_landing_page', 'popular_client_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'popular_client_sub_title', true);

            if ($request->hasFile('image')) {

                foreach ($request->file('image') as $index => $file) {
                    $key = 'popular_client_image';
                    $type = 'react_landing_page';
                    $filePath = 'popular_client_section';

                    $request->files->set($key, $file);

                    $data = DataSetting::create(['type' => $type, 'key' => $key]);
                    $format = strtolower($file->getClientOriginalExtension() ?? 'png');
                    $existingImage = $data->exists ? $data->value : null;

                    $data->value = empty($existingImage)
                        ? Helpers::upload(dir: $filePath, format: $format, image: $file)
                        : Helpers::update(dir: $filePath, old_image: $existingImage, format: $format, image: $file);
                    $data->save();
                }
            }
            if (!empty($request->remove_existing_images)) {
                foreach ($request->remove_existing_images as $oldImage) {
                    Helpers::check_and_delete('popular_client_section', $oldImage);
                    $data = DataSetting::where('type', 'react_landing_page')
                        ->where('key', 'popular_client_image')
                        ->where('value', $oldImage)
                        ->first();
                    $data->value = 0;
                    $data->save();
                }
            }
            Toastr::success(translate('messages.Popular client content section updated'));
            return back();
        } elseif ($tab == 'popular-client-section-images') {
            $request->validate([
                'popular_client_image_card_1' => 'nullable|max:2028',
                'popular_client_image_card_2' => 'nullable|max:2028',
                'popular_client_image_card_3' => 'nullable|max:2028',
                'popular_client_image_card_4' => 'nullable|max:2028',
                'popular_client_image_card_5' => 'nullable|max:2028',
                'popular_client_image_card_6' => 'nullable|max:2028',
                'popular_client_image_card_7' => 'nullable|max:2028',
                'popular_client_image_card_8' => 'nullable|max:2028',
                'popular_client_image_card_9' => 'nullable|max:2028',
                'popular_client_image_card_10' => 'nullable|max:2028',
                'popular_client_image_card_11' => 'nullable|max:2028',
                'popular_client_image_card_12' => 'nullable|max:2028',
            ]);


            foreach (range(1, 12) as $i) {
                $key = "popular_client_image_card_{$i}";
                if ($request->hasFile($key)) {
                    $this->getAddLandingPageData(request: $request, type: 'react_landing_page', key: $key, multiLang: false, filePath: 'popular_client_section/');
                }
                if ($request->input("{$key}_remove") == "1") {
                    $image_deleted = $this->imageDelete(dir: 'popular_client_section', type: 'react_landing_page', key: $key);
                    if ($image_deleted) {
                        $request[$key] = null;
                    }
                    $this->getAddLandingPageData(request: $request, type: 'react_landing_page', key: $key, multiLang: false, filePath: 'popular_client_section/');
                }
            }
            Toastr::success(translate('messages.Popular client content section updated'));
            return back();
        } elseif ($tab == 'download-dm-app-section') {
            $request->validate([
                'download_dm_app_title.0' => 'required|max:100',
                'download_dm_app_sub_title.0' => 'nullable|max:1000',
                'download_dm_app_button_title.0' => 'required|max:20',
                'download_dm_app_image' => ImageFile::rules('nullable'),
            ], [
                'download_dm_app_title.0.required' => translate('Default title is required'),
                'download_dm_app_button_title.0.required' => translate('Default button title is required'),
            ]);

            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'download_dm_app_section', type: 'react_landing_page', key: 'download_dm_app_image');
                if ($image_deleted) {
                    $request['download_dm_app_image'] = null;
                }
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_image', false, 'download_dm_app_section/');
            }
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_sub_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_button_title', true);
            if ($request->hasFile('download_dm_app_image')) {
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_image', false, 'download_dm_app_section/');
            }

            Toastr::success(translate('messages.Download deliveryman app section updated'));

            return back();
        } elseif ($tab == 'download-rider-app-section') {
            $request->validate([
                'download_rider_app_title.0' => 'required|max:100',
                'download_rider_app_sub_title.0' => 'nullable|max:1000',
                'download_rider_app_button_title.0' => 'required|max:20',
                'download_rider_app_image' => ImageFile::rules('nullable'),
            ], [
                'download_rider_app_title.0.required' => translate('Default title is required'),
                'download_rider_app_button_title.0.required' => translate('Default button title is required'),
            ]);

            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'download_rider_app_section', type: 'react_landing_page', key: 'download_rider_app_image');
                if ($image_deleted) {
                    $request['download_rider_app_image'] = null;
                }
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_image', false, 'download_rider_app_section/');
            }
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_sub_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_button_title', true);
            if ($request->hasFile('download_rider_app_image')) {
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_image', false, 'download_rider_app_section/');
            }

            Toastr::success(translate('messages.Download rider app section updated'));
            return back();
        } elseif ($tab == 'download-seller-app-section') {
            $request->validate([
                'download_seller_app_title.0' => 'required|max:100',
                'download_seller_app_sub_title.0' => 'nullable|max:1000',
                'download_seller_app_button_title.0' => 'required|max:20',
                'download_seller_app_image' => ImageFile::rules('nullable'),
            ], [
                'download_seller_app_title.0.required' => translate('Default title is required'),
                'download_seller_app_sub_title.0.required' => translate('Default subtitle is required'),
                'download_seller_app_button_title.0.required' => translate('Default button title is required'),
            ]);
            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'download_seller_app_section', type: 'react_landing_page', key: 'download_seller_app_image');
                if ($image_deleted) {
                    $request['download_seller_app_image'] = null;
                }
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_image', false, 'download_seller_app_section/');
            }
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_sub_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_button_title', true);
            if ($request->hasFile('download_seller_app_image')) {
                $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_image', false, 'download_seller_app_section/');
            }
            Toastr::success(translate('messages.Download seller app section updated'));

            return back();
        } elseif ($tab == 'download-dm-app-button-section') {
            $request->validate([
                'download_dm_app_main_button_title.0' => 'required',
                'download_dm_app_main_button_sub_title.0' => 'required',
            ], [
                'download_dm_app_main_button_title.0.required' => translate('messages.Default title is required'),
                'download_dm_app_main_button_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_main_button_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_dm_app_main_button_sub_title', true);

            $download_links = [
                'playstore_url_status' => $request->has('dm_playstore_url_status') ? 1 : 0,
                'playstore_url' => Helpers::get_business_settings('app_url_android_deliveryman'),
                'apple_store_url_status' => $request->has('dm_apple_store_url_status') ? 1 : 0,
                'apple_store_url' => Helpers::get_business_settings('app_url_ios_deliveryman'),
            ];

            DataSetting::updateOrCreate(
                [
                    'key' => 'download_dm_app_links',
                    'type' => 'react_landing_page'
                ],
                [
                    'value' => json_encode($download_links)
                ]
            );

            Toastr::success(translate('messages.Download deliveryman app button section updated'));

            return back();
        } elseif ($tab == 'download-rider-app-button-section') {
            $request->validate([
                'download_rider_app_main_button_title.0' => 'required',
                'download_rider_app_main_button_sub_title.0' => 'required',
            ], [
                'download_rider_app_main_button_title.0.required' => translate('messages.Default title is required'),
                'download_rider_app_main_button_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_main_button_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_rider_app_main_button_sub_title', true);

            $download_links = [
                'playstore_url_status' => $request->has('rider_playstore_url_status') ? 1 : 0,
                'playstore_url' => Helpers::get_business_settings('app_url_android_rider'),
                'apple_store_url_status' => $request->has('rider_apple_store_url_status') ? 1 : 0,
                'apple_store_url' => Helpers::get_business_settings('app_url_ios_rider'),
            ];

            DataSetting::updateOrCreate(
                [
                    'key' => 'download_rider_app_links',
                    'type' => 'react_landing_page'
                ],
                [
                    'value' => json_encode($download_links)
                ]
            );

            Toastr::success(translate('messages.Download rider app button section updated'));

            return back();
        } elseif ($tab == 'download-seller-app-button-section') {
            $request->validate([
                'download_seller_app_main_button_title.0' => 'required',
                'download_seller_app_main_button_sub_title.0' => 'required',
            ], [
                'download_seller_app_main_button_title.0.required' => translate('messages.Default title is required'),
                'download_seller_app_main_button_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_main_button_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'download_seller_app_main_button_sub_title', true);

            $download_links = [
                'playstore_url_status' => $request->has('seller_playstore_url_status') ? 1 : 0,
                'playstore_url' => Helpers::get_business_settings('app_url_android_store'),
                'apple_store_url_status' => $request->has('seller_apple_store_url_status') ? 1 : 0,
                'apple_store_url' => Helpers::get_business_settings('app_url_ios_store'),
            ];

            DataSetting::updateOrCreate(
                [
                    'key' => 'download_seller_app_links',
                    'type' => 'react_landing_page'
                ],
                [
                    'value' => json_encode($download_links)
                ]
            );

            Toastr::success(translate('messages.Download seller app button section updated'));

            return back();
        } elseif ($tab == 'gallery-section-images') {

            $request->validate([
                'gallery_image_' . $request->gallery_tab => 'nullable|max:2028',

            ]);

            $key = "gallery_image_{$request->gallery_tab}";

            $data = DataSetting::firstOrNew(['type' => 'react_landing_page', 'key' => $key . '_status']);

            $data->value = $request->{$key . '_status'} ?? 0;
            $data->save();
            if ($request->input($key . '_remove') == "1") {
                $image_deleted = $this->imageDelete(dir: 'gallery_section', type: 'react_landing_page', key: $key);
                if ($image_deleted) {
                    $request[$key] = null;
                }
                $this->getAddLandingPageData(request: $request, type: 'react_landing_page', key: $key, multiLang: false, filePath: 'gallery_section/');
            }
            if ($request->hasFile($key)) {
                $this->getAddLandingPageData(request: $request, type: 'react_landing_page', key: $key, multiLang: false, filePath: 'gallery_section/');
            }


            Toastr::success(translate('messages.Gallery image section updated'));
            return back();
        } elseif ($tab == 'faq-section') {
            $request->validate([
                'faq_title.0' => 'required|max:254',
            ], [
                'faq_title.0.required' => translate('Default FAQ section title is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_landing_page', 'faq_title', true);

            Toastr::success(translate('Updated successfully'));
            return back();

        } elseif ($tab == 'faq-store') {
            $request->validate([
                'user_type' => 'required',
                'question' => 'required',
                'answer' => 'required',
            ]);
            $this->reactFaqStore($request);
        } elseif ($tab == 'highlight-section') {
            $request->validate([
                'highlight_title' => 'required|max:50',
                'highlight_sub_title' => 'required|max:200',
                'highlight_image' => ImageFile::rules('nullable'),
            ]);
            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'highlight_section', type: 'react_landing_page', key: 'highlight_image');
                if ($image_deleted) {
                    $request['highlight_image'] = null;
                }
                $this->getAddLandingPageData($request, 'react_landing_page', 'highlight_image', false, 'highlight_section/');
            }
            if ($request->hasFile('highlight_image')) {
                $this->getAddLandingPageData($request, 'react_landing_page', 'highlight_image', false, 'highlight_section/');
            }
            $this->getAddLandingPageData($request, 'react_landing_page', 'highlight_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'highlight_sub_title', true);

            Toastr::success(translate('Updated successfully'));

            return back();

        } elseif ($tab == 'banner') {
            $request->validate([
                'banner' => ImageFile::rules('nullable'),
            ]);
            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'banner_section', type: 'react_landing_page', key: 'banner');
                if ($image_deleted) {
                    $request['banner'] = null;
                }
                $this->getAddLandingPageData($request, 'react_landing_page', 'banner', false, 'banner_section/');
            }
            if ($request->hasFile('banner')) {
                $this->getAddLandingPageData($request, 'react_landing_page', 'banner', false, 'banner_section/');
            }
            Toastr::success(translate('messages.Banner section updated'));

            return back();

        } elseif (str_starts_with($tab, 'trust-section-card-')) {
            $cardNumber = str_replace('trust-section-card-', '', $tab);

            $request->validate([
                "trust_title_card_$cardNumber.0" => 'required|max:20',
                "trust_sub_title_card_$cardNumber.0" => 'required|max:30',
                "trust_image_card_$cardNumber" => ImageFile::rules('nullable'),
            ], [
                "trust_title_card_$cardNumber.0.required" => translate('Default title is required'),
                "trust_sub_title_card_$cardNumber.0.required" => translate('Default subtitle is required')
            ]);


            if ($request->hasFile("trust_image_card_$cardNumber")) {
                $data["trust_image_card_$cardNumber"] = $request->input("trust_image_card_{$cardNumber}");
            } else {
                $data["trust_image_card_$cardNumber"] = $request->input("trust_image_card_{$cardNumber}_existing");
            }

            $trustStatusKey = "trust_status_card_$cardNumber";
            $trustTitleKey = "trust_title_card_$cardNumber";
            $trustSubTitleKey = "trust_sub_title_card_$cardNumber";
            $trustImageKey = "trust_image_card_$cardNumber";

            if ($request->input("trust_image_card_{$cardNumber}_remove") == '1') {
                $image_deleted = $this->imageDelete(dir: 'trust_section', type: 'react_landing_page', key: $trustImageKey);
                if ($image_deleted) {
                    $data[$trustImageKey] = null;
                }
            }

            $this->getAddLandingPageData($request, 'react_landing_page', $trustStatusKey, false);
            $this->getAddLandingPageData($request, 'react_landing_page', $trustTitleKey, true);
            $this->getAddLandingPageData($request, 'react_landing_page', $trustSubTitleKey, true);

            $request->merge([$trustImageKey => $data["trust_image_card_$cardNumber"]]);
            $this->getAddLandingPageData($request, 'react_landing_page', $trustImageKey, false, 'trust_section/');

            Toastr::success(translate('Updated successfully'));
            return back();
        } elseif ($tab == 'download-app-section') {
            $request->validate([
                'download_user_app_title.0' => 'required',
                'download_user_app_sub_title.0' => 'required',

            ], [
                'download_user_app_title.0.required' => translate('messages.Default title is required'),
                'download_user_app_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $download_user_app_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting;
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'react_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting;
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'react_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();


            $download_user_app_image = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_image')->first();
            if ($request->image_remove == '1') {
                $image_deleted = $this->imageDelete(dir: 'download_user_app_image', type: 'react_landing_page', key: 'download_user_app_image');
                if ($image_deleted) {
                    $download_user_app_image->value = null;
                    $download_user_app_image->save();
                }
            }
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting;
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'react_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Download app section updated'));
        } elseif ($tab == 'download-app-button-section') {
            $request->validate([
                'download_user_app_button_title.0' => 'required',
                'download_user_app_button_sub_title.0' => 'required',
            ], [
                'download_user_app_button_title.0.required' => translate('messages.Default title is required'),
                'download_user_app_button_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $download_user_app_button_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_button_title')->first();
            if ($download_user_app_button_title == null) {
                $download_user_app_button_title = new DataSetting;
            }

            $download_user_app_button_title->key = 'download_user_app_button_title';
            $download_user_app_button_title->type = 'react_landing_page';
            $download_user_app_button_title->value = $request->download_user_app_button_title[array_search('default', $request->lang)];
            $download_user_app_button_title->save();

            $download_user_app_button_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'download_user_app_button_sub_title')->first();
            if ($download_user_app_button_sub_title == null) {
                $download_user_app_button_sub_title = new DataSetting;
            }

            $download_user_app_button_sub_title->key = 'download_user_app_button_sub_title';
            $download_user_app_button_sub_title->type = 'react_landing_page';
            $download_user_app_button_sub_title->value = $request->download_user_app_button_sub_title[array_search('default', $request->lang)];
            $download_user_app_button_sub_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {

                if ($default_lang == $key && !($request->download_user_app_button_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_button_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_button_title',
                            ],
                            ['value' => $download_user_app_button_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_button_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_button_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_button_title',
                            ],
                            ['value' => $request->download_user_app_button_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_button_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_button_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_button_sub_title',
                            ],
                            ['value' => $download_user_app_button_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_button_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_button_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_button_sub_title',
                            ],
                            ['value' => $request->download_user_app_button_sub_title[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'react_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url'],
                ]),
            ]);

            Toastr::success(translate('messages.Download app section updated'));
        } elseif ($tab == 'available-zone-section') {
            if ($request['available_zone_status']) {
                $request->validate([
                    'available_zone_title.0' => 'required',

                ], [
                    'available_zone_title.0.required' => translate('Default title is required'),
                ]);
            }
            $available_zone_title = DataSetting::where('type', 'react_landing_page')->where('key', 'available_zone_title')->first();
            if ($available_zone_title == null) {
                $available_zone_title = new DataSetting;
            }

            $available_zone_title->key = 'available_zone_title';
            $available_zone_title->type = 'react_landing_page';
            $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
            $available_zone_title->save();

            $available_zone_short_description = DataSetting::where('type', 'react_landing_page')->where('key', 'available_zone_short_description')->first();
            if ($available_zone_short_description == null) {
                $available_zone_short_description = new DataSetting;
            }

            $available_zone_short_description->key = 'available_zone_short_description';
            $available_zone_short_description->type = 'react_landing_page';
            $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
            $available_zone_short_description->save();



            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->available_zone_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $available_zone_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $request->available_zone_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $available_zone_short_description?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $request->available_zone_short_description[$index]]
                        );
                    }
                }
            }


            Toastr::success(translate('messages.Available zone section updated'));
        } elseif ($tab == 'testimonial-title') {
            $request->validate([
                'testimonial_title.0' => 'required|max:50',
                'testimonial_sub_title.0' => 'required|max:200',
                'testimonial_button_title.0' => 'required|max:20',
            ], [
                'testimonial_title.0.required' => translate('messages.Default title is required'),
                'testimonial_sub_title.0.required' => translate('messages.Default subtitle is required'),
                'testimonial_button_title.0.required' => translate('messages.Default button title is required'),
            ]);
            $this->getAddLandingPageData($request, 'react_landing_page', 'testimonial_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'testimonial_sub_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'testimonial_button_title', true);

            Toastr::success(translate('messages.Testimonial section updated'));
        } elseif ($tab == 'testimonial-list') {
            $request->validate([
                'name.0' => 'required',
                'designation.0' => 'nullable',
                'review.0' => 'required|max:200',
                'reviewer_image' => ImageFile::rules('required'),
            ], [
                'name.0.required' => translate('messages.Default name is required'),
                'review.0.required' => translate('messages.Default review is required'),
            ]);

            $testimonial = new ReactTestimonial;
            $testimonial->name = $request->name[array_search('default', $request->lang)];
            $testimonial->designation = $request->designation[array_search('default', $request->lang)];
            $testimonial->review = $request->review[array_search('default', $request->lang)];
            $testimonial->reviewer_image = Helpers::upload('reviewer_image/', 'png', $request->file('reviewer_image'));
            $testimonial->save();

            Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'ReactTestimonial', data_id: $testimonial->id, data_value: $testimonial->name);
            Helpers::add_or_update_translations(request: $request, key_data: 'designation', name_field: 'designation', model_name: 'ReactTestimonial', data_id: $testimonial->id, data_value: $testimonial->designation);
            Helpers::add_or_update_translations(request: $request, key_data: 'review', name_field: 'review', model_name: 'ReactTestimonial', data_id: $testimonial->id, data_value: $testimonial->review);
            Toastr::success(translate('Added successfully'));
        }
        elseif ($tab == 'header-section') {
            $request->validate([
                'header_title.0' => 'required',
                'header_sub_title.0' => 'required',
            ], [
                'header_title.0.required' => translate('messages.Default title is required'),
                'header_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);
            $header_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'header_banner')->first();
            if ($header_banner == null) {
                $header_banner = new DataSetting;
            }
            if (!$header_banner->value && !$request->has('banner_image')) {
                Toastr::error(translate('messages.Banner image is required'));

                return back();
            }
            $header_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_title')->first();
            if ($header_title == null) {
                $header_title = new DataSetting;
            }

            $header_title->key = 'header_title';
            $header_title->type = 'react_landing_page';
            $header_title->value = $request->header_title[array_search('default', $request->lang)];
            $header_title->save();

            $header_sub_title = DataSetting::where('type', 'react_landing_page')->where('key', 'header_sub_title')->first();
            if ($header_sub_title == null) {
                $header_sub_title = new DataSetting;
            }

            $header_sub_title->key = 'header_sub_title';
            $header_sub_title->type = 'react_landing_page';
            $header_sub_title->value = $request->header_sub_title[array_search('default', $request->lang)];
            $header_sub_title->save();

            $header_tag_line = DataSetting::where('type', 'react_landing_page')->where('key', 'header_tag_line')->first();
            if ($header_tag_line == null) {
                $header_tag_line = new DataSetting;
            }

            $header_tag_line->key = 'header_tag_line';
            $header_tag_line->type = 'react_landing_page';
            $header_tag_line->value = $request->header_tag_line[array_search('default', $request->lang)];
            $header_tag_line->save();

            $header_icon = DataSetting::where('type', 'react_landing_page')->where('key', 'header_icon')->first();
            if ($header_icon == null) {
                $header_icon = new DataSetting;
            }
            $header_icon->key = 'header_icon';
            $header_icon->type = 'react_landing_page';
            $header_icon->value = $request->has('image') ? Helpers::update('header_icon/', $header_icon->value, 'png', $request->file('image')) : $header_icon->value;
            $header_icon->save();

            $header_banner->key = 'header_banner';
            $header_banner->type = 'react_landing_page';
            $header_banner->value = $request->has('banner_image') ? Helpers::update('header_banner/', $header_banner->value, 'png', $request->file('banner_image')) : $header_banner->value;
            $header_banner->save();

            $pick_location_title = DataSetting::where('type', 'react_landing_page')->where('key', 'pick_location_title')->first();
            if ($pick_location_title == null) {
                $pick_location_title = new DataSetting;
            }

            $pick_location_title->key = 'pick_location_title';
            $pick_location_title->type = 'react_landing_page';
            $pick_location_title->value = $request->pick_location_title[array_search('default', $request->lang)];
            $pick_location_title->save();

            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_title->id,
                                'locale' => $key,
                                'key' => 'header_title',
                            ],
                            ['value' => $header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_title->id,
                                'locale' => $key,
                                'key' => 'header_title',
                            ],
                            ['value' => $request->header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_sub_title->id,
                                'locale' => $key,
                                'key' => 'header_sub_title',
                            ],
                            ['value' => $header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_sub_title->id,
                                'locale' => $key,
                                'key' => 'header_sub_title',
                            ],
                            ['value' => $request->header_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->header_tag_line[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_tag_line->id,
                                'locale' => $key,
                                'key' => 'header_tag_line',
                            ],
                            ['value' => $header_tag_line->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->header_tag_line[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $header_tag_line->id,
                                'locale' => $key,
                                'key' => 'header_tag_line',
                            ],
                            ['value' => $request->header_tag_line[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->pick_location_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $pick_location_title->id,
                                'locale' => $key,
                                'key' => 'pick_location_title',
                            ],
                            ['value' => $pick_location_title->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->pick_location_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $pick_location_title->id,
                                'locale' => $key,
                                'key' => 'pick_location_title',
                            ],
                            ['value' => $request->pick_location_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Header section updated'));
        }
        elseif ($tab == 'promotion-banner') {
            if (!$request->has('image')) {
                Toastr::error(translate('messages.Banner image is required'));

                return back();
            }
            $data = [];
            $imageName = null;
            $promotion_banner = DataSetting::firstOrNew(['key' => 'promotion_banner', 'type' => 'react_landing_page']);
            if ($promotion_banner) {
                $data = json_decode($promotion_banner->value, true);
            }
            if (count($data) >= 3) {
                Toastr::error(translate('messages.You have already added maximum banner image'));

                return back();
            }
            if ($request->has('image')) {
                $imageName = Helpers::upload('promotional_banner/', 'png', $request->file('image'));
            }
            array_push($data, [
                'img' => $imageName,
                'storage' => Helpers::getDisk(),
            ]);
            $promotion_banner->value = json_encode($data);

            $promotion_banner->save();
            Toastr::success(translate('messages.Landing page promotion banner updated'));
        } elseif ($tab == 'fixed-banner') {
            $fixed_promotional_banner = DataSetting::where('type', 'react_landing_page')->where('key', 'fixed_promotional_banner')->first();
            if ($fixed_promotional_banner == null) {
                $fixed_promotional_banner = new DataSetting;
            }
            $fixed_promotional_banner->key = 'fixed_promotional_banner';
            $fixed_promotional_banner->type = 'react_landing_page';
            $fixed_promotional_banner->value = $request->has('fixed_promotional_banner') ? Helpers::update('promotional_banner/', $fixed_promotional_banner->value, 'png', $request->file('fixed_promotional_banner')) : $fixed_promotional_banner->value;
            $fixed_promotional_banner->save();
            Toastr::success(translate('messages.Landing page promotion banner updated'));
        } elseif ($tab == 'fixed-newsletter') {
            $request->validate([
                'fixed_newsletter_title.0' => 'required',
                'fixed_newsletter_sub_title.0' => 'required',
            ], [
                'fixed_newsletter_title.0.required' => translate('messages.Default title is required'),
                'fixed_newsletter_sub_title.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_landing_page', 'fixed_newsletter_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'fixed_newsletter_sub_title', true);
            $this->getAddLandingPageData($request, 'react_landing_page', 'fixed_footer_description', true);

            Toastr::success(translate('messages.Landing page newsletter content updated'));
        } elseif ($tab == 'meta-data') {
            $this->landingPageMetaDataUpdate($request, 'react');
            Toastr::success(translate('Updated successfully'));

            return back();
        }

        return back();
    }

    public function delete_react_landing_page_settings($tab, $key)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        $item = DataSetting::where('type', 'react_landing_page')->where('key', $tab)->first();
        $data = $item ? json_decode($item->value, true) : null;
        if ($data && array_key_exists($key, $data)) {
            if (isset($data[$key]['img'])) {
                Helpers::check_and_delete('promotion_banner/', $data[$key]['img']);
            }
            array_splice($data, $key, 1);

            $item->value = json_encode($data);
            $item->save();
            Toastr::success(translate('messages.' . $tab) . ' ' . translate('messages.deleted'));

            return back();
        }
        Toastr::error(translate('No data found'));

        return back();
    }

    public function review_react_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this review!');

            return back();
        }
        $review = ReactTestimonial::findOrFail($request->id);
        $review->status = $request->status;
        $review->save();
        Toastr::success(translate('messages.Review status updated'));

        return back();
    }

    public function review_react_edit($id)
    {
        $review = ReactTestimonial::withoutGlobalScope('translate')->withStorage()->with('translations')->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.react-landing-testimonial-edit', compact('review'));
    }

    public function review_react_update(Request $request, $id)
    {
        $request->validate([
            'name.0' => 'required',
            'review.0' => 'required|max:200',
            'reviewer_image' => ImageFile::rules('required'),
        ], [
            'name.0.required' => translate('messages.Default name is required'),
            'review.0.required' => translate('messages.Default review is required'),
        ]);

        $review = ReactTestimonial::withStorage()->findOrFail($id);
        $review->name = $request->name[array_search('default', $request->lang)];
        $review->designation = $request->designation[array_search('default', $request->lang)];
        $review->review = $request->review[array_search('default', $request->lang)];
        $review->reviewer_image = $request->has('reviewer_image') ? Helpers::update('reviewer_image/', $review->reviewer_image, 'png', $request->file('reviewer_image')) : $review->reviewer_image;
        $review->save();

        Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'ReactTestimonial', data_id: $review->id, data_value: $review->name);
        Helpers::add_or_update_translations(request: $request, key_data: 'designation', name_field: 'designation', model_name: 'ReactTestimonial', data_id: $review->id, data_value: $review->designation);
        Helpers::add_or_update_translations(request: $request, key_data: 'review', name_field: 'review', model_name: 'ReactTestimonial', data_id: $review->id, data_value: $review->review);

        Toastr::success(translate('Updated successfully'));

        return redirect()->route('admin.business-settings.react-landing-page-settings', 'testimonials');
    }

    public function review_react_destroy(ReactTestimonial $review)
    {
        if (getEnvMode() == 'demo' && $review->id == 1) {
            Toastr::warning(translate('messages.You can not delete this review please add a new review to delete'));

            return back();
        }
        $review->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function react_ride_share_page_settings($tab)
    {
        if (addon_published_status('RideShare') != 1) {
            abort(404);
        }

        $base = 'admin-views.business-settings.landing-page-settings.';
        $views = [
            'hero' => 'react-ride-share-page-hero',
        ];

        if (!isset($views[$tab])) {
            abort(404);
        }

        return view($base . $views[$tab]);
    }

    public function update_react_ride_share_page_settings(Request $request, $tab)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        if (addon_published_status('RideShare') != 1) {
            abort(404);
        }

        $keyPrefix = '';
        if (str_starts_with($tab, 'rider-')) {
            $keyPrefix = 'rider_';
            $tab = substr($tab, 6);
        }

        if ($tab == 'hero-intro') {
            $titleField = $keyPrefix.'hero_intro_title';
            $subTitleField = $keyPrefix.'hero_intro_sub_title';
            $imageField = $keyPrefix.'hero_intro_image';

            $request->validate([
                $titleField.'.0' => 'required|max:50',
                $subTitleField.'.0' => 'required|max:150',
                $imageField => ImageFile::rules('nullable'),
            ], [
                $titleField.'.0.required' => translate('messages.Default title is required'),
                $subTitleField.'.0.required' => translate('messages.Default subtitle is required'),
            ]);

            $this->getAddLandingPageData($request, 'react_ride_share_page', $titleField, true);
            $this->getAddLandingPageData($request, 'react_ride_share_page', $subTitleField, true);
            $this->saveRideShareImage($request, $imageField, 'ride_share_hero_section');

            Toastr::success(translate('messages.hero_section_updated'));
            return back();
        } elseif (str_starts_with($tab, 'hero-point-card-')) {
            $cardNumber = str_replace('hero-point-card-', '', $tab);
            $statusKey = $keyPrefix."hero_point_status_card_$cardNumber";
            $titleKey = $keyPrefix."hero_point_title_card_$cardNumber";
            $imageKey = $keyPrefix."hero_point_image_card_$cardNumber";

            $request->validate([
                "$titleKey.0" => 'required|max:20',
                $imageKey => ImageFile::rules('nullable'),
            ], [
                "$titleKey.0.required" => translate('messages.Default title is required'),
                "$imageKey.max" => translate('messages.Maximum size') . ': ' . MAX_FILE_SIZE . ' MB',
                "$imageKey.mimes" => translate('messages.Invalid file type'),
            ]);

            $this->getAddLandingPageData($request, 'react_ride_share_page', $statusKey, false);
            $this->getAddLandingPageData($request, 'react_ride_share_page', $titleKey, true);
            $this->saveRideShareImage($request, $imageKey, 'ride_share_hero_section');

            Toastr::success(translate('messages.Hero point card updated'));
            return back();
        }

        Toastr::error(translate('No data found'));
        return back();
    }

    private function saveRideShareImage($request, $key, $dir)
    {
        $data = DataSetting::firstOrNew(['type' => 'react_ride_share_page', 'key' => $key]);

        if ($request->input("{$key}_deleted") == '1') {
            if ($data->value) {
                Helpers::check_and_delete($dir . '/', $data->value);
            }
            $data->value = null;
            $data->save();
            return;
        }

        if ($request->hasFile($key)) {
            $file = $request->file($key);
            $format = strtolower($file->getClientOriginalExtension() ?? 'png');
            $data->value = empty($data->value)
                ? Helpers::upload(dir: $dir . '/', format: $format, image: $file)
                : Helpers::update(dir: $dir . '/', old_image: $data->value, format: $format, image: $file);
            $data->save();
        } elseif (!$data->exists) {
            $data->value = null;
            $data->save();
        }
    }

    public function delete_react_ride_share_page_settings($tab, $key)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        Toastr::error(translate('No data found'));
        return back();
    }

    public function flutter_landing_page_settings($tab)
    {
        view()->share('flutterSettings',
            app(DataSettingService::class)->getEditableRowsByType('flutter_landing_page'));

        if ($tab == 'fixed-data') {
            return view('admin-views.business-settings.landing-page-settings.flutter-fixed-data');
        } elseif ($tab == 'special-criteria') {
            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria');
        } elseif ($tab == 'join-as') {
            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-join-as');
        } elseif ($tab == 'available-zone') {
            return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-available-zone');
        } elseif ($tab == 'download-apps') {
            return view('admin-views.business-settings.landing-page-settings.flutter-download-apps');
        }
    }

    public function update_flutter_landing_page_settings(Request $request, $tab)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        if ($tab == 'special-criteria-list') {
            $request->validate([
                'title' => 'required',
                'image' => ImageFile::rules('required'),
            ]);
            if ($request->title[array_search('default', $request->lang)] == '') {
                Toastr::error(translate('Default data is required'));

                return back();
            }
            $criteria = new FlutterSpecialCriteria;
            $criteria->title = $request->title[array_search('default', $request->lang)];
            $criteria->image = Helpers::upload('special_criteria/', 'png', $request->file('image'));
            $criteria->save();
            $default_lang = str_replace('_', '-', app()->getLocale());
            $data = [];
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                'translationable_id' => $criteria->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $criteria->title]
                        );
                    }
                } else {

                    if ($request->title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                                'translationable_id' => $criteria->id,
                                'locale' => $key,
                                'key' => 'title',
                            ],
                            ['value' => $request->title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('Added successfully'));
        } elseif ($tab == 'available-zone-section') {
            if ($request['available_zone_status']) {
                $request->validate([
                    'available_zone_title.0' => 'required',

                ], [
                    'available_zone_title.0.required' => translate('Default title is required'),
                ]);
            }
            $available_zone_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_title')->first();
            if ($available_zone_title == null) {
                $available_zone_title = new DataSetting;
            }

            $available_zone_title->key = 'available_zone_title';
            $available_zone_title->type = 'flutter_landing_page';
            $available_zone_title->value = $request->available_zone_title[array_search('default', $request->lang)];
            $available_zone_title->save();

            $available_zone_short_description = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_short_description')->first();
            if ($available_zone_short_description == null) {
                $available_zone_short_description = new DataSetting;
            }

            $available_zone_short_description->key = 'available_zone_short_description';
            $available_zone_short_description->type = 'flutter_landing_page';
            $available_zone_short_description->value = $request->available_zone_short_description[array_search('default', $request->lang)];
            $available_zone_short_description->save();

            $available_zone_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'available_zone_image')->first();

            if ($available_zone_image == null) {
                if ($request['available_zone_status']) {
                    $request->validate([
                        'image' => ImageFile::rules('required'),
                    ]);
                }

                $available_zone_image = new DataSetting;
            }
            $available_zone_image->key = 'available_zone_image';
            $available_zone_image->type = 'flutter_landing_page';
            $available_zone_image->value = $request->has('image') ? Helpers::update('available_zone_image/', $available_zone_image->value, 'png', $request->file('image')) : $available_zone_image->value;
            $available_zone_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->available_zone_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $available_zone_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_title->id,
                                'locale' => $key,
                                'key' => 'available_zone_title',
                            ],
                            ['value' => $request->available_zone_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->available_zone_short_description[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $available_zone_short_description?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->available_zone_short_description[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $available_zone_short_description->id,
                                'locale' => $key,
                                'key' => 'available_zone_short_description',
                            ],
                            ['value' => $request->available_zone_short_description[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['type' => 'flutter_landing_page', 'key' => 'available_zone_status'], [
                'value' => $request['available_zone_status'],
            ]);

            Toastr::success(translate('messages.Available zone section updated'));
        } elseif ($tab == 'download-app-section') {

            $download_user_app_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_title')->first();
            if ($download_user_app_title == null) {
                $download_user_app_title = new DataSetting;
            }

            $download_user_app_title->key = 'download_user_app_title';
            $download_user_app_title->type = 'flutter_landing_page';
            $download_user_app_title->value = $request->download_user_app_title[array_search('default', $request->lang)];
            $download_user_app_title->save();

            $download_user_app_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_sub_title')->first();
            if ($download_user_app_sub_title == null) {
                $download_user_app_sub_title = new DataSetting;
            }

            $download_user_app_sub_title->key = 'download_user_app_sub_title';
            $download_user_app_sub_title->type = 'flutter_landing_page';
            $download_user_app_sub_title->value = $request->download_user_app_sub_title[array_search('default', $request->lang)];
            $download_user_app_sub_title->save();

            $download_user_app_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'download_user_app_image')->first();
            if ($download_user_app_image == null) {
                $download_user_app_image = new DataSetting;
            }
            $download_user_app_image->key = 'download_user_app_image';
            $download_user_app_image->type = 'flutter_landing_page';
            $download_user_app_image->value = $request->has('image') ? Helpers::update('download_user_app_image/', $download_user_app_image->value, 'png', $request->file('image')) : $download_user_app_image->value;
            $download_user_app_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->download_user_app_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $download_user_app_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_title',
                            ],
                            ['value' => $request->download_user_app_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->download_user_app_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $download_user_app_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->download_user_app_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $download_user_app_sub_title->id,
                                'locale' => $key,
                                'key' => 'download_user_app_sub_title',
                            ],
                            ['value' => $request->download_user_app_sub_title[$index]]
                        );
                    }
                }
            }

            Helpers::dataUpdateOrInsert(['key' => 'download_user_app_links', 'type' => 'flutter_landing_page'], [
                'value' => json_encode([
                    'playstore_url_status' => $request['playstore_url_status'],
                    'playstore_url' => $request['playstore_url'],
                    'apple_store_url_status' => $request['apple_store_url_status'],
                    'apple_store_url' => $request['apple_store_url'],
                ]),
            ]);

            Toastr::success(translate('messages.Download app section updated'));
        } elseif ($tab == 'fixed-header') {

            $fixed_header_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_title')->first();
            if ($fixed_header_title == null) {
                $fixed_header_title = new DataSetting;
            }

            $fixed_header_title->key = 'fixed_header_title';
            $fixed_header_title->type = 'flutter_landing_page';
            $fixed_header_title->value = $request->fixed_header_title[array_search('default', $request->lang)];
            $fixed_header_title->save();

            $fixed_header_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_sub_title')->first();
            if ($fixed_header_sub_title == null) {
                $fixed_header_sub_title = new DataSetting;
            }

            $fixed_header_sub_title->key = 'fixed_header_sub_title';
            $fixed_header_sub_title->type = 'flutter_landing_page';
            $fixed_header_sub_title->value = $request->fixed_header_sub_title[array_search('default', $request->lang)];
            $fixed_header_sub_title->save();

            $fixed_header_image = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_header_image')->first();
            if ($fixed_header_image == null) {
                $fixed_header_image = new DataSetting;
            }
            $fixed_header_image->key = 'fixed_header_image';
            $fixed_header_image->type = 'flutter_landing_page';
            $fixed_header_image->value = $request->has('image') ? Helpers::update('fixed_header_image/', $fixed_header_image->value, 'png', $request->file('image')) : $fixed_header_image->value;
            $fixed_header_image->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_header_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title',
                            ],
                            ['value' => $fixed_header_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_title',
                            ],
                            ['value' => $request->fixed_header_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_header_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title',
                            ],
                            ['value' => $fixed_header_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_header_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_header_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_header_sub_title',
                            ],
                            ['value' => $request->fixed_header_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Landing page header updated'));
        } elseif ($tab == 'fixed-location') {

            $fixed_location_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_location_title')->first();
            if ($fixed_location_title == null) {
                $fixed_location_title = new DataSetting;
            }

            $fixed_location_title->key = 'fixed_location_title';
            $fixed_location_title->type = 'flutter_landing_page';
            $fixed_location_title->value = $request->fixed_location_title[array_search('default', $request->lang)];
            $fixed_location_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_location_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_location_title->id,
                                'locale' => $key,
                                'key' => 'fixed_location_title',
                            ],
                            ['value' => $fixed_location_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_location_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_location_title->id,
                                'locale' => $key,
                                'key' => 'fixed_location_title',
                            ],
                            ['value' => $request->fixed_location_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Landing page location title updated'));
        } elseif ($tab == 'fixed-module') {

            $fixed_module_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_title')->first();
            if ($fixed_module_title == null) {
                $fixed_module_title = new DataSetting;
            }

            $fixed_module_title->key = 'fixed_module_title';
            $fixed_module_title->type = 'flutter_landing_page';
            $fixed_module_title->value = $request->fixed_module_title[array_search('default', $request->lang)];
            $fixed_module_title->save();

            $fixed_module_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'fixed_module_sub_title')->first();
            if ($fixed_module_sub_title == null) {
                $fixed_module_sub_title = new DataSetting;
            }

            $fixed_module_sub_title->key = 'fixed_module_sub_title';
            $fixed_module_sub_title->type = 'flutter_landing_page';
            $fixed_module_sub_title->value = $request->fixed_module_sub_title[array_search('default', $request->lang)];
            $fixed_module_sub_title->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->fixed_module_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title',
                            ],
                            ['value' => $fixed_module_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_title',
                            ],
                            ['value' => $request->fixed_module_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->fixed_module_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title',
                            ],
                            ['value' => $fixed_module_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->fixed_module_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $fixed_module_sub_title->id,
                                'locale' => $key,
                                'key' => 'fixed_module_sub_title',
                            ],
                            ['value' => $request->fixed_module_sub_title[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Landing page module updated'));
        } elseif ($tab == 'join-seller') {

            if ($request->join_seller_flutter_status !== null) {
                $join_seller_flutter_status = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_flutter_status')->first();
                if ($join_seller_flutter_status == null) {
                    $join_seller_flutter_status = new DataSetting;
                }

                $join_seller_flutter_status->key = 'join_seller_flutter_status';
                $join_seller_flutter_status->type = 'flutter_landing_page';
                $join_seller_flutter_status->value = $request->join_seller_flutter_status ? 0 : 1;
                $join_seller_flutter_status->save();

                Toastr::success(translate('messages.Join as seller section status updated'));

                return back();
            }

            $join_seller_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_title')->first();
            if ($join_seller_title == null) {
                $join_seller_title = new DataSetting;
            }

            $join_seller_title->key = 'join_seller_title';
            $join_seller_title->type = 'flutter_landing_page';
            $join_seller_title->value = $request->join_seller_title[array_search('default', $request->lang)];
            $join_seller_title->save();

            $join_seller_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_sub_title')->first();
            if ($join_seller_sub_title == null) {
                $join_seller_sub_title = new DataSetting;
            }

            $join_seller_sub_title->key = 'join_seller_sub_title';
            $join_seller_sub_title->type = 'flutter_landing_page';
            $join_seller_sub_title->value = $request->join_seller_sub_title[array_search('default', $request->lang)];
            $join_seller_sub_title->save();

            $join_seller_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_name')->first();
            if ($join_seller_button_name == null) {
                $join_seller_button_name = new DataSetting;
            }

            $join_seller_button_name->key = 'join_seller_button_name';
            $join_seller_button_name->type = 'flutter_landing_page';
            $join_seller_button_name->value = $request->join_seller_button_name[array_search('default', $request->lang)];
            $join_seller_button_name->save();

            $join_seller_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_seller_button_url')->first();
            if ($join_seller_button_url == null) {
                $join_seller_button_url = new DataSetting;
            }

            $join_seller_button_url->key = 'join_seller_button_url';
            $join_seller_button_url->type = 'flutter_landing_page';
            $join_seller_button_url->value = $request->join_seller_button_url;
            $join_seller_button_url->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->join_seller_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_title',
                            ],
                            ['value' => $join_seller_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_title',
                            ],
                            ['value' => $request->join_seller_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_seller_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_sub_title',
                            ],
                            ['value' => $join_seller_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_seller_sub_title',
                            ],
                            ['value' => $request->join_seller_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_seller_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'join_seller_button_name',
                            ],
                            ['value' => $join_seller_button_name->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_seller_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_seller_button_name->id,
                                'locale' => $key,
                                'key' => 'join_seller_button_name',
                            ],
                            ['value' => $request->join_seller_button_name[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Join as seller data updated'));
        } elseif ($tab == 'join-delivery') {

            if ($request->join_DM_flutter_status !== null) {
                $join_DM_flutter_status = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_DM_flutter_status')->first();
                if ($join_DM_flutter_status == null) {
                    $join_DM_flutter_status = new DataSetting;
                }

                $join_DM_flutter_status->key = 'join_DM_flutter_status';
                $join_DM_flutter_status->type = 'flutter_landing_page';
                $join_DM_flutter_status->value = $request->join_DM_flutter_status ? 0 : 1;
                $join_DM_flutter_status->save();

                Toastr::success(translate('messages.Join as seller section status updated'));

                return back();
            }

            $join_delivery_man_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_title')->first();
            if ($join_delivery_man_title == null) {
                $join_delivery_man_title = new DataSetting;
            }

            $join_delivery_man_title->key = 'join_delivery_man_title';
            $join_delivery_man_title->type = 'flutter_landing_page';
            $join_delivery_man_title->value = $request->join_delivery_man_title[array_search('default', $request->lang)];
            $join_delivery_man_title->save();

            $join_delivery_man_sub_title = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_sub_title')->first();
            if ($join_delivery_man_sub_title == null) {
                $join_delivery_man_sub_title = new DataSetting;
            }

            $join_delivery_man_sub_title->key = 'join_delivery_man_sub_title';
            $join_delivery_man_sub_title->type = 'flutter_landing_page';
            $join_delivery_man_sub_title->value = $request->join_delivery_man_sub_title[array_search('default', $request->lang)];
            $join_delivery_man_sub_title->save();

            $join_delivery_man_button_name = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_name')->first();
            if ($join_delivery_man_button_name == null) {
                $join_delivery_man_button_name = new DataSetting;
            }

            $join_delivery_man_button_name->key = 'join_delivery_man_button_name';
            $join_delivery_man_button_name->type = 'flutter_landing_page';
            $join_delivery_man_button_name->value = $request->join_delivery_man_button_name[array_search('default', $request->lang)];
            $join_delivery_man_button_name->save();

            $join_delivery_man_button_url = DataSetting::where('type', 'flutter_landing_page')->where('key', 'join_delivery_man_button_url')->first();
            if ($join_delivery_man_button_url == null) {
                $join_delivery_man_button_url = new DataSetting;
            }

            $join_delivery_man_button_url->key = 'join_delivery_man_button_url';
            $join_delivery_man_button_url->type = 'flutter_landing_page';
            $join_delivery_man_button_url->value = $request->join_delivery_man_button_url;
            $join_delivery_man_button_url->save();

            $data = [];
            $default_lang = str_replace('_', '-', app()->getLocale());
            foreach ($request->lang as $index => $key) {
                if ($default_lang == $key && !($request->join_delivery_man_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_title',
                            ],
                            ['value' => $join_delivery_man_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_title',
                            ],
                            ['value' => $request->join_delivery_man_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_delivery_man_sub_title[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_sub_title',
                            ],
                            ['value' => $join_delivery_man_sub_title?->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_sub_title[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_sub_title->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_sub_title',
                            ],
                            ['value' => $request->join_delivery_man_sub_title[$index]]
                        );
                    }
                }
                if ($default_lang == $key && !($request->join_delivery_man_button_name[$index])) {
                    if ($key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_button_name->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_button_name',
                            ],
                            ['value' => $join_delivery_man_button_name->getRawOriginal('value')]
                        );
                    }
                } else {
                    if ($request->join_delivery_man_button_name[$index] && $key != 'default') {
                        Translation::updateOrInsert(
                            [
                                'translationable_type' => 'App\Models\DataSetting',
                                'translationable_id' => $join_delivery_man_button_name->id,
                                'locale' => $key,
                                'key' => 'join_delivery_man_button_name',
                            ],
                            ['value' => $request->join_delivery_man_button_name[$index]]
                        );
                    }
                }
            }

            Toastr::success(translate('messages.Join as delivery man data updated'));
        }

        return back();
    }

    public function flutter_criteria_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this criteria!');

            return back();
        }
        $criteria = FlutterSpecialCriteria::findOrFail($request->id);
        $criteria->status = $request->status;
        $criteria->save();
        Toastr::success(translate('messages.Criteria status updated'));

        return back();
    }

    public function flutter_criteria_edit($id)
    {
        $criteria = FlutterSpecialCriteria::withoutGlobalScope('translate')->withStorage()->with('translations')->findOrFail($id);

        return view('admin-views.business-settings.landing-page-settings.flutter-landing-page-special-criteria-edit', compact('criteria'));
    }

    public function flutter_criteria_update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|max:100',
        ]);

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('Default data is required'));

            return back();
        }
        $criteria = FlutterSpecialCriteria::withStorage()->find($id);
        $criteria->title = $request->title[array_search('default', $request->lang)];
        $criteria->image = $request->has('image') ? Helpers::update('special_criteria/', $criteria->image, 'png', $request->file('image')) : $criteria->image;
        $criteria->save();
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->title[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                            'translationable_id' => $criteria->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $criteria->title]
                    );
                }
            } else {

                if ($request->title[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\FlutterSpecialCriteria',
                            'translationable_id' => $criteria->id,
                            'locale' => $key,
                            'key' => 'title',
                        ],
                        ['value' => $request->title[$index]]
                    );
                }
            }
        }
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function flutter_criteria_destroy(FlutterSpecialCriteria $criteria)
    {
        if (getEnvMode() == 'demo' && $criteria->id == 1) {
            Toastr::warning(translate('messages.You can not delete this criteria please add a new criteria to delete'));

            return back();
        }
        $criteria->delete();
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function email_index(Request $request, $type, $tab)
    {
        $template = $request->query('template');
        $exceptions = [
            'new-order' => 'place-order-format',
            'forgot-password' => 'forgot-pass-format',
            'offline-payment-approve' => 'offline-approved-format',
            'offline-payment-deny' => 'offline-deny-format',
        ];
        if (isset($exceptions[$tab])) {
            $viewName = $exceptions[$tab];
        } else {
            $viewName = $tab . '-format';
        }
        $view = "admin-views.business-settings.email-format-setting.{$type}-email-formats.{$viewName}";

        if (!view()->exists($view)) {
            abort(404, translate('No data found'));
        }

        return view($view, compact('template'));
    }

    public function update_email_index(Request $request, $type, $tab)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        $request->validate([
            'title.*' => 'nullable|max:255',
            'button_name.*' => 'nullable|max:255',
            'footer_text.*' => 'nullable|max:255',
            'copyright_text.*' => 'nullable|max:255',
        ], [
            'title.*.max' => 'The title may not be greater than 255 characters.',
            'button_name.*.max' => 'The button_name may not be greater than 255 characters.',
            'footer_text.*.max' => 'The footer_text may not be greater than 255 characters.',
            'copyright_text.*.max' => 'The copyright_text may not be greater than 255 characters.',
        ]);

        $email_types = [
            'new-order' => 'new_order',
            'forget-password' => 'forget_password',
            'store-registration' => 'store_registration',
            'dm-registration' => 'dm_registration',
            'withdraw-request' => 'withdraw_request',
            'dm-withdraw-request' => 'dm_withdraw_request',
            'withdraw-approve' => 'withdraw_approve',
            'withdraw-deny' => 'withdraw_deny',
            'campaign-request' => 'campaign_request',
            'campaign-approve' => 'campaign_approve',
            'campaign-deny' => 'campaign_deny',
            'refund-request' => 'refund_request',
            'refund-request-deny' => 'refund_request_deny',
            'add-fund' => 'add_fund',
            'refund-order' => 'refund_order',
            'product-deny' => 'product_deny',
            'product-approved' => 'product_approved',
            'offline-payment-deny' => 'offline_payment_deny',
            'offline-payment-approve' => 'offline_payment_approve',
            'pos-registration' => 'pos_registration',
            'registration-otp' => 'registration_otp',
            'login-otp' => 'login_otp',
            'order-verification' => 'order_verification',
            'cash-collect' => 'cash_collect',
            'subscription-successful' => 'subscription-successful',
            'subscription-renew' => 'subscription-renew',
            'subscription-shift' => 'subscription-shift',
            'subscription-cancel' => 'subscription-cancel',
            'subscription-deadline' => 'subscription-deadline',
            'subscription-plan_upadte' => 'subscription-plan_upadte',
            'new-advertisement' => 'new_advertisement',
            'update-advertisement' => 'update_advertisement',
            'advertisement-pause' => 'advertisement_pause',
            'advertisement-approved' => 'advertisement_approved',
            'advertisement-create' => 'advertisement_create',
            'advertisement-deny' => 'advertisement_deny',
            'advertisement-resume' => 'advertisement_resume',
            'unsuspend' => 'unsuspend',
            'suspend' => 'suspend',
            'approve' => 'approve',
            'deny' => 'deny',
            'registration' => 'registration',
        ];

        $email_type = $email_types[$tab] ?? null;

        if (!$email_type) {
            Toastr::error(translate('No data found'));

            return back();
        }

        $template = EmailTemplate::where('type', $type)
            ->where('email_type', $email_type)
            ->firstOrNew();

        if ($request->title[array_search('default', $request->lang)] == '') {
            Toastr::error(translate('Default data is required'));

            return back();
        }
        $template->title = $request->title[array_search('default', $request->lang)];
        $template->body = $request->body[array_search('default', $request->lang)];
        $template->body_2 = $request?->body_2 ? $request->body_2[array_search('default', $request->lang)] : null;
        $template->button_name = $request->button_name ? $request->button_name[array_search('default', $request->lang)] : '';
        $template->footer_text = $request->footer_text[array_search('default', $request->lang)];
        $template->copyright_text = $request->copyright_text[array_search('default', $request->lang)];
        $template->background_image = $request->has('background_image') ? Helpers::update('email_template/', $template->background_image, 'png', $request->file('background_image')) : $template->background_image;
        $template->image = $request->has('image') ? Helpers::update('email_template/', $template->image, 'png', $request->file('image')) : $template->image;
        $template->logo = $request->has('logo') ? Helpers::update('email_template/', $template->logo, 'png', $request->file('logo')) : $template->logo;
        $template->icon = $request->has('icon') ? Helpers::update('email_template/', $template->icon, 'png', $request->file('icon')) : $template->icon;
        $template->email_type = $email_type;
        $template->type = $type;
        $template->button_url = $request->button_url ?? '';
        $template->email_template = $request->email_template;
        $template->privacy = $request->privacy ? '1' : 0;
        $template->refund = $request->refund ? '1' : 0;
        $template->cancelation = $request->cancelation ? '1' : 0;
        $template->contact = $request->contact ? '1' : 0;
        $template->facebook = $request->facebook ? '1' : 0;
        $template->instagram = $request->instagram ? '1' : 0;
        $template->twitter = $request->twitter ? '1' : 0;
        $template->linkedin = $request->linkedin ? '1' : 0;
        $template->pinterest = $request->pinterest ? '1' : 0;
        $template->save();

        Helpers::add_or_update_translations(request: $request, key_data: 'title', name_field: 'title', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->title);
        Helpers::add_or_update_translations(request: $request, key_data: 'body', name_field: 'body', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->body);
        if ($request?->body_2) {
            Helpers::add_or_update_translations(request: $request, key_data: 'body_2', name_field: 'body_2', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->body_2);
        }
        Helpers::add_or_update_translations(request: $request, key_data: 'button_name', name_field: 'button_name', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->button_name);
        Helpers::add_or_update_translations(request: $request, key_data: 'footer_text', name_field: 'footer_text', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->footer_text);
        Helpers::add_or_update_translations(request: $request, key_data: 'copyright_text', name_field: 'copyright_text', model_name: 'EmailTemplate', data_id: $template->id, data_value: $template->copyright_text);

        Toastr::success(translate('Added successfully'));

        return back();
    }

    public function update_email_status($type, $tab, $status)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }

        $specialCases = [
            'forgot-password' => 'forget_password',
        ];

        $key = ($specialCases[$tab] ?? str_replace('-', '_', $tab)) . '_mail_status_' . $type;

        Helpers::businessUpdateOrInsert(['key' => $key], ['value' => $status]);

        Toastr::success(translate('messages.Email status updated'));

        return back();
    }

    public function login_url_page()
    {
        $data = array_column(DataSetting::whereIn('key', [
            'store_employee_login_url',
            'store_login_url',
            'admin_employee_login_url',
            'admin_login_url',
        ])->get(['key', 'value'])->toArray(), 'value', 'key');

        return view('admin-views.login-setup.login_setup', compact('data'));
    }

    public function login_page()
    {

        abort(404);

        return view('admin-views.login-setup.login_page');
    }

    public function login_url_page_update(Request $request)
    {

        $request->validate([
            'type' => 'required',
            'admin_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'admin_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'store_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
            'store_employee_login_url' => 'nullable|regex:/^[a-zA-Z0-9\-\_]+$/u|unique:data_settings,value',
        ]);

        if ($request->type == 'admin') {
            DataSetting::query()->updateOrInsert(['key' => 'admin_login_url', 'type' => 'login_admin'], [
                'value' => $request->admin_login_url,
            ]);
        } elseif ($request->type == 'admin_employee') {
            DataSetting::query()->updateOrInsert(['key' => 'admin_employee_login_url', 'type' => 'login_admin_employee'], [
                'value' => $request->admin_employee_login_url,
            ]);
        } elseif ($request->type == 'store') {
            DataSetting::query()->updateOrInsert(['key' => 'store_login_url', 'type' => 'login_store'], [
                'value' => $request->store_login_url,
            ]);
        } elseif ($request->type == 'store_employee') {
            DataSetting::query()->updateOrInsert(['key' => 'store_employee_login_url', 'type' => 'login_store_employee'], [
                'value' => $request->store_employee_login_url,
            ]);
        }
        Toastr::success(translate('messages.update_successfull'));

        return back();
    }

    public function remove_image(Request $request)
    {

        $request->validate([
            'model_name' => 'required',
            'id' => 'required',
            'image_path' => 'required',
            'field_name' => 'required',
        ]);
        try {

            $model_name = $request->model_name;
            $model = app("\\App\\Models\\{$model_name}");
            $data = $model->where('id', $request->id)->first();

            $data_value = $data?->{$request->field_name};

            if ($request?->json == 1) {
                $data_value = json_decode($data?->value, true);

                Helpers::check_and_delete($request->image_path . '/', $data_value[$request->field_name]);

                $data_value[$request->field_name] = null;
                $data->value = json_encode($data_value);
            } else {

                Helpers::check_and_delete($request->image_path . '/', $data_value);

                $data->{$request->field_name} = null;
            }

            $data?->save();
        } catch (\Throwable $th) {
            Toastr::error($th->getMessage() . 'Line....' . $th->getLine());

            return back();
        }
        Toastr::success(translate('Deleted successfully'));

        return back();
    }

    public function landing_page_settings_update(Request $request)
    {
        if ($request->choose_admin_landing === 'default') {
            return $this->storeDefaultLandingPage($request);
        }

        if ($request->choose_admin_landing === 'custom') {
            return $this->storeCustomLandingPage($request);
        }

        return response()->json([
            'status' => 'error',
            'message' => translate('Invalid request'),
        ]);
    }

    private function storeDefaultLandingPage(Request $request)
    {
        Helpers::businessUpdateOrInsert(
            ['key' => 'landing_page'],
            ['value' => 1]
        );

        return response()->json([
            'status' => 'success',
            'message' => translate('Updated successfully'),
        ]);
    }

    private function storeCustomLandingPage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'landing_integration_via' => 'required|in:url,file_upload,none',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $response = match ($request->landing_integration_via) {
            'url' => $this->handleIfUrl($request),
            'file_upload' => $this->handleIfFileUpload($request),
            'none' => $this->handleIfNone($request),
        };

        if (
            !isset($response->original['status']) ||
            $response->original['status'] !== 'success'
        ) {
            return $response;
        }

        Helpers::businessUpdateOrInsert(
            ['key' => 'landing_page'],
            ['value' => 0]
        );

        Helpers::businessUpdateOrInsert(
            ['key' => 'landing_integration_type'],
            ['value' => $request->landing_integration_via]
        );

        return $response;
    }


    private function handleIfUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'redirect_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        Helpers::businessUpdateOrInsert(
            ['key' => 'landing_page_custom_url'],
            ['value' => $request->redirect_url]
        );

        return response()->json([
            'status' => 'success',
            'message' => translate('Saved successfully'),
        ]);
    }

    private function handleIfFileUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_upload' => 'required_if:file_exist,0|mimes:zip',
        ],[
            'file_upload.required_if' => translate('messages.Zip file is required'),
        ]);

        if (
            !File::exists('resources/views/layouts/landing/custom/index.blade.php') &&
            !$request->hasFile('file_upload')
        ) {
            $validator->errors()->add(
                'file_upload',
                translate('messages.Zip file is required, upload zip file first')
            );
        }

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        return $this->processLandingZip($request);
    }

    private function processLandingZip(Request $request)
    {
        if (!$request->hasFile('file_upload')) {
            return response()->json([
                'status' => 'success',
                'message' => translate('Updated successfully'),
            ]);
        }

        $file = $request->file('file_upload');
        $filename = $file->getClientOriginalName();
        $tempZipPath = $file->storeAs('temp', $filename);

        $zipPath = storage_path('app/' . $tempZipPath);
        $extractPath = base_path('resources/views/layouts/landing/custom');
        $tempExtractPath = storage_path('app/temp/landing_extract');

        try {
            File::deleteDirectory($tempExtractPath);
            File::makeDirectory($tempExtractPath, 0755, true);

            $zip = new \ZipArchive;

            if ($zip->open($zipPath) !== true) {
                throw new \Exception('Failed to open zip file');
            }

            $zip->extractTo($tempExtractPath);
            $zip->close();

            $indexFile = collect(File::allFiles($tempExtractPath))
                ->first(fn($file) => $file->getFilename() === 'index.blade.php');

            if (!$indexFile) {
                throw new \Exception('index.blade.php not found');
            }

            File::deleteDirectory($extractPath);
            File::makeDirectory($extractPath, 0755, true);
            File::copyDirectory($indexFile->getPath(), $extractPath);

            File::deleteDirectory($tempExtractPath);
            Storage::delete($tempZipPath);

            return response()->json([
                'status' => 'success',
                'message' => translate('File upload successfully!'),
            ]);

        } catch (\Exception $e) {
            File::deleteDirectory($tempExtractPath);
            Storage::delete($tempZipPath);

            return response()->json([
                'status' => 'error',
                'message' => translate('File upload fail!') . ' ' . $e->getMessage(),
            ]);
        }
    }


    private function handleIfNone(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => translate('Updated successfully'),
        ]);
    }

    private function validationErrorResponse($validator)
    {
        $error = Helpers::error_processor($validator);

        return response()->json([
            'status' => 'error',
            'message' => $error[0]['message'],
        ]);
    }



    public function delete_custom_landing_page()
    {
        $filePath = 'resources/views/layouts/landing/custom/index.blade.php';

        if (File::exists($filePath)) {
            File::delete($filePath);
            Toastr::success(translate('Deleted successfully'));

            return back();
        } else {
            Toastr::error(translate('No data found'));

            return back();
        }
    }

    public static function product_approval_all()
    {
        $temp_data = TempProduct::where('is_rejected', 0)->get();

        foreach ($temp_data as $data) {
            $item = Item::withoutGlobalScope('translate')->with('translations')->findOrfail($data->item_id);

            $item->name = $data->name;
            $item->description = $data->description;
            $item->image = $data->image;
            $item->images = $data->images;

            $item->store_id = $data->store_id;
            $item->module_id = $data->module_id;
            $item->unit_id = $data->unit_id;

            $item->category_id = $data->category_id;
            $item->category_ids = $data->category_ids;
            $item->store_category_id = $data->store_category_id;

            $item->choice_options = $data->choice_options;
            $item->food_variations = $data->food_variations;
            $item->variations = $data->variations;
            $item->add_ons = $data->add_ons;
            $item->attributes = $data->attributes;

            $item->price = $data->price;
            $item->discount = $data->discount;
            $item->discount_type = $data->discount_type;

            $item->available_time_starts = $data->available_time_starts;
            $item->available_time_ends = $data->available_time_ends;
            $item->maximum_cart_quantity = $data->maximum_cart_quantity;
            $item->veg = $data->veg;

            $item->organic = $data->organic;
            $item->stock = $data->stock;
            $item->is_approved = 1;

            $item->save();
            $item->tags()->sync(json_decode($data->tag_ids));
            if ($item->module->module_type == 'pharmacy') {
                PharmacyItemDetails::updateOrInsert(
                    ['item_id' => $item->id],
                    [
                        'common_condition_id' => $data->condition_id,
                        'is_basic' => $data->basic ?? 0,
                        'is_prescription_required' => $data->is_prescription_required ?? 0,
                    ]
                );
            }
            if ($item->module->module_type == 'ecommerce') {
                EcommerceItemDetails::updateOrInsert(
                    ['item_id' => $item->id],
                    [
                        'brand_id' => $data->brand_id,
                    ]
                );
            }
            $item?->translations()?->delete();
            Translation::where('translationable_type', 'App\Models\TempProduct')->where('translationable_id', $data->id)->update([
                'translationable_type' => 'App\Models\Item',
                'translationable_id' => $item->id,
            ]);
            $item?->taxVats()?->delete();
            if (addon_published_status('TaxModule')) {
                $SystemTaxVat = \Modules\TaxModule\Entities\SystemTaxSetup::where('is_active', 1)->where('is_default', 1)->first();
                if ($SystemTaxVat?->tax_type == 'product_wise') {
                    \Modules\TaxModule\Entities\Taxable::where('taxable_type', 'App\Models\TempProduct')->where('taxable_id', $data->id)
                        ->update(['taxable_type' => 'App\Models\Item', 'taxable_id' => $item->id]);
                }
            }
            $data->delete();
        }

        return true;
    }

    public function notification_setup(Request $request)
    {

        abort_if(!addon_published_status('Rental') && $request?->module == 'rental', 404);
        abort_if(!addon_published_status('Service') && $request?->module == 'service', 404);

        if (NotificationSetting::count() == 0) {
            Helpers::notificationDataSetup();
        }
        if (addon_published_status('Rental') && $request?->module == 'rental') {
            Helpers::seedRentalAdminNotificationSettings();
        }
        if (addon_published_status('Service') && $request?->module == 'service') {
            Helpers::seedServiceAdminNotificationSettings();
        }

        Helpers::addNewAdminNotificationSetupDataSetup();

        $module_type = $request?->module == 'rental' ? 'rental' : ($request?->module == 'service' ? 'service' : 'all');

        $data = NotificationSetting::where('module_type', $module_type)
            ->when($request?->type == null || $request?->type == 'admin', function ($query) {
                $query->where('type', 'admin');
            })
            ->when($request?->type == 'store', function ($query) {
                $query->where('type', 'store');
            })
            ->when($request?->type == 'provider', function ($query) {
                $query->where('type', 'provider');
            })
            ->when($request?->type == 'customers', function ($query) {
                $query->where('type', 'customer');
            })
            ->when($request?->type == 'deliveryman', function ($query) {
                $query->where('type', 'deliveryman');
            })->get();

        $business_name = Helpers::get_business_settings('business_name', false);

        $view = $request?->module == 'rental'
            ? 'admin-views.business-settings.notification_setup_rental'
            : ($request?->module == 'service' ? 'admin-views.business-settings.notification_setup_service' : 'admin-views.business-settings.notification_setup');

        return view($view, compact('business_name', 'data'));
    }

    public function notification_status_change($key, $user_type, $type)
    {
        $data = NotificationSetting::where('type', $user_type)->where('key', $key)->first();
        if (!$data) {
            Toastr::error(translate('No data found'));

            return back();
        }
        if ($type == 'Mail') {
            $data->mail_status = $data->mail_status == 'active' ? 'inactive' : 'active';
        } elseif ($type == 'push_notification') {
            $data->push_notification_status = $data->push_notification_status == 'active' ? 'inactive' : 'active';
        } elseif ($type == 'SMS') {
            $data->sms_status = $data->sms_status == 'active' ? 'inactive' : 'active';
        }
        $data?->save();

        Toastr::success(translate('messages.Notification settings updated'));

        return back();
    }

    public function openAI()
    {
        return view('admin-views.business-settings.3rd_party.open_ai_config');
    }

    public function openAISettings()
    {
        $data = Helpers::get_business_settings_many([
            'section_wise_ai_limit',
            'image_upload_limit_for_ai',
            'ai_chat_status',
        ]);

        return view('admin-views.business-settings.3rd_party.open_ai_settings', compact('data'));
    }

    public function openAISettingsUpdate(Request $request)
    {
        $limits = [
            'section_wise_ai_limit' => $request->section_wise_ai_limit ?? 0,
            'image_upload_limit_for_ai' => $request->image_upload_limit_for_ai ?? 0,
            'ai_chat_status' => $request->ai_chat_status ?? 0,
        ];

        foreach ($limits as $key => $value) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'key' => $key,
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        Toastr::success(translate('messages.Updated successfully'));

        return back();
    }

    public function openAIConfigStatus(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        $config = BusinessSetting::where(['key' => 'openai_config'])->first();

        $data = $config ? json_decode($config['value'], true) : null;

        Helpers::businessUpdateOrInsert(
            ['key' => 'openai_config'],
            [
                'value' => json_encode([
                    'status' => $request['status'] ?? 0,
                    'OPENAI_ORGANIZATION' => $data['OPENAI_ORGANIZATION'] ?? '',
                    'OPENAI_API_KEY' => $data['OPENAI_API_KEY'] ?? '',
                ]),
                'updated_at' => now(),
            ]
        );
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    public function openAIConfigUpdate(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        $config = BusinessSetting::where(['key' => 'openai_config'])->first();

        $data = $config ? json_decode($config['value'], true) : null;

        Helpers::businessUpdateOrInsert(
            ['key' => 'openai_config'],
            [
                'value' => json_encode([
                    'status' => $data['status'] ?? 0,
                    'OPENAI_ORGANIZATION' => $request['OPENAI_ORGANIZATION'] ?? '',
                    'OPENAI_API_KEY' => $request['OPENAI_API_KEY'] ?? '',
                ]),
                'updated_at' => now(),
            ]
        );
        Toastr::success(translate('Updated successfully'));

        return back();
    }

    private function getAddLandingPageData($request, $type, $key, $multiLang, $filePath = '/')
    {
        $data = DataSetting::firstOrNew(['type' => $type, 'key' => $key]);

        if ($request->hasFile($key)) {
            $file = $request->file($key);
            $format = strtolower($file->getClientOriginalExtension() ?? 'png');
            $existingImage = $data->exists ? $data->value : null;
            $data->value = empty($existingImage)
                ? Helpers::upload(dir: $filePath, format: $format, image: $file)
                : Helpers::update(dir: $filePath, old_image: $existingImage, format: $format, image: $file);
        } else {

            if ($multiLang) {
                $defaultIndex = array_search('default', $request->lang);
                $data->value = $request->{$key}[$defaultIndex] ?? null;
            } else {
                $data->value = $request->{$key} ?? 0;
            }
        }
        $data->save();

        if ($multiLang) {
            Helpers::add_or_update_translations(
                request: $request,
                key_data: $key,
                name_field: $key,
                model_name: 'DataSetting',
                data_id: $data->id,
                data_value: $data->value
            );
        }

        return $data;

    }

    public function reactFaqStore(Request $request)
    {
        $request->validate([
            'user_type' => 'required',
            'question.0' => 'required|max:150',
            'answer.0' => 'required|max:500',
        ], [
            'user_type' => translate('User type required'),
            'question.0.required' => translate('Default question is required'),
            'answer.0.required' => translate('Default answer is required')
        ]);

        $faq = new FAQ();
        $faq->question = $request->question[array_search('default', $request->lang)];
        $faq->answer = $request->answer[array_search('default', $request->lang)];
        $faq->user_type = $request->user_type ?? 'customer';
        $faq->page_type = 'react_landing_page';
        $faq->save();
        Helpers::add_or_update_translations(request: $request, key_data: 'question', name_field: 'question', model_name: 'FAQ', data_id: $faq->id, data_value: $faq->question);
        Helpers::add_or_update_translations(request: $request, key_data: 'answer', name_field: 'answer', model_name: 'FAQ', data_id: $faq->id, data_value: $faq->answer);

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function reactFaqStatus(Request $request)
    {

        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this faq!');
            return back();
        }
        $faq = FAQ::findOrFail($request->id);
        $faq->status = !$faq->status;
        $faq->save();
        Toastr::success(translate('messages.Faq status updated'));
        return back();
    }

    public function reactfaqEdit($id)
    {
        $language = Helpers::get_business_settings('language');
        $faq = FAQ::withoutGlobalScope('translate')->with('translations')->findOrfail($id);

        return response()->json([
            'view' => view('admin-views.business-settings.landing-page-settings._react-landing-page-faq-edit', compact('faq', 'language'))->render(),
        ]);
    }

    public function reactFaqUpdate(Request $request, $id)
    {
        $request->validate([
            'question' => 'required|max:100',
            'answer' => 'required|max:1000',

        ]);
        $faq = FAQ::findOrFail($id);
        $faq->question = $request->question[array_search('default', $request->lang)];
        $faq->answer = $request->answer[array_search('default', $request->lang)];

        $faq->save();
        Helpers::add_or_update_translations(request: $request, key_data: 'question', name_field: 'question', model_name: 'FAQ', data_id: $faq->id, data_value: $faq->question);
        Helpers::add_or_update_translations(request: $request, key_data: 'answer', name_field: 'answer', model_name: 'FAQ', data_id: $faq->id, data_value: $faq->answer);


        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function reactFaqDestroy(FAQ $faq)
    {
        if (getEnvMode() == 'demo' && $faq->id == 1) {
            Toastr::warning(translate('messages.You can not delete this review please add a new review to delete'));
            return back();
        }
        $faq->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function statusUpdate($type, $key)
    {
        $dataSetting = DataSetting::firstOrNew([
            'type' => $type,
            'key' => $key,
        ]);
        $dataSetting->value = !$dataSetting->value;
        $dataSetting->save();
        $key = $this->formatSectionName($key);
        Toastr::success(translate('Updated successfully') . ': ' . $key);
        return back();
    }

    private function imageDelete($dir, $type, $key)
    {
        $image = DataSetting::where('type', $type)->where('key', $key)->first();
        if ($image && $image->value) {
            \App\CentralLogics\Helpers::check_and_delete(
                $dir . '/',
                $image->value,
            );
        }
        return true;
    }

    public function react_promotional_banner_update(Request $request, $id)
    {
        $ReactPromotionalBanner = ReactPromotionalBanner::withStorage()->findOrFail($id);
        $ReactPromotionalBanner->image = $request->has('image') ? Helpers::update(dir: 'promotional_banner/', old_image: $ReactPromotionalBanner->image, format: 'png', image: $request->file('image')) : $ReactPromotionalBanner->image;
        $ReactPromotionalBanner->save();

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function react_promotional_banner_destroy(ReactPromotionalBanner $react_promotional_banner)
    {
        if (getEnvMode() == 'demo' && $react_promotional_banner->id == 1) {
            Toastr::warning(translate('messages.You can not delete this review please add a new review to delete'));
            return back();
        }

        Helpers::check_and_delete('react_promotional_banner/', $react_promotional_banner->image);

        $react_promotional_banner?->translations()?->delete();
        $react_promotional_banner?->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    private function formatSectionName($key)
    {
        $lastUnderscorePos = strrpos($key, '_');
        $firstPart = substr($key, 0, $lastUnderscorePos);
        $firstPart = str_replace('_', ' ', $firstPart);
        $firstPart = ucwords($firstPart);
        return $firstPart;
    }

    public function react_promotional_banner_store(Request $request)
    {
        $request->validate([
            'image' => ImageFile::rules('required'),
        ]);

        $react_promotional_banner = new ReactPromotionalBanner();
        $react_promotional_banner->image = Helpers::upload(dir: 'promotional_banner/', format: 'png', image: $request->file('image'));
        $react_promotional_banner->save();

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function react_promotional_banner_status(Request $request)
    {
        if (getEnvMode() == 'demo' && $request->id == 1) {
            Toastr::warning('Sorry!You can not inactive this review!');
            return back();
        }
        $ReactPromotionalBanner = ReactPromotionalBanner::findOrFail($request->id);
        $ReactPromotionalBanner->status = $request->status;
        $ReactPromotionalBanner->save();
        Toastr::success(translate('messages.React promotional banner status updated'));
        return back();
    }

    public function pageMetaData(Request $request)
    {
        $pages = Helpers::seoPageList();

        if ($request->page_name && in_array($request->page_name, $pages)) {
            $language = Helpers::get_business_settings('language');
            $pageMetaData = PageSeoData::where('page_name', $request->page_name)->first();
            return view('admin-views.business-settings.seo-settings.page-meta-data-edit', compact('language', 'pageMetaData'));
        }

        if($request->has('search')){
            $key = explode(' ', $request['search'] ?? '');
            $pages = $pages = collect($pages)->filter(function ($item) use ($key) {
                foreach ($key as $k) {
                    if (stripos($item, $k) !== false) {
                        return true;
                    }
                }
                return false;
            })->values()->all();
        }
        $pageMetaData = PageSeoData::whereIn('page_name', $pages)
            ->withStorage()
            ->select(['id', 'page_name', 'title', 'description', 'image', 'meta_data', 'updated_at'])
            ->get()
            ->keyBy('page_name');

        $configured = $pageMetaData->count();
        $summary = [
            'total' => count($pages),
            'configured' => $configured,
            'pending' => max(count($pages) - $configured, 0),
            'no_index' => $pageMetaData->filter(fn ($row) => ($row->meta_data['meta_index'] ?? 1) == 0)->count(),
        ];

        return view('admin-views.business-settings.seo-settings.page-meta-data', compact('pages', 'pageMetaData', 'summary'));
    }
    public function pageMetaDataUpdate(Request $request)
    {
        $pages = Helpers::seoPageList();

        if ($request->page_name && in_array($request->page_name, $pages)) {
            $pageMetaData = PageSeoData::firstOrNew([
                'page_name' => $request->page_name,
            ]);

            if ($request->has('meta_image_deleted') && $request->meta_image_deleted == 1) {
                Helpers::check_and_delete('page_meta_data/', $pageMetaData->image);
                $pageMetaData->image = null;
            }

            $imageFile = $request->hasFile('meta_image') ? $request->file('meta_image') : $pageMetaData->image;
            $originalExtension = $request->hasFile('meta_image') ? $imageFile->getClientOriginalExtension() : 'png';

            $pageMetaData->title = $request->meta_title;
            $pageMetaData->description = $request->meta_description;
            $pageMetaData->image = $request->file('meta_image') ? Helpers::upload(dir: 'page_meta_data/', format: $originalExtension, image: $imageFile) : $pageMetaData->image;
            $pageMetaData->meta_data = Helpers::formatMetaData($request->all(), $pageMetaData->meta_data);

            $slug = Str::slug($pageMetaData->title);
            $pageMetaData->slug = $pageMetaData->slug ?: "{$slug}{$pageMetaData->id}";
            $pageMetaData->save();

            Toastr::success(translate('Updated successfully'));
        } else {
            Toastr::error(translate('messages.invalid_page_name'));
        }

        return redirect()->route('admin.business-settings.seo-settings.pageMetaData');
    }

    public function websocket()
    {
        return view('admin-views.business-settings.websocket-index');
    }

    public function update_websocket(Request $request)
    {
        if (getEnvMode() == 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));

            return back();
        }
        $request->validate([
            'websocket_url' => [
                'required',
                'regex:/^(ws|wss):\/\/[^\s\/$.?#].[^\s]*$/'
            ],
        ], [
            'websocket_url.regex' =>
                translate('messages.Please enter a valid WebSocket URL'),
        ]);

        $settings = [
            'websocket_status' => $request['websocket_status'],
            'websocket_url' => $request['websocket_url'],
            'websocket_port' => $request['websocket_port'],
        ];
        foreach ($settings as $key => $value) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $value,
            ]);
        }

        Toastr::success(translate('messages.Successfully updated to changes restart app'));

        return back();
    }

    public function paymentMethodStatusUpdate(Request $request)
    {
        $request['status'] = $request->status ?? 0;
        $settings = Setting::firstOrNew(['key_name' => $request['gateway'], 'settings_type' => 'payment_config']);

        if ($request['status'] == 1) {
            $additional_data = json_decode($settings->additional_data, true);
            $live_values = $settings->live_values;

            if (empty($additional_data) || empty($live_values)) {
                Toastr::error(translate('messages.Please fill all required fields in setup first'));
                return back();
            }

            if (empty($additional_data['gateway_title']) || empty($additional_data['gateway_image'])) {
                Toastr::error(translate('messages.Please fill all required fields in setup first'));
                return back();
            }

            foreach ($live_values as $key => $value) {
                if ($key != 'mode' && $key != 'status' && $key != 'gateway' && empty($value)) {
                    Toastr::error(translate('messages.Please fill all required fields in setup first'));
                    return back();
                }
            }
        }

        $settings->is_active = $request['status'];
        $settings->save();

        Toastr::success(translate('messages.Payment method status updated'));

        return back();
    }

    public function updatePaymentSetup(Request $request)
    {
        $partialMethods = $request->partial_payment_method ?? [];

        $partialPaymentMethod = null;
        if($request->boolean('partial_payment_status') && empty($partialMethods)){
            Toastr::error(translate('messages.Combined payment method is required'));
            return back();
        }

        if (!empty($partialMethods)) {
            if (count(array_intersect($partialMethods, ['cod', 'digital_payment'])) === 2) {
                $partialPaymentMethod = 'both';
            } else {
                $partialPaymentMethod = $partialMethods[0];
            }
        }

        $keysToUpdate = [
            'cash_on_delivery'       => $request->boolean('cash_on_delivery'),
            'digital_payment'        => $request->boolean('digital_payment'),
            'offline_payment_status' => $request->boolean('offline_payment'),
            'partial_payment_status' => $request->boolean('partial_payment_status'),
            'partial_payment_method' => $partialPaymentMethod,
        ];

        foreach ($keysToUpdate as $key => $value) {

            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $key == 'cash_on_delivery' || $key == 'digital_payment' ? json_encode(['status' => $value]) : $value,
                'updated_at' => now(),
            ]);
        }

        Toastr::success(translate('messages.Payment settings updated'));
        return back();
    }

    private function updateBasicSettings(Request $request): void
    {
                $settings = [
                'business_name' => $request->business_name,
                'currency' => $request->currency,
                'timezone' => $request->timezone,
                'site_direction' => $request->site_direction,
                'phone' => $request->phone,
                'email_address' => $request->email_address,
                'address' => $request->address,
                'footer_text' => $request->footer_text,
                'cookies_text' => $request->cookies_text,
                'currency_symbol_position' => $request->currency_symbol_position,
                'admin_commission' => $request->admin_commission,
                'country' => $request->country,
                'country_picker_status' => $request->country_picker_status ?? 0,
                'timeformat' => $request->timeformat,
                'digit_after_decimal_point' => $request->digit_after_decimal_point,
                'delivery_charge_comission' => $request->delivery_charge_comission,
                // Measurement units ride this form like every other Business Info field.
                // Switching one only changes the unit stored numbers are READ in — nothing is
                // converted, which is why there is no confirmation step: the numbers an admin
                // typed stay exactly as they typed them.
                'distance_unit' => in_array($request->distance_unit, ['km', 'mi'], true) ? $request->distance_unit : 'km',
                'weight_unit' => in_array($request->weight_unit, ['kg', 'lb'], true) ? $request->weight_unit : 'kg',
                'dimension_unit' => in_array($request->dimension_unit, ['cm', 'in'], true) ? $request->dimension_unit : 'in',
            ];

            foreach ($settings as $key => $value) {
                Helpers::businessUpdateOrInsert(['key' => $key], ['value' => $value]);
            }

            Config::set('currency', $request->currency);
            Config::set('currency_symbol_position', $request->currency_symbol_position);
    }

    private function updateImages(Request $request): void
    {
        $this->updateImage('logo', $request->file('logo'));
        $this->updateImage('icon', $request->file('icon'));
    }

    private function updateImage(string $key, $file = null): void
    {
        $setting = BusinessSetting::firstOrNew(['key' => $key]);

        if ($file) {
            $setting->value = Helpers::update('business/', $setting->value, 'png', $file);
        }

        $setting->save();
    }

    private function updateLocationSettings(Request $request): void
    {
        Helpers::businessUpdateOrInsert(
            ['key' => 'default_location'],
            ['value' => json_encode([
                'lat' => $request->latitude,
                'lng' => $request->longitude,
            ])]
        );
    }

    private function updateAdditionalChargeSettings(Request $request): void
    {
        $settings = [
            'additional_charge_status' => $request->additional_charge_status ?: null,
            'additional_charge_name' => $request->additional_charge_name ?: null,
            'additional_charge' => $request->additional_charge ?: null,
        ];

        foreach ($settings as $key => $value) {
            Helpers::businessUpdateOrInsert(['key' => $key], ['value' => $value]);
        }
    }

    private function updateBusinessModelSettings(Request $request): void
    {
            if (!$request->subscription_business_model && !$request->commission_business_model) {
                Toastr::error(translate('You must select at least one business model between commission and subscription'));
                back()->throwResponse();
            }

            if ($request->subscription_business_model && !$request->commission_business_model) {
                $this->handleSubscriptionOnlyModel($request);
            } elseif ($request->commission_business_model && !$request->subscription_business_model) {
                $this->handleCommissionOnlyModel($request);
            } else {
                $this->handleBothBusinessModels($request);
            }
    }
    private function handleSubscriptionOnlyModel(Request $request): void
    {
            Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], ['value' => 1]);
            Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], ['value' => 0]);

            if (Helpers::get_business_settings('commission_business_model', false) === 0) {
                Store::where('store_business_model', 'commission')
                    ->update(['store_business_model' => 'unsubscribed', 'status' => 0]);
            }
    }

    private function handleCommissionOnlyModel(Request $request): void
    {
        if (StoreSubscription::where('status', 1)->exists()) {
            Toastr::warning(translate('You need to switch your subscribers to commission first'));
            back()->throwResponse();
        }

        Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], ['value' => 1]);
        Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], ['value' => 0]);

        if (Helpers::get_business_settings('subscription_business_model', false) === 0) {
            Store::query()->update(['store_business_model' => 'commission']);
        }
    }

    private function handleBothBusinessModels(Request $request): void
    {
        Helpers::businessUpdateOrInsert(['key' => 'commission_business_model'], ['value' => 1]);

        if (!$request->subscription_business_model &&  StoreSubscription::where('status', 1)->exists()) {
            Toastr::warning(translate('You need to switch your subscribers to commission first'));
            back()->throwResponse();
        }

        Helpers::businessUpdateOrInsert(['key' => 'subscription_business_model'], ['value' => 1]);
    }


}
