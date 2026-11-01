<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Models\Order;
use App\Models\Store;
use App\Models\Newsletter;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\CustomerListExport;
use App\Exports\CustomerOrderExport;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Pagination\Paginator;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SubscriberListExport;
use Modules\Builder\Entities\TenantDomainConfig;
use Modules\Rental\Entities\Trips;
use Modules\Rental\Exports\TripExport;

use function Illuminate\Support\defer;
use App\Support\Notification\SendNotification;

class CustomerController extends Controller
{
    public function customer_list(Request $request)
    {
        $builder_published = (bool) addon_published_status('Builder');
        $tab = $request->tab === 'storefront' && $builder_published ? 'storefront' : 'main';

        $publishedStoreIds = $builder_published
            ? TenantDomainConfig::where('website_visibility', true)->pluck('sub_tenant_id')->unique()->values()
            : collect();
        $storefronts = $builder_published
            ? Store::whereIn('id', $publishedStoreIds)->select('id', 'name')->orderBy('name')->get()
            : collect();
        $publishedStoreLookup = $storefronts->pluck('name', 'id')->all();

        $storefront_id = $request->storefront_id;
        if ($storefront_id !== null && !$publishedStoreIds->contains((int) $storefront_id)) {
            $storefront_id = null;
        }

        $zone_id=  $request->zone_id ?? null;
        $filter=  $request->filter ?? null;
        $order_wise=  $request->order_wise ?? null;
        $show_limit=  $request->show_limit ?? null;
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search'] ?? '');
        }

        $order_date_start = null;
        $order_date_end =null;

        $join_date_start =null;
        $join_date_end = null;

        if($request?->order_date){
            list($order_date_start, $order_date_end) = explode(' - ', $request?->order_date);
            $order_date_start = Carbon::createFromFormat('m/d/Y', $order_date_start)->startOfDay();
            $order_date_end = Carbon::createFromFormat('m/d/Y', $order_date_end)->endOfDay();
        }
        if($request?->join_date){
            list($join_date_start, $join_date_end) = explode(' - ', $request?->join_date);
            $join_date_start = Carbon::createFromFormat('m/d/Y', $join_date_start)->startOfDay();
            $join_date_end = Carbon::createFromFormat('m/d/Y', $join_date_end)->endOfDay();
        }




        $customers = User::withStorage()->when(count($key) > 0, function ($query) use ($key) {
            foreach ($key as $value) {
                $query->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            };
        })->withcount('orders')
        ->withSum('orders as total_order_amount', 'order_amount')
        ->when($tab === 'main', function ($query) {
            $query->where('tenant_id', 0)->where('sub_tenant_id', 0);
        })
        ->when($tab === 'storefront', function ($query) {
            $query->where('sub_tenant_id', '>', 0);
        })
        ->when($tab === 'storefront' && $storefront_id, function ($query) use ($storefront_id) {
            $query->where('sub_tenant_id', (int) $storefront_id);
        })
        ->when(isset($request->join_date) , function ($query) use($join_date_start, $join_date_end) {
            $query->WhereBetween('created_at', [$join_date_start, $join_date_end]);
        })
        ->when(isset($request->order_date) , function ($query) use($order_date_start, $order_date_end) {
            $query->wherehas('orders',function ($query) use($order_date_start, $order_date_end){
                $query->WhereBetween('created_at', [$order_date_start, $order_date_end]);
            });
        })
        ->when(isset($zone_id) && is_numeric($zone_id) , function ($query) use($zone_id){
            $query->where('zone_id' ,$zone_id);
        })
        ->when(isset($filter) && $filter == 'active' , function ($query) {
            $query->where('status' ,1);
        })
        ->when(isset($filter) && $filter == 'blocked' , function ($query) {
            $query->where('status' ,0);
        })
        ->when(isset($filter) && $filter == 'new' , function ($query) {
            $query->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
        })
        ->when(isset($order_wise) && $order_wise == 'top' , function ($query) {
            $query->orderBy('orders_count', 'desc');
        })
        ->when(isset($order_wise) && $order_wise == 'least' , function ($query) {
            $query->orderBy('orders_count', 'asc');
        })
        ->when(isset($order_wise) && $order_wise == 'latest' , function ($query) {
            $query->latest();
        })
        ->when(isset($order_wise) && $order_wise == 'oldest' , function ($query) {
            $query->oldest();
        })
        ->when(isset($order_wise) && $order_wise == 'order_amount', function ($query) {
            $query->orderByDesc('total_order_amount');
        })
        ->when(!$order_wise, function ($query) {
            $query->orderBy('orders_count', 'desc');
        });


