<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Traits\System\ActivationTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Storage;
use Madnest\Madzipper\Facades\Madzipper;
use Illuminate\Support\Facades\Session;

class InstallController extends Controller
{
    use ActivationTrait;

    public function step0()
    {
        return view('installation.step0');
    }

    public function step1(Request $request)
    {
        if (Hash::check('step_1', $request['token'])) {
            $permission = Helpers::system_permission_check();
            $fileChecks = Helpers::system_file_checks();
            $phpVersion = number_format((float)phpversion(), 2, '.', '');
            return view('installation.step1', compact('permission', 'fileChecks', 'phpVersion'));
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step2(Request $request)
    {
        if (Hash::check('step_2', $request['token'])) {
            return view('installation.step2');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step3(Request $request)
    {
        if (Hash::check('step_3', $request['token'])) {
            return view('installation.step3');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step4(Request $request)
    {
        if (Hash::check('step_4', $request['token'])) {
            return view('installation.step4');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function step5(Request $request)
    {
        if (Hash::check('step_5', $request['token'])) {
            return view('installation.step5');
        }
        session()->flash('error', 'Access denied!');
        return redirect()->route('step0');
    }

    public function purchase_code(Request $request)
    {
        Helpers::setEnvironmentValue('SOFTWARE_ID', 'MzY3NzIxMTI=');
        Helpers::setEnvironmentValue('BUYER_USERNAME', $request['username']);
        Helpers::setEnvironmentValue('PURCHASE_CODE', $request['purchase_key']);

        $post = [
            'name' => $request['name'],
            'email' => $request['email'],
            'username' => $request['username'],
            'purchase_key' => $request['purchase_key'],
            'domain' => preg_replace("#^[^:/.]*[:/]+#i", "", url('/')),
        ];

        Session::put(base64_decode('cHVyY2hhc2Vfa2V5'), $request[base64_decode('cHVyY2hhc2Vfa2V5')]);
        Session::put(base64_decode('dXNlcm5hbWU='), $request[base64_decode('dXNlcm5hbWU=')]);
        return redirect('step3?token='.bcrypt('step_3'));
    }

    public function system_settings(Request $request)
    {
        if (!Hash::check('step_6', $request['token'])) {
            session()->flash('error', 'Access denied!');
            return redirect()->route('step0');
        }

        DB::table('admins')->insertOrIgnore([
            'f_name' => $request['f_name'],
            'l_name' => $request['l_name'],
            'email' => $request['email'],
            'role_id' => 1,
            'password' => bcrypt($request['password']),
            'phone' => $request['phone'],
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('business_settings')->where(['key' => 'business_name'])->update([
            'value' => $request['business_name']
        ]);
        Helpers::clearBusinessSettingsCache();

        Helpers::insert_business_settings_key('system_language','[{"id":1,"direction":"ltr","code":"en","status":1,"default":true}]');

        Helpers::insert_data_settings_key('admin_login_url', 'login_admin' ,'admin');
        Helpers::insert_data_settings_key('admin_employee_login_url', 'login_admin_employee' ,'admin-employee');
        Helpers::insert_data_settings_key('store_login_url', 'login_store' ,'vendor');
        Helpers::insert_data_settings_key('store_employee_login_url', 'login_store_employee' ,'vendor-employee');

        Helpers::insert_business_settings_key('check_daily_subscription_validity_check', date('Y-m-d'));

        Helpers::insert_business_settings_key('country_picker_status', '1');
        Helpers::insert_business_settings_key('manual_login_status', '1');


        // Leaving install mode. copy() returns false rather than raising, and a site that
        // cannot get its normal provider back keeps showing the installer.
        $previousRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.php');
        $newRouteServiceProvier = base_path('app/Providers/RouteServiceProvider.txt');

        if (Helpers::is_wizard_route_provider($newRouteServiceProvier)) {
            // Restoring this would copy the installer's own routing back over itself and
            // leave every URL on the wizard, so it is left alone and reported instead.
            session()->flash('error', 'Installation finished but normal routing could not be restored: app/Providers/RouteServiceProvider.txt contains the installer\'s routing instead of the panel\'s. Replace it with the real provider.');
            info('InstallController: RouteServiceProvider.txt is a wizard stub, refusing to restore it');
        } elseif (!is_readable($newRouteServiceProvier) || !copy($newRouteServiceProvier, $previousRouteServiceProvier)) {
            // Also logged - step6 is the last screen, where a flash message is easy to miss.
            session()->flash('error', 'Installation finished but normal routing could not be restored: check that app/Providers/RouteServiceProvider.txt exists and that app/Providers/ is writable.');
            info('InstallController: could not restore RouteServiceProvider.php from RouteServiceProvider.txt');
        }

        // The restored provider only takes effect if nothing compiled is being read in its place.
        // A route cache pins whatever was compiled -- the installer's own routes, if the package
        // shipped one -- so the panel routes stay missing and every route('admin.…') throws on a
        // site that just finished installing. Guarded individually: a cache that cannot be cleared
        // must not fail the last step of an otherwise complete install.
        foreach (['route:clear', 'config:clear', 'cache:clear', 'view:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $throwable) {
                info('InstallController: '.$command.' failed -- '.$throwable->getMessage());
            }
        }

        // The installer just rewrote .env and the route provider. On a host with
        // opcache.validate_timestamps=0 those files would keep serving the bytecode compiled from
        // the package's placeholder versions, and the site would come up unconfigured.
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        Helpers::remove_dir('storage/app/public');
        Storage::disk('public')->makeDirectory('/');

        try {
            Madzipper::make('installation/backup/public.zip')->extractTo('storage/app');
        }catch (\Exception $exception){
            info($exception);
        }

        return view('installation.step6');
    }

    public function database_installation(Request $request)
    {
        if (self::check_database_connection($request->DB_HOST, $request->DB_DATABASE, $request->DB_USERNAME, $request->DB_PASSWORD)) {

            $key = base64_encode(random_bytes(32));
            $appUrl = URL::to('/');
            $hostDomain = parse_url($appUrl, PHP_URL_HOST) ?: '';
            $hostBaseDomain = Helpers::host_base_domain($hostDomain);
            $publicIp = $request->server('SERVER_ADDR')
                ?: (filter_var($hostDomain, FILTER_VALIDATE_IP) ? $hostDomain : '');
            $output = 'APP_NAME=6ammart'.time().
                    'APP_ENV=live
                    APP_KEY=base64:' . $key . '
                    APP_DEBUG=false
                    APP_INSTALL=true
                    APP_LOG_LEVEL=debug
                    APP_MODE=live
                    APP_URL=' . $appUrl . '
                    APP_HOST_DOMAIN=' . $hostDomain . '
                    APP_HOST_BASE_DOMAIN=' . $hostBaseDomain . '
                    APP_PUBLIC_IP=' . $publicIp . '

                    DB_CONNECTION=mysql
                    DB_HOST=' . $request->DB_HOST . '
                    DB_PORT=3306
                    DB_DATABASE=' . $request->DB_DATABASE . '
                    DB_USERNAME=' . $request->DB_USERNAME . '
                    DB_PASSWORD="' . $request->DB_PASSWORD . '"

                    BROADCAST_DRIVER=log
                    CACHE_DRIVER=file
                    APP_CACHE_STORE=file
                    APP_CACHE_MAX_PAGE=3
                    SESSION_DRIVER=file
                    SESSION_LIFETIME=120
                    QUEUE_DRIVER=sync
                    QUEUE_CONNECTION=sync
                    NOTIFICATION_MODE=after_response
                    NOTIFICATION_QUEUE_CONNECTION=database

                    REDIS_HOST=127.0.0.1
                    REDIS_PASSWORD=null
                    REDIS_PORT=6379

                    PUSHER_APP_ID=
                    PUSHER_APP_KEY=
                    PUSHER_APP_SECRET=
                    PUSHER_APP_CLUSTER=mt1

                    PURCHASE_CODE=' . session('purchase_key') . '
                    BUYER_USERNAME=' . session('username') . '
                    SOFTWARE_ID=MzY3NzIxMTI=

                    SOFTWARE_VERSION=4.2.1
                    REACT_APP_KEY=45370351
                    ';
            $file = fopen(base_path('.env'), 'w');
            fwrite($file, $output);
            fclose($file);

            $path = base_path('.env');
            if (file_exists($path)) {
                return redirect()->route('step4', ['token' => $request['token']]);
            } else {
                session()->flash('error', 'Database error!');
                return redirect()->route('step3', ['token' => bcrypt('step_3')]);
            }
        } else {
            session()->flash('error', 'Database host error!');
            return redirect()->route('step3', ['token' => bcrypt('step_3')]);
        }
    }

    public function import_sql()
    {
        try {
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            Artisan::call('cache:table');
            return redirect()->route('step5', ['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Your database is not clean, do you want to clean database then import?');
            return back();
        }
    }

    public function force_import_sql()
    {
        try {
            Artisan::call('db:wipe', ['--force' => true]);
            $sql_path = base_path('installation/backup/database.sql');
            DB::unprepared(file_get_contents($sql_path));
            Artisan::call('cache:table');
            return redirect()->route('step5', ['token' => bcrypt('step_5')]);
        } catch (\Exception $exception) {
            session()->flash('error', 'Check your database permission!');
            return back();
        }
    }

    function check_database_connection($db_host = "", $db_name = "", $db_user = "", $db_pass = ""): bool
    {
        try {
            if (@mysqli_connect($db_host, $db_user, $db_pass, $db_name)) {
                return true;
            } else {
                return false;
            }
        }catch(\Exception $exception){
            return false;
        }
    }
}
