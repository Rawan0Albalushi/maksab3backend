@php use App\CentralLogics\Helpers; @endphp
@extends('layouts.admin.app')

@section('title',translate('messages.Customer loyalty point report'))

@push('css_or_js')

@endpush

@section('content')
    @php
        $from = session('from_date');
        $to = session('to_date');
    @endphp
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/customer-loyalty.png')}}" class="w--26" alt="">
                </span>
                <span>
                     {{translate('messages.Customer loyalty point report')}}
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Where loyalty points came from and what customers have spent them on.') }}</p>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="card-title mb-4">
                    <span>{{translate('messages.Filter options')}}</span>
                </h4>

                <form action="{{route('admin.users.customer.loyalty-point.set-date')}}" method="post">
                    @csrf
                    <div class="row justify-content-end align-items-end g-3">
                        <div class="col-lg-4">
                            @php
                                $transaction_status=request()->input('transaction_type');
                            @endphp
                            <label class="text-dark text-capitalize"
                                   for="add-fund-type">{{translate('messages.Add fund type')}}</label>
                            <select name="transaction_type" id="add-fund-type"
                                    class="form-control js-select2-custom  set-filter" data-url="{{ url()->full() }}"
                                    data-filter="transaction_type"
                                    title="{{translate('messages.Select transaction type')}}">
                                <option value="all">{{translate('All type')}}</option>
                                <option
                                    value="point_to_wallet" {{isset($transaction_status) && $transaction_status=='point_to_wallet'?'selected':''}}>{{translate('messages.Point to wallet')}}</option>
                                <option
                                    value="order_place" {{isset($transaction_status) && $transaction_status=='order_place'?'selected':''}}>{{translate('messages.Order place')}}</option>
                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="text-dark text-capitalize"
                                   for="customer">{{translate('messages.Customer')}}</label>
                            <select id='customer' name="customer_id" data-url="{{ url()->full() }}"
                                    data-filter="customer_id"
                                    data-placeholder="{{translate('Select customer')}}"
                                    class="js-data-example-ajax form-control set-filter"
                                    title="{{translate('Select customer')}}">
                                @if (request()->input('customer_id') && $customer_info = \App\Models\User::find(request()->input('customer_id')))
                                    <option value="{{$customer_info->id}}"
                                            selected>{{$customer_info->f_name.' '.$customer_info->l_name}}
                                        ({{$customer_info->phone}})
                                    </option>
                                @endif

                            </select>
                        </div>
                        <div class="col-lg-4">
                            <label class="text-dark text-capitalize"
                                   for="filter">{{translate('messages.Duration')}}</label>
                            <select class="form-control js-select2-custom  set-filter" name="filter"
                                    data-url="{{ url()->full() }}" data-filter="filter">
                                <option
                                    value="all_time" {{ isset($filter) && $filter == 'all_time' ? 'selected' : '' }}>
                                    {{ translate('All time') }}</option>
                                <option
                                    value="this_year" {{ isset($filter) && $filter == 'this_year' ? 'selected' : '' }}>
                                    {{ translate('This year') }}</option>
                                <option value="previous_year"
                                    {{ isset($filter) && $filter == 'previous_year' ? 'selected' : '' }}>
                                    {{ translate('Previous year') }}</option>
                                <option value="this_month"
                                    {{ isset($filter) && $filter == 'this_month' ? 'selected' : '' }}>
                                    {{ translate('This month') }}</option>
                                <option
                                    value="this_week" {{ isset($filter) && $filter == 'this_week' ? 'selected' : '' }}>
                                    {{ translate('This week') }}</option>
                                <option value="custom" {{ isset($filter) && $filter == 'custom' ? 'selected' : '' }}>
                                    {{ translate('messages.Custom') }}</option>
                            </select>
                        </div>
                        @if (isset($filter) && $filter == 'custom')
                            <div class="col-lg-4">

                                <input type="date" name="from" id="from_date" class="form-control"
                                       placeholder="{{ translate('Start date') }}"
                                       {{ session()->has('from_date') ? 'value=' . session('from_date') : '' }} required>

                            </div>
                            <div class="col-lg-4">

                                <input type="date" name="to" id="to_date" class="form-control"
                                       placeholder="{{ translate('End date') }}"
                                       {{ session()->has('to_date') ? 'value=' . session('to_date') : '' }} required>

                            </div>
                        @endif
                        <div class="col-lg-4">
                            <div class="btn--container justify-content-end">
                                <button type="reset" class="btn btn--reset location-reload-to-base"
                                        data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                <button type="submit" class="btn btn--primary"><i class="tio-filter-list"></i> {{translate('messages.Filter')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    @php
                        $credit = (int)$data[0]->total_credit??0;
                        $debit = (int)$data[0]->total_debit??0;
                        $balance = $credit - $debit;
                    @endphp
                    <div class="col-md-4">
                        <div class="color-card color-6">
                            <div class="img-box">
                                <img class="resturant-icon w--30"
                                     src="{{asset('public/assets/admin/img/customer-loyality/1.png')}}"
                                     alt="transactions">
                            </div>
                            <div>
                                <h2 class="title">
                                    {{$credit}}
                                </h2>
                                <div class="subtitle">
                                    {{translate('messages.points Earned')}}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="color-card color-2">
                            <div class="img-box">
                                <img class="resturant-icon w--30"
                                     src="{{asset('public/assets/admin/img/customer-loyality/4.png')}}"
                                     alt="transactions">
                            </div>
                            <div>
                                <h2 class="title">
                                    {{$debit}}
                                </h2>
                                <div class="subtitle">
                                    {{translate('messages.points Converted')}}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="color-card color-4">
                            <div class="img-box">
                                <img class="resturant-icon w--30"
                                     src="{{asset('public/assets/admin/img/customer-loyality/2.png')}}"
                                     alt="transactions">
                            </div>
                            <div>
                                <h2 class="title">
                                    {{$balance}}
                                </h2>
                                <div class="subtitle">
                                    {{translate('messages.Current Points in Wallet')}}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card">
            <div class="card-header border-0">
                <h4 class="card-title">
                    <span>{{translate('messages.Transactions')}}</span>
                </h4>
                <div class="hs-unfold mr-2">
                    <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40"
                       href="javascript:;"
                       data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                        <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                    </a>

                    <div id="usersExportDropdown"
                         class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                        <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                        <a id="export-excel" class="dropdown-item"
                           href="{{route('admin.users.customer.loyalty-point.export', ['type'=>'excel',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                 alt="Image Description">
                            Excel
                        </a>
                        <a id="export-csv" class="dropdown-item"
                           href="{{route('admin.users.customer.loyalty-point.export', ['type'=>'csv',request()->getQueryString()])}}">
                            <img class="avatar avatar-xss avatar-4by3 mr-2"
                                 src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                 alt="Image Description">
                            CSV
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="datatable"
                           class="table table-thead-bordered table-align-middle card-table table-nowrap">
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('SL')}}</th>
                            <th class="border-0">{{translate('messages.Transaction ID')}}</th>
                            <th class="border-0">{{translate('Customer information')}}</th>
                            <th class="border-0">{{translate('messages.points Earned')}}</th>
                            <th class="border-0">{{translate('messages.points Converted')}}</th>
                            <th class="border-0">{{translate('messages.Current Points in Wallet')}}</th>
                            <th class="border-0">{{translate('Transaction type')}}</th>
                            <th class="border-0">{{translate('messages.reference')}}</th>
                            <th class="border-0">{{translate('messages.Created at')}}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($transactions as $k=>$wt)
                            <tr scope="row">
                                <td>{{$k+$transactions->firstItem()}}</td>
                                <td>{{$wt->transaction_id}}</td>
                                <td><a class="text-dark"
                                       href="{{route('admin.users.customer.view',['user_id'=>$wt->user_id])}}">{{Str::limit($wt->user?$wt->user->f_name.' '.$wt->user->l_name:translate('No data found'),20,'...')}}</a>
                                </td>
                                <td>{{$wt->credit}}</td>
                                <td>{{$wt->debit}}</td>
                                <td>{{$wt->balance}}</td>
                                <td>
                                    <span
                                        class="badge badge-soft-{{$wt->transaction_type=='point_to_wallet'?'success':'dark'}}">
                                        {{translate('messages.'.$wt->transaction_type)}}
                                    </span>
                                </td>
                                <td>{{$wt->reference}}</td>
                                <td>
                                    {{ Helpers::date_format($wt->created_at) }}
                                    <br>
                                    {{ Helpers::time_format($wt->created_at) }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($transactions) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $transactions->withQueryString()->links() !!}
            </div>
            @if(count($transactions) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>
                        {{translate('No data found')}}
                    </h5>
                </div>
            @endif
        </div>
    </div>
@endsection


@push('script_2')


    <script>
        "use strict";
        $('.js-data-example-ajax').select2({
            ajax: {
                url: '{{route('admin.users.customer.select-list')}}',
                data: function (params) {
                    return {
                        q: params.term, // search term
                        all: true,
                        page: params.page
                    };
                },
                processResults: function (data) {
                    return {
                        results: data
                    };
                },
                __port: function (params, success, failure) {
                    let $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                }
            }
        });
    </script>
@endpush
