<?php

namespace App\Http\Controllers\Admin;

use App\Support\Settings\BusinessRules;
use Carbon\Carbon;
use App\Models\Item;
use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\Review;
use App\Models\Wishlist;
use App\Scopes\ZoneScope;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Modules\RideShare\Entities\UserManagement\Rider;

class DashboardController extends Controller
{

    private function buildParams(Request $request): array
    {
        return [
            'zone_id' => $request['zone_id'] ?? 'all',
            'module_id' => Config::get('module.current_module_id'),
            'statistics_type' => $request['statistics_type'] ?? 'overall',
            'user_overview' => $request['user_overview'] ?? 'overall',
            'commission_overview' => $request['commission_overview'] ?? 'this_year',
            'business_overview' => $request['business_overview'] ?? 'overall',
        ];
    }

    private function updateDashParam(string $key, $value): array
    {
        $params = session('dash_params');
        $params[$key] = $value;
        session()->put('dash_params', $params);
        return $params;
    }

    private function statNewDateCase(string $column, $module_id): array
    {
        $params = session('dash_params');
        $type = ($module_id && in_array($params['statistics_type'], ['today', 'this_year', 'this_month', 'this_week']))
            ? $params['statistics_type'] : 'overall';

        return match ($type) {
            'today' => ["DATE($column) = ?", [Carbon::now()->format('Y-m-d')]],
            'this_year' => ["YEAR($column) = ?", [now()->format('Y')]],
            'this_month' => ["MONTH($column) = ? AND YEAR($column) = ?", [now()->format('m'), now()->format('Y')]],
            'this_week' => ["$column BETWEEN ? AND ?", [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]],
            default => ["DATE($column) >= ?", [now()->subDays(30)->format('Y-m-d')]],
        };
    }

    private function statDatePredicate(string $type, string $column): array
    {
        return match ($type) {
            'today' => ["DATE(`$column`) = ?", [Carbon::now()->format('Y-m-d')]],
            'this_year' => ["YEAR(`$column`) = ?", [now()->format('Y')]],
            'this_month' => ["MONTH(`$column`) = ? AND YEAR(`$column`) = ?", [now()->format('m'), now()->format('Y')]],
            'this_week' => ["`$column` BETWEEN ? AND ?", [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]],
            default => [null, []],
        };
    }

