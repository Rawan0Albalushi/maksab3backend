<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Support\Notification\Fcm\WebPushServiceWorker;
use App\Models\Setting;
use App\Models\DataSetting;

ini_set('max_execution_time', 180);

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\Coupon;
use App\Models\DeliveryHistory;
use App\Models\Module;
use App\Support\Addon\AddonCompatibility;
use App\Traits\System\ActivationTrait;
use Illuminate\Support\Facades\DB;
use App\Models\NotificationSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

class UpdateController extends Controller
{
    use ActivationTrait;

    private const MODULE_STATUS_FILE = 'modules_statuses.json';

    public function update_software_index()
    {
        $this->discardCompiledRoutes();

        $permission = Helpers::system_permission_check();

        $phpVersion = number_format((float)phpversion(), 2, '.', '');
        $buyerUsername = env('BUYER_USERNAME');
        $purchaseCode = env('PURCHASE_CODE');
        $fileChecks = Helpers::system_file_checks();

        return view('update.update-software', compact('permission', 'phpVersion', 'buyerUsername', 'purchaseCode', 'fileChecks'));
    }

    /**
     * Take the Builder add-on out of the boot path for the length of the update.
     *
     * The update package carries core only -- no Modules/ -- so a client's Builder copy stays
     * at whatever version they last installed while core moves forward. Its adapters live in
     * core (app/Builder) but their contracts live in the add-on, its storefront claims a root
     * route on every hostname, and its provider is package-discovered, so it registers before
     * the application's own. Any one of those can take the panel or the updater down on the
     * first request after the files are replaced, and none of it can be fixed by shipping
     * module code the package does not contain.
     *
     * Deactivating it in modules_statuses.json stops nwidart from loading the module at all,
     * which needs no cooperation from whatever version of the module is on disk. The cached
     * provider manifest names the same providers, so it goes with it.
     *
     * @return bool|null what the file said before, to hand back to restoreBuilderModule()
     */
    private function suspendBuilderModule(): ?bool
    {
        try {
            $path = base_path(self::MODULE_STATUS_FILE);

            if (!is_file($path) || !is_writable($path)) {
                return null;
            }

            $statuses = json_decode((string) file_get_contents($path), true);

            if (!is_array($statuses) || !array_key_exists('Builder', $statuses)) {
                return null;
            }

            $previous = (bool) $statuses['Builder'];

            if ($previous === false) {
                return false;
            }

            $statuses['Builder'] = false;
            file_put_contents($path, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->forgetModuleManifest();

            return $previous;
        } catch (\Throwable $throwable) {
            info('update: could not suspend the Builder module -- '.$throwable->getMessage());

            return null;
        }
    }

    /**
     * Put Builder back exactly as it was. Called only once the update has gone through: an
     * update that failed halfway is safer with the add-on still off -- the panel works, the
     * storefront is down, and the add-on page turns it back on in one click -- than with a
     * module the new core cannot boot against.
     */
    private function restoreBuilderModule(?bool $previous): void
    {
        if ($previous !== true) {
            return;
        }

        try {
            $path = base_path(self::MODULE_STATUS_FILE);
            $statuses = json_decode((string) file_get_contents($path), true);

            if (!is_array($statuses)) {
                return;
            }

            $statuses['Builder'] = true;
            file_put_contents($path, json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->forgetModuleManifest();
        } catch (\Throwable $throwable) {
            info('update: could not restore the Builder module -- '.$throwable->getMessage());
        }
    }

    private function unpublishIncompatibleAddons(): void
    {
        $moduleTypes = ['Rental' => 'rental', 'RideShare' => 'ride-share', 'Service' => 'service'];

        foreach (array_keys(AddonCompatibility::requiredVersions()) as $name) {
            try {
                $path = base_path('Modules/'.$name.'/Addon/info.php');

                if (AddonCompatibility::isCompatible(name: $name) || ! is_file($path)) {
                    continue;
                }

                $info = include $path;

                if (! is_array($info) || empty($info['is_published'])) {
                    continue;
                }

                $info['is_published'] = 0;
                file_put_contents($path, '<?php return '.var_export($info, true).';');

                if (isset($moduleTypes[$name])) {
                    Module::where('module_type', $moduleTypes[$name])->update(['status' => 0]);
                }
            } catch (\Throwable $throwable) {
                info('update: could not unpublish the '.$name.' add-on -- '.$throwable->getMessage());
            }
        }
    }

    private function forgetModuleManifest(): void
    {
        $manifest = base_path('bootstrap/cache/modules.php');

        if (is_file($manifest)) {
            @unlink($manifest);
        }
    }

    /**
     * Drop the compiled route and config caches before the wizard renders.
     *
     * A route cache makes Laravel skip the RouteServiceProvider's registration entirely, so while
     * one is on disk the panel routes are whatever was compiled -- and if it was compiled in wizard
     * mode it holds routes/update.php and nothing else. Restoring the real provider then changes
     * nothing: `admin.dashboard` still does not exist, and everything that builds a panel URL dies
     * with "Route [admin.dashboard] not defined" on a site that looks fully updated.
     *
     * clearStaleCaches() already covers the end of a successful run. This covers the rest: a cache
     * shipped inside the package, one left by the client's own `artisan optimize`, and the case
     * where the update aborts early and never reaches the clear. Neither cache can be correct at
     * this point -- both were built either by the previous version or by the wizard -- so dropping
     * them costs nothing.
     */
    private function discardCompiledRoutes(): void
    {
        foreach (['route:clear', 'config:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $throwable) {
                info('update: '.$command.' failed on the wizard page -- '.$throwable->getMessage());
            }
        }
    }

    /**
     * Every compiled cache the new code must not be read through.
     *
     * The route cache is the one that bites: while the update is running the shipped
     * RouteServiceProvider serves only routes/update.php, so a route cache written in that
     * state -- or one carried over from the previous version -- keeps the panel routes missing
     * after the update has finished, and every admin URL 404s on a site that is otherwise fine.
     * The provider manifest and the module manifest (bootstrap/cache/services.php,
     * modules.php) are the same hazard one level up: they name provider classes, so a stale
     * copy can point at a class this version renamed or removed and fail at boot.
     *
     * Each one is guarded on its own. A cache that cannot be cleared -- a database cache store
     * whose table does not exist yet, say -- must not abort the update at this point.
     */
    private function clearStaleCaches(): void
    {
        foreach (['cache:clear', 'view:clear', 'route:clear', 'config:clear', 'clear-compiled'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $throwable) {
                info('update: '.$command.' failed -- '.$throwable->getMessage());
            }
        }

        // nwidart writes its own provider manifest and has no artisan command to drop it.
        $moduleManifest = base_path('bootstrap/cache/modules.php');

        if (is_file($moduleManifest)) {
            @unlink($moduleManifest);
        }

        // Per-addon provider manifests, in the same PackageManifest shape as services.php and
        // carrying the same hazard: they name provider classes. No artisan command knows about
        // them, and some on a long-lived install date back several releases.
        foreach (glob(base_path('bootstrap/cache').'/*_module.php') ?: [] as $addonManifest) {
            @unlink($addonManifest);
        }

        $this->resetOpcache();
    }

    /**
     * Drop the opcode cache, so the PHP that just replaced the old PHP is the PHP that runs.
     *
     * An update replaces files underneath a running interpreter. With opcache.validate_timestamps=1
     * -- the default -- the new mtime is noticed within revalidate_freq seconds and this is merely
     * belt and braces. Hosts tune it to 0 for throughput, though, and then a replaced file keeps
     * executing its OLD bytecode until the pool is reloaded: the update reports success, the files
     * on disk are correct, and the site still runs the previous release, fixes included. None of
     * artisan's clear commands touch this -- clear-compiled removes Laravel's own compiled files.
     *
     * Only this process's cache can be reset from here. Other php-fpm workers hold their own copies,
     * so a reload is still the reliable move on a tuned host; this at least guarantees the request
     * that finishes the update is not itself served stale.
     */
    private function resetOpcache(): void
    {
        if (! function_exists('opcache_reset')) {
            return;
        }

        try {
            @opcache_reset();
        } catch (\Throwable $throwable) {
            info('update: opcache_reset failed -- '.$throwable->getMessage());
        }
    }

    public function update_software(Request $request)
    {
        // Checked before the first write, so an incomplete package changes nothing. Without
        // the restore file the update completes, the copy() below returns false silently, and
        // every URL bounces back to this wizard - a finished update that looks like it never
        // ran.
        $restoreRoutes = base_path('app/Providers/RouteServiceProvider.txt');

        if (!is_readable($restoreRoutes)) {
            Toastr::error('Update package is incomplete: app/Providers/RouteServiceProvider.txt is missing or unreadable. Nothing has been changed - re-upload the package and try again.');

            return back();
        }

        // A .txt holding the wizard's own routing restores nothing - it puts this page back
        // over itself and every URL keeps landing here. Caught before the first write, the
        // same as a missing file.
        if (Helpers::is_wizard_route_provider($restoreRoutes)) {
            Toastr::error('app/Providers/RouteServiceProvider.txt contains the update wizard\'s routing instead of the panel\'s, so restoring it would leave every URL on this page. Nothing has been changed - replace it with the real provider and try again.');

            return back();
        }

        $builderWasEnabled = $this->suspendBuilderModule();

        if (env('SOFTWARE_VERSION') == '1.0') {
            $filesystem = new Filesystem;
            $filesystem->cleanDirectory('database/migrations');
        }

        Helpers::setEnvironmentValue('BUYER_USERNAME', $request['username']);
        Helpers::setEnvironmentValue('PURCHASE_CODE', $request['purchase_key']);
        Helpers::setEnvironmentValue('APP_MODE', 'live');
        Helpers::setEnvironmentValue('SOFTWARE_VERSION', '4.2.1');
        Helpers::setEnvironmentValue('REACT_APP_KEY', '45370351');
        Helpers::setEnvironmentValue('APP_NAME', '6amMart' . time());

        $hostDomain = parse_url(env('APP_URL', ''), PHP_URL_HOST) ?: '';
        if (!env('APP_HOST_DOMAIN') && $hostDomain !== '') {
            Helpers::setEnvironmentValue('APP_HOST_DOMAIN', $hostDomain);
        }
        if (!env('APP_HOST_BASE_DOMAIN') && $hostDomain !== '') {
            Helpers::setEnvironmentValue('APP_HOST_BASE_DOMAIN', Helpers::host_base_domain($hostDomain));
        }

        if (!env('APP_PUBLIC_IP')) {
            $publicIp = $request->server('SERVER_ADDR')
                ?: (filter_var($hostDomain, FILTER_VALIDATE_IP) ? $hostDomain : '');
            if ($publicIp !== '') {
                Helpers::setEnvironmentValue('APP_PUBLIC_IP', $publicIp);
            }
        }

        Artisan::call('cache:table');
        Helpers::setEnvironmentValueIfMissing('CACHE_DRIVER', 'file');
        Helpers::setEnvironmentValueIfMissing('APP_CACHE_STORE', env('CACHE_DRIVER', 'file'));
        Helpers::setEnvironmentValueIfMissing('APP_CACHE_MAX_PAGE', 3);
        Helpers::setEnvironmentValueIfMissing('QUEUE_CONNECTION', 'sync');
        Helpers::setEnvironmentValueIfMissing('NOTIFICATION_MODE', 'after_response');
        Helpers::setEnvironmentValueIfMissing('NOTIFICATION_QUEUE_CONNECTION', 'database');

        Artisan::call('migrate', ['--force' => true]);
        // Leaving update mode. The source was checked above, so a failure here is app/Providers/
        // being unwritable. Reported, not swallowed: the migrations have already run.
        $previousRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.php');

        if (!copy($restoreRoutes, $previousRouteServiceProvier)) {
            Toastr::error('The update finished but normal routing could not be restored: app/Providers/RouteServiceProvider.php is not writable. Fix the permission and run the update again - the database work is already done.');

            return back();
        }
        $this->clearStaleCaches();
        Helpers::insert_business_settings_key("mobile_app_section_heading", "Download the App for Enjoy Best Restaurant Test");
        Helpers::insert_business_settings_key("mobile_app_section_text", "Default Text Mobile App Section");
        Helpers::insert_business_settings_key("feature_section_description", "Feature section description");
        Helpers::insert_business_settings_key("Feature section description", json_encode([
            "app_url_android_status" => "0",
            "app_url_android" => "https://play.google.com",
            "app_url_ios_status" => "0",
            "app_url_ios" => "https://www.apple.com/app-store",
            "web_app_url_status" => "0",
            "web_app_url" => "https://6ammart-web.6amtech.com/"
        ]));

        Helpers::insert_business_settings_key("wallet_status", "0");
        Helpers::insert_business_settings_key("loyalty_point_status", "0");
        Helpers::insert_business_settings_key("ref_earning_status", "0");
        Helpers::insert_business_settings_key("wallet_add_refund", "0");
        Helpers::insert_business_settings_key("loyalty_point_exchange_rate", "0");
        Helpers::insert_business_settings_key("ref_earning_exchange_rate", "0");
        Helpers::insert_business_settings_key("loyalty_point_item_purchase_point", "0");
        Helpers::insert_business_settings_key("loyalty_point_minimum_point", "0");
        Helpers::insert_business_settings_key("dm_tips_status", "0");

        Helpers::insert_business_settings_key('refund_active_status', '1');
        Helpers::insert_business_settings_key('social_login', '[{"login_medium":"google","client_id":"","client_secret":"","status":"0"},{"login_medium":"facebook","client_id":"","client_secret":"","status":""}]');
        Helpers::insert_business_settings_key('system_language', '[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]');
        Helpers::insert_business_settings_key('language', '["en"]');

        Helpers::insert_business_settings_key("home_delivery_status", "1");
        Helpers::insert_business_settings_key("takeaway_status", "1");

        $data_settings = file_get_contents('database/partial/data_settings.sql');
        $email_tempaltes = file_get_contents('database/partial/email_tempaltes.sql');

        if (DataSetting::count() < 1) {
            DB::statement($data_settings);
        }
        if (EmailTemplate::count() < 1) {
            DB::statement($email_tempaltes);
        }

        Helpers::insert_data_settings_key('admin_login_url', 'login_admin', 'admin');
        Helpers::insert_data_settings_key('admin_employee_login_url', 'login_admin_employee', 'admin-employee');
        Helpers::insert_data_settings_key('store_login_url', 'login_store', 'vendor');
        Helpers::insert_data_settings_key('store_employee_login_url', 'login_store_employee', 'vendor-employee');

        Helpers::insert_business_settings_key('subscription_business_model', '0');
        Helpers::insert_business_settings_key('commission_business_model', '1');
        Helpers::insert_business_settings_key('subscription_deadline_warning_days', '7');
        Helpers::insert_business_settings_key('subscription_free_trial_days', '7');
        Helpers::insert_business_settings_key('subscription_free_trial_type', 'day');
        Helpers::insert_business_settings_key('subscription_free_trial_status', '1');
        Helpers::insert_business_settings_key('subscription_usage_max_time', '80');

        Helpers::insert_business_settings_key('check_daily_subscription_validity_check', date('Y-m-d'));

        try {
            if (!Schema::hasTable('addon_settings')) {
                $sql = file_get_contents('database/partial/addon_settings.sql');
                DB::unprepared($sql);
                $this->set_data();
                $this->set_sms_data();

            }

            if (env('SOFTWARE_VERSION') == '2.4') {

                $this->set_sms_data();
                $this->update_table();
            }


            if (!Schema::hasTable('payment_requests')) {
                $sql = file_get_contents('database/partial/payment_requests.sql');
                DB::unprepared($sql);
            }


            $storesToUpdate = Store::whereNull('slug')->get(['id', 'name', 'slug']);
            foreach ($storesToUpdate as $store) {
                $slug = Str::slug($store->name);
                $store->slug = $store->slug ? $store->slug : "{$slug}{$store->id}";
                $store->save();
            }

            if (Schema::hasTable('addon_settings')) {
                $data_values = Setting::whereIn('settings_type', ['payment_config'])
                    ->where('key_name', 'paystack')
                    ->first();


                if ($data_values) {
                    $additional_data = $data_values->live_values;

                    if (array_key_exists("callback_url", $additional_data)) {
                        unset($additional_data['callback_url']);
                        $data_values->live_values = $additional_data;
                        $data_values->test_values = $additional_data;
                        $data_values->save();
                    }
                }
            }

            $hasDuplicates = DB::table('delivery_histories')
                ->select('delivery_man_id')
                ->groupBy('delivery_man_id')
                ->havingRaw('COUNT(*) > 1')
                ->limit(1)
                ->exists();

            if ($hasDuplicates) {
                DeliveryHistory::truncate();
            }

        } catch (\Exception $exception) {
            Toastr::error('Database import failed! try again');
            return back();
        }

        $landing = BusinessSetting::where('key', 'landing_page')->exists();
        if (!$landing) {
            Helpers::insert_business_settings_key('landing_page', '1');
            Helpers::insert_business_settings_key('landing_integration_type', 'none');
        }
        Helpers::insert_business_settings_key("dm_max_cash_in_hand", "5000");

        if (NotificationSetting::count() == 0) {
            Helpers::notificationDataSetup();
        }
        Helpers::updateAdminNotificationSetupDataSetup();
        Helpers::addNewAdminNotificationSetupDataSetup();
        Helpers::addPreviousParcelReturnFees();

        Helpers::insert_business_settings_key('country_picker_status', '1');
        Helpers::insert_business_settings_key('manual_login_status', '1');

        WebPushServiceWorker::generate();

        $recaptcha = BusinessSetting::where('key', 'recaptcha')->first();
        if ($recaptcha?->value) {
            $recaptcha_value = json_decode($recaptcha->value, true);
            $recaptcha->value = json_encode([
                'status' => null,
                'site_key' => $recaptcha_value['site_key'],
                'secret_key' => $recaptcha_value['secret_key']
            ]);
            $recaptcha->save();
        }
        Helpers::promotionalImage();

        Coupon::where('coupon_type', 'store_wise')
            ->whereNull('store_id')
            ->update([
                'store_id' => DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data, '$[0]'))")
            ]);

        $twoFactor = Setting::where(['key_name' => '2factor', 'settings_type' => 'sms_config'])->first();
        if ($twoFactor && $twoFactor->live_values) {
            $liveValues = is_array($twoFactor->live_values) ? $twoFactor->live_values : json_decode($twoFactor->live_values, true);
            $liveValues['otp_template'] = $liveValues['otp_template'] ?? 'Your OTP is: #OTP#';
            Setting::where(['key_name' => '2factor', 'settings_type' => 'sms_config'])->update([
                'live_values' => json_encode($liveValues),
                'test_values' => json_encode($liveValues),
            ]);
        }
        $free_delivery_over_status = BusinessSetting::where('key', 'free_delivery_over_status')->first();
        $free_delivery_over = Helpers::get_business_settings('free_delivery_over', false);
        if ($free_delivery_over_status?->value == 1 && $free_delivery_over > 0) {
            $free_delivery_over_status->key = 'admin_free_delivery_status';
            $free_delivery_over_status->save();
            Helpers::businessUpdateOrInsert(['key' => 'admin_free_delivery_option'], [
                'value' => 'free_delivery_by_order_amount'
            ]);
        }

        Module::regenerateSlugs();

        $this->restoreBuilderModule($builderWasEnabled);
        $this->unpublishIncompatibleAddons();
        $this->clearStaleCaches();

        $data = DataSetting::where('type', 'login_admin')->pluck('value')->first();
        return redirect('/login/' . $data);
    }

