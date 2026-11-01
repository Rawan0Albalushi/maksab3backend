<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Store;
use App\Models\StoreWallet;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use App\Models\StoreSubscription;
use App\Models\SubscriptionPackage;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Session;
use App\Exports\SubscriptionTransactionsExport;
use App\Models\SubscriptionBillingAndRefundHistory;
use App\Services\Payment\StoreSubscriptionService;

class SubscriptionController extends Controller
{
    public function subscriberDetail(){
        // Re-querying the store built a second Store instance for the row the auth user
        // already carries, paying its translate/storage scopes again. Load onto that
        // instance instead — same row, same relations, same counts.
        $store = Helpers::get_store_data();

        // _plan-overview.blade.php reads $store?->vendor?->status. When the vendor guard is
        // the one logged in, that relation is the user the session guard already hydrated
        // (EloquentUserProvider), so loading it again duplicated the Vendor HasStorage scope.
        // Attached before loadMissing, which then skips it. A vendor_employee has no
        // auth('vendor') user, so it falls through and loads normally.
        $auth_vendor = auth('vendor')->user();
        if ($store && $auth_vendor && (int) $store->vendor_id === (int) $auth_vendor->id) {
            $store->setRelation('vendor', $auth_vendor);
        }

        $store?->loadMissing([
            'store_sub_update_application.package','vendor','store_sub_update_application.last_transcations','module:id,module_type'
        ]);
        $store?->loadCount(['items','store_all_sub_trans']);
        if($store->module_type == 'rental') {
            $store->loadCount('vehicles as items_count' );
        } elseif($store->module_type == 'service') {
            $store->loadCount('services as items_count' );
        }

        $packages = SubscriptionPackage::where('status',1)
        ->where('module_type', Helpers::subscriptionPackageType($store) )
        ->latest()->get();
        $admin_commission=Helpers::get_business_settings('admin_commission', false) ;
        $business_name=Helpers::get_business_settings('business_name', false) ;
        try {
            $index=  $store->store_business_model == 'commission' ? 0 : 1+ array_search($store?->store_sub_update_application?->package_id??1 ,array_column($packages->toArray() ,'id') );
        } catch (\Throwable $th) {
            $index= 2;
        }
        return view('vendor-views.subscription.subscriber.vendor-subscription',compact('store','packages','business_name','admin_commission','index'));
    }