        if(isset($show_limit) && $show_limit > 0 ){
            $customers= $customers->take($show_limit)->get();
            $perPage = config('default_pagination');
            $page =  $request?->page ?? 1;
            $offset = ($page - 1) * $perPage;
            $itemsForCurrentPage = $customers->slice($offset, $perPage);
            $customers = new \Illuminate\Pagination\LengthAwarePaginator(
                $itemsForCurrentPage,
                $customers->count(),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
            );


        } else{
            $customers=$customers->paginate(config('default_pagination'));
        }


        return view('admin-views.customer.list', compact(
            'customers',
            'tab',
            'builder_published',
            'storefronts',
            'publishedStoreLookup',
            'storefront_id',
        ));
    }

    public function status(User $customer, Request $request)
    {
        $request->validate([
            'status' => 'required|in:0,1',
        ]);

        $status = (int) $request->status;

        try {
            $customer->status = $status;
            $customer->save();

            if ($status == 0) {
                $customer->tokens()->delete();
            }
        } catch (\Exception $e) {
            Log::error('Customer status update failed for user '.$customer->id.': '.$e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['message' => translate('messages.Status update failed')], 500);
            }

            Toastr::error(translate('messages.Status update failed'));
            return back();
        }

        // The FCM push and the SMTP mail are slow third party round trips. Running them after the
        // response is flushed keeps the toggle instant instead of blocking the admin for seconds.
        defer(fn () => $this->sendCustomerStatusNotifications($customer, $status));

        $message = $status
            ? translate('messages.Customer activated successfully')
            : translate('messages.Customer blocked successfully');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $status,
                'message' => $message,
            ]);
        }

        Toastr::success($message);
        return back();
    }

    private function sendCustomerStatusNotifications(User $customer, int $status): void
    {
        $suspended = $status == 0;
        $email = $customer->getRawOriginal('email');

        try {
            $push_key = $suspended ? 'customer_account_block' : 'customer_account_unblock';

            if (isset($customer->cm_firebase_token) && SendNotification::channelEnabled('customer', $push_key, 'push_notification_status')) {
                $data = $suspended
                    ? [
                        'title' => translate('messages.suspended'),
                        'description' => translate('messages.Your account has been blocked'),
                        'order_id' => '',
                        'image' => '',
                        'type' => 'block'
                    ]
                    : [
                        'title' => translate('messages.Account activation'),
                        'description' => translate('messages.Your account has been activated'),
                        'order_id' => '',
                        'image' => '',
                        'type' => 'unblock'
                    ];

                SendNotification::pushToCustomer($customer->id, $customer->cm_firebase_token, $data);
            }

            $mail_key = $suspended ? 'suspend_mail_status_user' : 'unsuspend_mail_status_user';

            if ($email && config('mail.status') && SendNotification::mailTemplateEnabled($mail_key) && SendNotification::channelEnabled('customer', $push_key, 'mail_status')) {
                SendNotification::mail($email, new \App\Mail\UserStatus($suspended ? 'suspended' : 'unsuspended', $customer->f_name.' '.$customer->l_name));
            }
        } catch (\Exception $e) {
            // The response is already sent at this point, so surface the failure in the log instead of a toast.
            Log::error('Customer status notification failed for user '.$customer->id.': '.$e->getMessage());
        }
    }

    public function search(Request $request)
    {
        $builder_published = (bool) addon_published_status('Builder');
        $tab = $request->tab === 'storefront' && $builder_published ? 'storefront' : 'main';

        $publishedStoreIds = $builder_published
            ? TenantDomainConfig::where('website_visibility', true)->pluck('sub_tenant_id')->unique()->values()
            : collect();
        $publishedStoreLookup = $builder_published
            ? Store::whereIn('id', $publishedStoreIds)->pluck('name', 'id')->all()
            : [];

        $storefront_id = $request->storefront_id;
        if ($storefront_id !== null && !$publishedStoreIds->contains((int) $storefront_id)) {
            $storefront_id = null;
        }

        $key = explode(' ', $request['search'] ?? '');
        $customers = User::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            }
        })
            ->when($tab === 'main', function ($query) {
                $query->where('tenant_id', 0)->where('sub_tenant_id', 0);
            })
            ->when($tab === 'storefront', function ($query) {
                $query->where('sub_tenant_id', '>', 0);
            })
            ->when($tab === 'storefront' && $storefront_id, function ($query) use ($storefront_id) {
                $query->where('sub_tenant_id', (int) $storefront_id);
            })
            ->orderBy('order_count', 'desc')->limit(50)->get();
        return response()->json([
            'count' => count($customers),
            'view' => view('admin-views.customer.partials._table', compact('customers', 'tab', 'publishedStoreLookup'))->render()
        ]);
    }

    public function view(Request $request,$id)
    {
        $key = $request['search'];
        $customer = User::withStorage()->with('addresses')->find($id);
        if (isset($customer)) {
            $total_order_amount = Order::selectRaw('sum(order_amount) as total_order_amount')->latest()->where(['user_id' => $id])
                ->when(isset($request['search']), function($query) use($key){
                    $query->Where('id', 'like', "%{$key}%");
                } )
                ->Notpos()->get();
            $orders = Order::with('store')->withcount('details')->latest()->where(['user_id' => $id])
            ->when(isset($request['search']), function($query) use($key){
                $query->Where('id', 'like', "%{$key}%");
            } )
            ->Notpos()->paginate(config('default_pagination'));
            $moduleType = 'normal';
            return view('admin-views.customer.customer-view', compact('customer', 'orders','total_order_amount','moduleType'));
        }
        Toastr::error(translate('No data found'));
        return back();
    }

    public function rentalView(Request $request,$id)
    {
        $key = $request['search'];
        $customer = User::withStorage()->with('addresses')->find($id);
        if (isset($customer)) {
            $total_trips_amount = Trips::selectRaw('sum(trip_amount) as total_trip_amount')->latest()->where(['user_id' => $id])
                ->when(isset($request['search']), function($query) use($key){
                    $query->Where('id', 'like', "%{$key}%");
                })->get();
            $trips = Trips::with('provider')->withcount('trip_details')->latest()->where(['user_id' => $id])
            ->when(isset($request['search']), function($query) use($key){
                $query->Where('id', 'like', "%{$key}%");
            })->paginate(config('default_pagination'));
            $moduleType = 'rental';
            return view('admin-views.customer.customer-rental-view', compact('customer', 'trips','total_trips_amount','moduleType'));
        }
        Toastr::error(translate('No data found'));
        return back();
    }

    public function customer_order_export(Request $request)
    {
        $customer = User::find($request->id);

        if (!$customer) {
            Toastr::error(translate('No data found'));

            return back();
        }


        $orders = Order::with(['orderProDiscount'])->latest()->where(['user_id' => $request->id])->Notpos()->get();

        $data = [
            'orders'=>$orders,
            'customer_id'=>$customer->id,
            'customer_name'=>$customer->f_name.' '.$customer->l_name,
            'customer_phone'=>$customer->phone,
            'customer_email'=>$customer->email,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CustomerOrderExport($data), 'CustomerOrders.csv');
        }
    }

    public function customer_trip_export(Request $request)
    {
        $key = explode(' ', $request['search'] ?? '');

        $trips = Trips::latest()->where(['user_id' => $request->id])
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%");
                    }
                });
            })
            ->get();

        $data = [
            'data' => $trips,
            'search' => $request['search'] ?? null,
        ];

        if ($request->type == 'excel') {
            return Excel::download(new TripExport($data), 'CustomerTrips.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new TripExport($data), 'CustomerTrips.csv');
        }
    }

    public function subscribedCustomers(Request $request)
    {
        $filter=  $request->filter ?? null;
        $show_limit=  $request->show_limit ?? null;
        $join_date_start =null;
        $join_date_end = null;
        if($request?->join_date){
            list($join_date_start, $join_date_end) = explode(' - ', $request?->join_date);
            $join_date_start = Carbon::createFromFormat('m/d/Y', $join_date_start)->startOfDay();
            $join_date_end = Carbon::createFromFormat('m/d/Y', $join_date_end)->endOfDay();
        }

        $key = explode(' ', $request['search'] ?? '');


        $customers = Newsletter::when($request['search'], function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('newsletters.email', 'like', "%". $value."%");
                }
            });
        })
        ->when(isset($request->join_date) , function ($query) use($join_date_start, $join_date_end) {
            $query->WhereBetween('newsletters.created_at', [$join_date_start, $join_date_end]);
        });

        $stats = [
            'total' => (clone $customers)->count(),
            'registered' => (clone $customers)
                ->join('users', 'users.email', '=', 'newsletters.email')
                ->distinct()->count('newsletters.id'),
            'this_month' => (clone $customers)
                ->where('newsletters.created_at', '>=', now()->startOfMonth())->count(),
        ];
        $stats['guest'] = max($stats['total'] - $stats['registered'], 0);

        if(isset($filter) && $filter == 'oldest' ){
            $customers=$customers->oldest();
            } else{
                $customers=$customers->latest();
        }

        if(isset($show_limit) && $show_limit > 0 ){
            $customers= $customers->take($show_limit)->get();
            $perPage = config('default_pagination');
            $page =  $request?->page ?? 1;
            $offset = ($page - 1) * $perPage;
            $itemsForCurrentPage = $customers->slice($offset, $perPage);
            $customers = new \Illuminate\Pagination\LengthAwarePaginator(
                $itemsForCurrentPage,
                $customers->count(),
                $perPage,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
            );


        } else{
            $customers=$customers->paginate(config('default_pagination'));
        }

        $data['subscribedCustomers'] = $customers;
        $data['stats'] = $stats;
        $data['linkedCustomers'] = $this->linked_customers_for_subscribers($customers->getCollection());

        return view('admin-views.customer.subscribed-emails', $data);
    }

    private function linked_customers_for_subscribers($subscribers)
    {
        $emails = $subscribers->pluck('email')->filter()->unique()->values();

        if ($emails->isEmpty()) {
            return collect();
        }

        return User::whereIn('email', $emails)
            ->select(['id', 'f_name', 'l_name', 'email', 'phone', 'image', 'status', 'order_count', 'pro_status', 'created_at'])
            ->with('storage')
            ->get()
            ->keyBy(fn ($user) => strtolower($user->email));
    }

    public function subscribed_customer_export(Request $request){
        $key = explode(' ', $request['search'] ?? '');

        $filter=  $request->filter ?? null;
        $show_limit=  $request->show_limit ?? null;
        $join_date_start =null;
        $join_date_end = null;
        if($request?->join_date){
            list($join_date_start, $join_date_end) = explode(' - ', $request?->join_date);
            $join_date_start = Carbon::createFromFormat('m/d/Y', $join_date_start)->startOfDay();
            $join_date_end = Carbon::createFromFormat('m/d/Y', $join_date_end)->endOfDay();
        }



        $customers = Newsletter::when($request['search'], function($query) use($key) {
            $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('email', 'like', "%". $value."%");
                }
            });
        })
        ->when(isset($request->join_date) , function ($query) use($join_date_start, $join_date_end) {
            $query->WhereBetween('created_at', [$join_date_start, $join_date_end]);
        });

        if(isset($filter) && $filter == 'oldest' ){
            $customers=$customers->oldest();
            } else{
                $customers=$customers->latest();
        }

        if(isset($show_limit) && $show_limit > 0 ){
            $customers= $customers->take($show_limit)->get();
        } else{
            $customers= $customers->get();
        }


        $data = [
            'customers'=>$customers
        ];

        if ($request->type == 'excel') {
            return Excel::download(new SubscriberListExport($data), 'Subscribers.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new SubscriberListExport($data), 'Subscribers.csv');
        }
    }



    public function get_customers(Request $request){
        $key = explode(' ', $request['q'] ?? '');
        $data = User::query()
        ->where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('f_name', 'like', "%{$value}%")
                ->orWhere('l_name', 'like', "%{$value}%")
                ->orWhere('phone', 'like', "%{$value}%");
            }
        })
        ->limit(8)
        ->get([DB::raw('id, CONCAT(f_name, " ", l_name, " (", phone ,")") as text')])
        ->makeHidden('image_full_url');
        if($request->all) $data[]=(object)['id'=>false, 'text'=>translate('All')];


        return response()->json($data);
    }

    public function settings()
    {
        $data = BusinessSetting::where('key','like','wallet_%')
            ->orWhere('key','like','loyalty_%')
            ->orWhere('key','like','ref_earning_%')
            ->orWhere('key','like','ref_earning_%')->get();
        $data = array_column($data->toArray(), 'value','key');
        return view('admin-views.customer.settings', compact('data'));
    }

    public function update_settings(Request $request)
    {
        if (getEnvMode()== 'demo') {
            Toastr::info(translate('messages.Update option is disable for demo'));
            return back();
        }

        $request->validate([
            'add_fund_bonus'=>'nullable|numeric|max:100|min:0',
            'loyalty_point_exchange_rate'=>'nullable|numeric',
            'ref_earning_exchange_rate'=>'nullable|numeric',
        ]);
            $keys=['guest_checkout_status','toggle_veg_non_veg','wallet_status','wallet_add_refund','add_fund_status','loyalty_point_status','loyalty_point_exchange_rate','loyalty_point_item_purchase_point',
                    'loyalty_point_minimum_point','ref_earning_status','ref_earning_exchange_rate','new_customer_discount_status',
                    'new_customer_discount_amount_type','new_customer_discount_validity_type','new_customer_discount_amount','new_customer_discount_amount_validity',
                    'pro_member_status',
                    'customer_personalization_status',
                ];

        foreach ($keys as $key) {
            Helpers::businessUpdateOrInsert(['key' => $key], [
                'value' => $request->$key ?? 0,
            ]);
        }
        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function export(Request $request){
        $builder_published = (bool) addon_published_status('Builder');
        $tab = $request->tab === 'storefront' && $builder_published ? 'storefront' : 'main';

        $publishedStoreIds = $builder_published
            ? TenantDomainConfig::where('website_visibility', true)->pluck('sub_tenant_id')->unique()->values()
            : collect();

        $storefront_id = $request->storefront_id;
        if ($storefront_id !== null && !$publishedStoreIds->contains((int) $storefront_id)) {
            $storefront_id = null;
        }

        $zone_id=  $request->zone_id ?? null;
        $filter=  $request->filter ?? null;
        $order_wise=  $request->order_wise ?? null;
        $show_limit=  $request->show_limit ?? null;
        $key = [];
        if ($request->search) {
            $key = explode(' ', $request['search'] ?? '');
        }

        $order_date_start = null;
        $order_date_end =null;

        $join_date_start =null;
        $join_date_end = null;

        if($request?->order_date){
            list($order_date_start, $order_date_end) = explode(' - ', $request?->order_date);
            $order_date_start = Carbon::createFromFormat('m/d/Y', $order_date_start)->startOfDay();
            $order_date_end = Carbon::createFromFormat('m/d/Y', $order_date_end)->endOfDay();
        }
        if($request?->join_date){
            list($join_date_start, $join_date_end) = explode(' - ', $request?->join_date);
            $join_date_start = Carbon::createFromFormat('m/d/Y', $join_date_start)->startOfDay();
            $join_date_end = Carbon::createFromFormat('m/d/Y', $join_date_end)->endOfDay();
        }



        $customers = User::when(count($key) > 0, function ($query) use ($key) {
            foreach ($key as $value) {
                $query->orWhere('f_name', 'like', "%{$value}%")
                    ->orWhere('l_name', 'like', "%{$value}%")
                    ->orWhere('email', 'like', "%{$value}%")
                    ->orWhere('phone', 'like', "%{$value}%");
            };
        })->withcount('orders')
        ->withSum('orders as total_order_amount', 'order_amount')
        ->when($tab === 'main', function ($query) {
            $query->where('tenant_id', 0)->where('sub_tenant_id', 0);
        })
        ->when($tab === 'storefront', function ($query) {
            $query->where('sub_tenant_id', '>', 0);
        })
        ->when($tab === 'storefront' && $storefront_id, function ($query) use ($storefront_id) {
            $query->where('sub_tenant_id', (int) $storefront_id);
        })
        ->when(isset($request->join_date) , function ($query) use($join_date_start, $join_date_end) {
            $query->WhereBetween('created_at', [$join_date_start, $join_date_end]);
        })
        ->when(isset($request->order_date) , function ($query) use($order_date_start, $order_date_end) {
            $query->wherehas('orders',function ($query) use($order_date_start, $order_date_end){
                $query->WhereBetween('created_at', [$order_date_start, $order_date_end]);
            });
        })
        ->when(isset($zone_id) && is_numeric($zone_id) , function ($query) use($zone_id){
            $query->where('zone_id' ,$zone_id);
        })
        ->when(isset($filter) && $filter == 'active' , function ($query) {
            $query->where('status' ,1);
        })
        ->when(isset($filter) && $filter == 'blocked' , function ($query) {
            $query->where('status' ,0);
        })
        ->when(isset($filter) && $filter == 'new' , function ($query) {
            $query->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
        })
        ->when(isset($order_wise) && $order_wise == 'top' , function ($query) {
            $query->orderBy('orders_count', 'desc');
        })
        ->when(isset($order_wise) && $order_wise == 'least' , function ($query) {
            $query->orderBy('orders_count', 'asc');
        })
        ->when(isset($order_wise) && $order_wise == 'latest' , function ($query) {
            $query->latest();
        })
        ->when(isset($order_wise) && $order_wise == 'oldest' , function ($query) {
            $query->oldest();
        })
        ->when(isset($order_wise) && $order_wise == 'order_amount', function ($query) {
            $query->orderByDesc('total_order_amount');
        })
        ->when(!$order_wise, function ($query) {
            $query->orderBy('orders_count', 'desc');
        });


        if(isset($show_limit) && $show_limit > 0 ){
            $customers= $customers->take($show_limit)->get();
            } else{
            $customers= $customers->get();
        }


        if($order_wise == 'top'){
            $order_wise = translate('messages.Sort by Orders');
        }elseif ($order_wise == 'order_amount'){
            $order_wise = translate('messages.Sort by order amount');
        }elseif ($order_wise == 'oldest'){
            $order_wise = translate('messages.Sort by First created');
        }elseif ($order_wise == 'latest'){
            $order_wise =  translate('messages.Sort by newest');
        }


        $data = [
            'customers'=>$customers,
            'filter'=>$request->filter ?? null,
            'order_wise'=>$order_wise ?? null,
            'show_limit'=>$request->show_limit ?? null,
            'order_date'=>$request?->order_date,
            'join_date'=>$request?->join_date,
            'search'=>$request->search??null,

        ];

        if ($request->type == 'excel') {
            return Excel::download(new CustomerListExport($data), 'Customers.xlsx');
        } else if ($request->type == 'csv') {
            return Excel::download(new CustomerListExport($data), 'Customers.csv');
        }
    }
}
