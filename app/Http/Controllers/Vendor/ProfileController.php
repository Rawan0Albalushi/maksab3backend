<?php

namespace App\Http\Controllers\Vendor;

use App\Rules\EmailAddress;
use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function view()
    {
        $is_employee = !auth('vendor')->check();
        $profile_user = $is_employee ? auth('vendor_employee')->user() : auth('vendor')->user();
        $profile_user?->loadMissing($is_employee ? ['storage', 'role'] : ['storage']);
        $store = Helpers::get_store_data();

        return view('vendor-views.profile.index', compact('profile_user', 'is_employee', 'store'));
    }


    public function update(Request $request)
    {
        $table=auth('vendor')->check()?'vendors':'vendor_employees';
        $seller = auth('vendor')->check()?auth('vendor')->user():auth('vendor_employee')->user();
        $request->validate([
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'email' => EmailAddress::rules('required', $table.',email,'.$seller->id),
            'phone' => PhoneNumber::rules('required', ''.$table.',phone,'.$seller->id),
            'image' => ImageFile::rules('nullable'),
        ], [
            'f_name.required' => translate('messages.First name is required'),
        ]);
        $seller->f_name = $request->f_name;
        $seller->l_name = $request->l_name;
        $seller->phone = $request->phone;
        $seller->email = $request->email;
        if($table == 'vendors' ){
            $seller->store()->update(['email' =>$request->email]);
        }

        if ($request->image) {
            $seller->image = Helpers::update('vendor/', $seller->image, 'png', $request->file('image'));
        }
        $seller->save();

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function settings_password_update(Request $request)
    {
        $request->validate([
            'password' => StrongPassword::rules('required', ['same:confirm_password']),
            'confirm_password' => 'required',
        ]);

        $seller = auth('vendor')->check()?Helpers::get_vendor_data():auth('vendor_employee')->user();
        $seller->password = bcrypt($request['password']);
        $seller->save();
        Toastr::success(translate('Updated successfully'));
        return back();
    }
}
