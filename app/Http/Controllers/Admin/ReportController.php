<?php

namespace App\Http\Controllers\Admin;

use App\Exports\DisbursementReportExport;
use App\Models\DeliveryMan;
use App\Models\DisbursementDetails;
use App\Models\WithdrawalMethod;
use Carbon\Carbon;
use App\Models\Item;
use App\Models\User;
use App\Models\Zone;
use App\Models\Order;
use App\Models\Store;
use App\Models\Expense;
use App\Models\Category;
use App\Scopes\StoreScope;
use App\Traits\Report\ExportRowStreamTrait;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Services\System\BusinessSettingService;
use App\Models\BusinessSetting;
use App\Models\OrderTransaction;
use App\Exports\ExpenseReportExport;
use App\Exports\ItemReportExport;
use App\Exports\LimitedStockReportExport;
use App\Exports\OrderReportExport;
use App\Exports\ParcelReportExport;
use App\Exports\StoreOrderReportExport;
use App\Exports\StoreSalesReportExport;
use App\Exports\StoreSummaryReportExport;
use App\Exports\TransactionReportExport;
use App\Exports\ParcelTransactionReportExport;
use App\Exports\ParcelExpenseReportExport;
use App\Exports\RentalExpenseReportExport;
use App\Exports\ServiceExpenseReportExport;
use App\Exports\RideshareExpenseReportExport;
use App\Exports\OtherExpenseReportExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\View;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Config;
use App\Services\Order\OrderTransactionService;
class ReportController extends Controller
{
    use ExportRowStreamTrait;

    public function day_wise_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $from =  null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if($filter == 'custom'){
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        $module_id = request('module_id');
        $search = $request['search'] ?? null;

        $order_transactions = $this->dayWiseTransactionQuery(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to)
            ->with(['order' => fn ($query) => $query->withSum('details as item_discount_total', DB::raw('discount_on_item * quantity')), 'order.customer', 'order.store', 'delivery_man'])
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        // All three earnings figures from one pass. Each used to be its own aggregate over the
        // same filter, and that filter carries an EXISTS against orders which matches nearly
        // every row -- so each cost a full join across ~2M transactions. Conditional SUMs
        // carry the differing extra predicates (notRefunded() for two, a delivery man for the
        // third) inside the single scan.
        $notRefunded = "(order_transactions.status NOT IN ('refunded_with_delivery_charge','refunded_without_delivery_charge')
                         OR order_transactions.status IS NULL)";
        $netIncome = app(OrderTransactionService::class)->adminNetIncomeSql('order_transactions', 'o');