    public function user_dashboard(Request $request)
    {
        $params = $this->buildParams($request);

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $customers = User::withStorage()->zone($params['zone_id'])->take(2)->get();

        // Only the avatars are rendered, so last_location was loaded and never read. `id` has
        // to be selected for the storage relation behind image_full_url to match its rows —
        // without it that lookup ran against id 0 and could never resolve a disk.
        $delivery_man = DeliveryMan::withStorage()->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->limit(2)->get(['id', 'image']);

        $last30 = now()->subDays(30)->format('Y-m-d');
        // toBase() on the aggregates below: they return computed columns, not rows, so
        // hydrating a model only made the storage global scope fetch storages for id 0.
        $dmStats = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->toBase()
            ->selectRaw("
                SUM(CASE WHEN active = 1 AND application_status = 'approved' THEN 1 ELSE 0 END) as active_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND active = 0 THEN 1 ELSE 0 END) as inactive_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND status = 0 THEN 1 ELSE 0 END) as blocked_deliveryman,
                SUM(CASE WHEN application_status = 'approved' AND DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined_deliveryman
            ", [$last30])
            ->first();

        $active_deliveryman = (int) $dmStats->active_deliveryman;
        $inactive_deliveryman = (int) $dmStats->inactive_deliveryman;
        $blocked_deliveryman = (int) $dmStats->blocked_deliveryman;
        $newly_joined_deliveryman = (int) $dmStats->newly_joined_deliveryman;

        $reviewStats = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN rating IN (4, 5) THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as good,
            SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as negative
        ")->first();

        $reviews = (int) $reviewStats->total;
        $positive_reviews = (int) $reviewStats->positive;
        $good_reviews = (int) $reviewStats->good;
        $neutral_reviews = (int) $reviewStats->neutral;
        $negative_reviews = (int) $reviewStats->negative;

        $number = 12;

        $users = User::zone($params['zone_id'])
            ->toBase()
            ->select(
                DB::raw('(count(id)) as total'),
                DB::raw('YEAR(created_at) year, MONTH(created_at) month')
            )
            ->whereBetween('created_at', [Carbon::parse(now())->startOfYear(), Carbon::parse(now())->endOfYear()])
            ->groupBy('year', 'month')->get()
            ->map(fn ($row) => (array) $row)->all();

        for ($inc = 1; $inc <= $number; $inc++) {
            $user_data[$inc] = 0;
            foreach ($users as $match) {
                if ($match['month'] == $inc) {
                    $user_data[$inc] = $match['total'];
                }
            }
        }

        $customerStats = User::zone($params['zone_id'])->toBase()->selectRaw("
            SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_customers,
            SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as blocked_customers,
            SUM(CASE WHEN DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined,
            SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_month,
            SUM(CASE WHEN MONTH(created_at) = 12 AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as last_year_users
        ", [$last30, now()->format('m'), now()->format('Y'), now()->format('Y') - 1])->first();

        $active_customers = (int) $customerStats->active_customers;
        $blocked_customers = (int) $customerStats->blocked_customers;
        $newly_joined = (int) $customerStats->newly_joined;
        $this_month = (int) $customerStats->this_month;
        $last_year_users = (int) $customerStats->last_year_users;

        $employees = Admin::withStorage()->zone()->with(['role'])->where('role_id', '!=', '1')
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->get();

        $deliveryMen = DeliveryMan::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $module_type = Config::get('module.current_module_type');

        if(addon_published_status('RideShare')) {
            $rider_data = self::get_rider_data($params);
        } else {
            $rider_data = [];
        }

        return view("admin-views.dashboard-{$module_type}", compact('data', 'reviews', 'this_month', 'user_data', 'neutral_reviews', 'good_reviews', 'negative_reviews', 'positive_reviews', 'employees', 'active_deliveryman', 'deliveryMen', 'inactive_deliveryman', 'newly_joined_deliveryman', 'delivery_man', 'total_sell', 'commission', 'delivery_commission', 'params', 'module_type', 'customers', 'active_customers', 'blocked_customers', 'newly_joined', 'last_year_users', 'blocked_deliveryman', 'rider_data'));
    }

    private function get_rider_data($params) {

        $data['rider_images'] = Rider::withStorage()->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->limit(2)
            ->get('image');

        $riderStats = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->selectRaw("
                SUM(CASE WHEN active = 1 AND application_status = 'approved' THEN 1 ELSE 0 END) as active_rider,
                SUM(CASE WHEN application_status = 'approved' AND active = 0 THEN 1 ELSE 0 END) as inactive_rider,
                SUM(CASE WHEN application_status = 'approved' AND status = 0 THEN 1 ELSE 0 END) as blocked_rider,
                SUM(CASE WHEN application_status = 'approved' AND DATE(created_at) >= ? THEN 1 ELSE 0 END) as newly_joined_rider
            ", [now()->subDays(30)->format('Y-m-d')])
            ->first();

        $data['active_rider'] = (int) $riderStats->active_rider;
        $data['inactive_rider'] = (int) $riderStats->inactive_rider;
        $data['blocked_rider'] = (int) $riderStats->blocked_rider;
        $data['newly_joined_rider'] = (int) $riderStats->newly_joined_rider;

        $data['top_riders'] = Rider::withStorage()->withCount('driverTrips')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("driver_trips_count", '>', 0)
            ->orderBy("driver_trips_count", 'desc')
            ->take(6)
            ->get();

        $riders = Rider::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->available()
            ->active()
            ->get();

        $data['map_riders'] = Helpers::deliverymen_list_formatting($riders);

        $data['total_riders'] = $data['active_rider'] + $data['inactive_rider'] + $data['blocked_rider'];

        return $data;

    }

    public function transaction_dashboard(Request $request)
    {
        $module_type = Config::get('module.current_module_type');
        return view("admin-views.dashboard-{$module_type}");
    }

    public function dispatch_dashboard(Request $request)
    {
        $params = $this->buildParams($request);

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);

        $maxOrder = BusinessRules::dmMaximumOrders();

        $deliveryman_stats = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->Zonewise()
            ->selectRaw("
                COUNT(*) as total,

                SUM(CASE
                    WHEN active = 1
                    THEN 1 ELSE 0 END
                ) as active_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND active = 0
                    THEN 1 ELSE 0 END
                ) as inactive_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND status = 0
                    THEN 1 ELSE 0 END
                ) as suspend_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders > {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as unavailable_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders < {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as available_deliveryman
            ")
            ->first();

        $active_deliveryman      = $deliveryman_stats->active_deliveryman;
        $inactive_deliveryman    = $deliveryman_stats->inactive_deliveryman;
        $suspend_deliveryman     = $deliveryman_stats->suspend_deliveryman;
        $unavailable_deliveryman = $deliveryman_stats->unavailable_deliveryman;
        $available_deliveryman   = $deliveryman_stats->available_deliveryman;


        $deliveryMen = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $module_type = Config::get('module.current_module_type');
        return view("admin-views.dashboard-{$module_type}", compact('data', 'active_deliveryman', 'deliveryMen', 'unavailable_deliveryman', 'available_deliveryman', 'inactive_deliveryman', 'module_type', 'suspend_deliveryman'));
    }

    public function dashboard(Request $request)
    {
        $admin = auth('admin')->user();
        if ($admin && $admin->role_id != 1 && !Helpers::module_permission_check('dashboard')) {
            $landing = Helpers::admin_landing_url();
            if ($landing) {
                return redirect($landing);
            }
        }

        $module_type = Config::get('module.current_module_type');
        $redirect = match ($module_type) {
            'settings' => redirect()->route('admin.business-settings.business-setup'),
            'ride-share' => addon_published_status('RideShare') == 1
                ? redirect()->route('admin.ride-share.dashboard')
                : view('errors.404'),
            'rental' => addon_published_status('Rental') == 1
                ? redirect()->route('admin.rental.dashboard')
                : view('errors.404'),
            'service' => addon_published_status('Service') == 1
                ? redirect()->route('admin.service.dashboard')
                : view('errors.404'),
            default => null,
        };
        if ($redirect) {
            return $redirect;
        }

        $params = $this->buildParams($request);
        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $label = $data['label'];

        return view("admin-views.dashboard-{$module_type}", compact('data', 'total_sell', 'commission', 'delivery_commission', 'label', 'params', 'module_type'));

    }

    public function order(Request $request)
    {
        $params = $this->updateDashParam('statistics_type', $request['statistics_type']);

        if ($params['zone_id'] != 'all') {
            $store_ids = Store::where(['module_id' => $params['module_id']])->where(['zone_id' => $params['zone_id']])->pluck('id')->toArray();
        } else {
            $store_ids = Store::where(['module_id' => $params['module_id']])->pluck('id')->toArray();
        }
        $data = self::order_stats_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render()
            ], 200);
        } elseif ($module_type == 'food') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render()
            ], 200);
        }
        return response()->json([
            'view' => view('admin-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    public function zone(Request $request)
    {
        $params = $this->updateDashParam('zone_id', $request['zone_id']);

        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $popular = $data['popular'];
        $top_deliveryman = $data['top_deliveryman'];
        $top_rated_foods = $data['top_rated_foods'];
        $top_restaurants = $data['top_restaurants'];
        $top_customers = $data['top_customers'];
        $top_sell = $data['top_sell'];
        $delivery_commission = $data['delivery_commission'];
        $module_type = Config::get('module.current_module_type');
        $label = $data['label'];

        return response()->json([
            'popular_restaurants' => view('admin-views.partials._popular-restaurants', compact('popular'))->render(),
            'top_deliveryman' => view('admin-views.partials._top-deliveryman', compact('top_deliveryman'))->render(),
            'top_rated_foods' => view('admin-views.partials._top-rated-foods', compact('top_rated_foods'))->render(),
            'top_restaurants' => view('admin-views.partials._top-restaurants', compact('top_restaurants'))->render(),
            'top_customers' => view('admin-views.partials._top-customer', compact('top_customers'))->render(),
            'top_selling_foods' => view('admin-views.partials._top-selling-foods', compact('top_sell'))->render(),


            'user_overview' => view('admin-views.partials._user-overview-chart', compact('data'))->render(),
            'monthly_graph' => view('admin-views.partials._monthly-earning-graph', compact('total_sell', 'commission', 'delivery_commission', 'label'))->render(),
            'stat_zone' => view('admin-views.partials._zone-change', compact('data'))->render(),
            'order_stats' => $module_type == 'parcel' ? view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render() :
                ($module_type == 'food' ? view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render() :
                    view('admin-views.partials._dashboard-order-stats', compact('data'))->render()),
        ], 200);
    }

    public function user_overview(Request $request)
    {
        $params = $this->updateDashParam('user_overview', $request['user_overview']);

        $data = self::user_overview_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._user-overview-chart-parcel', compact('data'))->render()
            ], 200);
        }

        return response()->json([
            'view' => view('admin-views.partials._user-overview-chart', compact('data'))->render()
        ], 200);
    }

    public function commission_overview(Request $request)
    {
        $params = $this->updateDashParam('commission_overview', $request['commission_overview']);

        $data = self::commission_chart_calc();

        return response()->json([
            'view' => view('admin-views.partials._commission-overview-chart', compact('data'))->render(),
            'gross_sale' => view('admin-views.partials._gross_sale', compact('data'))->render()
        ], 200);
    }

    public function order_stats_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        $module_type = Config::get('module.current_module_type');

        $statistics_type = ($module_id && in_array($params['statistics_type'], ['today', 'this_year', 'this_month', 'this_week']))
            ? $params['statistics_type'] : 'overall';

        $isParcel = $module_id && $module_type == 'parcel';
        $zoneOnOrders = is_numeric($zone_id) && $module_id;
        $typeSql = $isParcel ? "`order_type` = 'parcel'" : "(`order_type` = 'take_away' or `order_type` = 'delivery')";

        $scheduledInSql = '((created_at <> schedule_at and (`schedule_at` between ? and ?) or `schedule_at` < ?) or created_at = schedule_at)';
        $scheduledInBind = [now()->toDateTimeString(), now()->addMinutes(30)->toDateTimeString(), now()->toDateTimeString()];
        $searchingSql = "`delivery_man_id` is null and `order_type` in ('delivery', 'parcel') and `order_status` not in ('delivered', 'failed', 'canceled', 'refund_requested', 'refund_request_canceled', 'refunded')";
        $failedSql = "`order_status` = 'failed' and not exists (select * from `offline_payments` where `orders`.`id` = `offline_payments`.`order_id`)";

        $dated = $statistics_type !== 'overall';

        $metrics = [
            'searching_for_dm' => [$searchingSql . ' and ' . $scheduledInSql, $scheduledInBind, 'created_at', true],
            'accepted_by_dm' => ["`order_status` = 'accepted'", [], 'accepted', true],
            'preparing_in_rs' => ["`order_status` in ('confirmed', 'processing', 'handover')", [], 'processing', true],
            'picked_up' => ["`order_status` = 'picked_up'", [], 'picked_up', true],
            'delivered' => ["`order_status` = 'delivered'", [], 'delivered', true],
            'canceled' => ["`order_status` = 'canceled'", [], 'canceled', true],
            'refund_requested' => [$dated ? "`order_status` = 'refund_requested'" : $failedSql, [], 'refund_requested', true],
            'refunded' => ["`order_status` = 'refunded'", [], 'refunded', true],
            'new_orders' => [null, [], 'schedule_at', (bool) $module_id],
            'total_orders' => [null, [], ($statistics_type == 'today' && $module_type == 'parcel') ? 'created_at' : null, (bool) $module_id],
        ];

        $select = [];
        $bindings = [];

        foreach ($metrics as $alias => [$statusSql, $statusBind, $dateColumn, $withType]) {
            $conditions = [];
            $binds = [];

            if ($statusSql !== null) {
                $conditions[] = $statusSql;
                $binds = array_merge($binds, $statusBind);
            }

            if ($withType) {
                $conditions[] = $typeSql;
            }

            if ($dateColumn !== null) {
                [$dateSql, $dateBind] = $alias == 'new_orders' && ! $dated
                    ? ['DATE(`schedule_at`) >= ?', [now()->subDays(30)->format('Y-m-d')]]
                    : $this->statDatePredicate($statistics_type, $dateColumn);

                if ($dateSql !== null) {
                    $conditions[] = $dateSql;
                    $binds = array_merge($binds, $dateBind);
                }
            }

            $select[] = empty($conditions)
                ? "count(*) as {$alias}"
                : 'sum(case when ' . implode(' and ', $conditions) . " then 1 else 0 end) as {$alias}";

            $bindings = array_merge($bindings, $binds);
        }

        $orderRow = Order::query()
            ->when($module_id, fn($q) => $q->where('module_id', $module_id))
            ->when($zoneOnOrders, fn($q) => $q->where('zone_id', $zone_id))
            ->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->first();

        $searching_for_dm = (int) ($orderRow?->searching_for_dm ?? 0);
        $accepted_by_dm = (int) ($orderRow?->accepted_by_dm ?? 0);
        $preparing_in_rs = (int) ($orderRow?->preparing_in_rs ?? 0);
        $picked_up = (int) ($orderRow?->picked_up ?? 0);
        $delivered = (int) ($orderRow?->delivered ?? 0);
        $canceled = (int) ($orderRow?->canceled ?? 0);
        $refund_requested = (int) ($orderRow?->refund_requested ?? 0);
        $refunded = (int) ($orderRow?->refunded ?? 0);
        $new_orders = (int) ($orderRow?->new_orders ?? 0);
        $total_orders = (int) ($orderRow?->total_orders ?? 0);

        [$newCase, $newBind] = $this->statNewDateCase('created_at', $module_id);
        $zoneOnStores = is_numeric($zone_id) && $module_id;
        $zoneOnCustomers = is_numeric($zone_id) && $module_id && $isParcel;

        $itemRow = Item::where('is_approved', 1)
            ->when($module_id, fn($q) => $q->where('module_id', $module_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_items = (int) $itemRow->total;
        $new_items = (int) $itemRow->new;

        $storeRow = Store::whereHas('vendor', fn($q) => $q->where('status', 1))
            ->when($module_id, fn($q) => $q->where('module_id', $module_id))
            ->when($zoneOnStores, fn($q) => $q->where('zone_id', $zone_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_stores = (int) $storeRow->total;
        $new_stores = (int) $storeRow->new;

        $customerRow = User::when($zoneOnCustomers, fn($q) => $q->where('zone_id', $zone_id))
            ->toBase()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN {$newCase} THEN 1 ELSE 0 END) as new", $newBind)
            ->first();
        $total_customers = (int) $customerRow->total;
        $new_customers = (int) $customerRow->new;
        $data = [
            'searching_for_dm' => $searching_for_dm,
            'accepted_by_dm' => $accepted_by_dm,
            'preparing_in_rs' => $preparing_in_rs,
            'picked_up' => $picked_up,
            'delivered' => $delivered,
            'canceled' => $canceled,
            'refund_requested' => $refund_requested,
            'refunded' => $refunded,
            'total_orders' => $total_orders,
            'total_items' => $total_items,
            'total_stores' => $total_stores,
            'total_customers' => $total_customers,
            'new_orders' => $new_orders,
            'new_items' => $new_items,
            'new_stores' => $new_stores,
            'new_customers' => $new_customers,
        ];

        return $data;
    }

    public function user_overview_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        if (is_numeric($zone_id)) {
            $customer = User::where('zone_id', $zone_id);
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->where(['zone_id' => $zone_id]);
            $delivery_man = DeliveryMan::where('application_status', 'approved')->where('zone_id', $zone_id)->Zonewise();
        } else {
            $customer = User::whereNotNull('id');
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereNotNull('id');
            $delivery_man = DeliveryMan::where('application_status', 'approved')->Zonewise();
        }
        $applyOverview = match ($params['user_overview']) {
            'overall' => fn($q) => $q,
            // Ranges, not whereMonth/whereYear -- see the note on the yearly chart below.
            'this_month' => fn($q) => $q->whereBetween('created_at', [
                now()->startOfMonth()->format('Y-m-d H:i:s'), now()->endOfMonth()->format('Y-m-d H:i:s'),
            ]),
            'this_year' => fn($q) => $q->whereBetween('created_at', [
                now()->startOfYear()->format('Y-m-d H:i:s'), now()->endOfYear()->format('Y-m-d H:i:s'),
            ]),
            default => fn($q) => $q->whereDate('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]),
        };

        $data = [
            'customer' => $applyOverview($customer)->count(),
            'stores' => $applyOverview($stores)->count(),
            'delivery_man' => $applyOverview($delivery_man)->count(),
        ];
        return $data;
    }


    public function dashboard_data($request)
    {
        $params = session('dash_params');
        if (!url()->current() == $request->is('admin/users')) {
            $data_os = self::order_stats_calc($params['zone_id'], $params['module_id']);
            if(Route::currentRouteName() == 'admin.dispatch.dashboard'){
                return $data_os;
            }
            $data_uo = self::user_overview_calc($params['zone_id'], $params['module_id']);
        }

        // Ranked separately from $top_restaurants below (wishlist count vs. order count are
        // different metrics), but the two rankings' top-6 lists overlap heavily in practice --
        // popular stores tend to rank high on both. Each used to hydrate its own Store models
        // (with the storage/translations/store_configs Store always eager-loads), so the shared
        // stores got fetched twice. Only the ranking queries run here; the actual Store rows are
        // hydrated once, together, after $top_restaurants below.
        $wishlistCounts = Wishlist::whereHas('store')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->select('store_id', DB::raw('COUNT(store_id) as count'))->groupBy('store_id')
            ->having("count", '>', 0)
            ->orderBy('count', 'DESC')
            ->limit(6)->get();
        $top_sell = Item::withoutGlobalScope(ZoneScope::class)
            ->select('id', 'name', 'image', 'order_count')
            ->with('storage')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id'])->where('zone_id', $params['zone_id']);
                });
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->get();
        $top_rated_foods = Item::withoutGlobalScope(ZoneScope::class)
            ->select('id', 'name', 'image', 'rating_count')
            ->with('storage')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->having("rating_count", '>', 0)
            ->orderBy('rating_count', 'desc')
            ->orderBy('id')
            ->take(6)
            ->get();

        $top_deliveryman = DeliveryMan::select('id', 'f_name', 'phone', 'image')->with('storage')->withCount('orders')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->having("orders_count", '>', 0)
            ->orderBy("orders_count", 'desc')
            ->take(6)
            ->get();

        // Ranked from the orders side: withCount + having + orderByDesc ran a correlated
        // COUNT per user and sorted all of them to return six rows (12.9s, 2.2M rows examined
        // on 200k users), because HAVING on the counted alias cannot terminate early.
        // Grouping orders is served by orders_status_guest_user_index.
        // is_guest = 0 mirrors the User::orders() relation.
        $topCustomerOrderCounts = DB::table('orders')
            ->selectRaw('user_id, COUNT(*) AS order_count')
            ->where('order_status', 'delivered')
            ->where('is_guest', 0)
            ->whereNotNull('user_id')
            ->when(is_numeric($params['zone_id']), function ($query) use ($params) {
                // Users in the zone, not orders in it: orders.zone_id is the store's zone.
                return $query->whereIn('user_id', User::select('id')->where('zone_id', $params['zone_id']));
            })
            ->groupBy('user_id')
            ->orderByDesc('order_count')
            ->limit(6)
            ->pluck('order_count', 'user_id');

        $top_customers = User::select('id', 'f_name', 'phone', 'image')->with('storage')
            ->whereIn('id', $topCustomerOrderCounts->keys())
            ->get()
            ->each(function ($customer) use ($topCustomerOrderCounts) {
                $customer->order_count = (int) ($topCustomerOrderCounts[$customer->id] ?? 0);
            })
            ->sortByDesc('order_count')
            ->values();

        $topOrderStoreIds = Store::select('id', 'order_count')->whereHas('vendor', fn($query) => $query->where('status', 1))->when(is_numeric($params['module_id']), function ($q) use ($params) {
            return $q->where('module_id', $params['module_id']);
        })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->pluck('id');

        // One shared fetch for whichever stores either ranking needs -- id/name/logo/order_count
        // plus Store's own always-on storage/translations/store_configs eager loads, once, instead
        // of once per ranking.
        $rankedStoreIds = $wishlistCounts->pluck('store_id')->merge($topOrderStoreIds)->unique()->values();
        $rankedStores = Store::select('id', 'name', 'logo', 'order_count')->with('storage')
            ->whereIn('id', $rankedStoreIds)->get()->keyBy('id');

        $popular = $wishlistCounts
            ->map(fn($row) => $row->setRelation('store', $rankedStores->get($row->store_id)))
            ->filter(fn($row) => $row->store)
            ->values();

        $top_restaurants = $topOrderStoreIds
            ->map(fn($id) => $rankedStores->get($id))
            ->filter()
            ->values();


        if (!url()->current() == $request->is('admin/users')) {
            $dash_data = array_merge($data_os, $data_uo);
        }

        $dash_data['popular'] = $popular;
        $dash_data['top_sell'] = $top_sell;
        $dash_data['top_rated_foods'] = $top_rated_foods;
        $dash_data['top_deliveryman'] = $top_deliveryman;
        $dash_data['top_restaurants'] = $top_restaurants;
        $dash_data['top_customers'] = $top_customers;

        return array_merge($dash_data, self::commission_chart_calc());
    }

    public function commission_chart_calc(): array
    {
        $params = session('dash_params');
        $months = array(
            '"' . 'Jan' . '"',
            '"' . 'Feb' . '"',
            '"' . 'Mar' . '"',
            '"' . 'Apr' . '"',
            '"' . 'May' . '"',
            '"' . 'Jun' . '"',
            '"' . 'Jul' . '"',
            '"' . 'Aug' . '"',
            '"' . 'Sep' . '"',
            '"' . 'Oct' . '"',
            '"' . 'Nov' . '"',
            '"' . 'Dec' . '"'
        );
        $days = array(
            '"' . 'Mon' . '"',
            '"' . 'Tue' . '"',
            '"' . 'Wed' . '"',
            '"' . 'Thu' . '"',
            '"' . 'Fri' . '"',
            '"' . 'Sat' . '"',
            '"' . 'Sun' . '"',
        );
        $total_sell = [];
        $commission = [];
        $delivery_commission = [];
        $label = [];
        $currentYear = now()->format('Y');
        $commissionSelect = [
            DB::raw('SUM(order_amount) as total_sell'),
            DB::raw("SUM(admin_commission + admin_expense - delivery_fee_comission + COALESCE((SELECT CASE WHEN orders.delivery_type = 'express' THEN orders.delivery_type_charge ELSE 0 END FROM orders WHERE orders.id = order_transactions.order_id), 0)) as commission"),
            DB::raw('SUM(delivery_fee_comission) as delivery_commission'),
        ];
        $applyFilters = function ($q) use ($params) {
            return $q->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->where('module_id', $params['module_id']);
            })
                ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                    return $q->where('zone_id', $params['zone_id']);
                });
        };
        switch ($params['commission_overview']) {
            case "this_week":
                $weekStartDate = now()->startOfWeek();
                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereBetween('created_at', [$weekStartDate->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')])
                    ->select(array_merge([DB::raw('DATE(created_at) as period')], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 0; $i < 7; $i++) {
                    $row = $rows->get($weekStartDate->copy()->addDays($i)->format('Y-m-d'));
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }

                $label = $days;
                break;

            case "this_month":
                $start = now()->startOfMonth();
                $total_days = now()->daysInMonth;
                $weeks = array(
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . $total_days . '"',
                );

                $ranges = [];
                for ($i = 1; $i <= 4; $i++) {
                    $end = $start->copy()->addDays(6);
                    if ($i == 4) {
                        $end = now()->endOfMonth();
                    }
                    $ranges[$i] = ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"];
                    $start = $end->copy()->addDay();
                }

                $caseSql = 'CASE';
                foreach ($ranges as $idx => $range) {
                    $caseSql .= " WHEN created_at BETWEEN '{$range[0]}' AND '{$range[1]}' THEN {$idx}";
                }
                $caseSql .= ' END';

                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereBetween('created_at', [$ranges[1][0], $ranges[4][1]])
                    ->select(array_merge([DB::raw("{$caseSql} as period")], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 1; $i <= 4; $i++) {
                    $row = $rows->get($i);
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }

                $label = $weeks;
                break;

            case "this_year":
            default:
                // A range, not whereYear(): year(created_at) = ? cannot use the index.
                // Grouping by MONTH() is fine -- only the WHERE decides index usability.
                $rows = $applyFilters(OrderTransaction::NotRefunded())
                    ->whereBetween('created_at', [
                        $currentYear.'-01-01 00:00:00',
                        $currentYear.'-12-31 23:59:59',
                    ])
                    ->select(array_merge([DB::raw('MONTH(created_at) as period')], $commissionSelect))
                    ->groupBy('period')
                    ->get()->keyBy('period');

                for ($i = 1; $i <= 12; $i++) {
                    $row = $rows->get($i);
                    $total_sell[$i] = $row?->total_sell ?? 0;
                    $commission[$i] = $row?->commission ?? 0;
                    $delivery_commission[$i] = $row?->delivery_commission ?? 0;
                }
                $label = $months;
        }

        return [
            'total_sell' => $total_sell,
            'commission' => $commission,
            'delivery_commission' => $delivery_commission,
            'label' => $label,
        ];
    }
}
