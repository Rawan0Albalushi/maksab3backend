<?php

namespace App\Http\Controllers\Vendor;

use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Http\Controllers\Controller;
use App\Models\DeliveryMan;
use App\Models\DMReview;
use App\Models\OrderTransaction;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;

class DeliveryManController extends Controller
{
    public function index()
    {
        return view('vendor-views.delivery-man.index');
    }

    public function list(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $delivery_men = DeliveryMan::withStorage()->with(['rating', 'vehicle.storage'])->withCount('orders')->where('store_id', Helpers::get_store_id())
                 ->when( $request['search'] , function($query) use($key){
                    $query->where(function ($q) use ($key) {
                        foreach ($key as $value) {
                            $q->orWhere('f_name', 'like', "%{$value}%")
                                ->orWhere('l_name', 'like', "%{$value}%")
                                ->orWhere('email', 'like', "%{$value}%")
                                ->orWhere('phone', 'like', "%{$value}%")
                                ->orWhere('identity_number', 'like', "%{$value}%");
                        }
                    });
                }
        )->latest()->paginate(config('default_pagination'));
        return view('vendor-views.delivery-man.list', compact('delivery_men'));
    }



    public function reviews_list(){
        // This store's delivery men only -- it listed every review on the platform, customers and all.
        $reviews=DMReview::with(['delivery_man','customer'])
            ->whereHas('delivery_man', fn ($q) => $q->where('store_id', Helpers::get_store_id()))
            ->latest()->paginate(config('default_pagination'));
        return view('vendor-views.delivery-man.reviews-list',compact('reviews'));
    }

    public function preview($id, $tab='info')
    {
        // orders was only ever ->count()ed in the blade, which hydrated every order row.
        $dm = DeliveryMan::withStorage()->with(['reviews', 'wallet', 'rating'])->withCount('orders')->where('store_id', Helpers::get_store_id())->where(['id' => $id])->first();

        if (! $dm) {
            abort(404);
        }

        if($tab == 'info')
        {
            $reviews=DMReview::where(['delivery_man_id'=>$id])->latest()->paginate(config('default_pagination'));
            // info.blade.php ran Helpers::dm_rating_count() five times — five COUNT queries
            // for data already present in the eager-loaded reviews relation.
            $review_total = $dm?->reviews->count() ?? 0;
            $rating_breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
            foreach ($dm?->reviews ?? [] as $review) {
                if (isset($rating_breakdown[$review->rating])) {
                    $rating_breakdown[$review->rating]++;
                }
            }
            $store = Helpers::get_store_data();
            return view('vendor-views.delivery-man.view.info', compact('dm', 'reviews', 'review_total', 'rating_breakdown', 'store'));
        }
        else if($tab == 'transaction')
        {
            $digital_transaction = OrderTransaction::where('delivery_man_id', $id)->paginate(25);

            return view('vendor-views.delivery-man.view.transaction', compact('dm', 'digital_transaction'));
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => EmailAddress::rules('required', 'delivery_men'),
            'phone' => PhoneNumber::rules('required', 'delivery_men'),
            'password' => StrongPassword::rules('required'),
            'image' => ImageFile::rules('required'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        if ($request->has('image')) {
            $image_name = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($id_img_names, ['img'=>$identity_image, 'storage'=> Helpers::getDisk()]);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        $dm = New DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->identity_number = $request->identity_number;
        $dm->identity_type = $request->identity_type;
        $dm->store_id =  Helpers::get_store_id();
        $dm->identity_image = $identity_image;
        $dm->image = $image_name;
        $dm->active = 0;
        $dm->earning = 0;
        $dm->type = 'restaurant_wise';
        $dm->password = bcrypt($request->password);
        $dm->save();

        return response()->json([
            'message' => translate('Added successfully'),
            'redirect' => route('vendor.delivery-man.list')
        ], 200);

    }

    public function edit($id)
    {
        $delivery_man = DeliveryMan::withStorage()->where('store_id', Helpers::get_store_id())->findOrFail($id);
        return view('vendor-views.delivery-man.edit', compact('delivery_man'));
    }

    public function status(Request $request)
    {
        $delivery_man = DeliveryMan::where('store_id', Helpers::get_store_id())->findOrFail($request->id);
        $delivery_man->status = $request->status;

        try
        {
            if($request->status == 0)
            {   $delivery_man->auth_token = null;
                if(isset($delivery_man->fcm_token) && SendNotification::channelEnabled('deliveryman','deliveryman_account_block','push_notification_status') )
                {
                    $data = NotificationMessages::accountSuspended();
                    SendNotification::pushToDeliveryMan($delivery_man->id, $delivery_man->fcm_token, $data);
                }

            } else{
                if( SendNotification::channelEnabled('deliveryman','deliveryman_account_unblock','push_notification_status') && isset($delivery_man->fcm_token))
                {
                    $data = NotificationMessages::accountActivated();
                    SendNotification::pushToDeliveryMan($delivery_man->id, $delivery_man->fcm_token, $data);
                }
            }

        }
        catch (\Exception $e) {
            Toastr::warning(translate('messages.Push notification failed'));
        }

        $delivery_man->save();

        Toastr::success(translate('messages.Deliveryman status updated'));
        return back();
    }

    public function earning(Request $request)
    {
        $delivery_man = DeliveryMan::where('store_id', Helpers::get_store_id())->findOrFail($request->id);
        $delivery_man->earning = $request->status;

        $delivery_man->save();

        Toastr::success(translate('messages.Deliveryman type updated'));
        return back();
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'identity_number' => 'required|max:30',
            'email' => EmailAddress::rules('required', 'delivery_men,email,'.$id),
            'phone' => PhoneNumber::rules('required', 'delivery_men,phone,'.$id),
            'password' => StrongPassword::rules('nullable'),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)]);
        }

