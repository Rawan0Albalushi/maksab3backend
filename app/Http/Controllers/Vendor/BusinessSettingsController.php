<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Store;
use App\Models\StoreConfig;
use Illuminate\Http\Request;
use App\Models\StoreSchedule;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\System\DistanceService;
use Brian2694\Toastr\Facades\Toastr;
use App\Models\NotificationSetting;
use App\Models\StoreNotificationSetting;
use App\Models\Zone;
use Illuminate\Support\Facades\Validator;
use App\Support\Notification\SendNotification;

class BusinessSettingsController extends Controller
{

    private $store;

    /**
     * The switches a vendor may flip on its own store through toggle-settings. The handler writes
     * `$store[$menu]` for whatever column is named, so without this list a vendor could set
     * vendor_id, status or the commission; the admin-only switches (item_section, pos_system,
     * self_delivery_system...) go through the admin route instead.
     */
    private const VENDOR_TOGGLES = [
        'schedule_order', 'delivery', 'take_away', 'prescription_order', 'free_delivery', 'veg',
        'non_veg', 'cutlery', 'announcement', 'halal_tag_status', 'extra_packaging_status',
        'extra_packaging_amount', 'can_edit_order',
    ];

    /**
     * Every {store} route here arrives by route model binding, which knows nothing of who is
     * asking -- any store id in the URL was that store's settings page, writable.
     */
    private function authorizeStore(Store $store): void
    {
        abort_unless((int) $store->id === (int) Helpers::get_store_id(), 404);
    }

    public function store_index()
    {


        $auth_store = Helpers::get_store_data();
        $auth_store->loadMissing(['module.storage', 'storage', 'storeConfig']);

        // The edit form needs the untranslated columns, hence the re-read. Relations the
        // translate scope does not affect are carried over from the already-hydrated
        // instance the layout uses, instead of being queried a second time.
        $store = Store::withoutGlobalScope('translate')->without('storeConfig')->with('translations')->findOrFail($auth_store->id);
        $store->setRelation('storage', $auth_store->storage);
        $store->setRelation('module', $auth_store->module);
        $store->setRelation('storeConfig', $auth_store->storeConfig);
        $store->setRelation('store_sub', $auth_store->store_sub);
        $store->load('schedules');

        $admin_website_builder_status = Helpers::get_business_settings('admin_website_builder_status');

        if($store->module_type == 'rental' ){
            $zones=Zone::active()->get(['id','name']);
            $admin_website_builder_status = 0;
            return view('rental::provider.settings.settings', compact('store','zones','admin_website_builder_status'));
        }

        $currency_symbol = Helpers::currency_symbol();
        $prescription_order_status = Helpers::get_business_settings('prescription_order_status', false) ?? 0;
        $extra_packaging_data = json_decode(Helpers::get_business_settings('extra_packaging_data', false) ?? '', true);

        // §13.2 — the per-unit rate label follows `distance_unit` from a DYNAMIC key. A static
        // `delivery_charge_per_km` translation would keep saying "km" after the switch.
        $distanceUnitLabel = app(DistanceService::class)->unitLabel();

        return view('vendor-views.business-settings.restaurant-index', compact('store','admin_website_builder_status','currency_symbol','prescription_order_status','extra_packaging_data','distanceUnitLabel'));
    }