    private function set_data()
    {
        try {
            $gateway = ['ssl_commerz_payment',
                'razor_pay',
                'paypal',
                'stripe',
                'senang_pay',
                'paystack',
                'flutterwave',
                'mercadopago',
                'paymob_accept',
                'liqpay',
                'paytm',
                'bkash',
                'paytabs'];

            $data = BusinessSetting::whereIn('key', $gateway)->pluck('value', 'key')->toArray();


            foreach ($data as $key => $value) {

                $gateway = $key;
                if ($key == 'ssl_commerz_payment') {
                    $gateway = 'ssl_commerz';
                }

                $decoded_value = json_decode($value, true);
                $data = ['gateway' => $gateway,
                    'mode' => isset($decoded_value['status']) == 1 ? 'live' : 'test'
                ];

                if ($gateway == 'ssl_commerz') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'store_id' => $decoded_value['store_id'],
                        'store_password' => $decoded_value['store_password'],
                    ];
                } elseif ($gateway == 'paypal') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'client_id' => $decoded_value['paypal_client_id'],
                        'client_secret' => $decoded_value['paypal_secret'],
                    ];
                } elseif ($gateway == 'stripe') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'api_key' => $decoded_value['api_key'],
                        'published_key' => $decoded_value['published_key'],
                    ];
                } elseif ($gateway == 'razor_pay') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'api_key' => $decoded_value['razor_key'],
                        'api_secret' => $decoded_value['razor_secret'],
                    ];
                } elseif ($gateway == 'senang_pay') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'callback_url' => null,
                        'secret_key' => $decoded_value['secret_key'],
                        'merchant_id' => $decoded_value['merchant_id'],
                    ];
                } elseif ($gateway == 'paytabs') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'profile_id' => $decoded_value['profile_id'],
                        'server_key' => $decoded_value['server_key'],
                        'base_url' => $decoded_value['base_url'],
                    ];
                } elseif ($gateway == 'paystack') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'public_key' => $decoded_value['publicKey'],
                        'secret_key' => $decoded_value['secretKey'],
                        'merchant_email' => $decoded_value['merchantEmail'],
                    ];
                } elseif ($gateway == 'paymob_accept') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'callback_url' => null,
                        'api_key' => $decoded_value['api_key'],
                        'iframe_id' => $decoded_value['iframe_id'],
                        'integration_id' => $decoded_value['integration_id'],
                        'hmac' => $decoded_value['hmac'],
                    ];
                } elseif ($gateway == 'mercadopago') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'access_token' => $decoded_value['access_token'],
                        'public_key' => $decoded_value['public_key'],
                        'supported_country'=>''
                    ];
                } elseif ($gateway == 'liqpay') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'private_key' => $decoded_value['public_key'],
                        'public_key' => $decoded_value['private_key'],
                    ];
                } elseif ($gateway == 'flutterwave') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'secret_key' => $decoded_value['secret_key'],
                        'public_key' => $decoded_value['public_key'],
                        'hash' => $decoded_value['hash'],
                    ];
                } elseif ($gateway == 'paytm') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'merchant_key' => $decoded_value['paytm_merchant_key'],
                        'merchant_id' => $decoded_value['paytm_merchant_mid'],
                        'merchant_website_link' => $decoded_value['paytm_merchant_website'],
                    ];
                } elseif ($gateway == 'bkash') {
                    $additional_data = [
                        'status' => $decoded_value['status'],
                        'app_key' => $decoded_value['api_key'],
                        'app_secret' => $decoded_value['api_secret'],
                        'username' => $decoded_value['username'],
                        'password' => $decoded_value['password'],
                    ];
                }

                $credentials = json_encode(array_merge($data, $additional_data));

                $payment_additional_data = ['gateway_title' => ucfirst(str_replace('_', ' ', $gateway)),
                    'gateway_image' => null];

                DB::table('addon_settings')->updateOrInsert(['key_name' => $gateway, 'settings_type' => 'payment_config'], [
                    'key_name' => $gateway,
                    'live_values' => $credentials,
                    'test_values' => $credentials,
                    'settings_type' => 'payment_config',
                    'mode' => isset($decoded_value['status']) == 1 ? 'live' : 'test',
                    'is_active' => isset($decoded_value['status']) == 1 ? 1 : 0,
                    'additional_data' => json_encode($payment_additional_data),
                ]);
            }
        } catch (\Exception $exception) {
            Toastr::error('Database import failed! try again');
            return true;
        }
        return true;
    }

    private function set_sms_data()
    {
        try {
            $sms_gateway = ['twilio_sms',
                'nexmo_sms',
                'msg91_sms',
                '2factor_sms'];

            $data = BusinessSetting::whereIn('key', $sms_gateway)->pluck('value', 'key')->toArray();
            foreach ($data as $key => $value) {
                $decoded_value = json_decode($value, true);

                if ($key == 'twilio_sms') {
                    $sms_gateway = 'twilio';
                    $additional_data = [
                        'status' => data_get($decoded_value, 'status', null),
                        'sid' => data_get($decoded_value, 'sid', null),
                        'messaging_service_sid' => data_get($decoded_value, 'messaging_service_id', null),
                        'token' => data_get($decoded_value, 'token', null),
                        'from' => data_get($decoded_value, 'from', null),
                        'otp_template' => data_get($decoded_value, 'otp_template', null),
                    ];
                } elseif ($key == 'nexmo_sms') {
                    $sms_gateway = 'nexmo';
                    $additional_data = [
                        'status' => data_get($decoded_value, 'status', null),
                        'api_key' => data_get($decoded_value, 'api_key', null),
                        'api_secret' => data_get($decoded_value, 'api_secret', null),
                        'token' => data_get($decoded_value, 'token', null),
                        'from' => data_get($decoded_value, 'from', null),
                        'otp_template' => data_get($decoded_value, 'otp_template', null),
                    ];
                } elseif ($key == '2factor_sms') {
                    $sms_gateway = '2factor';
                    $additional_data = [
                        'status' => data_get($decoded_value, 'status', null),
                        'api_key' => data_get($decoded_value, 'api_key', null),
                        'otp_template' => data_get($decoded_value, 'otp_template', 'Your OTP is: #OTP#'),
                    ];
                } elseif ($key == 'msg91_sms') {
                    $sms_gateway = 'msg91';
                    $additional_data = [
                        'status' => data_get($decoded_value, 'status', null),
                        'template_id' => data_get($decoded_value, 'template_id', null),
                        'auth_key' => data_get($decoded_value, 'authkey', null),
                    ];
                }
                $data = ['gateway' => $sms_gateway,
                    'mode' => isset($decoded_value['status']) == 1 ? 'live' : 'test'
                ];
                $credentials = json_encode(array_merge($data, $additional_data));

                DB::table('addon_settings')->updateOrInsert(['key_name' => $sms_gateway, 'settings_type' => 'sms_config'], [
                    'key_name' => $sms_gateway,
                    'live_values' => $credentials,
                    'test_values' => $credentials,
                    'settings_type' => 'sms_config',
                    'mode' => isset($decoded_value['status']) == 1 ? 'live' : 'test',
                    'is_active' => isset($decoded_value['status']) == 1 ? 1 : 0,
                ]);
            }
        } catch (\Exception $exception) {
            Toastr::error('Database import failed! try again');
            return true;
        }
        return true;
    }


    private function update_table()
    {


        $gateways = [
            'viva_wallet' => [
                'status' => 0,
                'client_id' => null,
                'client_secret' => null,
                'source_code' => null,
            ],
            'paradox' => [
                'status' => 0,
                'api_key' => null,
                'sender_id' => null,
            ],
        ];


        foreach ($gateways as $key => $conf) {
            $data = [
                'gateway' => $key,
                'mode' => 'test',
            ];
            $credentials = json_encode(array_merge($data, $conf));

            $settings = $key == 'paradox' ? 'sms_config' : 'payment_config';

            DB::table('addon_settings')->updateOrInsert(['key_name' => $key, 'settings_type' => $settings], [
                'key_name' => $key,
                'live_values' => $credentials,
                'test_values' => $credentials,
                'settings_type' => $settings,
                'mode' => 'test',
                'is_active' => 0,
            ]);
        }
    }



}