    public function cancelSubscription(Request $request, $id){
    StoreSubscription::where(['store_id' => Helpers::get_store_id(), 'id'=>$request->subscription_id])->update([
            'is_canceled' => 1,
            'canceled_by' => 'store',
        ]);

        try {
            // Same row Helpers::get_store_id() just resolved off the auth user.
            $store = Helpers::get_store_data();
            app(StoreSubscriptionService::class)->notifyPlanCancellation($store);
        } catch (\Exception $ex) {
            info($ex->getMessage());
        }

        return response()->json(200);

    }
    public function switchToCommission($id){

        // The current store, whatever id the URL carries -- it switched any store to commission.
        $store=  Store::where('id', Helpers::get_store_id())->with('store_sub')->firstOrFail();

        $store_subscription=  $store->store_sub;
        if($store->store_business_model == 'subscription'  && $store_subscription?->is_canceled === 0 && $store_subscription?->is_trial === 0){
            Helpers::calculateSubscriptionRefundAmount(store:$store);
        }

        $store->store_business_model = 'commission';
        $store->item_section = 1;
        $store->save();

        StoreSubscription::where(['store_id' => Helpers::get_store_id()])->update([
            'status' => 0,
        ]);
        return response()->json(200);

    }
    public function packageView($id,$store_id){
        // The store id is in the URL; another store's plan, pending bill and wallet are not this page's.
        abort_unless((int) $store_id === (int) Helpers::get_store_id(), 404);
        $store_subscription= StoreSubscription::where('store_id', $store_id)->with(['package'])->latest()->first();
        $package = SubscriptionPackage::where('status',1)->where('id',$id)->firstOrFail();

        $store= Store::Where('id',$store_id)->firstOrFail();
        $pending_bill= SubscriptionBillingAndRefundHistory::where(['store_id'=>$store->id,
        'transaction_type'=>'pending_bill', 'is_success' =>0])?->sum('amount') ?? 0;

        $balance = Helpers::get_business_settings('wallet_status', false) == 1 ? StoreWallet::where('vendor_id',$store->vendor_id)->first()?->balance ?? 0 : 0;
        $payment_methods = Helpers::getActivePaymentGateways();
        $disable_item_count=null;
        if(data_get(Helpers::subscriptionConditionsCheck(store_id:$store->id,package_id:$package->id) , 'disable_item_count') > 0 && ( !$store_subscription || $package->id != $store_subscription->package_id)){
            $disable_item_count=data_get(Helpers::subscriptionConditionsCheck(store_id:$store->id,package_id:$package->id) , 'disable_item_count');
        }
        $store_business_model=$store->store_business_model;
        $admin_commission=Helpers::get_business_settings('admin_commission', false) ?? 0 ;

        $cash_backs=[];
        if($store->store_business_model == 'subscription' &&  $store_subscription->status == 1 && $store_subscription->is_canceled == 0 && $store_subscription->is_trial == 0  && $store_subscription->package_id !=  $package->id){
            $cash_backs= Helpers::calculateSubscriptionRefundAmount(store:$store, return_data:true);
        }

        return response()->json([
            'disable_item_count'=> $disable_item_count,
            'view' => view('vendor-views.subscription.subscriber.partials._package_selected', compact('store_subscription','package','store_id','balance','payment_methods','pending_bill','store_business_model','admin_commission','cash_backs'))->render()
        ]);

    }
    public function packageBuy(Request $request){


        $request->validate([
            'package_id' => 'required',
            'store_id' => 'required',
            'payment_gateway' => 'required'
        ]);
        // Always the current store: a store_id from the form bought a plan for any store and paid
        // for it out of that store's vendor wallet.
        $store= Store::Where('id', Helpers::get_store_id())->firstOrFail(['id','vendor_id']);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->with('translations')->find($request->package_id);
        $pending_bill= SubscriptionBillingAndRefundHistory::where(['store_id'=>$store->id,
        'transaction_type'=>'pending_bill', 'is_success' =>0])?->sum('amount') ?? 0;

        if(!in_array($request->payment_gateway,['wallet'])){
            $url= route('vendor.subscriptionackage.subscriberDetail',$store->id);
            return redirect()->away(Helpers::subscriptionPayment(store_id:$store->id,package_id:$package->id,payment_gateway:$request->payment_gateway,payment_platform:'web',url:$url,pending_bill:$pending_bill,type: $request?->type));
        }

        if($request->payment_gateway == 'wallet'){
        $wallet= StoreWallet::firstOrNew(['vendor_id'=> $store->vendor_id]);
        $balance = Helpers::get_business_settings('wallet_status', false) == 1 ? $wallet?->balance ?? 0 : 0;

            if($balance >= ($package?->price + $pending_bill)){
                $reference= 'wallet_payment_by_vendor';
                $plan_data=   Helpers::subscription_plan_chosen(store_id:$store->id,package_id:$package->id,payment_method:$reference,discount:0,pending_bill:$pending_bill,reference:$reference,type: $request?->type);
                if($plan_data != false){
                    $wallet->total_withdrawn= $wallet?->total_withdrawn + $package->price +$pending_bill;
                    $wallet?->save();
                }
            }
            else{
                Toastr::error( translate('messages.Insufficient wallet balance'));
                return to_route('vendor.subscriptionackage.subscriberDetail',$store->id);

            }
        }

        $plan_data != false ?  Toastr::success(  $request?->type == 'renew' ?  translate('Subscription package renewed successfully.'): translate('Subscription package shifted successfully.')  ) : Toastr::error( translate('Something went wrong'));
        return to_route('vendor.subscriptionackage.subscriberDetail',$store->id);

    }



