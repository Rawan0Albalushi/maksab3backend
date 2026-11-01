@php use App\CentralLogics\Helpers; @endphp
@extends('layouts.admin.app')

@section('title',translate('Parcel order list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @php($parcel_order = Request::is('admin/parcel/orders*'))
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-xl-10 col-md-9 col-sm-8 mb-3 mb-sm-0 mb-2">
                    <h1 class="page-header-title text-capitalize m-0">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/order.png')}}" class="w--26" alt="">
                        </span>
                        <span>
                            {{translate('messages.Parcel orders')}}
                            <span class="badge badge-soft-dark ml-2">{{$total}}</span>
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Parcels being carried from one address to another, and the state each is in.') }}</p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Parcel delivery requests with their pickup, drop-off and current progress.'),
                    ])

                    <form class="search-form min--260">
                        <div class="input-group input--group">
                            <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('messages.Ex') }}: 10010"
                                   value="{{ request()?->search ?? null}}"
                                   aria-label="{{translate('messages.Search')}}">
                            <input type="hidden" name="parcel_order" value="{{$parcel_order}}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    @if(request()->input('search'))
                        <button type="reset" class="btn btn--primary ml-2 location-reload-to-base"
                                data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                    @endif

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

                        <div id="usersExportDropdown"
                             class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{translate('messages.Download options')}}</span>
                            <a id="export-excel" class="dropdown-item" href="javascript:;">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{asset('public/assets/admin')}}/svg/components/excel.svg"
                                     alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="javascript:;">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                     src="{{asset('public/assets/admin')}}/svg/components/placeholder-csv-format.svg"
                                     alt="Image Description">
                                .csv
                            </a>
                        </div>
                    </div>

                    @if(Request::is('admin/refund/*'))
                        <div class="select-item">
                            <select name="slist" class="form-control js-select2-custom refund-filter">
                                <option
                                    {{($status=='requested')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['requested']) }}">{{translate('Refund requests')}}</option>
                                <option
                                    {{($status=='refunded')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['refunded']) }}">{{translate('messages.Refund')}}</option>
                                <option
                                    {{($status=='rejected')?'selected':''}} value="{{ route('admin.refund.refund_attr', ['rejected']) }}">{{translate('rejected')}}</option>
                            </select>
                        </div>
                    @endif

                    <div class="hs-unfold mr-2">
                        <a class="btn btn-sm btn-white h--40px filter-button-show"
                           href="javascript:;" role="button" aria-expanded="false"
                           aria-controls="datatableFilterSidebar">
                            <i class="tio-filter-list mr-1"></i> {{ translate('messages.Filter') }} <span
                                class="badge badge-success badge-pill ml-1" id="filter_count"></span>
                        </a>
                    </div>

                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table id="datatable"
                       class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table fz--14px"
                       data-hs-datatables-options='{
                     "columnDefs": [{
                        "targets": [0],
                        "orderable": false
                      }],
                     "order": [],
                     "info": {
                       "totalQty": "#datatableWithPaginationInfoTotalQty"
                     },
                     "search": "#datatableSearch",
                     "entries": "#datatableEntries",
                     "isResponsive": false,
                     "isShowPaging": false,
                     "paging": false
                   }'>
                    <thead class="thead-light">
                    <tr>
                        <th class="border-0">
                            {{translate('messages.SL')}}
                        </th>
                        <th class="table-column-pl-0 border-0">{{translate('messages.Order ID')}}</th>
                        <th class="border-0">{{translate('Order date')}}</th>
                        @if ($status == 'scheduled')
                            <th class="border-0">{{translate('Scheduled at')}}</th>
                        @endif
                        <th class="border-0">{{translate('Customer information')}}</th>
                        <th class="border-0">{{translate('Parcel category')}}</th>
                        <th class="border-0">{{translate('messages.payment By')}}</th>
                        <th class="border-0">{{translate('Total amount')}}</th>

                        @if ($status == 'refunded')
                            <th class="text-center border-0">{{translate('messages.Refunded order status')}}</th>
                        @else
                            <th class="text-center border-0">{{translate('Order status')}}</th>
                        @endif
                        <th class="text-center border-0">{{translate('messages.actions')}}</th>
                    </tr>
                    </thead>

                    <tbody id="set-rows">
                    @foreach($orders as $key=>$order)

                        <tr class="status-{{$order['order_status']}} class-all">
                            <td class="">
                                {{$key+$orders->firstItem()}}
                            </td>
                            <td class="table-column-pl-0">
                                <a href="{{route('admin.parcel.order.details',['id'=>$order['id']])}}">{{$order['id']}}</a>
                            </td>
                            <td>
                                <div>
                                    <div>
                                        {{ Helpers::date_format($order->created_at) }}
                                    </div>
                                    <div class="d-block text-uppercase">
                                        {{ Helpers::time_format($order->created_at) }}
                                    </div>
                                </div>
                            </td>
                            @if ($status == 'scheduled')
                                <td>
                                    <div>
                                        <div>
                                            {{ Helpers::date_format($order->schedule_at) }}
                                        </div>
                                        <div class="d-block text-uppercase">
                                            {{ Helpers::time_format($order->schedule_at) }}
                                        </div>
                                    </div>
                                </td>
                            @endif
                            <td>
                                @if($order->is_guest)
                                    @php($customer_details = json_decode($order['delivery_address'],true))
                                    <strong>{{$customer_details['contact_person_name']}}</strong>
                                    <a href="tel:{{$customer_details['contact_person_number']}}">
                                        <div>{{$customer_details['contact_person_number']}}</div>
                                    </a>
                                @elseif($order->customer)

                                    <a class="text-body" href="{{route('admin.users.customer.view',[$order['user_id']])}}">
                                        <strong>
                                            <div> {{$order->customer['f_name'].' '.$order->customer['l_name']}}</div>
                                        </strong>
                                    </a>
                                    <a href="tel:{{$order->customer['phone']}}">
                                        <div>{{$order->customer['phone']}}</div>
                                    </a>
                                @else
                                    <label
                                        class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                                @endif
                            </td>

                            <td>
                                <div>{{Str::limit($order->parcel_category?$order->parcel_category->name:translate('No data found'),20,'...')}}</div>
                            </td>

                            <td>
                                <div>{{translate($order->charge_payer)}}</div>
                                <strong class="text-success">
                                    {{payment_method_label($order->payment_method)}}
                                </strong>
                            </td>


                            <td>
                                <div class="text-right mw--85px">
                                    <div>
                                        {{Helpers::format_currency($order['order_amount'])}}
                                    </div>
                                    @if($order->payment_status=='paid')
                                        <strong class="text-success">
                                            {{translate('messages.paid')}}
                                        </strong>
                                    @elseif($order->payment_status=='partially_paid')
                                        <strong class="text-success">
                                            {{translate('messages.Partially paid')}}
                                        </strong>
                                    @else
                                        <strong class="text-danger">
                                            {{translate('messages.unpaid')}}
                                        </strong>
                                    @endif
                                </div>
                            </td>
                            <td class="text-capitalize text-center">
                                @if($order['order_status']=='pending')
                                    <span class="badge badge-soft-info">
                                      {{translate('Pending')}}
                                    </span>
                                @elseif($order['order_status']=='confirmed')
                                    <span class="badge badge-soft-info">
                                      {{translate('messages.confirmed')}}
                                    </span>
                                @elseif($order['order_status']=='processing')
                                    <span class="badge badge-soft-warning">
                                      {{translate('Processing')}}
                                    </span>
                                @elseif($order['order_status']=='picked_up')
                                    <span class="badge badge-soft-warning">
                                      {{translate('Out for delivery')}}
                                    </span>
                                @elseif($order['order_status']=='delivered')
                                    <span class="badge badge-soft-success">
                                      {{translate('Delivered')}}
                                    </span>
                                @elseif($order['order_status']=='failed')
                                    <span class="badge badge-soft-danger">
                                      {{translate('Payment failed')}}
                                    </span>
                                @elseif($order['order_status']=='handover')
                                    <span class="badge badge-soft-danger">
                                      {{translate('messages.handover')}}
                                    </span>
                                @elseif($order['order_status']=='canceled')
                                    <span class="badge badge-soft-danger">
                                      {{translate('Canceled')}}
                                    </span>
                                @elseif($order['order_status']=='accepted')
                                    <span class="badge badge-soft-danger">
                                      {{translate('Accepted')}}
                                    </span>
                                @elseif($order['order_status']=='refund_requested')
                                    <span class="badge badge-soft-danger">
                                      {{translate('messages.Refund requested')}}
                                    </span>
                                @else
                                    <span class="badge badge-soft-danger">
                                      {{str_replace('_',' ',$order['order_status'])}}
                                    </span>
                                @endif

                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="ml-2 btn btn-sm action-btn action-btn--view"
                                       href="{{route('admin.parcel.order.details',['id'=>$order['id']])}}">
                                        <i class="tio-visible-outlined"></i>
                                    </a>
                                    <a class="ml-2 btn btn-sm btn--primary btn-outline-primary action-btn"
                                       href="{{route('admin.order.generate-invoice',['id'=>$order['id']])}}">
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

        <div id="datatableFilterSidebar"
             class="filter-drawer sidebar sidebar-bordered sidebar-box-shadow">
            <div class="card card-lg sidebar-card sidebar-footer-fixed">
                @include('partials._filter-drawer-head', [
                    'fd_title'    => translate('messages.Order filter'),
                    'fd_subtitle' => translate('messages.Narrow the order list down by zone, store, status, type and date range.'),
                ])
                <?php
                $filter_count = 0;
                if (isset($zone_ids) && count($zone_ids) > 0) $filter_count += 1;
                if (isset($vendor_ids) && count($vendor_ids) > 0) $filter_count += 1;
                if ($status == 'all') {
                    if (isset($orderstatus) && count($orderstatus) > 0) $filter_count += 1;
                    if (isset($scheduled) && $scheduled == 1) $filter_count += 1;
                }

                if (isset($from_date) && isset($to_date)) $filter_count += 1;
                if (isset($order_type)) $filter_count += 1;

                ?>
                <form class="card-body sidebar-body sidebar-scrollbar" action="{{route('admin.order.filter')}}"
                      method="POST" id="order_filter_form">
                    @csrf
                    <small class="text-cap mb-3">{{translate('messages.Zone')}}</small>

                    <div class="mb-2 initial--21">
                        <select name="zone[]" data-title="{{ translate('Select zone') }}"
                                data-placeholder="{{ translate('Select zone') }}" id="zone_ids"
                                class="form-control js-select2-custom" multiple="multiple">
                            @foreach(\App\CentralLogics\Helpers::zones_dropdown() as $zone)
                                <option
                                    value="{{$zone->id}}" {{isset($zone_ids)?(in_array($zone->id, $zone_ids)?'selected':''):''}}>{{$zone->name}}</option>
                            @endforeach
                        </select>
                    </div>

                    <hr class="my-4">
                    @if($status == 'all')
                        <small class="text-cap mb-3">{{translate('Order status')}}</small>

                        <div class="fd-grid">
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus2" name="orderStatus[]" class="custom-control-input"
                                   {{isset($orderstatus)?(in_array('pending', $orderstatus)?'checked':''):''}} value="pending">
                            <label class="custom-control-label"
                                   for="orderStatus2">{{translate('Pending')}}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus1" name="orderStatus[]" class="custom-control-input"
                                   value="confirmed" {{isset($orderstatus)?(in_array('confirmed', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus1">{{translate('messages.confirmed')}}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus3" name="orderStatus[]" class="custom-control-input"
                                   value="processing" {{isset($orderstatus)?(in_array('processing', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus3">{{translate('Processing')}}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus4" name="orderStatus[]" class="custom-control-input"
                                   value="picked_up" {{isset($orderstatus)?(in_array('picked_up', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus4">{{translate('Out for delivery')}}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus5" name="orderStatus[]" class="custom-control-input"
                                   value="delivered" {{isset($orderstatus)?(in_array('delivered', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus5">{{translate('Delivered')}}</label>
                        </div>

                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus7" name="orderStatus[]" class="custom-control-input"
                                   value="failed" {{isset($orderstatus)?(in_array('failed', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus7">{{translate('messages.failed')}}</label>
                        </div>
                        <div class="custom-control custom-checkbox mb-2">
                            <input type="checkbox" id="orderStatus8" name="orderStatus[]" class="custom-control-input"
                                   value="canceled" {{isset($orderstatus)?(in_array('canceled', $orderstatus)?'checked':''):''}}>
                            <label class="custom-control-label"
                                   for="orderStatus8">{{translate('Canceled')}}</label>
                        </div>
                        </div>
                    @endif

                    <hr class="my-4">

                    <small class="text-cap mb-3">{{translate('Payment status')}}</small>
                    <div class="mb-2 initial--21">
                        <select name="payment_status" data-title="{{ translate('Payment status') }}"
                                data-placeholder="{{ translate('Payment status') }}"
                                class="form-control js-select2-custom">
                            <option
                                value="all" {{isset($payment_status) &&  $payment_status== 'all' ?'selected':''}}>{{translate('All')}}</option>
                            <option
                                value="paid" {{isset($payment_status) &&  $payment_status== 'paid' ?'selected':''}}>{{translate('messages.paid')}}</option>
                            <option
                                value="unpaid" {{isset($payment_status) &&  $payment_status== 'unpaid' ?'selected':''}}>{{translate('messages.unpaid')}}</option>
                        </select>
                    </div>
                    <hr class="my-4">

                    <small class="text-cap mb-3">{{translate('messages.payment By')}}</small>
                    <div class="mb-2 initial--21">
                        <select name="payment_by" data-title="{{ translate('messages.payment By') }}"
                                data-placeholder="{{ translate('messages.payment By') }}"
                                class="form-control js-select2-custom">
                            <option
                                value="all" {{isset($payment_By) &&  $payment_By== 'all' ?'selected':''}}>{{translate('All')}}</option>
                            <option
                                value="sender" {{isset($payment_By) &&  $payment_By== 'sender' ?'selected':''}}>{{translate('messages.sender')}}</option>
                            <option
                                value="receiver" {{isset($payment_By) &&  $payment_By== 'receiver' ?'selected':''}}>{{translate('messages.Receiver')}}</option>
                        </select>
                    </div>

                    <hr class="my-4">

                    <small class="text-cap mb-3">{{translate('messages.Date between')}}</small>

                    <div class="fd-daterange">
                        <div class="fd-daterange__field">
                            <label class="fd-sublabel" for="date_from">{{ translate('messages.from') }}</label>
                            <input type="date" name="from_date" class="form-control" id="date_from"
                                   value="{{isset($from_date)?$from_date:''}}">
                        </div>
                        <div class="fd-daterange__field">
                            <label class="fd-sublabel" for="date_to">{{ translate('messages.to') }}</label>
                            <input type="date" name="to_date" class="form-control" id="date_to"
                                   value="{{isset($to_date)?$to_date:''}}">
                        </div>
                    </div>

                    <div class="card-footer sidebar-footer">
                        <div class="row gx-2">
                            <div class="col">
                                <button type="reset" class="btn btn-block btn-white"
                                        id="reset"><i class="tio-clear-circle-outlined"></i> {{ translate('Clear all') }}</button>
                            </div>
                            <div class="col">
                                <button type="submit"
                                        class="btn btn-block btn-primary"><i class="tio-filter-list"></i> {{ translate('Apply filters') }}</button>
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
                "use strict";
                $(document).on('ready', function () {
                    @if($filter_count>0)
                    $('#filter_count').html({{$filter_count}});
                    @endif

                    // INITIALIZATION OF DATATABLES
                    // =======================================================
                    let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
                        dom: 'Bfrtip',
                        buttons: [
                            {
                                extend: 'copy',
                                className: 'd-none'
                            },
                            {
                                extend: 'excel',
                                className: 'd-none',
                                action: function (e, dt, node, config) {
                                    window.location.href = {!! json_encode(route("admin.parcel.parcel_orders_export",['status'=>$status,'file_type'=>'excel','type'=>'parcel', request()->getQueryString()]), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
                                }
                            },
                            {
                                extend: 'csv',
                                className: 'd-none',
                                action: function (e, dt, node, config) {
                                    window.location.href = {!! json_encode(route("admin.parcel.parcel_orders_export",['status'=>$status,'file_type'=>'csv','type'=>'parcel', request()->getQueryString()]), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
                                }
                            },

                            {
                                extend: 'print',
                                className: 'd-none'
                            },
                        ],
                        select: {
                            style: 'multi',
                            selector: 'td:first-child input[type="checkbox"]',
                            classMap: {
                                checkAll: '#datatableCheckAll',
                                counter: '#datatableCounter',
                                counterInfo: '#datatableCounterInfo'
                            }
                        },
                        language: {
                            zeroRecords: '<div class="text-center p-4">' +
                                '<img class="w-7rem mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="Image Description">' +

                                '</div>'
                        }
                    });
                    $('#export-copy').click(function () {
                        datatable.button('.buttons-copy').trigger()
                    });

                    $('#export-excel').click(function () {
                        datatable.button('.buttons-excel').trigger()
                    });

                    $('#export-csv').click(function () {
                        datatable.button('.buttons-csv').trigger()
                    });

                    $('#export-print').click(function () {
                        datatable.button('.buttons-print').trigger()
                    });

                    $('#datatableSearch').on('mouseup', function (e) {
                        let $input = $(this),
                            oldValue = $input.val();

                        if (oldValue == "") return;

                        setTimeout(function () {
                            let newValue = $input.val();

                            if (newValue == "") {
                                // Gotcha
                                datatable.search('').draw();
                            }
                        }, 1);
                    });

                    $('#toggleColumn_date').change(function (e) {
                        datatable.columns(2).visible(e.target.checked)
                    })

                    $('#toggleColumn_customer').change(function (e) {
                        datatable.columns(3).visible(e.target.checked)
                    })
                    $('#toggleColumn_store').change(function (e) {
                        datatable.columns(4).visible(e.target.checked)
                    })


                    $('#toggleColumn_total').change(function (e) {
                        datatable.columns(5).visible(e.target.checked)
                    })
                    $('#toggleColumn_order_status').change(function (e) {
                        datatable.columns(6).visible(e.target.checked)
                    })

                    $('#toggleColumn_actions').change(function (e) {
                        datatable.columns(7).visible(e.target.checked)
                    })
                });

                $('#reset').on('click', function () {
                    // e.preventDefault();
                    location.href = '{{url('/')}}/admin/order/filter/reset';
                });

            </script>
    @endpush
