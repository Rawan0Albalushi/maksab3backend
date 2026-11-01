@extends('layouts.admin.app')

@section('title',translate('Order list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @php($parcel_order = Request::is('admin/parcel/orders*'))
        @php($list_titles = [
            'all' => translate('All'),
            'pending' => translate('Pending'),
            'confirmed' => translate('messages.confirmed'),
            'accepted' => translate('Accepted'),
            'processing' => translate('Processing'),
            'handover' => translate('messages.handover'),
            'item_on_the_way' => translate('Item on the way'),
            'picked_up' => translate('Out for delivery'),
            'delivered' => translate('Delivered'),
            'canceled' => translate('Canceled'),
            'failed' => translate('Payment failed'),
            'refund_requested' => translate('messages.Refund requested'),
            'refunded' => translate('Refunded'),
            'refund_request_canceled' => translate('messages.Refund request canceled'),
            'scheduled' => translate('Scheduled'),
            'searching_for_deliverymen' => translate('messages.searching_for_deliverymen'),
        ])
        @php($order_status_labels = [
            'pending' => translate('Pending'),
            'confirmed' => translate('messages.confirmed'),
            'accepted' => translate('Accepted'),
            'processing' => translate('Processing'),
            'handover' => translate('messages.handover'),
            'picked_up' => translate('Out for delivery'),
            'delivered' => translate('Delivered'),
            'canceled' => translate('Canceled'),
            'failed' => translate('Payment failed'),
            'refund_requested' => translate('messages.Refund requested'),
            'refunded' => translate('Refunded'),
            'refund_request_canceled' => translate('messages.Refund request canceled'),
        ])
        @php($order_status_badges = [
            'pending' => 'badge-soft-primary',
            'confirmed' => 'badge-soft-info',
            'accepted' => 'badge-soft-info',
            'processing' => 'badge-soft-warning',
            'handover' => 'badge-soft-warning',
            'picked_up' => 'badge-soft-warning',
            'delivered' => 'badge-soft-success',
            'canceled' => 'badge-soft-danger',
            'failed' => 'badge-soft-danger',
            'refund_requested' => 'badge-soft-warning',
            'refunded' => 'badge-soft-info',
            'refund_request_canceled' => 'badge-soft-secondary',
        ])
        @php($payment_status_badges = [
            'paid' => 'badge-soft-success',
            'partially_paid' => 'badge-soft-warning',
        ])
        @php($payment_status_labels = [
            'paid' => translate('messages.paid'),
            'partially_paid' => translate('messages.Partially paid'),
        ])
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-xl-10 col-md-9 col-sm-8 mb-3 mb-sm-0 {{$parcel_order ? 'mb-2':''}}">
                    <h1 class="page-header-title text-capitalize m-0">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/order.png')}}" class="w--26" alt="">
                        </span>
                        <span>
                            @if ($parcel_order) {{translate('messages.Parcel orders')}}
                            @elseif(Request::is('admin/refund/*') ) {{translate('messages.Refund')}}  {{$list_titles[$status] ?? ucfirst(str_replace('_',' ',$status))}}
                            @else {{$list_titles[$status] ?? ucfirst(str_replace('_',' ',$status))}} {{translate('messages.Orders')}}
                            @endif
                            <span class="badge badge-soft-dark ml-2">{{$total}}</span>
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Orders at this stage, with the store, customer and deliveryman on each.') }}</p>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @php($is_refund_list = Request::is('admin/refund/*'))
                    @php($table_head_title = $parcel_order ? translate('messages.Parcel orders') : ($is_refund_list ? translate('messages.Refund') : translate('Order list')))
                    @php($table_head_subtitle = $parcel_order ? translate('messages.Parcel delivery requests with their pickup, drop-off and current progress.') : ($is_refund_list ? translate('messages.Refund requests and where each one stands in review.') : translate('messages.Orders at this stage, with customer, store, amount and delivery progress.')))
                    @include('partials._table-head', [
                        'subtitle' => $table_head_subtitle,
                    ])
                    <form class="search-form min--260">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                    placeholder="{{ translate('messages.Ex') }}: 10010" value="{{ request()?->search ?? null}}" aria-label="{{translate('messages.Search')}}">
                                    @if($parcel_order)
                                    <input type="hidden" name="parcel_order" value="{{$parcel_order}}">
                                    @endif
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>


                    <div id="datatableCounterInfo" class="mr-2 mb-2 mb-sm-0 initial-hidden">
                        <div class="d-flex align-items-center">
                                <span class="font-size-sm mr-3">
                                <span id="datatableCounter">0</span>
                                {{translate('Selected')}}
                                </span>
                        </div>
                    </div>

                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:;"
                            data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                        </a>

                        <div id="usersExportDropdown" class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{translate('messages.Download options')}}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route("admin.order.export",['status'=>$status,'file_type'=>'excel','type'=>$parcel_order?'parcel':'order', request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{asset('public/assets/admin')}}/svg/components/excel.svg"
                                        alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route("admin.order.export",['status'=>$status,'file_type'=>'csv','type'=>$parcel_order?'parcel':'order', request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                        alt="Image Description">
                                .csv
                            </a>
                        </div>
                    </div>

                    @if(Request::is('admin/refund/*'))
                    <div class="select-item">
                        <select name="slist" class="form-control js-select2-custom refund-filter" >
                            <option {{($status=='requested')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['requested']) }}">{{translate('Refund requests')}}</option>
                            <option {{($status=='refunded')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['refunded']) }}">{{translate('messages.Refund')}}</option>
                            <option {{($status=='rejected')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['rejected']) }}">{{translate('rejected')}}</option>
                        </select>
                    </div>
                    @endif

                    <div class="hs-unfold mr-2">
                        <a class="btn btn-sm btn-white h--40px filter-button-show" href="javascript:;"
                           role="button" aria-expanded="false" aria-controls="datatableFilterSidebar">
                            <i class="tio-filter-list mr-1"></i> {{ translate('messages.Filter') }} <span class="badge badge-success badge-pill ml-1" id="filter_count"></span>
                        </a>
                    </div>


                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table
                       class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head fz--14px" >
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">{{translate('messages.Order ID')}}</th>
                        <th class="border-0">{{translate('Order date')}}</th>
                        @if ($status == 'scheduled')
                            <th class="border-0">{{translate('Scheduled at')}}</th>
                        @endif
                        <th class="border-0">{{translate('Customer information')}}</th>
                        <th class="border-0">{{translate('Deliveryman')}}</th>
                        @if ($parcel_order)
                            <th class="border-0">{{translate('Parcel category')}}</th>
                        @else
                            <th class="border-0">{{translate('messages.Store')}}</th>
                            <th class="text-center border-0">{{translate('messages.Items')}}</th>
                        @endif
                        <th class="border-0">{{translate('messages.Payment')}}</th>
                        <th class="border-0 col--numeric">{{translate('Total amount')}}</th>
                        @if ($status == 'refunded')
                            <th class="text-center border-0">{{translate('messages.Refunded order status')}}</th>
                        @else
                            <th class="text-center border-0">{{translate('Order status')}}</th>
                        @endif
                        <th class="text-center border-0">{{translate('messages.actions')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($orders as $order)
                        @php($guest_details = $order->is_guest ? \App\CentralLogics\Helpers::decodeJsonToArray($order['delivery_address']) : [])
                        <tr class="status-{{$order['order_status']}} class-all">
                            <td data-order="{{$order['id']}}">
                                <div>
                                    <a class="font-weight-bold" href="{{route($parcel_order?'admin.parcel.order.details':'admin.order.details',['id'=>$order['id']])}}">
                                        #{{$order['id']}}
                                    </a>
                                </div>
                                @if($order->edited || $order->is_pos || ($order->scheduled && $status != 'scheduled'))
                                    <div class="cell-chips mt-1">
                                        @if($order->is_pos)
                                            <span class="cell-chip">{{ translate('messages.POS') }}</span>
                                        @endif
                                        @if($order->scheduled && $status != 'scheduled')
                                            <span class="cell-chip">{{ translate('Scheduled') }}</span>
                                        @endif
                                        @if($order->edited)
                                            <span class="cell-chip">{{ translate('Edited') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td data-order="{{$order['created_at']}}">
                                <span class="table-when">
                                    <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($order['created_at']) }}</span>
                                    <span class="table-when__ago text-uppercase" title="{{ \App\CentralLogics\Helpers::time_date_format($order['created_at']) }}">
                                        {{ \App\CentralLogics\Helpers::time_format($order['created_at']) }}
                                    </span>
                                </span>
                            </td>
                            @if ($status == 'scheduled')
                                <td data-order="{{$order['schedule_at']}}">
                                    @if($order['schedule_at'])
                                        <span class="table-when">
                                            <span class="table-when__day">{{ \App\CentralLogics\Helpers::date_format($order['schedule_at']) }}</span>
                                            <span class="table-when__ago text-uppercase" title="{{ \App\CentralLogics\Helpers::time_date_format($order['schedule_at']) }}">
                                                {{ \App\CentralLogics\Helpers::time_format($order['schedule_at']) }}
                                            </span>
                                        </span>
                                    @else
                                        <span class="text-muted">{{translate('messages.N/A')}}</span>
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if($order->is_guest)
                                    <strong>{{ $guest_details['contact_person_name'] ?? translate('messages.guest') }}</strong>
                                    @if(!empty($guest_details['contact_person_number']))
                                        <a class="d-block text-body" href="tel:{{$guest_details['contact_person_number']}}">{{$guest_details['contact_person_number']}}</a>
                                    @endif
                                    <div class="cell-chips mt-1"><span class="cell-chip">{{translate('messages.guest')}}</span></div>
                                @elseif($order->customer)
                                    <a class="d-block text-body" href="{{route('admin.users.customer.view',[$order['user_id']])}}">
                                        <strong>{{$order->customer['f_name'].' '.$order->customer['l_name']}}</strong>
                                    </a>
                                    <a class="d-block text-body" href="tel:{{$order->customer['phone']}}">{{$order->customer['phone']}}</a>
                                @else
                                    <span class="badge badge-soft-danger">{{translate('messages.Invalid customer data')}}</span>
                                @endif
                            </td>
                            <td>
                                @if($order->delivery_man)
                                    <strong>{{$order->delivery_man['f_name'].' '.$order->delivery_man['l_name']}}</strong>
                                    <a class="d-block text-body" href="tel:{{$order->delivery_man['phone']}}">{{$order->delivery_man['phone']}}</a>
                                    @if($order['order_type'] == 'take_away')
                                        <div class="cell-chips mt-1"><span class="cell-chip">{{translate('messages.Take away')}}</span></div>
                                    @endif
                                @elseif($order['order_type'] == 'take_away')
                                    <span class="text-muted">{{translate('messages.Take away')}}</span>
                                @else
                                    <span class="text-muted">{{translate('messages.Unassigned')}}</span>
                                @endif
                            </td>
                            <td>
                                @if ($parcel_order)
                                    <div>{{Str::limit($order->parcel_category?$order->parcel_category->name:translate('No data found'),20,'...')}}</div>
                                @elseif ($order->store)
                                    <a class="text--title" href="{{route('admin.store.view', $order->store_id)}}" title="{{ $order->store->name }}">{{Str::limit($order->store->name,20,'...')}}</a>
                                @else
                                    <span class="text-muted">{{translate('messages.Store deleted')}}</span>
                                @endif
                            </td>
                            @if (!$parcel_order)
                                <td class="text-center" data-order="{{ $order->items_count ?? 0 }}">
                                    {{ $order->items_count ?? 0 }}
                                </td>
                            @endif
                            <td>
                                <div>{{ payment_method_label($order['payment_method']) ?: translate('messages.N/A') }}</div>
                                <span class="badge {{ $payment_status_badges[$order->payment_status] ?? 'badge-soft-danger' }} mt-1">
                                    {{ $payment_status_labels[$order->payment_status] ?? translate('messages.unpaid') }}
                                </span>
                            </td>
                            <td class="col--numeric" data-order="{{$order['order_amount']}}">
                                {{\App\CentralLogics\Helpers::format_currency($order['order_amount'])}}
                            </td>
                            <td class="text-center">
                                <div>
                                    <span class="badge {{ $order_status_badges[$order['order_status']] ?? 'badge-soft-secondary' }}">
                                        {{ $order_status_labels[$order['order_status']] ?? ucfirst(str_replace('_',' ',$order['order_status'])) }}
                                    </span>
                                </div>
                                @if(in_array($order->delivery_type, ['express', 'slightly_delay'], true))
                                    <div class="cell-chips mt-1">
                                        @include('partials.delivery-type-badge', ['order' => $order])
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn btn-sm action-btn action-btn--view" href="{{route($parcel_order?'admin.parcel.order.details':'admin.order.details',['id'=>$order['id']])}}" title="{{translate('View details')}}">
                                        <i class="tio-visible-outlined"></i>
                                    </a>
                                    <a class="btn btn-sm action-btn action-btn--print" target="_blank" href="{{route('admin.order.generate-invoice',['id'=>$order['id']])}}" title="{{translate('messages.Print invoice')}}">
                                        <i class="tio-print"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>


            @if(count($orders) !== 0)
            <hr>
            @endif
            <div class="page-area">
                {!! $orders->appends($_GET)->links() !!}
            </div>
            @if(count($orders) === 0)
            <div class="empty--data">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>
                    {{translate('No data found')}}
                </h5>
            </div>
            @endif
        </div>
        <div id="datatableFilterSidebar" class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
            <div class="card card-lg sidebar-card sidebar-footer-fixed">
                @include('partials._filter-drawer-head', [
                    'fd_title'    => translate('messages.Order filter'),
                    'fd_subtitle' => translate('messages.Narrow the order list down by zone, store, status, type and date range.'),
                ])
                <?php
                $filter_count=0;
                if(isset($zone_ids) && count($zone_ids) > 0) $filter_count += 1;
                if(isset($vendor_ids) && count($vendor_ids)>0) $filter_count += 1;
                if($status=='all')
                {
                    if(isset($orderstatus) && count($orderstatus) > 0) $filter_count += 1;
                    if(isset($scheduled) && $scheduled == 1) $filter_count += 1;
                }

                if(isset($from_date) && isset($to_date)) $filter_count += 1;
                if(isset($order_type)) $filter_count += 1;

                ?>
                <form class="card-body sidebar-body sidebar-scrollbar" action="{{route('admin.order.filter')}}" method="POST" id="order_filter_form">
                    @csrf
                    <small class="text-cap mb-3">{{translate('messages.Zone')}}</small>

                    <div class="mb-2 initial--21">
                        <select name="zone[]" id="zone_ids" class="form-control js-select2-custom" multiple="multiple" data-url="{{route('admin.zone.get-zones')}}" data-placeholder="{{translate('Select zone')}}">
                            <option value="all" {{$selected_zones->count() > 0 ? '' : 'selected'}}>{{translate('All')}}</option>
                            @foreach($selected_zones as $zone)
                                <option value="{{$zone->id}}" selected>{{$zone->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (!$parcel_order)
                        <hr class="my-4">
                        <small class="text-cap mb-3">{{translate('messages.Store')}}</small>
                        <div class="mb-2 initial--21">
                            <select name="vendor[]" id="vendor_ids" class="form-control js-select2-custom" multiple="multiple" data-url="{{route('admin.store.get-stores')}}" data-placeholder="{{translate('Select store')}}">
                                <option value="all" {{$selected_stores->count() > 0 ? '' : 'selected'}}>{{translate('All')}}</option>
                                @foreach($selected_stores as $store)
                                    <option value="{{$store->id}}" data-verified="{{ (int) $store->verified_seller }}" selected>{{$store->name}}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif


                    <hr class="my-4">
                    @if($status == 'all')
                    <small class="text-cap mb-3">{{translate('Order status')}}</small>

                    <div class="fd-grid">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus2" name="orderStatus[]" class="custom-control-input" {{isset($orderstatus)?(in_array('pending', $orderstatus)?'checked':''):''}} value="pending">
                        <label class="custom-control-label" for="orderStatus2">{{translate('Pending')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus1" name="orderStatus[]" class="custom-control-input" value="confirmed" {{isset($orderstatus)?(in_array('confirmed', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus1">{{translate('messages.confirmed')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus3" name="orderStatus[]" class="custom-control-input" value="processing" {{isset($orderstatus)?(in_array('processing', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus3">{{translate('Processing')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus4" name="orderStatus[]" class="custom-control-input" value="picked_up" {{isset($orderstatus)?(in_array('picked_up', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus4">{{translate('Out for delivery')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus5" name="orderStatus[]" class="custom-control-input" value="delivered" {{isset($orderstatus)?(in_array('delivered', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus5">{{translate('Delivered')}}</label>
                    </div>

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus7" name="orderStatus[]" class="custom-control-input" value="failed" {{isset($orderstatus)?(in_array('failed', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus7">{{translate('messages.failed')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus8" name="orderStatus[]" class="custom-control-input" value="canceled" {{isset($orderstatus)?(in_array('canceled', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus8">{{translate('Canceled')}}</label>
                    </div>
                    @if (!$parcel_order)
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus9" name="orderStatus[]" class="custom-control-input" value="refund_requested" {{isset($orderstatus)?(in_array('refund_requested', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus9">{{translate('messages.refundRequest')}}</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="orderStatus10" name="orderStatus[]" class="custom-control-input" value="refunded" {{isset($orderstatus)?(in_array('refunded', $orderstatus)?'checked':''):''}}>
                        <label class="custom-control-label" for="orderStatus10">{{translate('Refunded')}}</label>
                    </div>
                    @endif
                    </div>

                    <hr class="my-4">

                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" id="scheduled" name="scheduled" class="custom-control-input" value="1" {{isset($scheduled)?($scheduled==1?'checked':''):''}}>
                        <label class="custom-control-label" for="scheduled">{{translate('Scheduled')}}</label>
                    </div>
                    @endif
                    @if (!$parcel_order)
                        <hr class="my-4">
                        <small class="text-cap mb-3">{{translate('Order type')}}</small>
                        <div class="fd-grid">
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="take_away" name="order_type" class="custom-control-input" value="take_away" {{isset($order_type)?($order_type=='take_away'?'checked':''):''}}>
                            <label class="custom-control-label" for="take_away">{{translate('messages.Take away')}}</label>
                        </div>
                        <div class="custom-control custom-radio mb-2">
                            <input type="radio" id="delivery" name="order_type" class="custom-control-input" value="delivery" {{isset($order_type)?($order_type=='delivery'?'checked':''):''}}>
                            <label class="custom-control-label" for="delivery">{{translate('Home delivery')}}</label>
                        </div>
                        </div>
                    @endif

                    <hr class="my-4">

                    <small class="text-cap mb-3">{{translate('messages.Date between')}}</small>

                    <div class="fd-daterange">
                        <div class="fd-daterange__field">
                            <label class="fd-sublabel" for="date_from">{{ translate('messages.from') }}</label>
                            <input type="date" name="from_date" class="form-control" id="date_from" value="{{isset($from_date)?$from_date:''}}">
                        </div>
                        <div class="fd-daterange__field">
                            <label class="fd-sublabel" for="date_to">{{ translate('messages.to') }}</label>
                            <input type="date" name="to_date" class="form-control" id="date_to" value="{{isset($to_date)?$to_date:''}}">
                        </div>
                    </div>

                    <div class="card-footer sidebar-footer">
                        <div class="row gx-2">
                            <div class="col">
                                <button type="reset" class="btn btn-block btn-white" id="reset"><i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}</button>
                            </div>
                            <div class="col">
                                <button type="submit" class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('Apply filters') }}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
        <script src="{{asset('public/assets/admin')}}/js/view-pages/order-list.js"></script>

        <script>
            $(document).ready(function () {

                @if($filter_count > 0)
                $('#filter_count').html({{$filter_count}});
                @endif

                function selectedZoneIds() {
                    return ($('#zone_ids').val() || []).filter(function (id) {
                        return id !== 'all';
                    });
                }

                function initFilterSelect($select, extraData) {
                    if (!$select.length) {
                        return;
                    }

                    if ($select.hasClass('select2-hidden-accessible')) {
                        $select.select2('destroy');
                    }

                    var config = {
                        placeholder: $select.data('placeholder'),
                        ajax: {
                            url: $select.data('url'),
                            delay: 250,
                            data: function (params) {
                                return $.extend({
                                    q: params.term,
                                    all: 1,
                                    page: params.page
                                }, extraData ? extraData() : {});
                            },
                            processResults: function (data) {
                                return {
                                    results: data
                                };
                            }
                        }
                    };

                    if (window.hsSelect2VerifiedTemplate) {
                        config.templateResult = window.hsSelect2VerifiedTemplate;
                        config.templateSelection = window.hsSelect2VerifiedTemplate;
                    }

                    $.HSCore.components.HSSelect2.init($select, config);
                }

                function enforceAllOption($select) {
                    var values = $select.val() || [];

                    if (values.length > 1 && values.indexOf('all') !== -1) {
                        $select.val(values.filter(function (id) {
                            return id !== 'all';
                        })).trigger('change.select2');
                    }
                }

                initFilterSelect($('#zone_ids'));
                initFilterSelect($('#vendor_ids'), function () {
                    return {
                        zone_ids: selectedZoneIds()
                    };
                });

                $('#zone_ids').on('change', function () {
                    enforceAllOption($(this));

                    var $vendor = $('#vendor_ids');
                    if (!$vendor.length) {
                        return;
                    }
                    $vendor.find('option').not('[value="all"]').remove();
                    $vendor.val(['all']).trigger('change.select2');
                });

                $('#vendor_ids').on('change', function () {
                    enforceAllOption($(this));
                });

                $('#reset').on('click', function(){
                    location.href = '{{url('/')}}/admin/order/filter/reset';
                });
            });
        </script>
        <script>
        $('#search-form').on('submit', function (e) {
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
            $.get({
                url: '{{route('admin.order.search')}}',
                data: $(this).serialize(),
                cache: false,
                contentType: false,
                processData: false,
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#set-rows').html(data.view);
                    $('.page-area').hide();
                },
                complete: function () {
                    $('#loading').hide();
                },
            });
        });
    </script>
@endpush
