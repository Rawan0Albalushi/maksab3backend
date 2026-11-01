<?php

namespace App\Http\Controllers\Admin;

use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Carbon;
use App\Support\Cache\ApiCache;
use Illuminate\Support\Str;
use Modules\Rental\Entities\Trips;
use Modules\Service\Entities\ServiceBooking;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;

class SystemController extends Controller
{

    public function store_data()
    {
        $new_order = 0;
        $type = 'store_order';
        $module_id = 0;

        $order = Order::where(['checked' => 0])
            ->orderByDesc('id')
            ->first(['order_type', 'module_id']);

        if ($order) {
            $new_order = 1;
            $type = $order->order_type === 'parcel' ? 'parcel' : 'store_order';
            $module_id = $order->module_id;
        } elseif (addon_published_status('Rental') && ($trip = Trips::where(['checked' => 0])->orderByDesc('id')->first(['module_id']))) {
            $new_order = 1;
            $type = 'trip';
            $module_id = $trip->module_id;
        } elseif (addon_published_status('Service') && ($booking = ServiceBooking::where(['notification_checked' => 0])->orderByDesc('id')->first(['module_id']))) {
            $new_order = 1;
            $type = 'service_booking';
            $module_id = $booking->module_id;
        }

        return response()->json([
            'success' => 1,
            'data' => [
                'new_order' => $new_order,
                'type' => $type,
                'module_id' => $module_id,
            ],
        ]);
    }

    public function settings()
    {
        return view('admin-views.settings');
    }

