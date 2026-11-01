<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\System\AddonActivationService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class AddonActivationController extends Controller
{
    public function __construct(
        private readonly AddonActivationService $addonActivationService,
    )
    {
    }

    public function index(): View
    {
        return view('admin-views.addon-activation.index', [
            'domain' => $this->addonActivationService->getCurrentDomain(),
        ]);
    }

    public function activation(Request $request): Redirector|RedirectResponse|Application
    {
        $data = $this->addonActivationService->activate($request->all());
        if ($data['status']) {
            Helpers::businessUpdateOrInsert(['key' => $request['key']], [
                'value' => json_encode([
                    'activation_status' => $request['status'] ?? 0,
                    'username' => $request['username'],
                    'purchase_key' => $request['purchase_key'],
                ])
            ]);
            Toastr::success(translate('Activated successfully'));
        } else {
            Toastr::error($data['message']);
        }
        return back();
    }
}
