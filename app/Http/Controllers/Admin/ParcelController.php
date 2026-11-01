<?php

namespace App\Http\Controllers\Admin;

use App\Models\Order;
use App\Scopes\ZoneScope;
use App\Services\Order\EtaService;
use App\Models\DeliveryMan;
use App\Models\Translation;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Exports\ParcelCancellationReasonExport;
use App\Models\BusinessSetting;
use App\Exports\ParcelOrderExport;
use App\Http\Controllers\Controller;
use App\Traits\Api\OrderListTrait;
use App\Models\ParcelCancellationReason;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Config;
use App\Models\ParcelDeliveryInstruction;


class ParcelController extends Controller
{
    use OrderListTrait;

    private function resolveParcelFilters(Request $request): array
    {
        if (session()->has('zone_filter') == false) {
            session()->put('zone_filter', 0);
        }

        Order::withOutGlobalScope(ZoneScope::class)->where(['checked' => 0, 'order_type' => 'parcel'])->update(['checked' => 1]);

        return [
            isset($request->search) ? explode(' ', $request->search) : ($request['amp;search'] ? explode(' ', $request['amp;search']) : null),
            session()->has('order_filter') ? json_decode(session('order_filter')) : $request,
        ];
    }

    private function parcelOrderQuery($filters, $key, string $status)
    {
        $query = Order::withOutGlobalScope(ZoneScope::class)
            ->when(isset($key), function ($query) use ($key) {
                return $this->applyOrderKeywordSearch($query, $key);
            })
            ->when(isset($filters->zone), function ($query) use ($filters) {
                return $query->whereIn('zone_id', (array) $filters->zone);
            });

        return $this->applyOrderStatusFilters($query, $status)
            ->when(isset($filters->vendor), function ($query) use ($filters) {
                return $query->whereHas('store', function ($query) use ($filters) {
                    return $query->whereIn('id', (array) $filters->vendor);
                });
            })
            ->when(isset($filters->orderStatus) && $status == 'all', function ($query) use ($filters) {
                return $query->whereIn('order_status', $filters->orderStatus);
            })
            ->when(isset($filters->scheduled) && $status == 'all', function ($query) {
                return $query->scheduled();
            })
            ->when(isset($filters->order_type), function ($query) use ($filters) {
                return $query->where('order_type', $filters->order_type);
            })
            ->when(isset($filters->from_date) && isset($filters->to_date) && $filters->from_date != null && $filters->to_date != null, function ($query) use ($filters) {
                return $query->whereBetween('created_at', [$filters->from_date . " 00:00:00", $filters->to_date . " 23:59:59"]);
            })
            ->when(isset($filters->payment_status) && $filters->payment_status == 'paid', function ($query) {
                return $query->where('payment_status', 'paid');
            })
            ->when(isset($filters->payment_status) && $filters->payment_status == 'unpaid', function ($query) {
                return $query->where('payment_status', 'unpaid');
            })
            ->when(isset($filters->payment_by) && $filters->payment_by == 'sender', function ($query) {
                return $query->where('charge_payer', 'sender');
            })
            ->when(isset($filters->payment_by) && $filters->payment_by == 'receiver', function ($query) {
                return $query->where('charge_payer', 'receiver');
            })
            ->ParcelOrder()
            ->module(Config::get('module.current_module_id'))
            ->orderBy('schedule_at', 'desc');
    }