    public function store_setup(Store $store, Request $request)
    {
        $this->authorizeStore($store);
        $request->validate([
            'minimum_order' => 'required|numeric|min:0.01',
            'gst' => 'required_if:gst_status,1',
            'extra_packaging_amount' => 'required_if:extra_packaging_status,1',
            'per_km_delivery_charge'=>'required_with:minimum_delivery_charge|nullable|numeric|min:0|max:999999999',
            'minimum_delivery_charge'=>'required_with:per_km_delivery_charge|nullable|numeric|min:0|max:99999999.99',
            'maximum_shipping_charge'=>'nullable|numeric|min:0|max:999999999',
        ], [
            'minimum_order.min' => translate('messages.Minimum order amount must be greater than 0'),
            'gst.required_if' => translate('GST can not be empty'),
            'extra_packaging_amount.required_if' => translate('messages.Extra packaging amount can not be empty'),
            'per_km_delivery_charge.min' => translate('messages.Delivery charge per unit cannot be negative'),
            'minimum_delivery_charge.min' => translate('messages.Minimum delivery charge cannot be negative'),
            'maximum_shipping_charge.min' => translate('messages.Maximum delivery charge cannot be negative'),
        ]);

        if(isset($request->maximum_shipping_charge) && ($request->minimum_delivery_charge > $request->maximum_shipping_charge)){
            Toastr::error(translate('Maximum delivery charge must be greater than minimum delivery charge.'));
                return back();
        }

        if($store->module_type == 'rental' && addon_published_status('Rental')){
            $store->pickup_zone_id =json_encode($request->pickup_zones ?? []);
            $store->schedule_order = $request->schedule_order ?? 0;
        }

        $store->minimum_order = $request->minimum_order??0;
        $store->gst = json_encode(['status'=>$request->gst_status, 'code'=>$request->gst]);
        $store->minimum_shipping_charge = $store->sub_self_delivery?$request->minimum_delivery_charge??0: $store->minimum_shipping_charge;
        $store->per_km_shipping_charge = $store->sub_self_delivery?$request->per_km_delivery_charge??0: $store->per_km_shipping_charge;
        $store->per_km_shipping_charge = $store->sub_self_delivery?$request->per_km_delivery_charge??0: $store->per_km_shipping_charge;
        $store->maximum_shipping_charge = $store->sub_self_delivery?$request->maximum_shipping_charge??0: $store->maximum_shipping_charge;
        $store->order_place_to_schedule_interval = $request->order_place_to_schedule_interval;
        $store->delivery_time = $request->minimum_delivery_time .'-'. $request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->save();
        $conf = StoreConfig::firstOrNew(
            ['store_id' =>  $store->id]
        );
        $conf->extra_packaging_amount = $request->extra_packaging_amount ?? 0;
        $conf->extra_packaging_status = $request->extra_packaging_status ?? 0;
        $conf->minimum_stock_for_warning = $request->has('minimum_stock_for_warning')
            ? (int) ($request->minimum_stock_for_warning ?? 0)
            : ($conf->minimum_stock_for_warning ?? 0);
        $conf->show_low_stock_count = $request->has('show_low_stock_count')
            ? (int) ($request->show_low_stock_count ?? 0)
            : ($conf->show_low_stock_count ?? 1);
        $conf->save();
        if($store->module_type == 'rental' && addon_published_status('Rental')){
            Toastr::success(translate('messages.Provider settings updated'));
        }else{
            Toastr::success(translate('messages.Store settings updated'));
        }
        return back();
    }

