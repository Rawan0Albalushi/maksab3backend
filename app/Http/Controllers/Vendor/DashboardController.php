<?php

namespace App\Http\Controllers\Vendor;

use App\Support\Settings\BusinessRules;
use Carbon\Carbon;
use App\Models\Item;
use App\Models\Order;
use App\Models\Vendor;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\OrderTransaction;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use Modules\Service\Entities\ServiceBooking;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $employee_landing = Helpers::employee_landing_url();
        if ($employee_landing) {
            return redirect($employee_landing);
        }
        if(Helpers::get_store_data()->module_type == 'rental'){
            return to_route('vendor.providerDashboard');

        }
        if(Helpers::get_store_data()->module_type == 'service' && service_addon_active()){
            return to_route('vendor.service.dashboard');
        }
        $params = [
            'statistics_type' => $request['statistics_type'] ?? 'overall'
        ];
        session()->put('dash_params', $params);

        $data = self::dashboard_order_stats_data();
        $earning = [];
        $commission = [];
        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');
        $store_earnings = OrderTransaction::NotRefunded()->where(['vendor_id' => Helpers::get_store_data()->vendor_id])->select(
            DB::raw('IFNULL(sum(store_amount),0) as earning'),
            DB::raw('IFNULL(sum(admin_commission + admin_expense - delivery_fee_comission),0) as commission'),
            DB::raw('YEAR(created_at) year, MONTH(created_at) month')
        )->whereBetween('created_at', [$from, $to])->groupby('year', 'month')->get()->toArray();
        for ($inc = 1; $inc <= 12; $inc++) {
            $earning[$inc] = 0;
            $commission[$inc] = 0;
            foreach ($store_earnings as $match) {
                if ($match['month'] == $inc) {
                    $earning[$inc] = $match['earning'];
                    $commission[$inc] = $match['commission'];
                }
            }
        }

        // Both lists used to ->get() Items, and Item carries the translate and storage
        // global scopes, so each list paid its own translations + storages eager load —
        // four statements, and on a small catalogue the two lists are the same rows, which
        // is why they showed up as exact duplicates. Resolve the ids first (pluck does not
        // hydrate models, so no eager load), then hydrate the union once and re-apply each
        // list's order from its own id list.
        $top_sell_ids = Item::orderBy("order_count", 'desc')
            ->take(6)
            ->pluck('id')
            ->all();
        $most_rated_ids = Item::where('avg_rating' ,'>' ,0)
        ->orderBy('avg_rating','desc')
        ->take(6)
        ->pluck('id')
        ->all();

        $dashboard_item_ids = array_values(array_unique(array_merge($top_sell_ids, $most_rated_ids)));
        $dashboard_items = $dashboard_item_ids
            ? Item::withStorage()->whereIn('id', $dashboard_item_ids)->get()->keyBy('id')
            : collect();

        $in_listed_order = fn (array $ids) => new EloquentCollection(
            array_values(array_filter(array_map(fn ($id) => $dashboard_items->get($id), $ids)))
        );

        $data['top_sell'] = $in_listed_order($top_sell_ids);
        $data['most_rated_items'] = $in_listed_order($most_rated_ids);

        if( Helpers::get_store_data()?->storeConfig?->show_low_stock_count && Helpers::get_store_data()?->storeConfig?->minimum_stock_for_warning > 0){
            $items=  Item::where('stock' ,'<=' , Helpers::get_store_data()->storeConfig->minimum_stock_for_warning );
        } else{
            $items=  Item::whereRaw('1 = 0');
        }

        $out_of_stock_count=  Helpers::get_store_data()->module->module_type != 'food' ?  $items->orderby('stock')->latest()->count() : null;

        $item = null;
        if($out_of_stock_count == 1 ){
            $item= $items->withStorage()->orderby('stock')->latest()->first();
        }

        $employee_first_name = auth('vendor_employee')->user()?->f_name;

        return view('vendor-views.dashboard', compact('data', 'earning', 'commission', 'params','out_of_stock_count','item','employee_first_name'));
    }

    public function store_data()
    {

        $store= Helpers::get_store_data();
        if($store->module_type == 'rental'){
            $type='trip';
            $new_pending_order=Trips::where(['checked' => 0])->where('provider_id', $store->id)->count();

        } elseif($store->module_type == 'service' && service_addon_active()){
            $type='service_booking';
            $new_pending_order=ServiceBooking::where(['notification_checked' => 0])->where('provider_id', $store->id)->count();

        } else{
            $new_pending_order = DB::table('orders')->where(['checked' => 0])->where('store_id', $store->id)->where('order_status','pending');
            if(! BusinessRules::storeConfirmsOrder() && !$store->sub_self_delivery)
            {
                $new_pending_order = $new_pending_order->where('order_type', 'take_away');
            }
            $new_pending_order = $new_pending_order->count();
            $new_confirmed_order = DB::table('orders')->where(['checked' => 0])->where('store_id', $store->id)->whereIn('order_status',['confirmed', 'accepted'])->whereNotNull('confirmed')->count();
            $type= 'store_order';
        }

        return response()->json([
            'success' => 1,
            'data' => ['new_pending_order' => $new_pending_order, 'new_confirmed_order' => $new_confirmed_order?? 0, 'order_type' =>$type]
        ]);
    }

    public function order_stats(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'statistics_type') {
                $params['statistics_type'] = $request['statistics_type'];
            }
        }
        session()->put('dash_params', $params);

        $data = self::dashboard_order_stats_data();
        return response()->json([
            'view' => view('vendor-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    public function dashboard_order_stats_data()
    {
        $params = session('dash_params');
        $today = $params['statistics_type'] == 'today' ? 1 : 0;
        $this_month = $params['statistics_type'] == 'this_month' ? 1 : 0;

        // These were eight separate COUNT(*) queries over one shared base. They are
        // collapsed into conditional aggregates below; the base filter and every
        // per-status condition are unchanged.
        $now = Carbon::now()->toDateTimeString();
        $window_end = Carbon::now()->addMinutes(30)->toDateTimeString();

        // Mirrors Order::scopeOrderScheduledIn(30).
        $scheduled_in = '((((created_at <> schedule_at) and (schedule_at between ? and ?)) or schedule_at < ?) or created_at = schedule_at)';

        // Mirrors Order::scopeScheduled().
        $is_scheduled = "(created_at <> schedule_at and scheduled = '1')";

        $store_confirms = BusinessRules::storeConfirmsOrder();
        $store_confirms_or_self_delivery = $store_confirms || Helpers::get_store_data()->sub_self_delivery;

        $active_for = function (bool $store_side) {
            return $store_side
                ? "(order_status not in ('failed','canceled','refund_requested','refunded'))"
                : "(order_status not in ('pending','failed','canceled','refund_requested','refunded') or (order_status = 'pending' and order_type = 'take_away'))";
        };

        $select = implode(', ', [
            "coalesce(sum(order_status in ('confirmed','accepted') and confirmed is not null and {$scheduled_in}), 0) as confirmed",
            "coalesce(sum(order_status = 'processing'), 0) as cooking",
            "coalesce(sum(order_status = 'handover'), 0) as ready_for_delivery",
            "coalesce(sum(order_status = 'picked_up'), 0) as item_on_the_way",
            "coalesce(sum(order_status = 'delivered'), 0) as delivered",
            "coalesce(sum(order_status = 'refunded'), 0) as refunded",
            "coalesce(sum({$is_scheduled} and {$active_for($store_confirms)}), 0) as scheduled",
            "coalesce(sum({$active_for($store_confirms_or_self_delivery)}), 0) as `all`",
        ]);

        $row = Order::when($today, function ($query) {
            return $query->whereDate('created_at', Carbon::today());
        })->when($this_month, function ($query) {
            return $query->whereMonth('created_at', Carbon::now());
        })->where(['store_id' => Helpers::get_store_id()])
            ->StoreOrder()->NotDigitalOrder()
            ->toBase()
            ->selectRaw($select, [$now, $window_end, $now])
            ->first();

        $confirmed = (int) ($row->confirmed ?? 0);
        $cooking = (int) ($row->cooking ?? 0);
        $ready_for_delivery = (int) ($row->ready_for_delivery ?? 0);
        $item_on_the_way = (int) ($row->item_on_the_way ?? 0);
        $delivered = (int) ($row->delivered ?? 0);
        $refunded = (int) ($row->refunded ?? 0);
        $scheduled = (int) ($row->scheduled ?? 0);
        $all = (int) ($row->all ?? 0);

        $data = [
            'confirmed' => $confirmed,
            'cooking' => $cooking,
            'ready_for_delivery' => $ready_for_delivery,
            'item_on_the_way' => $item_on_the_way,
            'delivered' => $delivered,
            'refunded' => $refunded,
            'scheduled' => $scheduled,
            'all' => $all,
        ];

        return $data;
    }

    public function updateDeviceToken(Request $request)
    {
        $vendor = Vendor::find(Helpers::get_vendor_id());
        $vendor->firebase_token =  $request->token;

        $vendor->save();

        return response()->json(['Token successfully stored.']);
    }

    public function verifiedBadgePopupSeen(Request $request)
    {
        $store = Helpers::get_store_data();
        Helpers::mark_verified_badge_popup_seen($store);

        return response()->json(['success' => 1]);
    }
}