        $delivery_man = DeliveryMan::withStorage()->where('store_id', Helpers::get_store_id())->findOrFail($id);

        if ($request->has('image')) {
            $image_name = Helpers::update('delivery-man/', $delivery_man->image, 'png', $request->file('image'));
        } else {
            $image_name = $delivery_man['image'];
        }

        if ($request->has('identity_image')){
            foreach (json_decode($delivery_man['identity_image'], true) as $img) {

                Helpers::check_and_delete('delivery-man/' , $img);

            }
            $img_keeper = [];
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($img_keeper, ['img'=>$identity_image, 'storage'=> Helpers::getDisk()]);
            }
            $identity_image = json_encode($img_keeper);
        } else {
            $identity_image = $delivery_man['identity_image'];
        }

        $delivery_man->f_name = $request->f_name;
        $delivery_man->l_name = $request->l_name;
        $delivery_man->email = $request->email;
        $delivery_man->phone = $request->phone;
        $delivery_man->identity_number = $request->identity_number;
        $delivery_man->identity_type = $request->identity_type;
        $delivery_man->identity_image = $identity_image;
        $delivery_man->image = $image_name;

        $delivery_man->password = strlen($request->password)>1?bcrypt($request->password):$delivery_man['password'];
        $delivery_man->save();

        if($delivery_man->userinfo) {
            $userinfo = $delivery_man->userinfo;
            $userinfo->f_name = $request->f_name;
            $userinfo->l_name = $request->l_name;
            $userinfo->email = $request->email;
            $userinfo->image = $image_name;
            $userinfo->save();
        }

        return response()->json([
            'message' => translate('Updated successfully'),
            'redirect' => route('vendor.delivery-man.list')
        ], 200);

    }

    public function delete(Request $request)
    {
        $delivery_man = DeliveryMan::where('store_id', Helpers::get_store_id())->findOrFail($request->id);

        Helpers::check_and_delete('delivery-man/' , $delivery_man['image']);


        foreach (json_decode($delivery_man['identity_image'], true) as $img) {

            Helpers::check_and_delete('delivery-man/' , $img);

        }
        if($delivery_man->userinfo){

            $delivery_man->userinfo->delete();
        }
        $delivery_man->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function get_deliverymen(Request $request){
        $key = explode(' ', $request->q ?? '');
        $zone_ids = isset($request->zone_ids)?(count($request->zone_ids)>0?$request->zone_ids:[]):0;
        $data=DeliveryMan::when($zone_ids, function($query) use($zone_ids){
            return $query->whereIn('zone_id', $zone_ids);
        })
        ->when($request->earning, function($query){
            return $query->earning();
        })
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%")
                    ->orWhere('identity_number', 'like', "%{$value}%");
            }
        })->where('store_id', Helpers::get_store_id())->limit(8)->get(['id',DB::raw('CONCAT(f_name, " ", l_name) as text')])->makeHidden('image_full_url');
        return response()->json($data);
    }

    public function get_account_data(DeliveryMan $deliveryman)
    {
        $wallet = $deliveryman->wallet;
        $cash_in_hand = 0;
        $balance = 0;

        if($wallet)
        {
            $cash_in_hand = $wallet->collected_cash;
            $balance = $wallet->total_earning - $wallet->total_withdrawn - $wallet->pending_withdraw;
        }
        return response()->json(['cash_in_hand'=>$cash_in_hand, 'earning_balance'=>$balance], 200);

    }


    public function transaction_search(Request $request){
        DeliveryMan::where('store_id', Helpers::get_store_id())->findOrFail($request->dm_id);
        $key = explode(' ', $request['search'] ?? '');
        $digital_transaction=OrderTransaction::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('order_id', 'like', "%{$value}%");
            }
        })->where('delivery_man_id', $request->dm_id)->get();
        return response()->json([
            'view'=>view('vendor-views.delivery-man.partials._transation',compact('digital_transaction'))->render(),
            'count'=>$digital_transaction->count()
        ]);
    }

}