    public function settings_update(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => EmailAddress::rules('required', 'admins,email,' . auth('admin')->id()),
            'phone' => PhoneNumber::rules('required', 'admins,phone,' . auth('admin')->id()),
        ], [
            'f_name.required' => translate('messages.First name is required'),
            'l_name.required' => translate('messages.Last name is required'),
        ]);

        $admin = Admin::withStorage()->find(auth('admin')->id());

        if ($request->has('image')) {
            $image_name = Helpers::update('admin/', $admin->image, 'png', $request->file('image'));
        } else {
            $image_name = $admin['image'];
        }


        $admin->f_name = $request->f_name;
        $admin->l_name = $request->l_name;

        if($admin->email != $request->email){
            $login_remember_token= Str::random(60);
            $admin->login_remember_token =  $login_remember_token;
            session(['login_remember_token' => $login_remember_token]);
        }
        $admin->email = $request->email;
        $admin->phone = $request->phone;
        $admin->image = $image_name;
        $admin->save();
        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function settings_password_update(Request $request)
    {
        $request->validate([
            'password' => StrongPassword::rules('required', ['same:confirm_password']),
            'confirm_password' => 'required',
        ]);

        $admin = Admin::withStorage()->find(auth('admin')->id());
        $admin->password = bcrypt($request['password']);
        $login_remember_token= Str::random(60);
        $admin->login_remember_token =  $login_remember_token;
        $admin->save();
        session(['login_remember_token' => $login_remember_token]);
        Toastr::success(translate('Updated successfully'));
        return back();
    }


    public function landing_page()
    {
        $landing_page = BusinessSetting::where('key', 'landing_page')->first();
        if (isset($landing_page) == false) {
            Helpers::businessInsert([
                'key' => 'landing_page',
                'value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            Helpers::businessUpdateOrInsert(['key' => 'landing_page'], [
                   'value' => $landing_page->value == 1 ? 0 : 1
               ]);
        }

        if (isset($landing_page) && $landing_page->value) {
            return response()->json(['message' => translate('Landing page is off.')]);
        }
        return response()->json(['message' => translate('Landing page is on.')]);
    }
    public function system_currency(Request $request)
    {
        $currency_check=Helpers::checkCurrency($request['currency']);
        if( $currency_check !== true ){
        return response()->json(['data'=> payment_method_label($currency_check) ],200);
        }
        return response()->json([],200);
    }

    public function maintenance_mode(Request $request)
    {
        if (getEnvMode() === 'demo') {
            Toastr::warning('Sorry! You can not enable maintainance mode in demo!');
            return back();
        }

        $previousSystems = json_decode(
            DataSetting::where('type', 'maintenance_mode')->where('key', 'maintenance_system_setup')->value('value') ?? '[]',
            true
        );

        $maintenanceMode = BusinessSetting::firstOrNew(['key' => 'maintenance_mode']);

        if ($request->maintenance_mode_off == 1) {
            $maintenanceMode->value = 0;
            $maintenanceMode->save();
            ApiCache::forget('maintenance');
            ApiCache::bust('data_setting');

            DataSetting::where('type', 'maintenance_mode')
                ->whereIn('key', ['maintenance_system_setup', 'maintenance_duration_setup', 'maintenance_message_setup'])
                ->delete();

            $this->sendMaintenanceNotifications($previousSystems, NotificationMessages::maintenanceOver());

            Toastr::success(translate('messages.Maintenance is off'));
            return back();
        }

        if ($request->maintenance_duration !== 'until_change') {
            $start = Carbon::parse($request->start_date);
            $end   = Carbon::parse($request->end_date);
            if ($start->gte($end)) {
                Toastr::error(translate('Sorry! Start date cannot be greater than end date.'));
                return back();
            }
        }

        $systems = ['vendor_panel', 'user_mobile_app', 'user_web_app', 'react_website', 'deliveryman_app', 'vendor_app', 'rider_app', 'serviceman_app', 'vendor_storefront'];
        $selectedSystems = array_values(array_filter($systems, fn($s) => $request->has($s)));

        if (empty($selectedSystems)) {
            Toastr::error(translate('messages.You must select a system for maintenance'));
            return back();
        }

        $wasActive = $maintenanceMode->value == 1;
        $maintenanceMode->value = 1;
        $maintenanceMode->save();

        DataSetting::updateOrCreate(
            ['key' => 'maintenance_system_setup',   'type' => 'maintenance_mode'],
            ['value' => json_encode($selectedSystems)]
        );

        DataSetting::updateOrCreate(
            ['key' => 'maintenance_duration_setup', 'type' => 'maintenance_mode'],
            ['value' => json_encode([
                'maintenance_duration' => $request->maintenance_duration,
                'start_date'           => $request->start_date,
                'end_date'             => $request->end_date,
            ])]
        );

        DataSetting::updateOrCreate(
            ['key' => 'maintenance_message_setup',  'type' => 'maintenance_mode'],
            ['value' => json_encode([
                'business_number'     => $request->has('business_number') ? 1 : 0,
                'business_email'      => $request->has('business_email') ? 1 : 0,
                'maintenance_message' => $request->maintenance_message,
                'message_body'        => $request->message_body,
            ])]
        );

        ApiCache::put('maintenance', null, [
            'status'               => 1,
            'start_date'           => $request->start_date,
            'end_date'             => $request->end_date,
            'vendor_panel'         => in_array('vendor_panel', $selectedSystems),
            'maintenance_duration' => $request->maintenance_duration,
        ], now()->addYears(1));
        ApiCache::bust('data_setting');

        $notifySystems = $wasActive ? array_diff($selectedSystems, $previousSystems) : $selectedSystems;
        if (!empty($notifySystems)) {
            $this->sendMaintenanceNotifications(array_values($notifySystems), NotificationMessages::maintenanceStarted());
        }

        $removedSystems = array_diff($previousSystems, $selectedSystems);
        if (!empty($removedSystems)) {
            $this->sendMaintenanceNotifications(array_values($removedSystems), NotificationMessages::maintenanceOver());
        }

        Toastr::success(translate('messages.Maintenance mode settings updated'));
        return back();
    }

    private function sendMaintenanceNotifications(array $systems, array $notification): void
    {
        $topicMap = [
            'user_mobile_app' => 'maintenance_mode_user_app',
            'deliveryman_app' => 'maintenance_mode_deliveryman_app',
            'vendor_app'      => 'maintenance_mode_vendor_app',
            'rider_app'       => 'maintenance_mode_rider_app',
            'serviceman_app'  => 'maintenance_mode_serviceman_app',
        ];

        foreach ($topicMap as $system => $topic) {
            if (in_array($system, $systems)) {
                SendNotification::pushSilentToTopic($notification, $topic, 'maintenance');
            }
        }
    }

}
