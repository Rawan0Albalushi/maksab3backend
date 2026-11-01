<?php

namespace App\Http\Controllers\Admin\Coupon;

use App\Contracts\Repositories\CouponRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Contracts\Repositories\ZoneRepositoryInterface;
use App\Enums\ExportFileNames\Admin\Coupon;
use App\Enums\ViewPaths\Admin\Coupon as CouponViewPath;
use App\Exports\CouponExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CouponAddRequest;
use App\Http\Requests\Admin\CouponUpdateRequest;
use App\Models\Store;
use App\Models\User;
use App\CentralLogics\Helpers;
use App\Models\Zone;
use App\Services\Marketing\CouponService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CouponController extends BaseController
{
    public function __construct(
        protected CouponRepositoryInterface $couponRepo,
        protected CouponService $couponService,
        protected TranslationRepositoryInterface $translationRepo,
        protected ZoneRepositoryInterface $zoneRepo
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|RedirectResponse|null
    {
        if (Config::get('module.current_module_type') === 'parcel') {
            Toastr::error(translate('messages.Coupon is not available for parcel module'));
            return back();
        }

        return $this->getAddView($request);
    }

    private function getAddView($request): View
    {
        $coupons = $this->couponRepo->getListWhere(
            searchValue: $request['search'],
            filters: ['created_by'=>'admin','module_id'=>Config::get('module.current_module_id')],
            relations: ['module', 'store:id,name'],
            dataLimit: config('default_pagination'),
        );
        $customer = $request['customer'];
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $zones = $this->zoneRepo->getList();

        $customerIds = (array) old('customer_ids', []);
        if (! $customerIds && is_numeric($customer)) {
            $customerIds = [$customer];
        }
        $selected_customers = Helpers::customers_by_ids($customerIds);

        $storeIds = array_values(array_filter((array) old('store_ids', []), function ($id) {
            return is_numeric($id);
        }));
        $selected_stores = $storeIds
            ? Store::whereIn('id', $storeIds)->get(['id', 'name'])
            : collect();

        return view(CouponViewPath::INDEX[VIEW], compact('coupons','language','defaultLang','zones','customer','selected_customers','selected_stores'));
    }

    public function add(CouponAddRequest $request): RedirectResponse
    {
        $coupon = $this->couponRepo->add(data: $this->couponService->getAddData($request->all(),moduleId: Config::get('module.current_module_id')));
        $this->translationRepo->addByModel(request: $request, model: $coupon, modelPath: 'App\Models\Coupon', attribute: 'title');
        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function getUpdateView(string|int $id): View
    {
        $coupon = $this->couponRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $zones = $this->zoneRepo->getList();
        $selected_customers = Helpers::customers_by_ids(json_decode($coupon->customer_id));

        return view(CouponViewPath::UPDATE[VIEW], compact('coupon','language','defaultLang','zones','selected_customers'));
    }

    public function update(CouponUpdateRequest $request, $id): RedirectResponse
    {
        $coupon = $this->couponRepo->update(id: $id ,data: $this->couponService->getAddData($request->all(), moduleId: Config::get('module.current_module_id')));
        $this->translationRepo->updateByModel(request: $request, model: $coupon, modelPath: 'App\Models\Coupon', attribute: 'title');
        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function updateStatus(Request $request): RedirectResponse
    {
        $coupon = $this->couponRepo->getFirstWhere(params: ['id' => $request['id']]);
        if ($request['status'] == 1 && Carbon::parse($coupon->expire_date)->startOfDay() < Carbon::today()) {
            Toastr::warning(translate('messages.This coupon is expired and cannot be activated'));
            return back();
        }
        $this->couponRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);
        Toastr::success(translate('messages.Coupon status updated'));
        return back();
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->couponRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }

    public function exportList(Request $request): BinaryFileResponse
    {
        $coupons = $this->couponRepo->getExportList($request);
        $data=[
            'data' =>$coupons,
            'search' =>$request['search'] ?? null
        ];
        if($request['type'] == 'csv'){
            return Excel::download(new CouponExport($data), Coupon::EXPORT_CSV);
        }
        return Excel::download(new CouponExport($data), Coupon::EXPORT_XLSX);
    }
    public function viewCoupon($id){
        $coupon = $this->couponRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id],relations:['store:id,name']);
        $selectedCustomers=json_decode($coupon->customer_id);
        if(in_array("all", $selectedCustomers)){
            $selectedCustomers='all';
        } else {
            $selectedCustomers=  User::withoutGlobalScopes()->whereIn('id', $selectedCustomers)->select('id','f_name','l_name')->get();
        }
        $zoneData = [];
        if($coupon->coupon_type=='zone_wise'){
           $zoneData = Zone::whereIn('id', json_decode($coupon->data))->select('id','name')->get();
        }
          return response()->json([
            'view' => view('admin-views.coupon._view', compact('coupon','selectedCustomers','zoneData'))->render(),
        ]);
    }

    public function generateCheckCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $code = $this->couponService->getUniqueCouponCode(title: $request['title']);
        return response()->json($code);
    }
}