    public function stock_setup(Store $store, Request $request)
    {
        $this->authorizeStore($store);
        $request->validate([
            'show_low_stock_count' => 'nullable|in:1',
            'minimum_stock_for_warning' => 'nullable|integer|min:0|max:999999999',
        ], [
            'minimum_stock_for_warning.integer' => translate('messages.Minimum stock for warning must be an integer'),
        ]);

        $conf = StoreConfig::firstOrNew(['store_id' => $store->id]);
        $conf->show_low_stock_count = $request->has('show_low_stock_count') ? 1 : 0;
        $conf->minimum_stock_for_warning = (int) ($request->minimum_stock_for_warning ?? 0);
        $conf->save();

        Toastr::success(translate('messages.Stock settings updated'));

        return back();
    }
    public function updateStoreMetaData(Store $store, Request $request)
    {
        $this->authorizeStore($store);
        if ($request->has('meta_image_deleted') && $request->meta_image_deleted == 1) {
            Helpers::check_and_delete('store/', $store->meta_image);
            $store->meta_image = null;
        }
        $store->meta_image = $request->has('meta_image') ? Helpers::update('store/', $store->meta_image, 'png', $request->file('meta_image')) : $store->meta_image;
        
        $store->meta_title = $request->meta_title;
        $store->meta_description = $request->meta_description;
        $store->meta_data = Helpers::formatMetaData($request->all(), $store->meta_data);
        $store->save();
        if($store->module->module_type == 'rental' && addon_published_status('Rental')){
            Toastr::success(translate('messages.Provider meta data updated!'));
        }else{
            Toastr::success(translate('messages.Store').' '.translate('messages.Meta data updated'));
        }

        return back();
    }
    public function store_status(Store $store, Request $request)
    {
        $this->authorizeStore($store);
        abort_unless(in_array($request->menu, self::VENDOR_TOGGLES, true), 404);

        if($request->menu == "schedule_order" && !Helpers::schedule_order())
        {
            Toastr::warning(translate('messages.Schedule order disabled warning'));
            return back();
        }

        if((($request->menu == "delivery" && $store->take_away==0) || ($request->menu == "take_away" && $store->delivery==0)) &&  $request->status == 0 )
        {
            Toastr::warning(translate('messages.Can not disable both take away and delivery'));
            return back();
        }

        if((($request->menu == "veg" && $store->non_veg==0) || ($request->menu == "non_veg" && $store->veg==0)) &&  $request->status == 0 )
        {
            Toastr::warning(translate('messages.Veg non veg disable warning'));
            return back();
        }

        if($request->menu == "announcement" &&  $request->status == 1 &&  !isset($store->announcement_message) )
        {
            Toastr::warning(translate('messages.You need to add announcement message first'));
            return back();
        }

        if($request->menu == 'free_delivery' &&(($store->store_business_model == 'subscription' && $store?->store_sub?->self_delivery == 0) || ($store->store_business_model == 'unsubscribed'))){
            Toastr::error(translate('Your subscription plane does not have this feature'));
            return back();
        }


        if(in_array($request->menu, ['halal_tag_status', 'extra_packaging_status', 'extra_packaging_amount', 'can_edit_order'])){

            $conf = StoreConfig::firstOrNew(
                ['store_id' =>  $store->id]
            );
            $conf[$request->menu] = $request->status;
            $conf->save();
            if($store->module->module_type == 'rental' && addon_published_status('Rental')){
                Toastr::success(translate('messages.Provider settings updated'));
            }else{
                Toastr::success(translate('messages.Store settings updated'));
            }
            return back();
        }


        $store[$request->menu] = $request->status;
        $store->save();
        if($store->module->module_type == 'rental' && addon_published_status('Rental')){
            Toastr::success(translate('messages.Provider settings updated'));
        }else{
            Toastr::success(translate('messages.Store settings updated'));
        }
        return back();
    }

    public function website_builder_status(Store $store, Request $request)
    {
        $this->authorizeStore($store);
        $store->storeConfig()->updateOrInsert(
            [
                'store_id' => $store->id,
            ],
            [
                'website_builder_status' => $request->status,
            ]
        );
        if($store->module->module_type == 'rental' && addon_published_status('Rental')){
            Toastr::success(translate('messages.Provider settings updated'));
        }else{
            Toastr::success(translate('messages.Store settings updated'));
        }
        return back();
    }

    public function active_status(Request $request)
    {
        $store = Helpers::get_store_data();
        $store->active = !$store->active;
        $store->save();
        return response()->json(['message' => $store->active?($store->module->module_type == 'rental' ? translate('Provider') : translate('Store')).' '.translate('messages.opened'):($store->module->module_type == 'rental' ? translate('Provider') : translate('Store')).' '.translate('messages.Temporarily closed')], 200);
    }

