<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Campaign;
use App\Models\ItemCampaign;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Store;
use Brian2694\Toastr\Facades\Toastr;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;


class CampaignController extends Controller
{
    function list(Request $request)
    {
        $store = Helpers::get_store_data();

        $campaigns=Campaign::withStorage()
        ->with(['stores' => function ($q) use ($store) {
            $q->where('stores.id', $store->id)->without('storeConfig')->withoutGlobalScope('translate');
        }])
        ->withCount(['stores as joined_stores_count' => function ($q) {
            $q->where('campaign_store.campaign_status', 'confirmed');
        }])
        ->running()->latest()->module($store->module_id)
        ->when($request->filled('search'), function ($query) use ($request) {
            $key = explode(' ', $request['search']);
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('title', 'like', "%{$value}%")
                    ->orWhere('description', 'like', "%{$value}%")
                    ->orWhere('slug', 'like', "%{$value}%")
                    ->orWhereHas('translations', function ($t) use ($value) {
                        $t->where('value', 'like', "%{$value}%");
                    });

                }
            });
        })
        ->paginate(config('default_pagination'))->withQueryString();
        return view('vendor-views.campaign.list',compact('campaigns'));
    }

    function itemlist()
    {
        $campaigns=ItemCampaign::withStorage()->where('store_id', Helpers::get_store_id())->latest()->paginate(config('default_pagination'));
        return view('vendor-views.campaign.item_list',compact('campaigns'));
    }

    /**
     * A store joins or leaves a campaign for itself. The store id is in the URL, so any store could
     * be enrolled in -- or pulled out of -- any campaign, in any module.
     */
    private function authorizeOwnEnrolment(Campaign $campaign, mixed $storeId): void
    {
        abort_unless(
            (string) $storeId === (string) Helpers::get_store_id()
                && (int) $campaign->module_id === (int) Helpers::get_store_data()?->module_id,
            404
        );
    }

    public function remove_store(Campaign $campaign, $store)
    {
        $this->authorizeOwnEnrolment($campaign, $store);
        $campaign->stores()->detach($store);
        $campaign->save();
        Toastr::success(translate('messages.Store remove from campaign'));
        return back();
    }
    public function addstore(Campaign $campaign, $store_id)
    {
        $this->authorizeOwnEnrolment($campaign, $store_id);
        $campaign->stores()->attach($store_id,['campaign_status' => 'pending','updated_at' => now(),'created_at' => now()]);
        $campaign->save();
        $store = Store::with('vendor')->find($store_id);
        try
        {
            $admin= Admin::where('role_id', 1)->first();
            $mail_status = SendNotification::mailTemplateEnabled('campaign_request_mail_status_admin');
            if(config('mail.status') && $mail_status &&  SendNotification::channelEnabled('admin','campaign_join_request','mail_status' )) {
                SendNotification::mail($admin?->getRawOriginal('email'), new \App\Mail\CampaignRequestMail($store->name));
            }
            $mail_status = SendNotification::mailTemplateEnabled('campaign_request_mail_status_store');
            if(config('mail.status') && $mail_status &&  SendNotification::channelEnabled('store','store_campaign_join_request','mail_status',$store->id )) {
                SendNotification::mail($store->vendor?->getRawOriginal('email'), new \App\Mail\VendorCampaignRequestMail($store->name,'pending'));
            }
        }
        catch(\Exception $e)
        {
            Log::error('vendor.campaign_controller.addstore_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
        }
        Toastr::success(translate('messages.Store added to campaign'));
        return back();
    }



    public function searchItem(Request $request){
        $key = explode(' ', $request['search'] ?? '');
        $campaigns=ItemCampaign::where('store_id', Helpers::get_store_id())
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('title', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('vendor-views.campaign.partials._item_table',compact('campaigns'))->render(),
            'count'=>$campaigns->count(),
        ]);
    }

}
