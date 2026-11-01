<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\Translation;
use App\Navigation\VendorRolePermissionForm;
use Illuminate\Validation\Rule;

class CustomRoleController extends Controller
{
    private function roleFormData(): array
    {
        $language = Helpers::get_business_settings('language', false) ?? null;
        $languages = $language ? (array) json_decode($language) : [];

        $languageLabels = [];
        foreach ($languages as $lang) {
            $languageLabels[$lang] = Helpers::get_language_name($lang).'('.strtoupper($lang).')';
        }

        return [
            'language' => $language,
            'languages' => $languages,
            'languageLabels' => $languageLabels,
            'permissionGroups' => VendorRolePermissionForm::forCurrentStore()->groups(),
        ];
    }

    private const MERGED_PERMISSIONS = [
        'customer_engagement' => ['reviews', 'chat'],
        'business_section' => ['store_setup', 'notification_setup', 'my_shop', 'business_plan'],
        'report_section' => ['expense_report', 'store_earning_report', 'disbursement_report', 'vat_report'],
        'wallet_management' => ['wallet', 'wallet_method'],
        'deliveryman_management' => ['deliveryman', 'deliveryman_list'],
        'advertisement_management' => ['advertisement', 'advertisement_list'],
        'employee' => ['role'],
    ];

    private function expandModules(array $modules): array
    {
        foreach (self::MERGED_PERMISSIONS as $merged => $covered) {
            if (in_array($merged, $modules, true)) {
                $modules = array_merge($modules, $covered);
            }
        }

        return array_values(array_unique($modules));
    }

    private function normalizeSelected(array $modules): array
    {
        foreach (self::MERGED_PERMISSIONS as $merged => $covered) {
            if (! in_array($merged, $modules, true) && array_intersect($covered, $modules)) {
                $modules[] = $merged;
            }
        }

        return $modules;
    }

    public function index(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $rl=EmployeeRole::where('store_id',Helpers::get_store_id())->withCount('employees')->orderBy('name')
            ->when( $request['search'] , function($query) use($key){
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                 });
                }
            )
            ->paginate(config('default_pagination'));

        $form = VendorRolePermissionForm::forCurrentStore();
        $permissionLabels = $form->labels();
        $permissionTotal = $form->total();

        return view('vendor-views.custom-role.index', compact('rl', 'permissionLabels', 'permissionTotal'));
    }

    public function create()
    {
        return view('vendor-views.custom-role.create', $this->roleFormData());
    }

    public function store(Request $request)
    {
        $request->validate([
            'modules'=>'required|array|min:1',
            'name' => [
                'required',Rule::unique('employee_roles')->where(function($query) {
                  $query->where('store_id', Helpers::get_store_id());
              })
            ],
            'name.0' => 'required',
        ],[
            'name.required'=>translate('messages.Role name is required'),
            'modules.required'=>translate('messages.Please select at least one module'),
            'name.0.required'=>translate('Default name is required'),
        ]);
        $role = new EmployeeRole();
        $role->name=$request->name[array_search('default', $request->lang)];
        $role->modules=json_encode($this->expandModules((array) $request['modules']));
        $role->status=1;
        $role->store_id=Helpers::get_store_id();
        $role->save();

        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->name[$index])){
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\EmployeeRole',
                        'translationable_id' => $role->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $role->name,
                    ));
                }
            }else{
                if ($request->name[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\EmployeeRole',
                        'translationable_id' => $role->id,
                        'locale' => $key,
                        'key' => 'name',
                        'value' => $request->name[$index],
                    ));
                }
            }
        }

        Translation::insert($data);


        Toastr::success(translate('Added successfully'));
        return redirect()->route('vendor.custom-role.index');
    }

    public function edit($id)
    {
        $role=EmployeeRole::withoutGlobalScope('translate')->with('translations')->where('store_id',Helpers::get_store_id())->where(['id'=>$id])->firstOrFail(['id','name','modules']);
        $selectedModules = $this->normalizeSelected((array) json_decode($role['modules']));

        return view('vendor-views.custom-role.edit', array_merge(compact('role', 'selectedModules'), $this->roleFormData()));
    }

    public function view($id)
    {
        $role=EmployeeRole::withoutGlobalScope('translate')->with('translations')->where('store_id',Helpers::get_store_id())->where(['id'=>$id])->firstOrFail(['id','name','modules']);
        $form = VendorRolePermissionForm::forCurrentStore();
        $permissionGroups = $form->groups();
        $permissionLabels = $form->labels();
        $legacyPermissions = array_values(array_unique(array_merge(...array_values(self::MERGED_PERMISSIONS))));

        return response()->json([
            'view' => view('vendor-views.custom-role.partials._view_role', compact('role', 'permissionGroups', 'permissionLabels', 'legacyPermissions'))->render(),
        ]);
    }

    public function update(Request $request,$id)
    {
        $request->validate([
            'modules'=>'required|array|min:1',
            'name' => [
                'required',Rule::unique('employee_roles')->where(function($query)use($id) {
                  $query->where('store_id', Helpers::get_store_id())->where('id','<>', $id);
              })
            ],
            'name.0' => 'required',
        ],[
            'name.required'=>translate('messages.Role name is required'),
            'name.unique'=>translate('messages.Role name already taken!'),
            'modules.required'=>translate('messages.Please select at least one module'),
            'name.0.required'=>translate('Default name is required'),
        ]);

        $role = EmployeeRole::where('store_id',Helpers::get_store_id())->where(['id'=>$id])->first();
        $role->name = $request->name[array_search('default', $request->lang)];
        $role->modules = json_encode($this->expandModules((array) $request['modules']));
        $role->status = 1;
        $role->store_id = Helpers::get_store_id();
        $role->save();

        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if($default_lang == $key && !($request->name[$index])){
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\EmployeeRole',
                            'translationable_id' => $role->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $role->name]
                    );
                }
            }else{

                if ($request->name[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\EmployeeRole',
                            'translationable_id' => $role->id,
                            'locale' => $key,
                            'key' => 'name'
                        ],
                        ['value' => $request->name[$index]]
                    );
                }
            }
        }


        Toastr::success(translate('Updated successfully'));
        return redirect()->route('vendor.custom-role.index');
    }

    public function distroy($id)
    {
        $role=EmployeeRole::where('store_id',Helpers::get_store_id())->where(['id'=>$id])->first();

        if ($role?->employees()->exists()) {
            Toastr::error(translate('Move its employees to another role before deleting this one.'));
            return back();
        }

        $role?->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }
}