        $earnings = $this->dayWiseTransactionQuery(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to)
            ->join('orders as o', 'o.id', '=', 'order_transactions.order_id')
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN {$notRefunded} THEN ({$netIncome}) ELSE 0 END), 0) AS admin_earned,
                COALESCE(SUM(CASE WHEN {$notRefunded} THEN order_transactions.store_amount - order_transactions.tax ELSE 0 END), 0) AS store_earned,
                COALESCE(SUM(CASE WHEN order_transactions.delivery_man_id IS NOT NULL
                                  THEN order_transactions.original_delivery_charge + order_transactions.dm_tips ELSE 0 END), 0) AS deliveryman_earned
            ")
            ->first();

        $admin_earned = (float) ($earnings->admin_earned ?? 0);
        $store_earned = (float) ($earnings->store_earned ?? 0);
        $deliveryman_earned = (float) ($earnings->deliveryman_earned ?? 0);

        [$total, $delivered, $canceled] = $this->dayWiseOrderStats(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to);

        return view('admin-views.report.day-wise-report', compact('order_transactions', 'zone', 'store', 'filter', 'admin_earned', 'store_earned', 'deliveryman_earned', 'key', 'from', 'to', 'total', 'delivered', 'canceled'));
    }

    /**
     * Every column here is table-qualified on purpose. The earnings aggregate below reuses this
     * builder with `join('orders as o', ...)`, and `zone_id`, `module_id` and `created_at` all
     * exist on BOTH order_transactions and orders -- unqualified, MySQL rejects the query with
     * "Column ... is ambiguous" and the whole report 500s the moment a zone, module or date
     * filter is applied.
     */
    private function dayWiseTransactionQuery($zone, $store, $search, $key, $moduleId, $filter, $from, $to)
    {
        return OrderTransaction::whereHas('order', function ($q) {
                $q->where('order_type', '!=', 'parcel');
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->where('order_transactions.zone_id', $zone->id);
            })
            ->when($search, function ($query) use ($key) {
                return $query->search(keywords: $key, mainCol: 'order_id', orderByRelevance: false);
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->whereHas('order', function ($q) use ($store) {
                    $q->where('store_id', $store->id);
                });
            })
            ->when($moduleId, function ($query) use ($moduleId) {
                return $query->where('order_transactions.module_id', $moduleId);
            })
            ->applyDateFilter($filter, $from, $to, 'order_transactions.created_at');
    }

    private function dayWiseOrderStats($zone, $store, $search, $key, $moduleId, $filter, $from, $to): array
    {
        $baseStats = function () use ($zone, $store, $search, $key, $moduleId, $filter, $from, $to) {
            return Order::where('order_type', '!=', 'parcel')
                ->when(isset($zone), function ($query) use ($zone) {
                    return $query->where('zone_id', $zone->id);
                })
                ->when($search, function ($query) use ($key) {
                    return $query->search(keywords: $key, mainCol: 'id', orderByRelevance: false);
                })
                ->when($moduleId, function ($query) use ($moduleId) {
                    return $query->module($moduleId);
                })
                ->when(isset($store), function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                })
                ->applyDateFilter($filter, $from, $to)
                ->Notpos();
        };

        $total = $baseStats()->count();
        if ($total == 0) {
            $total = 0.01;
        }

        $delivered = $baseStats()
            ->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
            ->sum('order_amount');

        $canceled = $baseStats()
            ->where('order_status', 'refunded')
            ->sum(DB::raw('order_amount - delivery_charge - dm_tips'));

        return [$total, $delivered, $canceled];
    }

    public function day_wise_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

         $from =  null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if($filter == 'custom'){
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        $module_id = request('module_id');
        $search = $request['search'] ?? null;

        $transactionQuery = $this->dayWiseTransactionQuery(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to)
            ->with(['order' => fn ($query) => $query->withSum('details as item_discount_total', DB::raw('discount_on_item * quantity')), 'order.customer', 'order.store', 'delivery_man'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc');

        $order_transactions_count = (clone $transactionQuery)->count();
        $order_transactions = $this->streamExportRows($transactionQuery);

        // One pass for all three figures -- see the note in day_wise_report(), which this
        // export mirrors.
        $notRefunded = "(order_transactions.status NOT IN ('refunded_with_delivery_charge','refunded_without_delivery_charge')
                         OR order_transactions.status IS NULL)";
        $netIncome = app(OrderTransactionService::class)->adminNetIncomeSql('order_transactions', 'o');

        $earnings = $this->dayWiseTransactionQuery(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to)
            ->join('orders as o', 'o.id', '=', 'order_transactions.order_id')
            ->toBase()
            ->selectRaw("
                COALESCE(SUM(CASE WHEN {$notRefunded} THEN ({$netIncome}) ELSE 0 END), 0) AS admin_earned,
                COALESCE(SUM(CASE WHEN {$notRefunded} THEN order_transactions.store_amount - order_transactions.tax ELSE 0 END), 0) AS store_earned,
                COALESCE(SUM(CASE WHEN order_transactions.delivery_man_id IS NOT NULL
                                  THEN order_transactions.original_delivery_charge + order_transactions.dm_tips ELSE 0 END), 0) AS deliveryman_earned
            ")
            ->first();

        $admin_earned = (float) ($earnings->admin_earned ?? 0);
        $store_earned = (float) ($earnings->store_earned ?? 0);
        $deliveryman_earned = (float) ($earnings->deliveryman_earned ?? 0);

        [, $delivered, $canceled] = $this->dayWiseOrderStats(zone: $zone, store: $store, search: $search, key: $key, moduleId: $module_id, filter: $filter, from: $from, to: $to);

            $data = [
                'order_transactions'=>$order_transactions,
                'order_transactions_count'=>$order_transactions_count,
                'search'=>$request->search??null,
                'from'=>(($filter == 'custom') && $from)?$from:null,
                'to'=>(($filter == 'custom') && $to)?$to:null,
                'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
                'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
                'module'=>request('module_id')?Helpers::get_module_name(request('module_id')):null,
                'admin_earned'=>$admin_earned,
                'store_earned'=>$store_earned,
                'deliveryman_earned'=>$deliveryman_earned,
                'delivered'=>$delivered,
                'canceled'=>$canceled,
                'filter'=>$filter,
            ];

        if ($request->type == 'excel') {
            return Excel::download(new TransactionReportExport($data), 'TransactionReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new TransactionReportExport($data), 'TransactionReport.csv');
        }
    }

    public function item_wise_report(Request $request)
    {

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $category = is_numeric($category_id) ? Category::findOrFail($category_id) : null;
        $items = $this->get_item_data($request);
        $items =  $items->paginate(config('default_pagination'))->withQueryString();
        return view('admin-views.report.item-wise-report', compact('zone', 'store', 'category', 'items', 'filter'));
    }
    public function item_wise_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        // Streamed rather than ->get(): hydrating every matching row with its relations at
        // once exhausted the memory limit outright on an unfiltered export. forPage() re-sorts
        // on every page, so the ordering below carries a unique tiebreaker -- without one a
        // tied row can land on both sides of a page boundary, duplicating it and dropping
        // another. The count is taken once because count() on the LazyCollection would re-run
        // every chunk query.
        $itemQuery = $this->get_item_data($request)->orderBy('items.id');
        $items_count = (clone $itemQuery)->count();

        $data = [
            'items'=>$this->streamExportRows($itemQuery),
            'items_count'=>$items_count,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
            'category'=>is_numeric($category_id)?Helpers::get_category_name($category_id):null,
            'module'=>request('module_id')?Helpers::get_module_name(request('module_id')):null,
            'filter'=>$filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new ItemReportExport($data), 'ItemReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new ItemReportExport($data), 'ItemReport.csv');
        }
    }


    private static function get_item_data($request){

        $key = explode(' ', $request['search'] ?? '');
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $category = is_numeric($category_id) ? Category::findOrFail($category_id) : null;

        // Aggregated on order_details and joined back to items, rather than joining the three
        // tables and grouping the result by items.id. That shape made MySQL build one group
        // per item across a 1M x 4M join, apply HAVING, and sort every group -- to return 25
        // rows. It exceeded the gateway timeout outright.
        //
        // The inner join replaces having('orders_count', '>', 0): only items that appear in a
        // qualifying order survive it, which is the same set.
        [$rangeStart, $rangeEnd] = self::itemReportRange($filter, $from, $to);

        $orderAggregates = DB::table('order_details')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_details.order_id')
                    ->whereIn('orders.order_status', ['delivered', 'refund_requested', 'refund_request_canceled']);
            })
            ->when($rangeStart && $rangeEnd, fn ($q) => $q->whereBetween('order_details.created_at', [$rangeStart, $rangeEnd]))
            ->selectRaw('
                order_details.item_id,
                COUNT(DISTINCT orders.id) as orders_count,
                SUM(order_details.quantity) as orders_sum_quantity,
                SUM(order_details.price * order_details.quantity) as orders_sum_price,
                -- A BOGO free item carries no discount_on_item: it is priced at zero rather
                -- than discounted, so its give-away is added from the column that records
                -- what one free unit was worth. Null on every other line, hence the IFNULL.
                SUM(order_details.discount_on_item * order_details.quantity)
                    + SUM(IFNULL(order_details.bogo_free_value, 0) * order_details.quantity) as total_discount
            ')
            ->groupBy('order_details.item_id');

        $items = Item::withoutGlobalScope(StoreScope::class)->withStorage()
        ->joinSub($orderAggregates, 'item_orders', function ($join) {
            $join->on('item_orders.item_id', '=', 'items.id');
        })
        ->select([
            'items.*',
            'item_orders.orders_count',
            'item_orders.orders_sum_quantity',
            'item_orders.orders_sum_price',
            'item_orders.total_discount',
        ])
        ->when($request->query('module_id', null), fn($q) => $q->where('items.module_id', $request->query('module_id')))
        // A subquery, not $zone->stores->pluck('id'): that hydrated every store in the zone
        // and inlined its ids, which at this store count is tens of thousands of bind values.
        ->when(isset($zone), fn($q) => $q->whereIn('items.store_id',
            Store::withoutGlobalScopes()->where('zone_id', $zone->id)->select('id')))
        ->when(isset($store), fn($q) => $q->where('items.store_id', $store->id))
        ->when(isset($category), fn($q) => $q->where('items.category_id', $category->id))
        ->when($request['search'], fn($q) => $q->where(function ($q2) use ($key) {
            foreach ($key as $value) {
                $q2->orWhere('items.name', 'like', "%{$value}%");
            }
        }))
        ->with('module', 'store')
        ->orderByDesc('orders_count');

        return $items;
    }

    /**
     * The order_details.created_at window an item-report filter means. Explicit bounds rather
     * than whereYear()/whereMonth(), which wrap the column in a function and cannot be indexed.
     */
    private static function itemReportRange($filter, $from = null, $to = null): array
    {
        [$start, $end] = match (true) {
            $filter == 'custom' && $from && $to => [$from.' 00:00:00', $to.' 23:59:59'],
            $filter == 'this_year' => [now()->startOfYear(), now()->endOfYear()],
            $filter == 'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            $filter == 'previous_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            $filter == 'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            default => [null, null],
        };

        return [
            $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start,
            $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end,
        ];
    }

    private static function chartSums($query, $bucketExpr, $start, $end)
    {
        return $query->whereBetween('schedule_at', [
                $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start,
                $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end,
            ])
            ->selectRaw("{$bucketExpr} as chart_bucket, SUM(order_amount) as chart_total")
            ->groupBy('chart_bucket')
            ->pluck('chart_total', 'chart_bucket');
    }

    public function parcel_transaction_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $from = null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if ($filter == 'custom') {
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $base = function () use ($request, $zone, $key, $module_id, $parcelModuleIds, $from, $to, $filter) {
            return OrderTransaction::whereHas('order', function ($q) {
                    $q->where('order_type', 'parcel');
                })
                ->when(! empty($parcelModuleIds), function ($q) use ($module_id, $parcelModuleIds) {
                    if ($module_id) {
                        $q->where('module_id', $module_id);
                    } else {
                        $q->whereIn('module_id', $parcelModuleIds);
                    }
                })
                ->when(isset($zone), function ($q) use ($zone) {
                    return $q->where('zone_id', $zone->id);
                })
                ->when($request['search'], function ($q) use ($key) {
                    return $q->where(function ($qq) use ($key) {
                        foreach ($key as $value) {
                            $qq->orWhere('order_id', 'like', "%{$value}%");
                        }
                    });
                })
                ->applyDateFilter($filter, $from, $to, 'created_at');
        };

        $order_transactions = $base()
            ->with('order', 'order.details', 'order.customer', 'order.orderProDiscount', 'delivery_man')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $admin_earned = (clone $base())->notRefunded()->sum(DB::raw('admin_commission'));

        $admin_earned_delivery_commission = (clone $base())
            ->sum(DB::raw('case when delivery_man_id is null then original_delivery_charge else delivery_fee_comission end'));

        $deliveryman_earned = (clone $base())
            ->whereNotNull('delivery_man_id')
            ->sum(DB::raw('original_delivery_charge + dm_tips'));

        $appliedModuleIds = $module_id ? [(int) $module_id] : $parcelModuleIds;
        [$total, $delivered, $canceled] = $this->parcelOrderStats(zone: $zone, appliedModuleIds: $appliedModuleIds, search: $request['search'] ?? null, key: $key, filter: $filter, from: $from, to: $to);

        return view('admin-views.report.parcel-transaction-report', compact(
            'order_transactions', 'zone', 'filter', 'admin_earned',
            'admin_earned_delivery_commission', 'deliveryman_earned', 'key', 'from', 'to', 'module_id',
            'total', 'delivered', 'canceled'
        ));
    }

    private function parcelOrderStats($zone, $appliedModuleIds, $search, $key, $filter, $from, $to): array
    {
        $baseStats = function () use ($zone, $appliedModuleIds, $search, $key, $filter, $from, $to) {
            return Order::where('order_type', 'parcel')
                ->when(! empty($appliedModuleIds), function ($query) use ($appliedModuleIds) {
                    return $query->whereIn('module_id', $appliedModuleIds);
                })
                ->when(isset($zone), function ($query) use ($zone) {
                    return $query->where('zone_id', $zone->id);
                })
                ->when($search, function ($query) use ($key) {
                    return $query->search(keywords: $key, mainCol: 'id', orderByRelevance: false);
                })
                ->applyDateFilter($filter, $from, $to)
                ->Notpos();
        };

        $total = $baseStats()->count();
        if ($total == 0) {
            $total = 0.01;
        }

        $delivered = $baseStats()
            ->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
            ->sum('order_amount');

        $canceled = $baseStats()
            ->where('order_status', 'refunded')
            ->sum(DB::raw('order_amount - delivery_charge - dm_tips'));

        return [$total, $delivered, $canceled];
    }

    public function parcel_transaction_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $from = null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if ($filter == 'custom') {
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $base = function () use ($request, $zone, $key, $module_id, $parcelModuleIds, $from, $to, $filter) {
            return OrderTransaction::whereHas('order', function ($q) {
                    $q->where('order_type', 'parcel');
                })
                ->when(! empty($parcelModuleIds), function ($q) use ($module_id, $parcelModuleIds) {
                    if ($module_id) {
                        $q->where('module_id', $module_id);
                    } else {
                        $q->whereIn('module_id', $parcelModuleIds);
                    }
                })
                ->when(isset($zone), function ($q) use ($zone) {
                    return $q->where('zone_id', $zone->id);
                })
                ->when($request['search'], function ($q) use ($key) {
                    return $q->where(function ($qq) use ($key) {
                        foreach ($key as $value) {
                            $qq->orWhere('order_id', 'like', "%{$value}%");
                        }
                    });
                })
                ->applyDateFilter($filter, $from, $to, 'created_at');
        };

        $parcelTransactionQuery = $base()
            ->with('order', 'order.details', 'order.customer', 'order.orderProDiscount', 'delivery_man')
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'asc');

        $order_transactions_count = (clone $parcelTransactionQuery)->count();
        $order_transactions = $this->streamExportRows($parcelTransactionQuery);

        $admin_earned = (clone $base())->notRefunded()->sum(DB::raw('admin_commission'));
        $admin_earned_delivery_commission = (clone $base())
            ->sum(DB::raw('case when delivery_man_id is null then original_delivery_charge else delivery_fee_comission end'));
        $deliveryman_earned = (clone $base())
            ->whereNotNull('delivery_man_id')
            ->sum(DB::raw('original_delivery_charge + dm_tips'));

        $parcelOrderBase = function () use ($request, $zone, $key, $module_id, $parcelModuleIds, $from, $to, $filter) {
            return Order::where('order_type', 'parcel')
                ->when(! empty($parcelModuleIds), function ($q) use ($module_id, $parcelModuleIds) {
                    if ($module_id) {
                        $q->where('module_id', $module_id);
                    } else {
                        $q->whereIn('module_id', $parcelModuleIds);
                    }
                })
                ->when(isset($zone), function ($q) use ($zone) {
                    return $q->where('zone_id', $zone->id);
                })
                ->when($request['search'], function ($q) use ($key) {
                    return $q->where(function ($qq) use ($key) {
                        foreach ($key as $value) {
                            $qq->orWhere('id', 'like', "%{$value}%");
                        }
                    });
                })
                ->applyDateFilter($filter, $from, $to, 'created_at')
                ->Notpos();
        };

        $delivered = (clone $parcelOrderBase())
            ->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled'])
            ->sum('order_amount');
        $canceled = (clone $parcelOrderBase())
            ->where('order_status', 'refunded')
            ->sum(DB::raw('order_amount - delivery_charge - dm_tips'));

        $data = [
            'order_transactions' => $order_transactions,
            'order_transactions_count' => $order_transactions_count,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'module' => $module_id ? Helpers::get_module_name($module_id) : null,
            'admin_earned' => $admin_earned + $admin_earned_delivery_commission,
            'deliveryman_earned' => $deliveryman_earned,
            'delivered' => $delivered,
            'canceled' => $canceled,
            'filter' => $filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new ParcelTransactionReportExport($data), 'ParcelTransactionReport.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new ParcelTransactionReportExport($data), 'ParcelTransactionReport.csv');
        }

        return redirect()->route('admin.transactions.report.parcel-transaction-report');
    }


    public function set_date(Request $request)
    {
        session()->put('from_date', date('Y-m-d', strtotime($request['from'])));
        session()->put('to_date', date('Y-m-d', strtotime($request['to'])));
        return back();
    }

    public function item_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $category_id = $request->query('category_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $category = is_numeric($category_id) ? Category::findOrFail($category_id) : null;
        $items = \App\Models\Item::withoutGlobalScope(StoreScope::class)
        ->withCount([
            'orders' => function ($query) use ($from, $to, $filter) {
                $query->applyDateFilter($filter, $from, $to, 'created_at')
                    ->when(isset($filter) && $filter == 'all_time', function ($query) {
                        return $query;
                    })
                    ->whereHas('order', function ($query) {
                        return $query->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled']);
                    });
            },
        ])
        ->withSum([
            'orders' => function ($query) use ($from, $to, $filter) {
                $query->applyDateFilter($filter, $from, $to, 'created_at')
                    ->when(isset($filter) && $filter == 'all_time', function ($query) {
                        return $query;
                    })
                    ->whereHas('order', function ($query) {
                        return $query->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled']);
                    });
            },
        ], 'discount_on_item')
        ->withSum([
            'orders' => function ($query) use ($from, $to, $filter) {
                $query->applyDateFilter($filter, $from, $to, 'created_at')
                    ->when(isset($filter) && $filter == 'all_time', function ($query) {
                        return $query;
                    })
                    ->whereHas('order', function ($query) {
                        return $query->whereIn('order_status', ['delivered', 'refund_requested', 'refund_request_canceled']);
                    });
            },
        ], 'price')
        ->when($request->query('module_id', null), function ($query) use ($request) {
            return $query->module($request->query('module_id'));
        })
        ->when(isset($zone), function ($query) use ($zone) {
            return $query->whereIn('store_id', $zone->stores->pluck('id'));
        })
        ->when(isset($store), function ($query) use ($store) {
            return $query->where('store_id', $store->id);
        })
        ->when(isset($category), function ($query) use ($category) {
            return $query->where('category_id', $category->id);
        })
        ->with('module', 'store')
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })
        ->limit(25)->get();

        return response()->json([
            'count' => count($items),
            'view' => view('admin-views.report.partials._item_table', compact('items'))->render()
        ]);
    }


    public function store_summary_report(Request $request)
    {
        $months = array(
            '"Jan"',
            '"Feb"',
            '"Mar"',
            '"Apr"',
            '"May"',
            '"Jun"',
            '"Jul"',
            '"Aug"',
            '"Sep"',
            '"Oct"',
            '"Nov"',
            '"Dec"'
        );
        $days = array(
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );

        $key = explode(' ', $request['search'] ?? '');

        $filter = $request->query('filter', 'all_time');

        // Aggregated once on the orders side and left-joined in. This was five withCount()
        // closures plus a withSum(), which is six correlated subqueries evaluated for every
        // one of the 110k stores before sorting on the alias -- and sorting on a counted alias
        // cannot terminate early, so no page could be produced without doing all of it. The
        // page exceeded the gateway timeout.
        //
        // Alias names are unchanged, so the view needs no edit. Left join keeps stores with no
        // orders in the report, coalesced to 0, exactly as the counts did.
        [$rangeStart, $rangeEnd] = self::itemReportRange($filter);

        $orderAggregates = Order::StoreOrder()
            ->when($rangeStart && $rangeEnd, fn ($q) => $q->whereBetween('schedule_at', [$rangeStart, $rangeEnd]))
            ->toBase()
            ->selectRaw("
                store_id,
                COUNT(*) as total_orders,
                COALESCE(SUM(order_status = 'delivered'), 0) as delivered,
                COALESCE(SUM(order_status = 'canceled'), 0) as canceled,
                COALESCE(SUM(order_status = 'refunded'), 0) as refunded,
                COALESCE(SUM(refund_requested IS NOT NULL), 0) as refund_requested,
                COALESCE(SUM(CASE WHEN order_status = 'delivered' THEN order_amount ELSE 0 END), 0) as delivered_amount
            ")
            ->groupBy('store_id');

        $stores = Store::leftJoinSub($orderAggregates, 'order_aggregates', function ($join) {
            $join->on('order_aggregates.store_id', '=', 'stores.id');
        })
        ->when($request['search'], function ($query) use ($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            });
        })
        ->select([
            'stores.*',
            DB::raw('COALESCE(order_aggregates.total_orders, 0) AS total_orders'),
            DB::raw('COALESCE(order_aggregates.delivered, 0) AS delivered'),
            DB::raw('COALESCE(order_aggregates.canceled, 0) AS canceled'),
            DB::raw('COALESCE(order_aggregates.refunded, 0) AS refunded'),
            DB::raw('COALESCE(order_aggregates.refund_requested, 0) AS refund_requested'),
            DB::raw('COALESCE(order_aggregates.delivered_amount, 0) AS delivered_amount'),
        ])
        ->orderByDesc('total_orders')
        ->paginate(config('default_pagination'));


        $new_stores = Store::applyDateFilter($filter, null, null, 'created_at')->count();

        $order_payment_methods = Order::applyDateFilter($filter, null, null, 'schedule_at')
            ->StoreOrder()->Delivered()->NotRefunded()
            ->selectRaw(DB::raw("sum(`order_amount`) as total_order_amount, count(*) as order_count, IF((`payment_method`='cash_on_delivery'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))->groupBy('payment_methods')
            ->get();


        $result = Order::applyDateFilter($filter, null, null, 'schedule_at')
        ->StoreOrder()
        ->selectRaw("
            COUNT(*) as total_orders,

            SUM(CASE
                WHEN order_status = 'delivered'
                THEN order_amount
                ELSE 0
            END) as total_order_amount,

            SUM(CASE
                WHEN order_status IN ('pending','accepted','confirmed','processing','handover','picked_up')
                THEN 1 ELSE 0
            END) as total_ongoing,

            SUM(CASE
                WHEN order_status IN ('failed','canceled')
                THEN 1 ELSE 0
            END) as total_canceled,

            SUM(CASE
                WHEN order_status = 'delivered'
                THEN 1 ELSE 0
            END) as total_delivered
        ")
        ->first();

        $total_order_amount = $result->total_order_amount;
        $total_ongoing      = $result->total_ongoing;
        $total_canceled     = $result->total_canceled;
        $total_delivered    = $result->total_delivered;
        $total_orders       = $result->total_orders;

        $items = Item::applyDateFilter($filter, null, null, 'created_at')->count();

        $monthly_order = [];
        switch ($filter) {
            case "all_time":
                $monthly_order = Order::select(
                    DB::raw("(sum(order_amount)) as order_amount"),
                    DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                )
                    ->StoreOrder()->Delivered()->NotRefunded()
                    ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                    ->get()->makeHidden('module_type')->toArray();

                $label = array_map(function ($order) {
                    return $order['year'];
                }, $monthly_order);
                $data = array_map(function ($order) {
                    return $order['order_amount'];
                }, $monthly_order);
                break;
            case "this_year":
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded(), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                }
                $label = $months;
                $data = $monthly_order;
                break;
            case "previous_year":
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded(), 'MONTH(schedule_at)', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                }
                $label = $months;
                $data = $monthly_order;
                break;
            case "this_week":
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded(), 'DATE(schedule_at)', now()->startOfWeek(), now()->endOfWeek());
                $weekStartDate = now()->startOfWeek();
                for ($i = 1; $i <= 7; $i++) {
                    $monthly_order[$i] = (float) ($sums[$weekStartDate->format('Y-m-d')] ?? 0);
                    $weekStartDate = $weekStartDate->addDays(1);
                }
                $label = $days;
                $data = $monthly_order;
                break;
            case "this_month":
                $start = now()->startOfMonth();
                $end = now()->startOfMonth()->addDays(6);
                $total_day = now()->daysInMonth;
                $remaining_days = now()->daysInMonth - 28;
                $weeks = array(
                    '"'.translate('Day').' 1-7"',
                    '"'.translate('Day').' 8-14"',
                    '"'.translate('Day').' 15-21"',
                    '"'.translate('Day').' 22-' . $total_day . '"',
                );
                for ($i = 1; $i <= 4; $i++) {
                    $monthly_order[$i] = Order::StoreOrder()->Delivered()->NotRefunded()
                        ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum('order_amount');
                    $start = $start->addDays(7);
                    $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                }
                $label = $weeks;
                $data = $monthly_order;
                break;
            default:
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded(), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                for ($i = 1; $i <= 12; $i++) {
                    $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                }
                $label = $months;
                $data = $monthly_order;
        }

        return view('admin-views.report.store-summary-report', compact('stores', 'new_stores', 'total_orders', 'order_payment_methods', 'items', 'monthly_order', 'label', 'data', 'filter', 'total_order_amount', 'total_ongoing', 'total_canceled', 'total_delivered'));
    }

    public function store_summary_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $filter = $request->query('filter', 'all_time');

        $stores = Store::with('orders')
            ->when(in_array($filter, ['this_year', 'this_month', 'previous_year', 'this_week', 'all_time']), function ($query) use ($filter) {
                return $query->with([
                    'orders' => function ($query) use ($filter) {
                        $query->StoreOrder()->applyDateFilter($filter, null, null, 'schedule_at');
                    },
                ]);
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            })->Active()
            ->limit(25)->get();

        return response()->json([
            'count' => count($stores),
            'view' => view('admin-views.report.partials._store_summary_table', compact('stores'))->render()
        ]);
    }

    public function store_sales_report(Request $request)
    {

        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');

        $months = array(
            '"Jan"',
            '"Feb"',
            '"Mar"',
            '"Apr"',
            '"May"',
            '"Jun"',
            '"Jul"',
            '"Aug"',
            '"Sep"',
            '"Oct"',
            '"Nov"',
            '"Dec"'
        );
        $days = array(
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;


        $data_array= $this->get_store_sales_data($request);

        $items=$data_array['items'];
        $items= $items->paginate(config('default_pagination'))->withQueryString();

        $orders=$data_array['orders'];


        $monthly_order = [];
        $label = [];
        $data = [];
        if ($filter != 'custom') {
            switch ($filter) {
                case "all_time":
                    $monthly_order = Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        })->select(
                            DB::raw("(sum(order_amount)) as order_amount"),
                            DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                        )
                        ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                        ->get()->makeHidden('module_type')->toArray();

                    $label = array_map(function ($order) {
                        return $order['year'];
                    }, $monthly_order);
                    $data = array_map(function ($order) {
                        return $order['order_amount'];
                    }, $monthly_order);
                    break;
                case "this_year":
                    $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            }), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
                    break;
                case "previous_year":
                    $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            }), 'MONTH(schedule_at)', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
                    break;
                case "this_week":
                    $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            }), 'DATE(schedule_at)', now()->startOfWeek(), now()->endOfWeek());
                    $weekStartDate = now()->startOfWeek();
                    for ($i = 1; $i <= 7; $i++) {
                        $monthly_order[$i] = (float) ($sums[$weekStartDate->format('Y-m-d')] ?? 0);
                        $weekStartDate = $weekStartDate->addDays(1);
                    }
                    $label = $days;
                    $data = $monthly_order;
                    break;
                case "this_month":
                    $start = now()->startOfMonth();
                    $end = now()->startOfMonth()->addDays(6);
                    $total_day = now()->daysInMonth;
                    $remaining_days = now()->daysInMonth - 28;
                    $weeks = array(
                        '"'.translate('Day').' 1-7"',
                        '"'.translate('Day').' 8-14"',
                        '"'.translate('Day').' 15-21"',
                        '"'.translate('Day').' 22-' . $total_day . '"',
                    );
                    for ($i = 1; $i <= 4; $i++) {
                        $monthly_order[$i] = Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })
                            ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                            ->sum('order_amount');
                        $start = $start->addDays(7);
                        $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                    }
                    $label = $weeks;
                    $data = $monthly_order;
                    break;
                default:
                    $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            }), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
            }
        } else {

            $to = Carbon::parse($to);
            $from = Carbon::parse($from);

            $years_count = (int) $from->diffInYears($to);
            $months_count = (int) $from->diffInMonths($to);
            $weeks_count = (int) $from->diffInWeeks($to);
            $days_count = (int) $from->diffInDays($to);

            if ($years_count > 0) {
                $monthly_order = Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                    return $query->whereIn('store_id', $zone->stores->pluck('id'));
                })
                    ->when(isset($store), function ($query) use ($store) {
                        return $query->where('store_id', $store->id);
                    })
                    ->whereBetween('schedule_at', ["{$from}", "{$to->format('Y-m-d')} 23:59:59"])
                    ->select(
                        DB::raw("(sum(order_amount)) as order_amount"),
                        DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                    )
                    ->groupBy('year')
                    ->get()->makeHidden('module_type')->toArray();

                $label = array_map(function ($order) {
                    return $order['year'];
                }, $monthly_order);
                $data = array_map(function ($order) {
                    return $order['order_amount'];
                }, $monthly_order);
            } elseif ($months_count > 0) {
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        }), "DATE_FORMAT(schedule_at, '%Y-%m')", $from->copy()->startOfMonth(), $to->copy()->endOfMonth());
                $cursor = $from->copy()->startOfMonth();
                for ($i = (int)$from->format('m'); $i <= (int)$from->format('m') + $months_count; $i++) {
                    $monthly_order[$i] = (float) ($sums[$cursor->format('Y-m')] ?? 0);
                    $label[$i] = $months[$cursor->month - 1];
                    $cursor = $cursor->addMonth();
                }
                $label = $label;
                $data = $monthly_order;
            } elseif ($weeks_count > 0) {
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        }), 'DATE(schedule_at)', $from->copy()->startOfDay(), $to->copy()->endOfDay());
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_order[$key] = (float) ($sums[$key] ?? 0);
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_order;
            } elseif ($days_count >= 0) {
                $sums = self::chartSums(Order::StoreOrder()->Delivered()->NotRefunded()->when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        }), 'DATE(schedule_at)', $from->copy()->startOfDay(), $to->copy()->endOfDay());
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_order[$key] = (float) ($sums[$key] ?? 0);
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_order;
            }
        }

        return view('admin-views.report.store-sales-report', compact('zone', 'store', 'items', 'orders', 'data', 'label', 'filter'));
    }


    public function store_sales_export(Request $request)
    {
        $from = session('from_date');
        $to = session('to_date');
        $filter = $request->query('filter', 'all_time');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');


        $data= $this->get_store_sales_data($request);
        $items=$data['items'];
        $items= $items->get();
        $orders=$data['orders'];

            $data = [
                'items'=>$items,
                'orders'=>$orders,
                'search'=>$request->search??null,
                'from'=>(($filter == 'custom') && $from)?$from:null,
                'to'=>(($filter == 'custom') && $to)?$to:null,
                'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
                'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
                'filter'=>$filter,
            ];
        if ($request->type == 'excel') {
            return Excel::download(new StoreSalesReportExport($data), 'StoreSalesReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new StoreSalesReportExport($data), 'StoreSalesReport.csv');
        }
    }


    private static function get_store_sales_data($request){
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;



            // Same rewrite as get_item_data(): aggregate on order_details and join back,
            // rather than joining items x order_details x orders and grouping the result by
            // items.id. That built a group per item across a 1M x 4M join, then sorted every
            // group for one page. The inner join replaces having('orders_count', '>', 0).
            [$rangeStart, $rangeEnd] = self::itemReportRange($filter, $from, $to);

            $orderAggregates = DB::table('order_details')
                ->join('orders', function ($join) use ($rangeStart, $rangeEnd) {
                    $join->on('orders.id', '=', 'order_details.order_id')
                        ->whereIn('orders.order_status', ['delivered', 'refund_requested', 'refund_request_canceled']);

                    if ($rangeStart && $rangeEnd) {
                        $join->whereBetween('orders.schedule_at', [$rangeStart, $rangeEnd]);
                    }
                })
                ->selectRaw('
                    order_details.item_id,
                    COUNT(DISTINCT orders.id) as orders_count,
                    SUM(order_details.quantity) as orders_sum_quantity,
                    SUM(order_details.price * order_details.quantity) as orders_sum_price,
                    -- Same rule as the item report above: the BOGO give-away is not a discount
                    -- on the line, it is the line, so its recorded worth is added in.
                    SUM(order_details.discount_on_item * order_details.quantity)
                    + SUM(IFNULL(order_details.bogo_free_value, 0) * order_details.quantity) as total_discount
                ')
                ->groupBy('order_details.item_id');

            $items = Item::withoutGlobalScope(StoreScope::class)
            ->joinSub($orderAggregates, 'item_orders', function ($join) {
                $join->on('item_orders.item_id', '=', 'items.id');
            })
            // A subquery rather than pluck('id'), which inlined every store id in the zone.
            ->when(isset($zone), fn($q) => $q->whereIn('items.store_id',
                Store::withoutGlobalScopes()->where('zone_id', $zone->id)->select('id')))
            ->when(isset($store), fn($q) => $q->where('items.store_id', $store->id))
            ->when(isset($request['search']), function ($q) use ($key) {
                $q->where(function ($sub) use ($key) {
                    foreach ($key as $value) {
                        $sub->orWhere('items.name', 'like', "%{$value}%");
                    }
                });
            })
            ->select([
                'items.*',
                'item_orders.orders_count',
                'item_orders.orders_sum_quantity',
                'item_orders.orders_sum_price',
                'item_orders.total_discount',
            ])
            ->orderByDesc('orders_count');

        $orders = Order::StoreOrder()
            ->whereNotIn('orders.order_status', ['refunded', 'failed', 'canceled'])
            ->Delivered()
            ->when(isset($zone), fn($q) => $q->whereIn('orders.store_id', $zone->stores()->pluck('id')))
            ->when(isset($store), fn($q) => $q->where('orders.store_id', $store->id))
            ->when(isset($from, $to) && $from && $to && $filter == 'custom', fn($q) =>
                $q->whereBetween('orders.schedule_at', [$from . " 00:00:00", $to . " 23:59:59"])
            )
            ->applyDateFilter($filter, $from, $to, 'orders.schedule_at')
            ->leftJoin('order_transactions', 'order_transactions.order_id', '=', 'orders.id')
            ->selectRaw("
                SUM(orders.order_amount) as total_order_amount,
                SUM(orders.total_tax_amount) as total_tax_amount,
                COUNT(*) as total_order,
                SUM(order_transactions.admin_commission) as transaction_sum_admin_commission,
                SUM(order_transactions.delivery_fee_comission) as transaction_sum_delivery_fee_comission,
                SUM(order_transactions.admin_expense) as transaction_sum_admin_expense,
                SUM(order_transactions.store_amount) as transaction_sum_store_amount
            ")
            ->first();


        return ['items'=> $items , 'orders'=> $orders];
    }







    public function store_order_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $data=[];
        $months = array(
            '"Jan"',
            '"Feb"',
            '"Mar"',
            '"Apr"',
            '"May"',
            '"Jun"',
            '"Jul"',
            '"Aug"',
            '"Sep"',
            '"Oct"',
            '"Nov"',
            '"Dec"'
        );
        $days = array(
            '"Sun"',
            '"Mon"',
            '"Tue"',
            '"Wed"',
            '"Thu"',
            '"Fri"',
            '"Sat"'
        );

        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $filter = $request->query('filter', 'all_time');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        $orders = Order::with(['customer', 'store', 'orderProDiscount'])
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->StoreOrder()->NotRefunded()
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')->paginate(config('default_pagination'));


        $orders_summary = Order::StoreOrder()
            ->NotRefunded()
            ->when(isset($zone), fn($q) => $q->whereIn('orders.store_id', $zone->stores->pluck('id')))
            ->when(isset($store), fn($q) => $q->where('orders.store_id', $store->id))
            ->when(isset($from, $to) && $from && $to && $filter == 'custom', fn($q) =>
                $q->whereBetween('orders.schedule_at', [$from . " 00:00:00", $to . " 23:59:59"])
            )
            ->applyDateFilter($filter, $from, $to, 'orders.schedule_at')
            ->leftJoin('order_transactions', 'order_transactions.order_id', '=', 'orders.id')
            ->selectRaw("
                COUNT(*) as total_orders_count,
                SUM(orders.order_amount) as total_order_amount,
                SUM(orders.coupon_discount_amount) as total_coupon_discount,
                SUM(orders.store_discount_amount) as total_product_discount,
                SUM(CASE WHEN orders.order_status IN ('pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up') THEN orders.order_amount ELSE 0 END) as total_ongoing,
                SUM(CASE WHEN orders.order_status IN ('failed', 'canceled') THEN orders.order_amount ELSE 0 END) as total_canceled,
                SUM(CASE WHEN orders.order_status = 'delivered' THEN orders.order_amount ELSE 0 END) as total_delivered,
                SUM(CASE WHEN orders.order_status IN ('pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up') THEN 1 ELSE 0 END) as total_ongoing_count,
                SUM(CASE WHEN orders.order_status IN ('failed', 'canceled') THEN 1 ELSE 0 END) as total_canceled_count,
                SUM(CASE WHEN orders.order_status = 'delivered' THEN 1 ELSE 0 END) as total_delivered_count,
                SUM(order_transactions.admin_commission) as transaction_sum_admin_commission,
                SUM(order_transactions.delivery_fee_comission) as transaction_sum_delivery_fee_comission,
                SUM(order_transactions.admin_expense) as transaction_sum_admin_expense
            ")
            ->first();

        $total_order_amount      = $orders_summary->total_order_amount;
        $total_coupon_discount   = $orders_summary->total_coupon_discount;
        $total_product_discount  = $orders_summary->total_product_discount;

        $total_ongoing           = $orders_summary->total_ongoing;
        $total_canceled          = $orders_summary->total_canceled;
        $total_delivered         = $orders_summary->total_delivered;

        $total_ongoing_count     = $orders_summary->total_ongoing_count;
        $total_canceled_count    = $orders_summary->total_canceled_count;
        $total_delivered_count   = $orders_summary->total_delivered_count;
        $order_payment_methods = Order::when(isset($zone), function ($query) use ($zone) {
            return $query->whereIn('store_id', $zone->stores->pluck('id'));
        })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->StoreOrder()->NotRefunded()
            ->selectRaw(DB::raw("sum(`order_amount`) as total_order_amount, count(*) as order_count, IF((`payment_method`='cash_on_delivery'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))
            ->groupBy('payment_methods')
            ->get();

        $monthly_order = [];
        $label = [];
        if ($filter != 'custom') {
            switch ($filter) {
                case "all_time":
                    $monthly_order = Order::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        })
                        ->StoreOrder()->NotRefunded()
                        ->select(
                            DB::raw("(sum(order_amount)) as order_amount"),
                            DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                        )
                        ->groupBy(DB::raw("DATE_FORMAT(schedule_at, '%Y')"))
                        ->get()->makeHidden('module_type')->toArray();

                    $label = array_map(function ($order) {
                        return $order['year'];
                    }, $monthly_order);
                    $data = array_map(function ($order) {
                        return $order['order_amount'];
                    }, $monthly_order);
                    break;
                case "this_year":
                    $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })
                            ->StoreOrder()->NotRefunded(), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
                    break;
                case "previous_year":
                    $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })
                            ->StoreOrder()->NotRefunded(), 'MONTH(schedule_at)', now()->subYear()->startOfYear(), now()->subYear()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
                    break;
                case "this_week":
                    $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })->StoreOrder()->NotRefunded(), 'DATE(schedule_at)', now()->startOfWeek(), now()->endOfWeek());
                    $weekStartDate = now()->startOfWeek();
                    for ($i = 1; $i <= 7; $i++) {
                        $monthly_order[$i] = (float) ($sums[$weekStartDate->format('Y-m-d')] ?? 0);
                        $weekStartDate = $weekStartDate->addDays(1);
                    }
                    $label = $days;
                    $data = $monthly_order;
                    break;
                case "this_month":
                    $start = now()->startOfMonth();
                    $end = now()->startOfMonth()->addDays(6);
                    $total_day = now()->daysInMonth;
                    $remaining_days = now()->daysInMonth - 28;
                    $weeks = array(
                        '"'.translate('Day').' 1-7"',
                        '"'.translate('Day').' 8-14"',
                        '"'.translate('Day').' 15-21"',
                        '"'.translate('Day').' 22-' . $total_day . '"',
                    );
                    for ($i = 1; $i <= 4; $i++) {
                        $monthly_order[$i] = Order::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })
                            ->StoreOrder()->NotRefunded()
                            ->whereBetween('schedule_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                            ->sum('order_amount');
                        $start = $start->addDays(7);
                        $end = $i == 3 ? $end->addDays(7 + $remaining_days) : $end->addDays(7);
                    }
                    $label = $weeks;
                    $data = $monthly_order;
                    break;
                default:
                    $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                            return $query->whereIn('store_id', $zone->stores->pluck('id'));
                        })
                            ->when(isset($store), function ($query) use ($store) {
                                return $query->where('store_id', $store->id);
                            })->StoreOrder()->NotRefunded(), 'MONTH(schedule_at)', now()->startOfYear(), now()->endOfYear());
                    for ($i = 1; $i <= 12; $i++) {
                        $monthly_order[$i] = (float) ($sums[$i] ?? 0);
                    }
                    $label = $months;
                    $data = $monthly_order;
            }
        } else {

            $to = Carbon::parse($to);
            $from = Carbon::parse($from);

            $years_count = (int) $from->diffInYears($to);
            $months_count = (int) $from->diffInMonths($to);
            $weeks_count = (int) $from->diffInWeeks($to);
            $days_count = (int) $from->diffInDays($to);



            if ($years_count > 0) {
                $monthly_order = Order::when(isset($zone), function ($query) use ($zone) {
                    return $query->whereIn('store_id', $zone->stores->pluck('id'));
                })
                    ->when(isset($store), function ($query) use ($store) {
                        return $query->where('store_id', $store->id);
                    })
                    ->StoreOrder()->NotRefunded()
                    ->whereBetween('schedule_at', ["{$from}", "{$to->format('Y-m-d')} 23:59:59"])
                    ->select(
                        DB::raw("(sum(order_amount)) as order_amount"),
                        DB::raw("(DATE_FORMAT(schedule_at, '%Y')) as year")
                    )
                    ->groupBy('year')
                    ->get()->makeHidden('module_type')->toArray();

                $label = array_map(function ($order) {
                    return $order['year'];
                }, $monthly_order);
                $data = array_map(function ($order) {
                    return $order['order_amount'];
                }, $monthly_order);
            } elseif ($months_count > 0) {
                $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        })
                        ->StoreOrder()->NotRefunded(), "DATE_FORMAT(schedule_at, '%Y-%m')", $from->copy()->startOfMonth(), $to->copy()->endOfMonth());
                $cursor = $from->copy()->startOfMonth();
                for ($i = (int)$from->format('m'); $i <= (int)$from->format('m') + $months_count; $i++) {
                    $monthly_order[$i] = (float) ($sums[$cursor->format('Y-m')] ?? 0);
                    $label[$i] = $months[$cursor->month - 1];
                    $cursor = $cursor->addMonth();
                }
                $label = $label;
                $data = $monthly_order;
            } elseif ($weeks_count > 0) {

                $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        })
                        ->StoreOrder()->NotRefunded(), 'DATE(schedule_at)', $from->copy()->startOfDay(), $to->copy()->endOfDay());
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_order[$key] = (float) ($sums[$key] ?? 0);
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_order;
            } elseif ($days_count >= 0) {
                $sums = self::chartSums(Order::when(isset($zone), function ($query) use ($zone) {
                        return $query->whereIn('store_id', $zone->stores->pluck('id'));
                    })
                        ->when(isset($store), function ($query) use ($store) {
                            return $query->where('store_id', $store->id);
                        })
                        ->StoreOrder()->NotRefunded(), 'DATE(schedule_at)', $from->copy()->startOfDay(), $to->copy()->endOfDay());
                for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
                    $key = $day->format('Y-m-d');
                    $monthly_order[$key] = (float) ($sums[$key] ?? 0);
                    $label[$key] = $day->format('j M');
                }
                $label = $label;
                $data = $monthly_order;
            }
        }


        return view('admin-views.report.store-order-report', compact('zone', 'store', 'orders', 'monthly_order', 'total_order_amount', 'order_payment_methods', 'total_coupon_discount', 'total_product_discount', 'label', 'data', 'filter', 'total_ongoing', 'total_canceled', 'total_delivered', 'total_ongoing_count', 'total_canceled_count', 'total_delivered_count'));
    }

    public function store_order_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;

        $orders = Order::with(['customer', 'store', 'orderProDiscount'])
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(isset($from) && isset($to) && $from != null && $to != null, function ($query) use ($from, $to) {
                return $query->whereBetween('created_at', [$from . " 00:00:00", $to . " 23:59:59"]);
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            })
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->StoreOrder()->NotRefunded()
            ->orderBy('schedule_at', 'desc')
            ->limit(25)->get();

        return response()->json([
            'count' => count($orders),
            'view' => view('admin-views.report.partials._store_order_table', compact('orders'))->render()
        ]);
    }

    public function store_order_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $from = session('from_date');
        $to = session('to_date');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $filter = $request->query('filter', 'all_time');

        $orders = Order::query()
        ->with([
            'customer',
            'store',
            'orderProDiscount',
        ])
        ->when(isset($request['search']), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('id', 'like', "%{$value}%");
                }
            });
        })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->StoreOrder()->NotRefunded()
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')
            ->orderBy('id');

            $orders_list = Order::query()
            ->with([
                'customer',
                'store',
                'orderProDiscount',
            ])
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->StoreOrder()->NotRefunded()
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')->get();

        $total_order_amount = $orders_list->sum('order_amount');
        $total_coupon_discount = $orders_list->sum('coupon_discount_amount');
        $total_product_discount = $orders_list->sum('store_discount_amount');

        $total_ongoing = $orders_list->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->sum('order_amount');
        $total_canceled = $orders_list->whereIn('order_status', ['failed', 'canceled'])->sum('order_amount');
        $total_delivered = $orders_list->where('order_status', 'delivered')->sum('order_amount');
        $total_ongoing_count = $orders_list->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->count();
        $total_canceled_count = $orders_list->whereIn('order_status', ['failed', 'canceled'])->count();
        $total_delivered_count = $orders_list->where('order_status', 'delivered')->count();


            // Streamed rather than ->get(): hydrating every matching row with its relations at
            // once exhausted the memory limit outright on an unfiltered export. forPage() re-sorts
            // on every page, so the ordering below carries a unique tiebreaker -- without one a
            // tied row can land on both sides of a page boundary, duplicating it and dropping
            // another. The count is taken once because count() on the LazyCollection would re-run
            // every chunk query.
            $total_orders = (clone $orders)->count();

            $data = [
                'orders'=>$this->streamExportRows($orders),
                'total_orders'=>$total_orders,
                'total_order_amount'=>$total_order_amount,
                'total_ongoing_count'=>$total_ongoing_count,
                'total_canceled_count'=>$total_canceled_count,
                'total_delivered_count'=>$total_delivered_count,
                'search'=>$request->search??null,
                'from'=>(($filter == 'custom') && $from)?$from:null,
                'to'=>(($filter == 'custom') && $to)?$to:null,
                'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
                'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
                'filter'=>$filter,
            ];
        if ($request->type == 'excel') {
            return Excel::download(new StoreOrderReportExport($data), 'StoreOrderReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new StoreOrderReportExport($data), 'StoreOrderReport.csv');
        }
    }



    public function store_summary_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $filter = $request->query('filter', 'all_time');

        $stores = Store::with('orders')
            ->when(in_array($filter, ['this_year', 'this_month', 'previous_year', 'this_week', 'all_time']), function ($query) use ($filter) {
                return $query->with([
                    'orders' => function ($query) use ($filter) {
                        $query->StoreOrder()->applyDateFilter($filter, null, null, 'schedule_at');
                    },
                ]);
            })
            ->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('name', 'like', "%{$value}%");
                }
            })
            ->Active()->orderBy('order_count', 'DESC')->get();
            $order_payment_methods = Order::applyDateFilter($filter, null, null, 'schedule_at')
                ->StoreOrder()->Delivered()->NotRefunded()
                ->selectRaw(DB::raw("sum(`order_amount`) as total_order_amount, count(*) as order_count, IF((`payment_method`='cash_on_delivery'), `payment_method`, IF(`payment_method`='wallet',`payment_method`, 'digital_payment')) as 'payment_methods'"))->groupBy('payment_methods')
                ->get();

            $new_stores = Store::applyDateFilter($filter, null, null, 'created_at')->count();
            $orders = Order::applyDateFilter($filter, null, null, 'schedule_at')->StoreOrder()->select(['id', 'order_status', 'order_amount'])->get();
            $total_order_amount = $orders->whereIn('order_status', ['delivered'])->sum('order_amount');
            $total_ongoing = $orders->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])->count();
            $total_canceled = $orders->whereIn('order_status', ['failed', 'canceled'])->count();
            $total_delivered = $orders->whereIn('order_status', ['delivered'])->count();

            $data = [
                'stores'=>$stores,
                'search'=>$request->search??null,
                'new_stores'=>$new_stores,
                'orders'=>$orders->count(),
                'total_order_amount'=>$total_order_amount,
                'total_ongoing'=>$total_ongoing,
                'total_canceled'=>$total_canceled,
                'total_delivered'=>$total_delivered,
                'cash_payments'=>count($order_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($order_payment_methods[0])?$order_payment_methods[0]->total_order_amount:0):0,
                'digital_payments'=>count($order_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($order_payment_methods[1])?$order_payment_methods[1]->total_order_amount:0):0,
                'wallet_payments'=>count($order_payment_methods)>0?\App\CentralLogics\Helpers::number_format_short(isset($order_payment_methods[2])?$order_payment_methods[2]->total_order_amount:0):0,
                'filter'=>$filter,
            ];
        if ($request->type == 'excel') {
            return Excel::download(new StoreSummaryReportExport($data), 'StoreSummaryReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new StoreSummaryReportExport($data), 'StoreSummaryReport.csv');
        }
    }

    public function expense_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $module = request()->module;
             $type = $request->query('type', 'all');

        $expense = Expense::with('order', 'order.customer:id,f_name,l_name')->notRefunded()->where('created_by', 'admin')->where('amount', '>' ,0)
            ->whereHas('order', function ($query) {
                $query->where('order_type', '!=', 'parcel');
            })
            ->when($zone || $module || $customer || $store, function ($query) use ($zone, $module, $customer, $store) {
                $query->whereHas('order', function ($query) use ($zone, $store, $customer, $module) {
                    $query->when($module, function ($query) use ($module) {
                        return $query->module($module);
                    });
                    $query->when($zone, function ($query) use ($zone) {
                        return $query->where('zone_id', $zone->id);
                    });
                    $query->when($store, function ($query) use ($store) {
                        return $query->where('store_id', $store->id);
                    });
                    $query->when($customer, function ($query) use ($customer) {
                        return $query->where('user_id', $customer->id);
                    });
                });
            })
                   ->when(isset($type) &&  $type != 'all', function ($query) use ($type) {
                return $query->where('type',$type);
            })
            ->when(isset($filter) , function ($query) use ($filter,$from, $to) {
                return $query->applyDateFilter($filter, $from, $to);
            })
            ->search(keywords:$request['search'], mainCol: ['type', 'order_id'])
            ->orderBy('id');

        $expense_count = (clone $expense)->count();
        $expense = $this->streamExportRows($expense);

        $data = [
            'expenses'=>$expense,
            'expenses_count'=>$expense_count,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
            'customer'=>is_numeric($customer_id)?Helpers::get_customer_name($customer_id):null,
            'module'=>request('module_id')?Helpers::get_module_name(request('module_id')):null,
            'filter'=>$filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new ExpenseReportExport($data), 'ExpenseReport.xlsx');
        } else if ($request->export_type == 'csv') {
            return Excel::download(new ExpenseReportExport($data), 'ExpenseReport.csv');
        }
    }

    public function expense_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
             $type = $request->query('type', 'all');

        $expense = Expense::with('order')->notRefunded()->where('amount', '>' ,0)
            ->whereHas('order', function ($query) use ($zone, $store, $customer) {
                $query->when(request('module_id'), function ($query) {
                    return $query->module(request('module_id'));
                });
                $query->when($zone, function ($query) use ($zone) {
                    return $query->where('zone_id', $zone->id);
                });
                $query->when($store, function ($query) use ($store) {
                    return $query->where('store_id', $store->id);
                });
                $query->when($customer, function ($query) use ($customer) {
                    return $query->where('user_id', $customer->id);
                });
            })
                   ->when(isset($type) &&  $type != 'all', function ($query) use ($type) {
                return $query->where('type',$type);
            })
            ->applyDateFilter($filter, $from, $to, 'created_at')
            ->when($request['search'], function ($query) use ($key){
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('type', 'like', "%{$value}%")
                            ->orWhere('order_id', 'like', "%{$value}%")
                            ->orWhere('trip_id', 'like', "%{$value}%");

                        if (addon_published_status('RideShare')) {
                            $q->orWhereHas('ride', function ($query) use ($value) {
                                $query->where('id', 'like', "%{$value}%")
                                    ->orWhere('ref_id', 'like', "%{$value}%");
                            });
                        }
                    }
                });
            })
            ->limit(25)->get();

        return response()->json([
            'count' => count($expense),
            'view' => view('admin-views.report.partials._expense_table', compact('expense'))->render()
        ]);
    }

    public function order_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        $orders = Order::with(['customer', 'store', 'transaction', 'orderProDiscount'])
            ->withSum('details as item_discount_total', DB::raw('discount_on_item * quantity'))
            ->when(request('module_id'), function ($query) {
                return $query->module(request('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(isset($customer), function ($query) use ($customer) {
                return $query->where('user_id', $customer->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->StoreOrder()
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')->paginate(config('default_pagination'))->withQueryString();

        $order_stats = Order::when(request('module_id'), function ($query) {
            return $query->module(request('module_id'));
        })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(isset($customer), function ($query) use ($customer) {
                return $query->where('user_id', $customer->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->StoreOrder()
            ->selectRaw("
                COUNT(CASE WHEN order_status = 'canceled' THEN 1 END) as canceled_count,
                COUNT(CASE WHEN order_status = 'delivered' THEN 1 END) as delivered_count,
                COUNT(CASE WHEN order_status IN ('accepted', 'confirmed', 'processing', 'handover') THEN 1 END) as progress_count,
                COUNT(CASE WHEN order_status = 'failed' THEN 1 END) as failed_count,
                COUNT(CASE WHEN order_status = 'refunded' THEN 1 END) as refunded_count,
                COUNT(CASE WHEN order_status = 'picked_up' THEN 1 END) as on_the_way_count
            ")
            ->first();

        $total_canceled_count = (int) ($order_stats->canceled_count ?? 0);
        $total_delivered_count = (int) ($order_stats->delivered_count ?? 0);
        $total_progress_count = (int) ($order_stats->progress_count ?? 0);
        $total_failed_count = (int) ($order_stats->failed_count ?? 0);
        $total_refunded_count = (int) ($order_stats->refunded_count ?? 0);
        $total_on_the_way_count = (int) ($order_stats->on_the_way_count ?? 0);
        return view('admin-views.report.order-report', compact('orders', 'zone', 'store', 'filter', 'customer', 'total_on_the_way_count', 'total_refunded_count', 'total_failed_count', 'total_progress_count', 'total_canceled_count', 'total_delivered_count'));
    }



    public function order_report_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        $orders = Order::with(['customer', 'store', 'orderProDiscount'])
            ->withSum('details as item_discount_total', DB::raw('discount_on_item * quantity'))
            ->when(request('module_id'), function ($query) {
                return $query->module(request('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->where('zone_id', $zone->id);
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(isset($customer), function ($query) use ($customer) {
                return $query->where('user_id', $customer->id);
            })
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->when(isset($request['search']), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->StoreOrder()
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')
            ->orderBy('id', 'asc');

        $orders_count = (clone $orders)->count();
        $orders = $this->streamExportRows($orders);

        $data = [
            'orders'=>$orders,
            'orders_count'=>$orders_count,
            'search'=>$request->search??null,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
            'customer'=>is_numeric($customer_id)?Helpers::get_customer_name($customer_id):null,
            'module'=>request('module_id')?Helpers::get_module_name(request('module_id')):null,
            'filter'=>$filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new OrderReportExport($data), 'OrderReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new OrderReportExport($data), 'OrderReport.csv');
        }
    }

    public function expense_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $module_id = $request->query('module_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expense = Expense::with('user','order', 'order.customer:id,f_name,l_name')->notRefunded()->where('amount', '>' ,0)
            ->whereHas('order', function ($query) {
                $query->where('order_type', '!=', 'parcel');
            })
            ->when(isset($zone) || isset($store) || isset($customer), function ($query) use ($zone, $store, $customer) {
                return $query->whereHas('order', function ($query) use ($zone, $store, $customer) {
                    $query->when($zone, function ($query) use ($zone) {
                        return $query->where('zone_id', $zone->id);
                    });
                    $query->when($store, function ($query) use ($store) {
                        return $query->where('store_id', $store->id);
                    });
                    $query->when($customer, function ($query) use ($customer) {
                        return $query->where('user_id', $customer->id);
                    });
                });
            })
            ->when(isset($type) &&  $type != 'all', function ($query) use ($type) {
                return $query->where('type',$type);
            })
            ->when(isset($module_id) &&  is_numeric($module_id), function ($query) use ($module_id) {
                return $query->whereHas('order', function ($query) use ($module_id) {
                    $query->when(is_numeric($module_id), function ($query) use ($module_id) {
                        return $query->where('module_id',$module_id);
                    });
                });
            })
            ->when(isset($filter) , function ($query) use ($filter,$from, $to) {
                return $query->applyDateFilter($filter, $from, $to);
            })
            ->search(keywords:$request['search'], mainCol: ['type', 'order_id'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.expense-report', compact('expense', 'zone', 'store', 'filter', 'customer','type'));
    }

    public function parcel_expense_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $expense = Expense::with('user', 'order', 'order.customer:id,f_name,l_name')->notRefunded()->where('amount', '>', 0)
            ->whereHas('order', function ($query) use ($parcelModuleIds) {
                $query->where('order_type', 'parcel');
                if (! empty($parcelModuleIds)) {
                    $query->whereIn('module_id', $parcelModuleIds);
                }
            })
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                return $query->whereHas('order', function ($query) use ($zone, $customer) {
                    $query->when($zone, fn ($q) => $q->where('zone_id', $zone->id));
                    $query->when($customer, fn ($q) => $q->where('user_id', $customer->id));
                });
            })
            ->when($module_id, function ($query) use ($module_id) {
                return $query->whereHas('order', fn ($q) => $q->where('module_id', $module_id));
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'order_id'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.parcel-expense-report', compact('expense', 'zone', 'filter', 'customer', 'type', 'module_id'));
    }

    public function parcel_expense_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $expenses = Expense::with('order', 'order.customer:id,f_name,l_name')->notRefunded()->where('created_by', 'admin')->where('amount', '>', 0)
            ->whereHas('order', function ($query) use ($parcelModuleIds) {
                $query->where('order_type', 'parcel');
                if (! empty($parcelModuleIds)) {
                    $query->whereIn('module_id', $parcelModuleIds);
                }
            })
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                return $query->whereHas('order', function ($query) use ($zone, $customer) {
                    $query->when($zone, fn ($q) => $q->where('zone_id', $zone->id));
                    $query->when($customer, fn ($q) => $q->where('user_id', $customer->id));
                });
            })
            ->when($module_id, function ($query) use ($module_id) {
                return $query->whereHas('order', fn ($q) => $q->where('module_id', $module_id));
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'order_id'])
            ->orderBy('id')->get();

        $data = [
            'expenses' => $expenses,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'module' => $module_id ? Helpers::get_module_name($module_id) : null,
            'filter' => $filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new ParcelExpenseReportExport($data), 'ParcelExpenseReport.xlsx');
        } elseif ($request->export_type == 'csv') {
            return Excel::download(new ParcelExpenseReportExport($data), 'ParcelExpenseReport.csv');
        }
    }

    public function rental_expense_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expense = Expense::with('trip', 'trip.customer')->notRefunded()->where('amount', '>', 0)
            ->whereNotNull('trip_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! addon_published_status('Rental')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('trip', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('user_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'trip_id'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.rental-expense-report', compact('expense', 'zone', 'filter', 'customer', 'type'));
    }

    public function rental_expense_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expenses = Expense::with('trip', 'trip.customer')->notRefunded()->where('created_by', 'admin')->where('amount', '>', 0)
            ->whereNotNull('trip_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! addon_published_status('Rental')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('trip', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('user_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'trip_id'])
            ->orderBy('id')->get();

        $data = [
            'expenses' => $expenses,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'filter' => $filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new RentalExpenseReportExport($data), 'RentalExpenseReport.xlsx');
        } elseif ($request->export_type == 'csv') {
            return Excel::download(new RentalExpenseReportExport($data), 'RentalExpenseReport.csv');
        }
    }

    public function rideshare_expense_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expense = Expense::with('ride', 'ride.customer')->notRefunded()->where('amount', '>', 0)
            ->whereNotNull('ride_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! addon_published_status('RideShare')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('ride', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('customer_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'ride_id'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.rideshare-expense-report', compact('expense', 'zone', 'filter', 'customer', 'type'));
    }

    public function rideshare_expense_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expenses = Expense::with('ride', 'ride.customer')->notRefunded()->where('created_by', 'admin')->where('amount', '>', 0)
            ->whereNotNull('ride_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! addon_published_status('RideShare')) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('ride', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('customer_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'ride_id'])
            ->orderBy('id')->get();

        $data = [
            'expenses' => $expenses,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'filter' => $filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new RideshareExpenseReportExport($data), 'RideshareExpenseReport.xlsx');
        } elseif ($request->export_type == 'csv') {
            return Excel::download(new RideshareExpenseReportExport($data), 'RideshareExpenseReport.csv');
        }
    }

    public function service_expense_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expense = Expense::with('serviceBooking', 'serviceBooking.customer')->notRefunded()->where('amount', '>', 0)
            ->whereNotNull('service_booking_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! service_addon_active()) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('serviceBooking', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('user_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'service_booking_id'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.service-expense-report', compact('expense', 'zone', 'filter', 'customer', 'type'));
    }

    public function service_expense_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expenses = Expense::with('serviceBooking', 'serviceBooking.customer')->notRefunded()->where('created_by', 'admin')->where('amount', '>', 0)
            ->whereNotNull('service_booking_id')
            ->when(isset($zone) || isset($customer), function ($query) use ($zone, $customer) {
                if (! service_addon_active()) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas('serviceBooking', function ($q) use ($zone, $customer) {
                    $q->when($zone, fn ($qq) => $qq->where('zone_id', $zone->id));
                    $q->when($customer, fn ($qq) => $qq->where('user_id', $customer->id));
                });
            })
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type', 'service_booking_id'])
            ->orderBy('id')->get();

        $data = [
            'expenses' => $expenses,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'zone' => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'filter' => $filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new ServiceExpenseReportExport($data), 'ServiceExpenseReport.xlsx');
        } elseif ($request->export_type == 'csv') {
            return Excel::download(new ServiceExpenseReportExport($data), 'ServiceExpenseReport.csv');
        }
    }

    public function parcel_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $base = function () use ($key, $zone, $customer, $module_id, $parcelModuleIds, $from, $to, $filter) {
            return Order::query()
                ->ParcelOrder()
                ->when(! empty($parcelModuleIds), function ($q) use ($module_id, $parcelModuleIds) {
                    if ($module_id) {
                        $q->where('module_id', $module_id);
                    } else {
                        $q->whereIn('module_id', $parcelModuleIds);
                    }
                })
                ->when(isset($zone), fn ($q) => $q->where('zone_id', $zone->id))
                ->when(isset($customer), fn ($q) => $q->where('user_id', $customer->id))
                ->applyDateFilter($filter, $from, $to, 'schedule_at')
                ->when(! empty($key), function ($q) use ($key) {
                    return $q->where(function ($qq) use ($key) {
                        foreach ($key as $value) {
                            $qq->orWhere('id', 'like', "%{$value}%");
                        }
                    });
                });
        };

        $orders = (clone $base())
            ->with(['customer', 'transaction', 'orderProDiscount'])
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        $counts = (clone $base())->toBase()->selectRaw("
            sum(case when order_status = 'canceled' then 1 else 0 end) as canceled,
            sum(case when order_status = 'delivered' then 1 else 0 end) as delivered,
            sum(case when order_status in ('accepted','confirmed','processing','handover') then 1 else 0 end) as in_progress,
            sum(case when order_status = 'failed' then 1 else 0 end) as failed,
            sum(case when order_status = 'refunded' then 1 else 0 end) as refunded,
            sum(case when order_status = 'picked_up' then 1 else 0 end) as on_the_way
        ")->first();

        $total_canceled_count   = (int) ($counts->canceled ?? 0);
        $total_delivered_count  = (int) ($counts->delivered ?? 0);
        $total_progress_count   = (int) ($counts->in_progress ?? 0);
        $total_failed_count     = (int) ($counts->failed ?? 0);
        $total_refunded_count   = (int) ($counts->refunded ?? 0);
        $total_on_the_way_count = (int) ($counts->on_the_way ?? 0);

        return view('admin-views.report.parcel-report', compact(
            'orders', 'zone', 'filter', 'customer', 'module_id',
            'total_on_the_way_count', 'total_refunded_count', 'total_failed_count',
            'total_progress_count', 'total_canceled_count', 'total_delivered_count'
        ));
    }

    public function parcel_report_export(Request $request)
    {
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        if (session()->has('from_date') == false) {
            session()->put('from_date', date('Y-m-01'));
            session()->put('to_date', date('Y-m-30'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');

        $parcelModuleIds = \App\Models\Module::where('module_type', 'parcel')->pluck('id')->all();
        $module_id = $request->query('module_id');
        if ($module_id && ! in_array((int) $module_id, $parcelModuleIds, true)) {
            $module_id = null;
        }

        $orders = Order::query()
            ->with([
                'customer',
                'transaction',
                'orderProDiscount',
            ])
            ->ParcelOrder()
            ->when(! empty($parcelModuleIds), function ($q) use ($module_id, $parcelModuleIds) {
                if ($module_id) {
                    $q->where('module_id', $module_id);
                } else {
                    $q->whereIn('module_id', $parcelModuleIds);
                }
            })
            ->when(isset($zone), fn ($q) => $q->where('zone_id', $zone->id))
            ->when(isset($customer), fn ($q) => $q->where('user_id', $customer->id))
            ->applyDateFilter($filter, $from, $to, 'schedule_at')
            ->when(! empty($key), function ($q) use ($key) {
                return $q->where(function ($qq) use ($key) {
                    foreach ($key as $value) {
                        $qq->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->withSum('transaction', 'admin_commission')
            ->withSum('transaction', 'admin_expense')
            ->withSum('transaction', 'delivery_fee_comission')
            ->orderBy('schedule_at', 'desc')->get();

        $data = [
            'orders'   => $orders,
            'search'   => $request->search ?? null,
            'from'     => (($filter == 'custom') && $from) ? $from : null,
            'to'       => (($filter == 'custom') && $to) ? $to : null,
            'zone'     => is_numeric($zone_id) ? Helpers::get_zones_name($zone_id) : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'module'   => $module_id ? Helpers::get_module_name($module_id) : null,
            'filter'   => $filter,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new ParcelReportExport($data), 'ParcelReport.xlsx');
        } elseif ($request->type == 'csv') {
            return Excel::download(new ParcelReportExport($data), 'ParcelReport.csv');
        }

        return redirect()->route('admin.transactions.report.parcel-report');
    }

    public function other_expense_report(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expense = Expense::with('user:id,f_name,l_name')->where('amount', '>', 0)
            ->whereNull('order_id')->whereNull('trip_id')->whereNull('ride_id')->whereNull('service_booking_id')
            ->when($customer, fn ($q) => $q->where('user_id', $customer->id))
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type'])
            ->where('created_by', 'admin')
            ->orderBy('created_at', 'desc')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.other-expense-report', compact('expense', 'filter', 'customer', 'type'));
    }

    public function other_expense_export(Request $request)
    {
        if (session()->has('from_date') == false) {
            session()->put('from_date', now()->firstOfMonth()->format('Y-m-d'));
            session()->put('to_date', now()->lastOfMonth()->format('Y-m-d'));
        }
        $from = session('from_date');
        $to = session('to_date');
        $customer_id = $request->query('customer_id', 'all');
        $customer = is_numeric($customer_id) ? User::findOrFail($customer_id) : null;
        $filter = $request->query('filter', 'all_time');
        $type = $request->query('type', 'all');

        $expenses = Expense::with('user:id,f_name,l_name')->where('created_by', 'admin')->where('amount', '>', 0)
            ->whereNull('order_id')->whereNull('trip_id')->whereNull('ride_id')->whereNull('service_booking_id')
            ->when($customer, fn ($q) => $q->where('user_id', $customer->id))
            ->when(isset($type) && $type != 'all', fn ($q) => $q->where('type', $type))
            ->when(isset($filter), fn ($q) => $q->applyDateFilter($filter, $from, $to))
            ->search(keywords: $request['search'], mainCol: ['type'])
            ->orderBy('id')->get();

        $data = [
            'expenses' => $expenses,
            'search' => $request->search ?? null,
            'from' => (($filter == 'custom') && $from) ? $from : null,
            'to' => (($filter == 'custom') && $to) ? $to : null,
            'customer' => is_numeric($customer_id) ? Helpers::get_customer_name($customer_id) : null,
            'type' => $type,
            'filter' => $filter,
        ];

        if ($request->export_type == 'excel') {
            return Excel::download(new OtherExpenseReportExport($data), 'OtherExpenseReport.xlsx');
        } elseif ($request->export_type == 'csv') {
            return Excel::download(new OtherExpenseReportExport($data), 'OtherExpenseReport.csv');
        }
    }

    public function generate_statement($id)
    {
        $company_phone = Helpers::get_business_settings('phone', false);
        $company_email = Helpers::get_business_settings('email_address', false);
        $company_name = Helpers::get_business_settings('business_name', false);
        $company_web_logo = Helpers::get_full_url(
            'business',
            Helpers::get_business_settings('logo', false),
            app(BusinessSettingService::class)->findStorageDisk('logo')
        );
        $footer_text = Helpers::get_business_settings('footer_text', false);

        $order_transaction = OrderTransaction::with('order', 'order.details', 'order.customer', 'order.store')->where('id', $id)->first();
        $data["email"] = $order_transaction->order->customer != null ? $order_transaction->order->customer["email"] : translate('email_not_found');
        $data["client_name"] = $order_transaction->order->customer != null ? $order_transaction->order->customer["f_name"] . ' ' . $order_transaction->order->customer["l_name"] : translate('No data found');
        $data["order_transaction"] = $order_transaction;
        $mpdf_view = View::make(
            'admin-views.report.order-transaction-statement',
            compact('order_transaction', 'company_phone', 'company_name', 'company_email', 'company_web_logo', 'footer_text')
        );
        Helpers::gen_mpdf($mpdf_view, 'order_trans_statement', $order_transaction->id);
    }

    public function low_stock_report(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $stock_modules = array_keys(array_filter(config('module'), function ($var) {
            if (isset($var['stock']) && $var['stock']) return $var;
        }));
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $items = Item::withoutGlobalScope(StoreScope::class)->withStorage()->with(['store', 'store.zone'])->whereHas('store.module', function ($query) {
            $query->where('module_type', '!=', 'food');
        })
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(count($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store.StoreConfig', function ($query) {
                $query->whereColumn('items.stock', '<=', 'store_configs.minimum_stock_for_warning')->orwhere('items.stock', 0);
            })
            ->orderBy('stock')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.low-stock-report', compact('zone', 'store', 'items'));
    }


    public function stock_report(Request $request)
    {
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $stock_modules = array_keys(array_filter(config('module'), function ($var) {
            if (isset($var['stock']) && $var['stock']) return $var;
        }));
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $items = Item::withoutGlobalScope(StoreScope::class)->withStorage()
        ->with(['store', 'store.zone'])->whereHas('store.module', function ($query) use ($stock_modules) {
            $query->where('module_type', Config::get('module.current_module_type'));
        })
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(count($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store.StoreConfig', function ($query) {
                $query->whereColumn('items.stock', '<=', 'store_configs.minimum_stock_for_warning')->orwhere('items.stock', 0);
            })
            ->orderBy('stock')
            ->paginate(config('default_pagination'))->withQueryString();

        return view('admin-views.report.stock-report', compact('zone', 'store', 'items'));
    }


    public function stock_wise_export(Request $request)
    {
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $module_id = $request->query('module_id', session()->get('current_module'));
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $items = Item::withoutGlobalScope(StoreScope::class)
            ->with(['store' => fn ($query) => $query->with('zone')])
            ->when($module_id, function ($query) use ($module_id) {
                return $query->module($module_id);
            }, function ($query) {
                return $query->whereHas('store.module', function ($q) {
                    $q->where('module_type', '!=', 'food');
                });
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(count($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store.StoreConfig', function ($query) {
                $query->whereColumn('items.stock', '<=', 'store_configs.minimum_stock_for_warning')->orwhere('items.stock', 0);
            })
            ->orderBy('stock')
            ->get();

        $data = [
            'items'=>$items,
            'search'=>$request->search??null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new LimitedStockReportExport($data), 'StockReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new LimitedStockReportExport($data), 'StockReport.csv');
        }
    }


    public function low_stock_wise_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $stock_modules = array_keys(array_filter(config('module'), function ($var) {
            if (isset($var['stock']) && $var['stock']) return $var;
        }));
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $items = Item::withoutGlobalScope(StoreScope::class)->with(['store' => fn ($query) => $query->with('zone')])->whereHas('store.module', function ($query) {
            $query->where('module_type', '!=', 'food');
        })
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(count($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->whereHas('store.StoreConfig', function ($query) {
                $query->whereColumn('items.stock', '<=', 'store_configs.minimum_stock_for_warning')->orwhere('items.stock', 0);
            })
            ->orderBy('stock')
            ->get();

        $data = [
            'items'=>$items,
            'search'=>$request->search??null,
            'zone'=>is_numeric($zone_id)?Helpers::get_zones_name($zone_id):null,
            'store'=>is_numeric($store_id)?Helpers::get_stores_name($store_id):null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new LimitedStockReportExport($data), 'StockReport.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new LimitedStockReportExport($data), 'StockReport.csv');
        }
    }

    public function low_stock_search(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $store_id = $request->query('store_id', 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $stock_modules = array_keys(array_filter(config('module'), function ($var) {
            if (isset($var['stock']) && $var['stock']) return $var;
        }));
        $key = isset($request['search']) ? explode(' ', $request['search'] ?? '') : [];

        $items = Item::withoutGlobalScope(StoreScope::class)->with(['store', 'store.zone'])->whereHas('store.module', function ($query) {
            $query->where('module_type', '!=', 'food');
        })
            ->when($request->query('module_id', null), function ($query) use ($request) {
                return $query->module($request->query('module_id'));
            })
            ->when(isset($zone), function ($query) use ($zone) {
                return $query->whereIn('store_id', $zone->stores->pluck('id'));
            })
            ->when(isset($store), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when(count($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('name', 'like', "%{$value}%");
                    }
                });
            })
            ->orderBy('stock')
            ->limit(25)->get();

        return response()->json([
            'count' => count($items),
            'view' => view('admin-views.report.partials._stock_table', compact('items'))->render()
        ]);
    }

    public function disbursement_report(Request $request,$tab = 'store')
    {
        $from =  null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if($filter == 'custom'){
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }
        $key = explode(' ', $request['search'] ?? '');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $delivery_man_id = $request->query('delivery_man_id', 'all');
        $delivery_man = is_numeric($delivery_man_id) ? DeliveryMan::findOrFail($delivery_man_id) : null;
        $rider_id = $request->query('rider_id', 'all');
        $rider = is_numeric($rider_id) ? DeliveryMan::rider()->findOrFail($rider_id) : null;
        $withdrawal_methods = WithdrawalMethod::ofStatus(1)->get();
        $status = $request->query('status', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $module_id = $request->query('module_id', 'all');

        $dis = DisbursementDetails::with(['store.vendor', 'delivery_man', 'rider', 'withdraw_method'])
        ->when((isset($tab) && ($tab == 'store')), function ($query) {
            return $query->whereNotNull('store_id');
        })
            ->when((isset($tab) && ($tab == 'delivery_man')), function ($query) {
                return $query->whereNotNull('delivery_man_id')->whereHas('delivery_man', function($q) {
                    $q->where('is_ride', 0);
                });
            })
            ->when((isset($tab) && ($tab == 'rider')), function ($query) {
                return $query->whereNotNull('delivery_man_id')->whereHas('rider', function($q) {
                    return $q;
                });
            })
            ->when((isset($zone) && ($tab == 'store')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($store) && ($tab == 'store')), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when((isset($module_id) &&  is_numeric($module_id)&& ($tab == 'store')), function ($query) use ($module_id) {
                return $query->whereHas('store', function ($query) use ($module_id) {
                    $query->where('module_id',$module_id);
                });
            })
            ->when((isset($zone) && ($tab == 'delivery_man')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($zone) && ($tab == 'rider')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($delivery_man) && ($tab == 'delivery_man')), function ($query) use ($delivery_man) {
                return $query->where('delivery_man_id', $delivery_man->id);
            })
            ->when((isset($rider) && ($tab == 'rider')), function ($query) use ($rider) {
                return $query->where('delivery_man_id', $rider->id);
            })
            ->when((isset($payment_method_id) && ($payment_method_id != 'all')), function ($query) use ($payment_method_id) {
                return $query->whereHas('withdraw_method',function($q)use ($payment_method_id){
                    $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->when((isset($status) && ($status != 'all')), function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when(isset($filter) , function ($query) use ($filter,$from, $to) {
                return $query->applyDateFilter($filter, $from, $to);
            })
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('disbursement_id', 'like', "%{$value}%")
                              ->orWhere('status', 'like', "%{$value}%")
                              ->orWhereHas('withdraw_method', function ($subQuery) use ($value) {
                                  $subQuery->where('method_name','like', "%{$value}%");
                              });
                    }
                });
            })
            ->latest();

        $totals = (clone $dis)->toBase()->selectRaw("
            sum(case when status = 'pending' then disbursement_amount else 0 end) as pending_total,
            sum(case when status = 'completed' then disbursement_amount else 0 end) as completed_total,
            sum(case when status = 'canceled' then disbursement_amount else 0 end) as canceled_total
        ")->first();

        $disbursements= $dis->paginate(config('default_pagination'))->withQueryString();

        $pending =(float) ($totals->pending_total ?? 0);
        $completed =(float) ($totals->completed_total ?? 0);
        $canceled =(float) ($totals->canceled_total ?? 0);

        return view('admin-views.report.disbursement-report', compact('disbursements','pending', 'completed','canceled','zone', 'store','filter','from','to','withdrawal_methods','status','payment_method_id','tab'));

    }
    public function disbursement_report_export(Request $request,$type,$tab = 'store')
    {
        if (! in_array($tab, ['store', 'delivery_man', 'rider'], true)) {
            $tab = 'store';
        }

        $from =  null;
        $to = null;
        $filter = $request->query('filter', 'all_time');
        if($filter == 'custom'){
            $from = $request->from ?? null;
            $to = $request->to ?? null;
        }
        $key = explode(' ', $request['search'] ?? '');
        $zone_id = $request->query('zone_id', auth('admin')?->user()?->zone_id ?: 'all');
        $zone = is_numeric($zone_id) ? Zone::findOrFail($zone_id) : null;
        $store_id = $request->query('store_id', 'all');
        $store = is_numeric($store_id) ? Store::findOrFail($store_id) : null;
        $delivery_man_id = $request->query('delivery_man_id', 'all');
        $delivery_man = is_numeric($delivery_man_id) ? DeliveryMan::findOrFail($delivery_man_id) : null;
        $rider_id = $request->query('rider_id', 'all');
        $rider = is_numeric($rider_id) ? DeliveryMan::rider()->findOrFail($rider_id) : null;
        $withdrawal_methods = WithdrawalMethod::ofStatus(1)->get();
        $status = $request->query('status', 'all');
        $payment_method_id = $request->query('payment_method_id', 'all');
        $module_id = $request->query('module_id', 'all');

        $disbursements = DisbursementDetails::with(['store', 'delivery_man', 'rider', 'withdraw_method'])
        ->when((isset($tab) && ($tab == 'store')), function ($query) {
            return $query->whereNotNull('store_id');
        })
            ->when((isset($tab) && ($tab == 'delivery_man')), function ($query) {
                return $query->whereNotNull('delivery_man_id')->whereHas('delivery_man', function($q) {
                    $q->where('is_ride', 0);
                });
            })
            ->when((isset($tab) && ($tab == 'rider')), function ($query) {
                return $query->whereNotNull('delivery_man_id')->whereHas('rider', function($q) {
                    return $q;
                });
            })
            ->when((isset($zone) && ($tab == 'store')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($store) && ($tab == 'store')), function ($query) use ($store) {
                return $query->where('store_id', $store->id);
            })
            ->when((isset($zone) && ($tab == 'delivery_man')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($zone) && ($tab == 'rider')), function ($query) use ($zone) {
                return $query->whereHas('store',function($q)use ($zone){
                    $q->where('zone_id', $zone->id);
                });
            })
            ->when((isset($module_id) &&  is_numeric($module_id)&& ($tab == 'store')), function ($query) use ($module_id) {
                return $query->whereHas('store', function ($query) use ($module_id) {
                    $query->where('module_id',$module_id);
                });
            })
            ->when((isset($delivery_man) && ($tab == 'delivery_man')), function ($query) use ($delivery_man) {
                return $query->where('delivery_man_id', $delivery_man->id);
            })
            ->when((isset($rider) && ($tab == 'rider')), function ($query) use ($rider) {
                return $query->where('delivery_man_id', $rider->id);
            })
            ->when((isset($payment_method_id) && ($payment_method_id != 'all')), function ($query) use ($payment_method_id) {
                return $query->whereHas('withdraw_method',function($q)use ($payment_method_id){
                    $q->where('withdrawal_method_id', $payment_method_id);
                });
            })
            ->when((isset($status) && ($status != 'all')), function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->when(isset($filter) , function ($query) use ($filter,$from, $to) {
                return $query->applyDateFilter($filter, $from, $to);
            })
            ->when($request['search'], function ($q) use ($key) {
                $q->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('disbursement_id', 'like', "%{$value}%")
                              ->orWhere('status', 'like', "%{$value}%")
                              ->orWhereHas('withdraw_method', function ($subQuery) use ($value) {
                                  $subQuery->where('method_name','like', "%{$value}%");
                              });
                    }
                });
            })
            ->latest()->orderBy('id', 'asc');

        $disbursements_count = (clone $disbursements)->count();
        $disbursements = $this->streamExportRows($disbursements);

        $data=[
            'type'=>$tab,
            'disbursements' =>$disbursements,
            'disbursements_count' =>$disbursements_count,
            'store'=>isset($store)?$store->name:null,
            'delivery_man'=>isset($delivery_man)?$delivery_man->f_name.' '.$delivery_man->l_name:null,
            'rider'=>isset($rider)?$rider->f_name.' '.$rider->l_name:null,
            'search'=>$request->search??null,
            'status'=>$status,
            'zone'=>isset($zone)?$zone->name:null,
            'filter'=>$filter,
            'from'=>(($filter == 'custom') && $from)?$from:null,
            'to'=>(($filter == 'custom') && $to)?$to:null,
            'pending' =>(float) $disbursements->where('status','pending')->sum('disbursement_amount'),
            'completed' =>(float) $disbursements->where('status','completed')->sum('disbursement_amount'),
            'canceled' =>(float) $disbursements->where('status','canceled')->sum('disbursement_amount'),
        ];
        if($type == 'csv'){
            return Excel::download(new DisbursementReportExport($data), 'DisbursementReport.csv');
        }
        return Excel::download(new DisbursementReportExport($data), 'DisbursementReport.xlsx');

    }
}