    public function add_schedule(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'start_time'=>'required|date_format:H:i',
            'end_time'=>'required|date_format:H:i|after:start_time',
        ],[
            'end_time.after'=>translate('messages.End time must be after the start time')
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }
        $temp = StoreSchedule::where('day', $request->day)->where('store_id',Helpers::get_store_id())
        ->where(function($q)use($request){
            return $q->where(function($query)use($request){
                return $query->where('opening_time', '<=' , $request->start_time)->where('closing_time', '>=', $request->start_time);
            })->orWhere(function($query)use($request){
                return $query->where('opening_time', '<=' , $request->end_time)->where('closing_time', '>=', $request->end_time);
            });
        })
        ->first();

        if(isset($temp))
        {
            return response()->json(['errors' => [
                ['code'=>'time', 'message'=>translate('messages.Schedule overlapping warning')]
            ]]);
        }

        $store = Helpers::get_store_data();
        $store_schedule = StoreSchedule::insert(['store_id'=>Helpers::get_store_id(),'day'=>$request->day,'opening_time'=>$request->start_time,'closing_time'=>$request->end_time]);
        return response()->json([
            'view' => view('vendor-views.business-settings.partials._schedule', compact('store'))->render(),
        ]);
    }

    public function remove_schedule($store_schedule)
    {
        $store = Helpers::get_store_data();
        $schedule = StoreSchedule::where('store_id', $store->id)->find($store_schedule);
        if(!$schedule)
        {
            return response()->json([],404);
        }
        $schedule->delete();
        return response()->json([
            'view' => view('vendor-views.business-settings.partials._schedule', compact('store'))->render(),
        ]);
    }


    public function site_direction_vendor(Request $request){
        session()->put('site_direction_vendor', ($request->status == 1?'ltr':'rtl'));
        return response()->json();
    }

    public function notification_index()
    {
        $module_type=Helpers::get_store_data()->module->module_type;
        if(StoreNotificationSetting::where('store_id',Helpers::get_store_id())->count() == 0 ){
            match ($module_type) {
                'rental' => Helpers::storeRentalNotificationDataSetup(Helpers::get_store_id()),
                'service' => Helpers::storeServiceNotificationDataSetup(Helpers::get_store_id()),
                default => Helpers::storeNotificationDataSetup(Helpers::get_store_id()),
            };
        }
        $notification_module_type = match ($module_type) {
            'rental' => 'rental',
            'service' => 'service',
            default => 'all',
        };
        $data= StoreNotificationSetting::where('store_id',Helpers::get_store_id())->where('module_type', $notification_module_type)->get();
        $business_name= Helpers::get_business_settings('business_name', false);

        // The view previously called SendNotification::settingFor() per row,
        // which issued one query per notification key.
        $admin_notification_data = NotificationSetting::where('type', in_array($module_type, ['rental', 'service']) ? 'provider' : 'store')
            ->select(['key', 'mail_status', 'push_notification_status', 'sms_status'])
            ->get()
            ->keyBy('key');

        return view('vendor-views.business-settings.notification-index', compact('business_name' ,'data', 'module_type', 'admin_notification_data'));
    }

    public function notification_status_change($key, $type){
        $data= StoreNotificationSetting::where('store_id',Helpers::get_store_id())->where('key',$key)->first();
        if(!$data){
            Toastr::error(translate('No data found'));
            return back();
        }
        if($type == 'Mail' ) {
            $data->mail_status =  $data->mail_status == 'active' ? 'inactive' : 'active';
        }
        elseif($type == 'push_notification' ) {
            $data->push_notification_status =  $data->push_notification_status == 'active' ? 'inactive' : 'active';
        }
        elseif($type == 'SMS' ) {
            $data->sms_status =  $data->sms_status == 'active' ? 'inactive' : 'active';
        }
        $data?->save();

        Toastr::success(translate('messages.Notification settings updated'));
        return back();
    }
}
