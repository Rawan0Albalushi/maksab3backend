@extends('layouts.admin.app')

@section('title', translate('Order report'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('/public/assets/admin/img/outline/report-search.svg')}}" class="w--26" alt="">
                </span>
                <span>
                    {{ translate('Order report') }}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('How many orders came in, what they were worth and how many fell through.') }}</p>
        </div>

        <div class="card mb-20">
            <div class="card-body">
                <h4 class="">{{ translate('Search data') }}</h4>
                <form action="{{ route('admin.transactions.report.set-date') }}" method="post">
                    @csrf
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <select name="module_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="module_id"
                                title="{{ translate('messages.Select modules') }}">
                                <option value="" {{ !request('module_id') ? 'selected' : '' }}>
                                    {{ translate('All modules') }}</option>
                                @foreach (\App\CentralLogics\Helpers::modules_list()->where('module_type', '!=', 'parcel')->whereNotIn('module_type', ['rental', 'ride-share', 'service']) as $module)
                                    <option value="{{ $module->id }}"
                                        {{ request('module_id') == $module->id ? 'selected' : '' }}>
                                        {{ $module['module_name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="zone_id" class="form-control js-select2-custom set-filter" data-url="{{ url()->full() }}" data-filter="zone_id" id="zone">
                                <option value="all">{{ translate('All zones') }}</option>
                                @foreach (\App\CentralLogics\Helpers::zones_dropdown() as $z)
                                    <option value="{{ $z['id'] }}"
                                        {{ isset($zone) && $zone->id == $z['id'] ? 'selected' : '' }}>
                                        {{ $z['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="store_id"
                                data-placeholder="{{ translate('Select store') }}"
                                class="js-data-example-ajax form-control set-filter" data-url="{{ url()->full() }}" data-filter="store_id">
                                @if (isset($store))
                                    <option value="{{ $store->id }}" data-verified="{{ (int) $store->verified_seller }}" selected>{{ $store->name }}</option>
                                @else
                                    <option value="all" selected>{{ translate('All stores') }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select name="customer_id"
                                data-placeholder="{{ translate('Select customer') }}"
                                class="js-data-example-ajax-2 form-control set-filter" data-url="{{ url()->full() }}" data-filter="customer_id">
                                @if (isset($customer))
                                    <option value="{{ $customer->id }}" selected>{{ $customer->f_name . ' ' .$customer->l_name }}</option>
                                @else
                                    <option value="all" selected>{{ translate('All customers') }}</option>
                                @endif
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <select class="form-control set-filter" data-url="{{ url()->full() }}" data-filter="filter" name="filter">
                                <option value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>
                                    {{ translate('All time') }}</option>
                                <option value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This year') }}</option>
                                <option value="previous_year"
                                    {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>
                                    {{ translate('Previous year') }}</option>
                                <option value="this_month"
                                    {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>
                                    {{ translate('This month') }}</option>
                                <option value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                    {{ translate('messages.Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                            <div class="col-sm-6 col-md-3">

                                <input type="date" name="from" id="from_date" class="form-control"
                                    placeholder="{{ translate('Start date') }}"
                                    {{ session()->has('from_date') ? 'value=' . session('from_date') : '' }} required>

                            </div>
                            <div class="col-sm-6 col-md-3">

                                <input type="date" name="to" id="to_date" class="form-control"
                                    placeholder="{{ translate('End date') }}"
                                    {{ session()->has('to_date') ? 'value=' . session('to_date') : '' }} required>

                            </div>
                        @endif
                        <div class="col-sm-6 col-md-3 ml-auto">
                            <button type="submit"
                                class="btn btn-primary btn-block h--45px"><i class="tio-filter-list"></i> {{ translate('Filter') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="mb-20">
            <div class="row g-4">
                <div class="col-lg-3">
                    <a class="__card-1 h-100" href="#">
                        <img src="{{asset('/public/assets/admin/img/report/new/total.png')}}" class="icon" alt="report/new">
                        <h3 class="title">{{$orders->total()}}</h3>
                        <h6 class="subtitle">{{translate('messages.Total orders')}}</h6>
                    </a>
                </div>
                <div class="col-lg-9">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-1" href="#">
                            <h4 class="title">{{$total_progress_count}}</h4>
                            <span class="subtitle">{{translate('messages.In progress orders')}} <span data-toggle="tooltip" data-placement="right" data-original-title="{{translate('Including accepted and processing orders')}}"><img src="{{asset('public/assets/admin/img/info-circle.svg')}}" alt="{{translate('messages.In progress orders')}}"></span></span>
                            <img src="{{asset('/public/assets/admin/img/report/new/progress-report.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-2" href="#">
                            <h4 class="title">{{$total_on_the_way_count}}</h4>
                            <span class="subtitle">{{translate('On the way')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/on-the-way.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-3" href="#">
                            <h4 class="title">{{$total_delivered_count}}</h4>
                            <span class="subtitle">{{ translate('messages.Delivered orders') }}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/delivered.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-4" href="#">
                            <h4 class="title">{{$total_failed_count}}</h4>
                            <span class="subtitle">{{translate('Failed orders')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/failed.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-5" href="#">
                            <h4 class="title">{{$total_refunded_count}}</h4>
                            <span class="subtitle">{{translate('messages.Refunded orders')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/refunded.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                        <div class="col-sm-6 col-md-4">
                            <a class="__card-2 __bg-6" href="#">
                            <h4 class="title">{{$total_canceled_count}}</h4>
                            <span class="subtitle">{{translate('messages.Canceled orders')}}</span>
                            <img src="{{asset('/public/assets/admin/img/report/new/canceled.png')}}" alt="report/new" class="card-icon">
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header border-0 py-2">
                <div class="search--button-wrapper">
                    <h3 class="card-title">
                        {{ translate('messages.Total orders') }} <span
                            class="badge badge-soft-secondary" id="countItems">{{ $orders->total() }}</span>
                    </h3>
                    <form class="search-form">
                        <div class="input--group input-group input-group-merge input-group-flush">
                            <input name="search" type="search" class="form-control" value="{{request()->query('search')}}" placeholder="{{ translate('Search by order ID') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="hs-unfold ml-3">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle btn export-btn font--sm"
                            href="javascript:;"
                            data-hs-unfold-options="{
                                &quot;target&quot;: &quot;#usersExportDropdown&quot;,
                                &quot;type&quot;: &quot;css-animation&quot;
                            }"
                            data-hs-unfold-target="#usersExportDropdown" data-hs-unfold-invoker="">
                            <i class="tio-download-to mr-1"></i> {{ translate('Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right hs-unfold-content-initialized hs-unfold-css-animation animated hs-unfold-reverse-y hs-unfold-hidden">

                            <span class="dropdown-header">{{ translate('Download options') }}</span>
                            <a id="export-excel" class="dropdown-item"
                                href="{{ route('admin.transactions.report.order-report-export', ['type' => 'excel', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/excel.svg') }}"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item"
                                href="{{ route('admin.transactions.report.order-report-export', ['type' => 'csv', request()->getQueryString()]) }}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin/svg/components/placeholder-csv-format.svg') }}"
                                    alt="Image Description">
                                CSV
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-borderless middle-align __txt-14px">
                        <thead class="thead-light white--space-false">
                            <tr>
                                <th class="border-top border-bottom">{{ translate('messages.SL') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Order ID') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Store') }}</th>
                                <th class="border-top border-bottom">{{ translate('Customer name') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Total item amount') }}</th>
                                <th class="border-top border-bottom">{{ translate('Item discount') }}</th>
                                <th class="border-top border-bottom">{{ translate('Coupon discount') }}</th>
                                <th class="border-top border-bottom">{{ translate('Referral discount') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Pro discount') }}</th>
                                <th class="border-top border-bottom">{{ translate('Discounted amount') }}</th>
                                <th class="border-top border-bottom text-center">{{ translate('messages.tax') }}</th>
                                <th class="border-top border-bottom text-center">{{ translate('Delivery charge') }}</th>
                                <th class="border-top border-bottom text-center">{{ translate('Delivery type') }}</th>
                                <th class="border-top border-bottom text-center">{{ \App\CentralLogics\Helpers::get_business_data('additional_charge_name')??translate('Additional charge') }}</th>
                                <th class="border-top border-bottom text-center">{{ translate('Extra packaging amount') }}</th>
                                <th class="border-top border-bottom">{{ translate('Order amount') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Amount received by') }}</th>
                                <th class="border-top border-bottom">{{ translate('messages.Payment method') }}</th>
                                <th class="border-top border-bottom">{{ translate('Order status') }}</th>
                                <th class="border-top border-bottom text-center">{{ translate('messages.Action') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody id="set-rows">
                            @foreach ($orders as $key => $order)
                                <tr class="status-{{ $order['order_status'] }} class-all">
                                    <td class="">
                                        {{ $key + $orders->firstItem() }}
                                    </td>
                                    <td class="table-column-pl-0">
                                        <a
                                            href="{{ route('admin.order.details', ['id' => $order['id'],'module_id'=>$order['module_id']]) }}">{{ $order['id'] }}</a>
                                    </td>
                                    <td  class="text-capitalize">
                                        @if($order->store)
                                            {{Str::limit($order->store->name,25,'...')}}
                                        @else
                                            <label class="badge badge-danger">{{ translate('messages.invalid') }}
                                        @endif
                                    </td>
                                    <td>
                                        @if($order->is_guest)
                                        @php($customer_details = json_decode($order['delivery_address'],true))
                                        <strong>{{$customer_details['contact_person_name']}}</strong>
                                        <div>{{$customer_details['contact_person_number']}}</div>

                                        @elseif ($order->customer)
                                        <a class="text-body text-capitalize"
                                            href="{{ route('admin.users.customer.view', [$order['user_id']]) }}">
                                            <strong>{{ $order->customer['f_name'] . ' ' . $order->customer['l_name'] }}</strong>
                                        </a>
                                        @else
                                            <label class="badge badge-danger">{{ translate('messages.Invalid customer data') }}</label>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="text-right mw--85px">
                                            <div>
                                                {{ \App\CentralLogics\Helpers::number_format_short($order['order_amount'] - $order->additional_charge - $order['dm_tips']-$order['total_tax_amount']-app(\App\Services\Order\OrderService::class)->adjustedFeeForOrder($order)['adjusted']+$order['coupon_discount_amount'] + $order['store_discount_amount'] + $order['ref_bonus_amount'] - $order['extra_packaging_amount'] +$order['flash_admin_discount_amount'] +$order['flash_store_discount_amount'] + $order['extra_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0) ) }}
                                            </div>
                                            @if ($order->payment_status == 'paid')
                                                <strong class="text-success">
                                                    {{ translate('messages.paid') }}
                                                </strong>
                                            @else
                                                <strong class="text-danger">
                                                    {{ translate('messages.unpaid') }}
                                                </strong>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center mw--85px">
                                        @if ($order->discount_type == 'flash_sale')
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['flash_admin_discount_amount'] +$order['flash_store_discount_amount'] ) }}
                                    @else
                                        {{ \App\CentralLogics\Helpers::number_format_short(($order->item_discount_total ?? 0) ) }}
                                    @endif
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['ref_bonus_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order->orderProDiscount?->amount_saved ?? 0) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['coupon_discount_amount'] + $order['store_discount_amount'] + $order['ref_bonus_amount'] + $order['extra_discount_amount'] + ($order->orderProDiscount?->amount_saved ?? 0))  }}
                                    </td>
                                    <td class="text-center mw--85px white-space-nowrap">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['total_tax_amount']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short(app(\App\Services\Order\OrderService::class)->proDeliveryBreakdown($order)['original_fee']) }}
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order->delivery_type_charge ?? 0) }}
                                        <small class="d-block text-muted">{{ translate('messages.'.($order->delivery_type ?? 'standard')) }}</small>
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['additional_charge']) }}
                                    </td>
                                    <td class="text-center mw--85px">
                                        {{ \App\CentralLogics\Helpers::number_format_short($order['extra_packaging_amount']) }}
                                    </td>
                                    <td>
                                        <div class="text-right mw--85px">
                                            <div>
                                                {{ \App\CentralLogics\Helpers::number_format_short($order['order_amount']) }}
                                            </div>
                                            @if ($order->payment_status == 'paid')
                                                <strong class="text-success">
                                                    {{ translate('messages.paid') }}
                                                </strong>
                                            @else
                                                <strong class="text-danger">
                                                    {{ translate('messages.unpaid') }}
                                                </strong>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
                                        {{isset($order->transaction) ? $order->transaction->received_by : translate('messages.Not received yet')}}
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
                                            {{ payment_method_label($order['payment_method']) }}
                                    </td>
                                    <td class="text-center mw--85px text-capitalize">
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
                                            @elseif($order['order_status']=='refund_request_canceled')
                                                <span class="badge badge-soft-danger">
                                                  {{translate('Refund request canceled')}}
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
                                                href="{{ route('admin.order.details', ['id' => $order['id'],'module_id'=>$order['module_id']]) }}">
                                                <i class="tio-visible-outlined"></i>
                                            </a>
                                            <a class="ml-2 btn btn-sm btn--primary btn-outline-primary action-btn"
                                                href="{{ route('admin.order.generate-invoice', ['id' => $order['id']]) }}">
                                                <i class="tio-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>


            </div>
            @if (count($orders) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $orders->links() !!}
            </div>
            @if (count($orders) === 0)
                <div class="empty--data">
                    <img src="{{ asset('/public/assets/admin/svg/illustrations/sorry.svg') }}" alt="public">
                    <h5>
                        {{ translate('No data found') }}
                    </h5>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script')
@endpush

@push('script_2')
    <script src="{{ asset('public/assets/admin') }}/vendor/chart.js/dist/Chart.min.js"></script>
    <script src="{{ asset('public/assets/admin') }}/vendor/chartjs-chart-matrix/dist/chartjs-chart-matrix.min.js">
    </script>
    <script src="{{ asset('public/assets/admin') }}/js/hs.chartjs-matrix.js"></script>
    <script src="{{ asset('public/assets/admin') }}/js/view-pages/admin-reports.js"></script>

    <script>
        "use strict";
        $(document).on('ready', function() {
            $('.js-data-example-ajax').select2({
                ajax: {
                    url: '{{ route('admin.store.get-stores') }}',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            // all:true,
                            @if (isset($zone))
                                zone_ids: [{{ $zone->id }}],
                            @endif
                            @if (request('module_id'))
                                module_id: {{ request('module_id') }},
                            @endif
                            page: params.page
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });

            $('.js-data-example-ajax-2').select2({
                ajax: {
                    url: '{{ route('admin.users.customer.select-list') }}',
                    data: function(params) {
                        return {
                            q: params.term, // search term
                            // all:true,
                            @if (isset($zone))
                                zone_ids: [{{ $zone->id }}],
                            @endif
                            @if (request('module_id'))
                                module_id: {{ request('module_id') }},
                            @endif
                            @if (request('store_id'))
                                store_id: {{ request('store_id') }},
                            @endif
                            page: params.page
                        };
                    },
                    processResults: function(data) {
                        return {
                            results: data
                        };
                    },
                    __port: function(params, success, failure) {
                        let $request = $.ajax(params);

                        $request.then(success);
                        $request.fail(failure);

                        return $request;
                    }
                }
            });
        });


    </script>
@endpush