    public function orders(Request $request, $status)
    {
        [$key, $filters] = $this->resolveParcelFilters($request);

        $orders = $this->parcelOrderQuery(filters: $filters, key: $key, status: $status)
            ->with(['customer', 'store', 'parcel_category'])
            ->paginate(config('default_pagination'));

        $orderstatus = isset($filters->orderStatus) ? $filters->orderStatus : [];
        $scheduled = isset($filters->scheduled) ? $filters->scheduled : 0;
        $vendor_ids = isset($filters->vendor) ? $filters->vendor : [];
        $zone_ids = isset($filters->zone) ? $filters->zone : [];
        $from_date = isset($filters->from_date) ? $filters->from_date : null;
        $to_date = isset($filters->to_date) ? $filters->to_date : null;
        $order_type = isset($filters->order_type) ? $filters->order_type : null;
        $payment_status = isset($filters->payment_status) ? $filters->payment_status : null;
        $payment_by = isset($filters->payment_by) ? $filters->payment_by : null;
        $total = $orders->total();

        return view('admin-views.order.parcel-list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total', 'payment_by', 'payment_status', 'order_type'));
    }



    public function parcel_orders_export(Request $request, $status, $file_type)
    {
        [$key, $filters] = $this->resolveParcelFilters($request);

        $orders = $this->parcelOrderQuery(filters: $filters, key: $key, status: $status)
            ->with(['customer', 'store', 'parcel_category', 'weight', 'dimension'])
            ->get();

        $data = [
            'orders' => $orders,
            'type' => 'parcel',
            'status' => $status,
            'order_status' => isset($filters->orderStatus) ? implode(', ', $filters->orderStatus) : null,
            'search' => $filters->search ?? null,
            'from' => $filters->from_date ?? null,
            'to' => $filters->to_date ?? null,
            'zones' => isset($filters->zone) ? Helpers::get_zones_name($filters->zone) : null,
        ];

        if ($file_type == 'excel') {
            return Excel::download(new ParcelOrderExport($data), 'ParcelOrders.xlsx');
        }
        return Excel::download(new ParcelOrderExport($data), 'ParcelOrders.csv');
    }

    public function order_details(Request $request, $id)
    {
        $order = Order::withOutGlobalScope(ZoneScope::class)->with(['customer' => function ($query) {
            return $query->with('storage')->withCount('orders');
        }, 'delivery_man' => function ($query) {
            return $query->withCount('orders');
        },'parcelCancellation','coupon','payments','offline_payments','orderProDiscount','parcel_category.storage','weight','dimension','zone','store','delivery_man.last_location'])->where(['id' => $id])->ParcelOrder()->first();
        if (isset($order)) {
            // Order::dm_last_location() is `$this->delivery_man?->last_location()` -- not a real
            // eager-loadable relation, since its return depends on instance state (null when
            // delivery_man is null). Left unset, the view's `$order->dm_last_location` access
            // makes Eloquent probe the method on a fresh empty Order to build the relation
            // object, which returns null and crashes with "Call to a member function
            // addEagerConstraints() on null". Setting it explicitly from the already-eager-loaded
            // delivery_man.last_location above avoids that probe entirely -- the same fix already
            // applied in OrderController::details()/view().
            $order->setRelation('dm_last_location', $order->delivery_man?->last_location);

            $isUnpaid = false;

            if (
                in_array($order->order_status, ['pending','failed']) &&
                !in_array($order->payment_method, ['cash_on_delivery', 'wallet'])
            ) {
                if ($order->payment_method == 'partial_payment') {
                    if ($order->payment_method === 'partial_payment') {
                        $isUnpaid = $order->payments()
                            ->where('payment_status', 'unpaid')
                            ->whereNotIn('payment_method', ['cash_on_delivery', 'wallet'])
                            ->exists();
                    }

                }

                elseif ($order->payment_method == 'offline_payment') {
                    if ($order?->offline_payments?->count() == 0) {
                        $isUnpaid = true;
                    }
                }

                else {
                    $isUnpaid = true;
                }
            }

            $order->is_unpaid_order = $isUnpaid ? true : false;



            // When the parcel doesn't require a specific vehicle, $order->dm_vehicle_id is null.
            // Eloquent's where('vehicle_id', null) auto-converts to whereNull('vehicle_id'), which
            // collapsed the intended "matching vehicle OR no vehicle set" OR-clause into just
            // "vehicle_id IS NULL" -- silently excluding every zone-matching, available deliveryman
            // who has any vehicle_id on file, even though this order places no vehicle requirement.
            // Only apply the vehicle filter when the order actually requires one, matching the
            // guarded pattern already used for non-parcel orders in OrderController::view().
            $deliveryMen = DeliveryMan::withOutGlobalScope(ZoneScope::class)->where('zone_id', $order->zone_id)
                ->when($order->dm_vehicle_id != null, function ($query) use ($order) {
                    $query->where(function ($query) use ($order) {
                        $query->where('vehicle_id', $order->dm_vehicle_id)->orWhereNull('vehicle_id');
                    });
                })
                ->available()->active()->get();
            $category = $request->query('category_id', 0);
            $categories = [];
            $products = [];
            $editing = false;
            $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);
            $keyword = null;

            // parcel-order-view renders the Estimated Delivery row from these two. Admin\OrderController
            // resolves them for the copy of this page it serves at /admin/order/details/{id}; this
            // route is the other way into the same view, and without them the row's `@if (!empty($eta))`
            // was simply never true -- an undefined variable reads as empty, so the ETA was missing
            // here while showing on every other module's details page.
            //
            // Null for a finished order, or a (zone, module) with no live ETA configuration -- the
            // view then omits the row (§11.2).
            $eta = app(EtaService::class)->forOrder($order);
            $etaWindow = app(EtaService::class)->panelWindow($eta);

            return view('admin-views.order.parcel-order-view', compact('order', 'deliveryMen', 'categories', 'products', 'category', 'keyword', 'editing', 'eta', 'etaWindow'));
        } else {
            Toastr::info(translate('messages.No more orders'));
            return back();
        }
    }

    public function settings()
    {
        $instructions = ParcelDeliveryInstruction::orderBy('id', 'desc')
            ->paginate(config('default_pagination'));

        // The two shipping-charge keys are gone from this screen (A14, 2026-09-03). A parcel is
        // priced by its zone's delivery rule plus the category's own additional charge, so the
        // deliveryman commission is the only setting left that still does anything here.
        $settings = Helpers::get_business_settings_many(['parcel_commission_dm']);

        $parcelCommissionDm = $settings['parcel_commission_dm'] ?? [null];

        $language = Helpers::get_business_settings('language') ?? [];

        return view('admin-views.parcel.settings', compact('instructions', 'parcelCommissionDm', 'language'));
    }

    public function update_settings(Request $request)
    {
        $request->validate([
            'parcel_commission_dm' => 'required|numeric|min:0',
        ], [
            'parcel_commission_dm.required' => translate('Deliveryman commission is required'),
            'parcel_commission_dm.numeric' => translate('Deliveryman commission must be a number'),
            'parcel_commission_dm.min' => translate('Deliveryman commission cannot be negative'),
        ]);

        Helpers::businessUpdateOrInsert(['key' => 'parcel_commission_dm'], ['value' => $request->parcel_commission_dm]);

        Toastr::success(translate('messages.Parcel settings updated'));
        return back();
    }

    public function dispatch_list($status, Request $request)
    {

        $key = isset($request->search) ? explode(' ', $request->search) : ($request['amp;search'] ? explode(' ', $request['amp;search']) : null);
        $module_id = $request->query('module_id', null);

        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
            $zone_ids = isset($request->zone) ? $request->zone : 0;
        }

        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($key), function ($query) use ($key) {
                return $this->applyOrderKeywordSearch($query, $key);
            })
            ->when(isset($request->zone), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('zone_id', $request->zone);
                });
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->ParcelOrder()
            ->module(Config::get('module.current_module_id'))
            ->OrderScheduledIn(30)
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $total = $orders->total();

        return view('admin-views.order.distaptch_list', compact('orders', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total'));
    }
    public function parcel_dispatch_list($module, $status, Request $request)
    {
        $key = isset($request->search) ? explode(' ', $request->search) : ($request['amp;search'] ? explode(' ', $request['amp;search']) : null);
        $module_id = $request->query('module_id', null);
        $zone_ids = [];
        if (session()->has('order_filter')) {
            $request = json_decode(session('order_filter'));
            $zone_ids = isset($request->zone) ? $request->zone : 0;
        }


        Order::where(['checked' => 0])->update(['checked' => 1]);

        $orders = Order::with(['customer', 'store'])
            ->whereHas('module', function ($query) use ($module) {
                $query->where('id', $module);
            })
            ->when(isset($key), function ($query) use ($key) {
                return $this->applyOrderKeywordSearch($query, $key);
            })
            ->when(isset($module_id), function ($query) use ($module_id) {
                return $query->module($module_id);
            })
            ->when(isset($request->zone), function ($query) use ($zone_ids) {
                return $query->whereIn('zone_id', $zone_ids);
            })
            ->when($status == 'searching_for_deliverymen', function ($query) {
                return $query->SearchingForDeliveryman();
            })
            ->when($status == 'on_going', function ($query) {
                return $query->Ongoing();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('store', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->ParcelOrder()
            ->OrderScheduledIn(30)
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        $orderstatus = isset($request->orderStatus) ? $request->orderStatus : [];
        $scheduled = isset($request->scheduled) ? $request->scheduled : 0;
        $vendor_ids = isset($request->vendor) ? $request->vendor : [];
        $zone_ids = isset($request->zone) ? $request->zone : [];
        $from_date = isset($request->from_date) ? $request->from_date : null;
        $to_date = isset($request->to_date) ? $request->to_date : null;
        $total = $orders->total();
        $parcel = true;
        return view('admin-views.order.distaptch_list', compact('orders', 'module', 'status', 'orderstatus', 'scheduled', 'vendor_ids', 'zone_ids', 'from_date', 'to_date', 'total', 'parcel'));
    }

    public function instruction(Request $request)
    {
        $request->validate([
            'instruction' => 'required|max:191',
            'instruction.0' => 'required',
        ], [
            'instruction.0.required' => translate('Default instruction is required'),
        ]);

        $instruction = new ParcelDeliveryInstruction();
        $instruction->instruction = $request->instruction[array_search('default', $request->lang)];
        $instruction->save();
        $data = [];
        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang as $index => $key) {
            if ($default_lang == $key && !($request->instruction[$index])) {
                if ($key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelDeliveryInstruction',
                        'translationable_id' => $instruction->id,
                        'locale' => $key,
                        'key' => 'instruction',
                        'value' => $instruction->instruction,
                    ));
                }
            } else {
                if ($request->instruction[$index] && $key != 'default') {
                    array_push($data, array(
                        'translationable_type' => 'App\Models\ParcelDeliveryInstruction',
                        'translationable_id' => $instruction->id,
                        'locale' => $key,
                        'key' => 'instruction',
                        'value' => $request->instruction[$index],
                    ));
                }
            }
        }
        Translation::insert($data);
        Toastr::success(translate('Added successfully'));
        return back();
    }
    public function instruction_edit(Request $request)
    {
        $request->validate([
            'instruction' => 'required|max:191',
            'instruction.0' => 'required',
        ], [
            'instruction.0.required' => translate('Default instruction is required'),
        ]);
        $instruction = ParcelDeliveryInstruction::findOrFail($request->instruction_id);
        $instruction->instruction = $request->instruction[array_search('default', $request->lang1)];
        $instruction->save();

        $default_lang = str_replace('_', '-', app()->getLocale());
        foreach ($request->lang1 as $index => $key) {
            if ($default_lang == $key && !($request->instruction[$index])) {
                if ($key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelDeliveryInstruction',
                            'translationable_id' => $instruction->id,
                            'locale' => $key,
                            'key' => 'instruction'
                        ],
                        ['value' => $instruction->instruction]
                    );
                }
            } else {
                if ($request->instruction[$index] && $key != 'default') {
                    Translation::updateOrInsert(
                        [
                            'translationable_type' => 'App\Models\ParcelDeliveryInstruction',
                            'translationable_id' => $instruction->id,
                            'locale' => $key,
                            'key' => 'instruction'
                        ],
                        ['value' => $request->instruction[$index]]
                    );
                }
            }
        }


        Toastr::success(translate('Updated successfully'));
        return back();
    }
    public function instruction_delete(Request $request)
    {
        $instruction = ParcelDeliveryInstruction::findOrFail($request->id);
        $instruction?->translations()?->delete();
        $instruction->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }
    public function instruction_status(Request $request)
    {
        $instruction = ParcelDeliveryInstruction::findOrFail($request->id);
        $instruction->status = $request->status;
        $instruction->save();
        Toastr::success(translate('messages.Status updated'));
        return back();
    }

    public function cancellationSettings(Request $request)
    {
        $parcel_cancellation_status =1?? Helpers::get_business_settings('parcel_cancellation_status');
        $parcel_cancellation_basic_setup = Helpers::get_business_settings('parcel_cancellation_basic_setup');
        $parcel_return_time_fee = Helpers::get_business_settings('parcel_return_time_fee');
        $language = Helpers::get_business_settings('language') ?? [];
        $key = isset($request->search) ? explode(' ', $request->search) : null;

        $cancellationReasons = ParcelCancellationReason::select('id', 'reason', 'cancellation_type', 'user_type', 'status')
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('reason', 'like', "%{$value}%")->orWhere('cancellation_type', 'like', "%{$value}%")->orWhere('user_type', 'like', "%{$value}%");
                    }
                });
            })
            ->paginate(config('default_pagination'));

        return view('admin-views.parcel.parcel-cancellation-setup', compact('parcel_cancellation_status', 'parcel_cancellation_basic_setup', 'parcel_return_time_fee', 'language', 'cancellationReasons'));
    }

    public function cancellationSettingsStatus()
    {

        $status = Helpers::get_business_settings('parcel_cancellation_status');
        if (isset($status) == false) {
            Helpers::businessUpdateOrInsert(['key' => 'parcel_cancellation_status'], ['value' => 1]);
        } else {
            $status = $status == 1 ? 0 : 1;
            Helpers::businessUpdateOrInsert(['key' => 'parcel_cancellation_status'], ['value' => $status]);
        }
        Toastr::success(translate('messages.Parcel cancellation status updated'));
        return back();
    }

    public function cancellationSettingsUpdate(Request $request)
    {
        $configs = [
            'parcel_cancellation_basic_setup' => [
                'return_fee_status' => $request->input('return_fee_status', 0),
                'return_fee'        => $request->input('return_fee', 0),
                'do_not_charge_return_fee_on_deliveryman_cancel'=> $request->input('do_not_charge_return_fee_on_deliveryman_cancel', 0),
            ],
            'parcel_return_time_fee' => [
                'status'             => $request->input('status', 0),
                'parcel_return_time' => $request->input('parcel_return_time', 0),
                'return_time_type'   => $request->input('return_time_type', 'day'),
                'return_fee_for_dm'  => $request->input('return_fee_for_dm', 0),
            ],
        ];

        foreach ($configs as $key => $data) {
            $setting = BusinessSetting::firstOrNew(['key' => $key]);
            $setting->value = json_encode($data);
            $setting->save();
        }
        Toastr::success(translate('messages.Parcel cancellation setup updated'));
        return back();
    }

    public function cancellationReason(Request $request)
    {
        $request->validate([
            'reason.0' => 'required',
            'reason.*' => 'max:150',
            'cancellation_type' => 'required|in:before_pickup,after_pickup',
            'user_type' => 'required|in:admin,deliveryman,vendor,customer',
        ], [
            'reason.0.required' => translate('Default reason is required'),
            'reason.*.max' => translate('Reason is too long.') . ' ' . translate('Character limit') . ': 150',
            'cancellation_type.required' => translate('Default cancellation type is required'),
            'user_type.required' => translate('Default user type is required'),
        ]);

        $cancellation_reason = new ParcelCancellationReason();
        $cancellation_reason->reason = $request->reason[array_search('default', $request->lang)];
        $cancellation_reason->cancellation_type = $request->cancellation_type;
        $cancellation_reason->user_type = $request->user_type;
        $cancellation_reason->save();

        Helpers::add_or_update_translations(request: $request, key_data: 'reason', name_field: 'reason', model_name: ParcelCancellationReason::class, data_id: $cancellation_reason->id, data_value: $cancellation_reason->reason, model_class: true);
        Toastr::success(translate('Added successfully'));
        return back();
    }
    public function cancellationReasonStatus(ParcelCancellationReason $reason)
    {
        $reason->status = !$reason->status;
        $reason->save();
        Toastr::success(translate('messages.Status updated'));
        return back();
    }
    public function cancellationReasonDelete(ParcelCancellationReason $reason)
    {
        $reason->translations()->delete();
        $reason->delete();
        Toastr::success(translate('Deleted successfully'));
        return back();
    }
    public function cancellationReasonUpdate(ParcelCancellationReason $reason, Request $request)
    {
        $reason->reason = $request->reason[array_search('default', $request->lang)];
        $reason->cancellation_type = $request->cancellation_type;
        $reason->user_type = $request->user_type;
        $reason->save();
        Helpers::add_or_update_translations(request: $request, key_data: 'reason', name_field: 'reason', model_name: ParcelCancellationReason::class, data_id: $reason->id, data_value: $reason->reason, model_class: true);

        Toastr::success(translate('Updated successfully'));
        return back();
    }

    public function cancellationReasonEdit(ParcelCancellationReason $reason)
    {
        $reason = $reason->load('translations');
        $language = Helpers::get_business_settings('language') ?? [];
        return response()->json([
            'view' => view('admin-views.parcel.parcel-cancellation-reason-edit', compact('reason', 'language'))->render(),
        ]);
    }
    public function cancellationReasonExport(Request $request)
    {
        $key = isset($request->search) ? explode(' ', $request->search) : null;
        $cancellationReasons = ParcelCancellationReason::select('id', 'reason', 'cancellation_type', 'user_type', 'status')
            ->when(isset($key), function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('reason', 'like', "%{$value}%")->orWhere('cancellation_type', 'like', "%{$value}%")->orWhere('user_type', 'like', "%{$value}%");
                    }
                });
            })
            ->get();

        $data = [
            'data' => $cancellationReasons,
            'search' => $request['search'] ?? null,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new ParcelCancellationReasonExport($data), 'Parcel_Cancellation_Reason.csv');
        }
        return Excel::download(new ParcelCancellationReasonExport($data), 'Parcel_Cancellation_Reason.xlsx');
    }
}
