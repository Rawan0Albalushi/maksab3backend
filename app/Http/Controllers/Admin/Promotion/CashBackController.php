<?php

namespace App\Http\Controllers\Admin\Promotion;

use App\Contracts\Repositories\CashBackRepositoryInterface;
use App\Contracts\Repositories\TranslationRepositoryInterface;
use App\Enums\ViewPaths\Admin\CashBack as CashBackViewPath;
use App\CentralLogics\Helpers;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Admin\CashBackAddRequest;
use App\Http\Requests\Admin\CashBackUpdateRequest;
use App\Services\Marketing\CashBackService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CashBackController extends BaseController
{
    public function __construct(
        protected CashBackRepositoryInterface $cashBackRepo,
        protected CashBackService $cashBackService,
        protected TranslationRepositoryInterface $translationRepo
    )
    {
    }

    public function index(?Request $request): View|Collection|LengthAwarePaginator|null
    {
        return $this->getAddView($request);
    }

    private function getAddView($request): View
    {
        $cashbacks = $this->cashBackRepo->getListWhere(
            searchValue: $request['search'],
            dataLimit: config('default_pagination')
        );
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $selected_customers = Helpers::customers_by_ids([$request['customer'] ?? null]);

        return view(CashBackViewPath::INDEX[VIEW], compact('cashbacks','language','defaultLang','selected_customers'));
    }

    public function add(CashBackAddRequest $request): RedirectResponse
    {
        $cashback = $this->cashBackRepo->add(data: $this->cashBackService->getAddData($request->all()));
        $this->translationRepo->addByModel(request: $request, model: $cashback, modelPath: 'App\Models\CashBack', attribute: 'title');

        Toastr::success(translate('Added successfully'));
        return back();
    }

    public function getUpdateView(string|int $id): view
    {
        $cashback = $this->cashBackRepo->getFirstWithoutGlobalScopeWhere(params: ['id' => $id]);
        $language = getWebConfig('language');
        $defaultLang = str_replace('_', '-', app()->getLocale());
        $selected_customers = Helpers::customers_by_ids(json_decode($cashback->customer_id));

        return view(CashBackViewPath::UPDATE[VIEW], compact('cashback','language','defaultLang','selected_customers'));
    }

    public function update(CashBackUpdateRequest $request, $id): RedirectResponse
    {
        $cashback = $this->cashBackRepo->update(id: $id ,data: $this->cashBackService->getUpdateData($request->all()));
        $this->translationRepo->updateByModel(request: $request, model: $cashback, modelPath: 'App\Models\CashBack', attribute: 'title');

        Toastr::success(translate('Updated successfully'));
        return redirect()->route('admin.users.cashback.add-new');
    }

    public function delete(Request $request): RedirectResponse
    {
        $this->cashBackRepo->delete(id: $request['id']);
        Toastr::success(translate('Deleted successfully'));
        return back();
    }


    public function updateStatus(Request $request): RedirectResponse
    {
        $this->cashBackRepo->update(id: $request['id'] ,data: ['status'=>$request['status']]);
        Toastr::success( $request['status'] == 1 ?  translate('messages.Cashback Successfully Enabled') : translate('Cashback Disabled') );
        return back();
    }
}
