<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Services\Marketing\CouponService;

class CouponController extends Controller
{
    /**
     * Only a coupon this store created. A bare find() reached every coupon on the platform,
     * admin ones included, for update, status and delete.
     */
    private function ownCoupon(mixed $id): Coupon
    {
        return Coupon::where(['created_by' => 'vendor', 'store_id' => Helpers::get_store_id()])->findOrFail($id);
    }

    public function add_new(Request $request)
    {
        $coupons = Coupon::latest()->where('created_by', 'vendor')->where('store_id',Helpers::get_store_id())
        ->search(keywords:$request['search'], mainCol:['title', 'code'])
        ->paginate(config('default_pagination'));
        $language = getWebConfig('language');
        return view('vendor-views.coupon.index', compact('coupons', 'language'));
    }

    public function generateCheckCode(Request $request, CouponService $couponService): \Illuminate\Http\JsonResponse
    {
        return response()->json($couponService->getUniqueCouponCode(title: $request['title']));
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:coupons|max:100',
            'title' => 'required|max:191',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount' => 'required_if:coupon_type,default',
            'min_purchase' => 'required|numeric|min:1',
            'coupon_type' => 'required|in:free_delivery,default',
            'max_discount' => 'exclude_unless:discount_type,percent|required|numeric|min:0.01',
            'title.0' => 'required',
        ],[
            'title.0.required'=>translate('Default title is required'),
            'max_discount.required'=>translate('Max discount is required for percentage discount type'),
            'max_discount.min'=>translate('Max discount must be greater than zero for percentage discount type'),
        ]);
        $customer_id  = $request->customer_ids ?? ['all'];
        $data = "";
        $coupon = new Coupon();
        $coupon->title = $request->title[array_search('default', $request->lang)];
        $coupon->code = $request->code;
        $coupon->limit = $request->coupon_type=='first_order'?1:$request->limit;
        $coupon->coupon_type = $request->coupon_type;
        $coupon->start_date = $request->start_date;
        $coupon->expire_date = $request->expire_date;
        $coupon->min_purchase = $request->min_purchase != null ? $request->min_purchase : 0;
        $coupon->max_discount = $request->max_discount != null ? $request->max_discount : 0;
        $coupon->discount = $request->discount_type == 'amount' ? $request->discount : $request['discount'];
        $coupon->discount_type = $request->discount_type??'';
        $coupon->status = 1;
        $coupon->created_by = 'vendor';
        $coupon->data = json_encode($data);
        $coupon->store_id =Helpers::get_store_id();
        $coupon->module_id =Helpers::get_store_data()->module_id;
        $coupon->customer_id = json_encode($customer_id);
        $coupon->save();

        Helpers::add_or_update_translations(request: $request, key_data:'title' , name_field:'title' , model_name: 'Coupon' ,data_id: $coupon->id,data_value: $coupon->title);

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function edit($id)
    {
        $coupon = Coupon::withoutGlobalScope('translate')->with(['translations', 'store'])->where(['id' => $id])->where('created_by', 'vendor' )->where('store_id', Helpers::get_store_id())->firstOrFail();
        $language = getWebConfig('language');
        return view('vendor-views.coupon.edit', compact('coupon', 'language'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'code' => 'required|max:100|unique:coupons,code,'.$id,
            'title' => 'required|max:191',
            'start_date' => 'required',
            'expire_date' => 'required',
            'discount' => 'required_if:coupon_type,default',
            'min_purchase' => 'required|numeric|min:1',
            'coupon_type' => 'required|in:free_delivery,default',
            'max_discount' => 'exclude_unless:discount_type,percent|required|numeric|min:0.01',
            'title.0' => 'required',
        ],[
            'title.0.required'=>translate('Default title is required'),
            'max_discount.required'=>translate('Max discount is required for percentage discount type'),
            'max_discount.min'=>translate('Max discount must be greater than zero for percentage discount type'),
        ]);

        $customer_id  = $request->customer_ids ?? ['all'];

        $coupon = $this->ownCoupon($id);
        $coupon->title = $request->title[array_search('default', $request->lang)];
        $coupon->code = $request->code;
        $coupon->limit = $request->coupon_type=='first_order'?1:$request->limit;
        $coupon->coupon_type = $request->coupon_type;
        $coupon->start_date = $request->start_date;
        $coupon->expire_date = $request->expire_date;
        $coupon->min_purchase = $request->min_purchase != null ? $request->min_purchase : 0;
        $coupon->max_discount = $request->max_discount != null ? $request->max_discount : 0;
        $coupon->discount = $request->discount_type == 'amount' ? $request->discount : $request['discount'];
        $coupon->discount_type = $request->discount_type??'';
        $coupon->customer_id = json_encode($customer_id);
        $coupon->save();
        Helpers::add_or_update_translations(request: $request, key_data:'title' , name_field:'title' , model_name: 'Coupon' ,data_id: $coupon->id,data_value: $coupon->title);


        Toastr::success(translate('Updated successfully'));
        return redirect()->route('vendor.coupon.add-new');
    }

    public function status(Request $request)
    {
        $coupon = $this->ownCoupon($request->id);
        $coupon->status = $request->status;
        $coupon->save();
        Toastr::success(translate('messages.Coupon status updated'));
        return back();
    }

    public function delete(Request $request)
    {
        $coupon = $this->ownCoupon($request->id);
        $coupon->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function viewCoupon($id){

        $coupon = Coupon::withoutGlobalScope('translate')->with(['translations', 'store'])->where(['id' => $id])->where('created_by', 'vendor' )->where('store_id', Helpers::get_store_id())->firstOrFail();
        $selectedCustomers='all';

        $current_module_type = config('module.current_module_type');

        return response()->json([
            'view' => view('vendor-views.coupon._view', compact('coupon','selectedCustomers','current_module_type'))->render(),
        ]);
    }

}
