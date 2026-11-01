<?php

namespace App\Http\Controllers\Admin\Erp;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\ErpApiToken;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ErpIntegrationController extends Controller
{
    public function index()
    {
        if (! Helpers::module_permission_check('settings')) {
            Toastr::error(translate('Access denied'));

            return back();
        }

        $tokens = ErpApiToken::latest()->get();

        return view('admin-views.business-settings.erp-integration', compact('tokens'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'webhook_url' => 'required|url|max:500',
        ]);

        $apiKey = Str::random(48);
        $apiSecret = Str::random(64);

        ErpApiToken::create([
            'name' => $request->name,
            'api_key' => $apiKey,
            'api_secret' => hash('sha256', $apiSecret),
            'webhook_url' => $request->webhook_url,
        ]);

        Toastr::success(translate('messages.token_generated_successfully'));

        return back()->with('new_credentials', [
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ]);
    }

    public function updateWebhook(Request $request, ErpApiToken $token)
    {
        $this->validateWebhookUrl($request);

        $token->update(['webhook_url' => $request->webhook_url ?: null]);

        Toastr::success(translate('messages.webhook_url_updated'));

        return back();
    }

    protected function validateWebhookUrl(Request $request): void
    {
        $request->validate([
            'webhook_url' => 'nullable|url|max:500',
        ]);
    }

    public function revoke(ErpApiToken $token)
    {
        $token->update(['is_active' => false]);

        Toastr::success(translate('messages.token_revoked_successfully'));

        return back();
    }

    public function destroy(ErpApiToken $token)
    {
        $token->delete();

        Toastr::success(translate('Deleted successfully'));

        return back();
    }
}
