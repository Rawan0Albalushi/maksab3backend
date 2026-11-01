<?php

namespace App\Http\Controllers;

use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Models\Zone;
use App\Models\Store;
use App\Models\Module;
use App\Models\Vendor;
use App\Services\Store\StoreService;
use App\Services\Vendor\VendorService;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Illuminate\Http\JsonResponse;
use App\Models\SubscriptionPackage;
use Gregwar\Captcha\CaptchaBuilder;
use App\Models\ModuleZone;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use MatanYadaev\EloquentSpatial\Objects\Point;
use App\Support\Storage\FileStorage;

class VendorController extends Controller
{
    public function create()
    {
        $status = Helpers::get_business_settings ('toggle_store_registration');
        if(!isset($status) || $status == '0')
        {
            Toastr::error(translate('No data found'));
            return back();
        }
        $admin_commission= Helpers::get_business_settings ('admin_commission');
        $business_name= Helpers::get_business_settings ('business_name');
        $packages= SubscriptionPackage::where('status',1)->where('module_type', 'all')->latest()->get();
        $custome_recaptcha = new CaptchaBuilder;
        $custome_recaptcha->build();
        Session::put('six_captcha', $custome_recaptcha->getPhrase());

        $zones = Zone::active()->get(['id', 'name', 'display_name']);
        $default_location = BusinessSetting::where('key', 'default_location')->first();
        // general-info.blade.php resolved all of this itself, and re-ran
        // subscription_check() on five separate lines.
        $default_location = $default_location->value ? json_decode($default_location->value, true) : 0;
        $language = Helpers::get_business_settings('language');
        $language_labels = [];
        foreach ((array) $language as $lang) {
            $language_labels[$lang] = Helpers::get_language_name($lang).'('.strtoupper($lang).')';
        }
        $admin_zone_id = auth('admin')?->user()?->zone_id;
        $subscription_check = Helpers::subscription_check();
        $commission_check = Helpers::commission_check();
        $map_api_key = Helpers::get_business_settings('map_api_key');

        return view('vendor-views.auth.general-info', compact('custome_recaptcha','admin_commission','business_name','packages','zones','default_location','language','language_labels','subscription_check','commission_check','map_api_key','admin_zone_id' ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make([], []);
        $status = Helpers::get_business_settings ('toggle_store_registration');
        if(!isset($status) || $status == '0')
        {
            $validator->getMessageBag()->add('latitude', translate('No data found'));
            return response()->json(['errors' => Helpers::error_processor($validator)]);

        }

        $recaptcha = Helpers::get_business_settings('recaptcha');

        if (isset($recaptcha) && $recaptcha['status'] == 1) {
            $request->validate([
                'g-recaptcha-response' => [
                    function ($attribute, $value, $fail) use ($recaptcha) {
                        $secret_key = $recaptcha['secret_key'];
                        $gResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret' => $secret_key,
                            'response' => $value,
                            'remoteip' => \request()->ip(),
                        ]);

                        if (!$gResponse->successful()) {
                            $fail(translate('ReCaptcha Failed'));
                        }
                    },
                ],
            ]);
        } else if(strtolower(session('six_captcha')) != strtolower($request->custome_recaptcha))
        {
              $validator->getMessageBag()->add('ReCAPTCHA', translate('reCAPTCHA failed'));
                 return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'name' => 'required',
            'address' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
            'email' => EmailAddress::rules('required', 'vendors'),
            'phone' => PhoneNumber::rules('required', 'vendors'),
            'minimum_delivery_time' => 'required',
            'maximum_delivery_time' => 'required',
            'password' => StrongPassword::rules('required'),
            'zone_id' => 'required',
            'module_id' => 'required',
            'logo' => ImageFile::rules('required'),
            'cover_photo' => ImageFile::rules('nullable'),
            'delivery_time_type'=>'required',
        ],[
        ]);
        if ($validator->fails()) {
                 return response()->json(['errors' => Helpers::error_processor($validator)]);
        }
        if($request->zone_id)
        {
            $zone = Zone::query()
            ->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))
            ->where('id',$request->zone_id)
            ->first();
            if(!$zone){
              $validator->getMessageBag()->add('zone', translate('Coordinates out of zone'));
                 return response()->json(['errors' => Helpers::error_processor($validator)]);
            }

            // D7's rule, applied here the same way it already is on Free Delivery / ETA / Delivery
            // Rule setup: a module the zone does not serve could never be connected, so a store
            // for it is refused rather than created unreachable. The module picker on this form is
            // filtered client-side by a separate AJAX endpoint; this is the server saying so too.
            if ($request->module_id && ! in_array((int) $request->module_id, app(\App\Services\Zone\ModuleZoneService::class)->connectedModuleIds($request->zone_id), true)) {
                $validator->getMessageBag()->add('module_id', translate('messages.This zone is not connected to the selected module'));
                return response()->json(['errors' => Helpers::error_processor($validator)]);
            }
        }

        $module = Module::find($request['module_id']);
        if ($module?->module_type == 'rental' && addon_published_status('Rental') && empty($request['pickup_zone_id'])){
            $validator->getMessageBag()->add('pickup_zone_id', translate('messages.You must select a pickup zone'));
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        if ($request->business_plan == 'subscription-base' && $request->package_id == null ) {
            $validator->getMessageBag()->add('package_id', translate('messages.You must select a package'));
             return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $vendor = new Vendor();
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->phone = $request->phone;
        $vendor->password = bcrypt($request->password);
        $vendor->status = null;
        $vendor->save();

        $store = new Store;
        $store->name =  $request->name[array_search('default', $request->lang)];
        $store->phone = $request->phone;
        $store->email = $request->email;
        $store->logo = FileStorage::upload('store/', $request->file('logo'));
        $store->cover_photo = FileStorage::upload('store/cover/', $request->file('cover_photo'));
        $store->address = $request->address[array_search('default', $request->lang)];
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;
        $store->vendor_id = $vendor->id;
        $store->zone_id = $request->zone_id;
        $store->module_id = $request->module_id;
        $store->pickup_zone_id = json_encode($request['pickup_zone_id']?? []) ;
        $store->tin = $request->tin;
        $store->tin_expire_date = $request->tin_expire_date;
        $extension = $request->has('tin_certificate_image') ? $request->file('tin_certificate_image')->getClientOriginalExtension() : 'png';
        $store->tin_certificate_image = FileStorage::upload('store/', $request->file('tin_certificate_image'));
        $store->delivery_time = $request->minimum_delivery_time .'-'. $request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->status = 0;
        $store->store_business_model = 'none';
        $store->save();

        Helpers::add_or_update_translations(request: $request, key_data: 'name', name_field: 'name', model_name: 'Store', data_id: $store->id, data_value: $store->name);
        Helpers::add_or_update_translations(request: $request, key_data: 'address', name_field: 'address', model_name: 'Store', data_id: $store->id, data_value: $store->address);


        app(VendorService::class)->notifyRegistration($vendor, $module, $request['email']);


        if(config('module.'.$store->module?->module_type.'.always_open'))
        {
            app(StoreService::class)->createSchedule($store->id);
        }

        if (Helpers::subscription_check()) {
            if ($request->business_plan == 'subscription-base' && $request->package_id != null ) {

                $store->package_id = $request->package_id;
                $store->save();

            }
            elseif($request->business_plan == 'commission-base' ){
                $store->store_business_model = 'commission';
                $store->save();

            }
        } else{
            $store->store_business_model = 'commission';
            $store->save();

        }

    return response()->json(['redirect_url' => route('restaurant.secondStep',['store_id' => $store->id,'business_plan'=>$request->business_plan])]);

    }

    public function get_all_modules(Request $request){
        $module_data = Module::translateOnly('module_name')
        ->select('modules.id', 'modules.module_name')
        ->Active()->whereHas('zones', function($query)use ($request){
            $query->where('zone_id', $request->zone_id);
        })->notParcel()->notRideShare()
        ->where('modules.module_name', 'like', '%'.$request->q.'%')
        ->limit(8)->get()->map(function($module) {
            return [
                'id' => $module->id,
                'text' => $module->module_name
            ];
        });
        return response()->json($module_data);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function get_modules_type(Request $request): JsonResponse
    {
        $module = Module::find($request->id);
        $packages=null;


        if ($module) {
            $packages= SubscriptionPackage::where('status',1)->where('module_type', Helpers::subscriptionPackageType($module))->latest()->get();

            $module = $module->module_type;
            return response()->json([
                'module_type' => $module,
                'view' => view('vendor-views.auth._package_data', compact('packages','module'))->render(),
            ]);
        }

        return response()->json(['module_type' => '','module_zone' => false]);
    }


    public function check_module_type(Request $request): JsonResponse
    {
        $module = Module::find($request->id);
        $moduleZone= null;
        if ($module) {
            if($request->zone_id){
            $moduleZone=  ModuleZone::where('module_id', $module->id)->where('zone_id', $request->zone_id)->exists();
            }

        }
        return response()->json(['module_zone' => $moduleZone]);
    }


    /**
     * The sign-up steps after the first run before the vendor can log in, so the store id in the
     * form is all they have. Only a store still mid-registration (no plan chosen: 'none') is
     * accepted -- otherwise any store id switched an existing store to commission or started a
     * plan on it.
     */
    private function registeringStore(mixed $storeId, array $businessModels = ['none']): Store
    {
        return Store::where('id', $storeId)->whereIn('store_business_model', $businessModels)->firstOrFail();
    }

    public function business_plan(Request $request){
        $store = $this->registeringStore($request->store_id);

        if ($request->business_plan == 'subscription-base' && $request->package_id != null ) {
            $key=['subscription_free_trial_days','subscription_free_trial_type','subscription_free_trial_status'];
            $free_trial_settings=Helpers::get_business_settings_many($key);

            return view('vendor-views.auth.register-subscription-payment',[
            'package_id'=> $request->package_id,
            'store_id' => $request->store_id,
            'free_trial_settings'=>$free_trial_settings,
            'payment_methods' => Helpers::getActivePaymentGateways(),

            ]);
        }
        elseif($request->business_plan == 'commission-base' ){
            $store->store_business_model = 'commission';
            $store->save();
            return view('vendor-views.auth.register-complete',[
                'type'=>'commission'
            ]);
        }
        else{
            $admin_commission= BusinessSetting::where('key','admin_commission')->first();
            $business_name= BusinessSetting::where('key','business_name')->first();
            $packages= SubscriptionPackage::where('status',1)->where('module_type', Helpers::subscriptionPackageType($store))->get();
            Toastr::error(translate('messages.Please follow the steps properly.'));
            return view('vendor-views.auth.register-step-2',[
                'admin_commission'=> $admin_commission?->value,
                'business_name'=> $business_name?->value,
                'packages'=> $packages,
                'store_id' => $request->store_id,
                'type'=>$request->type
                ]);
        }

    }

    public function secondStep(Request $request){
        $store = $this->registeringStore(
            storeId: $request->store_id,
            businessModels: $request->business_plan == 'commission-base' ? ['none', 'commission'] : ['none'],
        );
        if ($request->business_plan == 'subscription-base' && $store->package_id != null ) {

            $key=['subscription_free_trial_days','subscription_free_trial_type','subscription_free_trial_status'];
            $free_trial_settings=Helpers::get_business_settings_many($key);

            return view('vendor-views.auth.register-subscription-payment',[
            'package_id'=> $store->package_id,
            'store_id' => $store->id,
            'free_trial_settings'=>$free_trial_settings,
            'payment_methods' => Helpers::getActivePaymentGateways(),

            ]);
        }
        elseif($request->business_plan == 'commission-base' ){
            $store->store_business_model = 'commission';
            $store->save();
            return view('vendor-views.auth.register-complete',[
                'type'=>'commission'
            ]);
        }
        else{
            $admin_commission= BusinessSetting::where('key','admin_commission')->first();
            $business_name= BusinessSetting::where('key','business_name')->first();
            $packages= SubscriptionPackage::where('status',1)->where('module_type', Helpers::subscriptionPackageType($store))->get();
            return view('vendor-views.auth.register-step-2',[
                'admin_commission'=> $admin_commission?->value,
                'business_name'=> $business_name?->value,
                'packages'=> $packages,
                'store_id' => $store->id,
                'type'=>$request->type
                ]);
        }

    }

    public function payment(Request $request){
        $request->validate([
            'package_id' => 'required',
            'store_id' => 'required',
            'payment' => 'required'
        ]);

        $store = $this->registeringStore($request->store_id);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->with('translations')->find($request->package_id);

        if(!in_array($request->payment,['free_trial'])){
            $url= route('restaurant.final_step',['store_id' => $store->id?? null]);
            return redirect()->away(Helpers::subscriptionPayment(store_id:$store->id,package_id:$package->id,payment_gateway:$request->payment,payment_platform:'web',url:$url,type: 'new_join'));
        }
        if($request->payment == 'free_trial'){
            $plan_data=   Helpers::subscription_plan_chosen(store_id:$store->id,package_id:$package->id,payment_method:'free_trial',discount:0,reference:'free_trial',type: 'new_join');
        }
        $plan_data != false ?  Toastr::success( translate('Successfully subscribed.')) : Toastr::error( translate('Something went wrong'));
        return to_route('restaurant.final_step');
    }

public function back(Request $request){
    $admin_commission= BusinessSetting::where('key','admin_commission')->first();
    $business_name= BusinessSetting::where('key','business_name')->first();
    $store = $this->registeringStore($request->store_id)->load('module');
    $module=$store?->module?->module_type ?? 'all';
    $packages= SubscriptionPackage::where('status',1)->where('module_type', Helpers::subscriptionPackageType($store))->get();
    return view('vendor-views.auth.register-step-2',[
        'admin_commission'=> $admin_commission?->value,
        'business_name'=> $business_name?->value,
        'packages'=> $packages,
        'store_id' => $request->store_id,
        'module' => $module
        ]);
}


public function final_step(Request $request){


    $store_id= null;
    $payment_status= null;
    if($request?->store_id && is_string($request?->store_id)){
        $data = explode('?', $request?->store_id);
        $store_id = $data[0];
        $payment_status = $data[1]  != 'flag=success' ? 'fail': 'success';
    }

    return view('vendor-views.auth.register-complete',['store_id' =>$store_id,'payment_status'=> $payment_status]);
}

}
