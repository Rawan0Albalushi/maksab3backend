<?php

namespace App\Http\Controllers\Vendor;

use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\Rules\StrongPassword;
use App\Models\EmployeeRole;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\VendorEmployee;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;
use App\Exports\StoreEmployeeListExport;
use App\Models\DataSetting;
use App\Navigation\VendorRolePermissionForm;

class EmployeeController extends Controller
{

    public function add_new()
    {
        $rls = EmployeeRole::where('store_id',Helpers::get_store_id())->orderBy('name')->get();
        $permission_form = VendorRolePermissionForm::forCurrentStore();
        $permission_labels = $permission_form->labels();
        $permission_total = $permission_form->total();
        $login_slug = DataSetting::where('key', 'store_employee_login_url')->value('value');
        $login_url = $login_slug ? route('login', [$login_slug]) : null;

        return view('vendor-views.employee.add-new', compact('rls', 'permission_labels', 'permission_total', 'login_url'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'nullable|max:100',
            // Only one of this store's own roles; another store's role id was accepted as is.
            'role_id' => ['required', \Illuminate\Validation\Rule::exists('employee_roles', 'id')->where('store_id', Helpers::get_store_id())],
            'image' => ImageFile::rules('required'),
            'email' => EmailAddress::rules('required', 'vendor_employees'),
            'phone' => PhoneNumber::rules('required', 'vendor_employees'),
            'password' => StrongPassword::rules('required'),
        ]);
        $vendor = new VendorEmployee();
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->phone = $request->phone;
        $vendor->email = $request->email;
        $vendor->employee_role_id = $request->role_id;
        $vendor->password = bcrypt($request->password);
        $vendor->vendor_id = Helpers::get_vendor_id();
        $vendor->store_id =Helpers::get_store_id();
        $vendor->image = Helpers::upload('vendor/', 'png', $request->file('image'));
        $vendor->save();

        Toastr::success('Employee added successfully!');
        return redirect()->route('vendor.employee.list');
    }

    function list(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $em = VendorEmployee::withStorage()->where('store_id', Helpers::get_store_id())->with(['role'])
        ->when($request['search'] , function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%");
                    $q->orWhere('l_name', 'like', "%{$value}%");
                    $q->orWhere('phone', 'like', "%{$value}%");
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->paginate(config('default_pagination'));
        $current_employee_id = auth('vendor_employee')->id();

        return view('vendor-views.employee.list', compact('em', 'current_employee_id'));
    }

    public function edit($id)
    {
        $e = VendorEmployee::withStorage()->where('store_id', Helpers::get_store_id())->where(['id' => $id])->firstOrFail();
        $rls = EmployeeRole::where('store_id',Helpers::get_store_id())->get();
        if (auth('vendor_employee')->id()  != $e['id']){
            return view('vendor-views.employee.edit', compact('rls', 'e'));
        }
        Toastr::warning(translate('messages.Access denied'));
        return back();
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'f_name' => 'required',
            'l_name' => 'nullable|max:100',
            // Only one of this store's own roles; another store's role id was accepted as is.
            'role_id' => ['required', \Illuminate\Validation\Rule::exists('employee_roles', 'id')->where('store_id', Helpers::get_store_id())],
            'email' => EmailAddress::rules('required', 'vendor_employees,email,'.$id),
            'phone' => PhoneNumber::rules('required', 'vendor_employees,phone,'.$id),
            'password' => StrongPassword::rules('nullable'),
        ], [
            'f_name.required' => translate('messages.First name is required'),
        ]);

        $e = VendorEmployee::where('store_id', Helpers::get_store_id())->findOrFail($id);
        // edit() already refuses an employee their own record; update() did not, so an employee could
        // post their own id and give themselves a different role.
        if (auth('vendor_employee')->id() == $e->id) {
            Toastr::warning(translate('messages.Access denied'));
            return back();
        }
        if ($request['password'] == null) {
            $pass = $e['password'];
        } else {
            if (strlen($request['password']) < 7) {
                Toastr::warning(translate('messages.Password is too short.') . ' ' . translate('messages.Minimum characters') . ': 8');
                return back();
            }
            $pass = bcrypt($request['password']);
            $e->remember_token=null;
            $e->login_remember_token=null;
        }

        if ($request->has('image')) {
            $e['image'] = Helpers::update('vendor/', $e->image, 'png', $request->file('image'));
        }
            $e->f_name = $request->f_name;
            $e->l_name = $request->l_name;
            $e->phone = $request->phone;
            $e->email = $request->email;
            $e->employee_role_id = $request->role_id;
            $e->vendor_id = Helpers::get_vendor_id();
            $e->store_id =Helpers::get_store_id();
            $e->password = $pass;
            $e->image = $e['image'];
            $e->updated_at = now();
            $e->is_logged_in = 0;
            $e->save();

        Toastr::success('Employee updated successfully!');
        return redirect()->route('vendor.employee.list');
    }

    public function distroy($id)
    {
        $role=VendorEmployee::where('store_id', Helpers::get_store_id())->where(['id'=>$id])->delete();
        Toastr::info(translate('Deleted successfully'));
        return back();
    }


    public function list_export(Request $request){

        $key = explode(' ', $request['search'] ?? '');
        $em=VendorEmployee::where('store_id', Helpers::get_store_id())->with(['role'])
        ->when($request['search'] , function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('f_name', 'like', "%{$value}%");
                    $q->orWhere('l_name', 'like', "%{$value}%");
                    $q->orWhere('phone', 'like', "%{$value}%");
                    $q->orWhere('email', 'like', "%{$value}%");
                }
            });
        })
        ->latest()->get();
        $data = [
            'employees'=>$em,
            'search'=>$request->search??null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new StoreEmployeeListExport($data), 'Employees.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new StoreEmployeeListExport($data), 'Employees.csv');
        }


    }
}