    public function subscriberTransactions($id,Request $request){
        $filter= $request['filter'];
        $plan_type= $request['plan_type'];
        $from =$request['start_date'] ?? Carbon::now()->format('Y-m-d');
        $to =$request['end_date'] ?? Carbon::now()->format('Y-m-d');
        // Same row the auth guard already hydrated — re-querying it built a second Store
        // instance and re-ran its translate and storage scopes. Load onto that instance.
        $store = Helpers::get_store_data();
        $store?->loadMissing([
            'store_sub_update_application.package'
        ]);

        $key = explode(' ', $request['search'] ?? '');
        $transactions= SubscriptionTransaction::where('store_id',Helpers::get_store_id())
        ->when($request['search'], function($query) use($key){
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('id', 'like', "%{$value}%");
                }
            });
        })
        ->when($filter == 'this_year' , function($query){
            $query->whereYear('created_at', Carbon::now()->year );
        })
        ->when($filter == 'this_month' , function($query){
            $query->whereMonth('created_at', Carbon::now()->month );
        })
        ->when($filter == 'this_week' , function($query){
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()] );
        })
        ->when($filter == 'custom' , function($query) use($from,$to) {
            $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
        })
        ->when( in_array( $plan_type,['renew','new_plan','first_purchased','free_trial'])  , function($query) use($plan_type){
            $query->where('plan_type', $plan_type );
        })
        ->latest()->paginate(config('default_pagination'));
            $subscription_deadline_warning_days = Helpers::get_business_settings('subscription_deadline_warning_days', false) ?? 7;
        return view('vendor-views.subscription.subscriber.transaction',compact('store','transactions','id','filter','subscription_deadline_warning_days'));

    }
    public function invoice($id){
        $BusinessData= ['admin_commission' ,'business_name','address','phone','logo','email_address'];
        $transaction= SubscriptionTransaction::with(['store.vendor','package:id,package_name,price'])->where('store_id', Helpers::get_store_id())->findOrFail($id);
        $BusinessData=Helpers::get_business_settings_many($BusinessData);
        $logo=BusinessSetting::where('key', "logo")->first() ;

        $mpdf_view = View::make('subscription-invoice', compact('transaction','BusinessData','logo'));
        Helpers::gen_mpdf(view: $mpdf_view,file_prefix: 'Subscription',file_postfix: $id);
        return back();
    }

    public function subscriberTransactionExport(Request $request){


        $filter= $request['filter'];
        $plan_type= $request['plan_type'];
        $from =$request['start_date'] ?? Carbon::now()->format('Y-m-d');
        $to =$request['end_date'] ?? Carbon::now()->format('Y-m-d');
        // Same row the auth user already carries — re-querying it re-ran the Store
        // translate/storage scopes.
        $store = Helpers::get_store_data();

        $key = explode(' ', $request['search'] ?? '');
        $transactions= SubscriptionTransaction::where('store_id',$store->id)
        ->when($request['search'], function($query) use($key){
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->Where('id', 'like', "%{$value}%");
                }
            });
        })
        ->when($filter == 'this_year' , function($query){
            $query->whereYear('created_at', Carbon::now()->year );
        })
        ->when($filter == 'this_month' , function($query){
            $query->whereMonth('created_at', Carbon::now()->month );
        })
        ->when($filter == 'this_week' , function($query){
            $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()] );
        })
        ->when($filter == 'custom' , function($query) use($from,$to) {
            $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
        })
        ->when( in_array( $plan_type,['renew','new_plan','first_purchased','free_trial'])  , function($query) use($plan_type){
            $query->where('plan_type', $plan_type );
        })
        ->latest()->get();

        $data = [
            'data'=>$transactions,
            'plan_type'=>$request['plan_type'] ?? 'all',
            'filter'=>$request['filter'] ?? 'all',
            'search'=>$request['search'],
            'start_date'=>$request['start_date'],
            'end_date'=>$request['end_date'],
            'store'=>$store->name,
        ];
        if ($request->export_type == 'excel') {
            return Excel::download(new SubscriptionTransactionsExport($data), 'SubscriptionTransactionsExport.xlsx');
        }
        return Excel::download(new SubscriptionTransactionsExport($data), 'SubscriptionTransactionsExport.csv');
    }

    public function addToSession(Request $request)
    {
        // Only the dismissable banners' flags: the key came straight from the request, so any
        // session key could be set to true.
        abort_unless(in_array($request->value, [
            'subscription_renew_close_btn', 'subscription_free_trial_close_btn', 'subscription_cancel_close_btn',
            'subscription_plan_update_close_btn', 'stock_out_reminder_close_btn',
        ], true), 404);
        Session::put($request->value, true);
        return response()->json(['success' => true]);
    }

    public function subscriberWalletTransactions(Request $request){
        // Same row the auth user already carries — re-querying it only paid the Store
        // translate/storage scopes a second time.
        $store = Helpers::get_store_data();
        $transactions= SubscriptionBillingAndRefundHistory::where('store_id', $store->id)->with('package')
        ->where('transaction_type','refund')
        ->latest()->paginate(config('default_pagination'));

        return view('vendor-views.subscription.subscriber.wallet-transaction',compact('transactions','store'));

    }
}
