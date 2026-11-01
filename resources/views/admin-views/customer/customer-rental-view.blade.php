@extends('layouts.admin.app')

@section('title',translate('Rental customer details'))

@push('css_or_js')

@endpush
@section('customer')
active
@endsection

@section('content')
    <div class="content container-fluid">
        <div class="d-print-none pb-3">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title mb-1">
                        <span class="page-header-icon">
                            <img src="{{ asset('public/assets/admin/img/outline/group.svg') }}" class="w--26" alt="">
                        </span>
                        <span>{{translate('Customer ID')}} #{{$customer['id']}}</span>
                    </h1>
                    <p class="page-header-desc">
                        {{ translate('This customer\'s rental history, spending and account state in one place.') }}
                        <span class="d-block fs-12">
                            {{translate('messages.Joined at')}} : {{date('d M Y '.config('timeformat'),strtotime($customer['created_at']))}}
                        </span>
                    </p>

                </div>
            </div>
        </div>
        @include('admin-views.customer.partials._tab_view')
        @if ($customer['f_name'])
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div class="d-flex gap-2 align-items-center">
                        <img src="{{asset('public/assets/admin/img/icons/coupon-icon.png')}}" width="16" height="16" alt="">
                        <p class="mb-0">{{ translate('Create a custom coupon for this customer to encourage more orders.') }}</p>
                    </div>

                    <a href="{{ route('admin.coupon.add-new',['customer' => $customer['id']]) }}" class="btn btn-warning text-white font-semibold">
                        <i class="tio-add"></i>
                        {{translate('messages.Create coupon')}}
                    </a>
                </div>
            </div>
        </div>
        @endif

        <div class="row mb-3 g-2">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="color-card flex-column align-items-center justify-content-center color-2 flex-grow-1">
                                <div class="img-box">
                                    <img class="resturant-icon w--30" src="{{asset('/public/assets/admin/img/icons/order-icon-1.png')}}" alt="">
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <h2 class="title"> {{ $trips->total() }} </h2>
                                    <div class="subtitle">
                                        {{ translate('Total trip') }}
                                    </div>
                                </div>
                            </div>
                            <div class="color-card flex-column align-items-center justify-content-center color-5 flex-grow-1">
                                <div class="img-box">
                                    <img class="resturant-icon w--30" src="{{asset('/public/assets/admin/img/icons/order-icon-2.png')}}" alt="">
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <h2 class="title"> {{ \App\CentralLogics\Helpers::format_currency($total_trips_amount[0]->total_trip_amount) }} </h2>
                                    <div class="subtitle">
                                        {{ translate('Total trip amount') }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-3">
                            <div class="color-card flex-column align-items-center justify-content-center color-7 flex-grow-1">
                                <div class="img-box">
                                    <img class="resturant-icon w--30" src="{{asset('/public/assets/admin/img/icons/order-icon-3.png')}}" alt="transactions">
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <h2 class="title"> {{$customer->wallet_balance??0}} </h2>
                                    <div class="subtitle">
                                        {{translate('Wallet balance')}}
                                    </div>
                                </div>
                            </div>
                            <div class="color-card flex-column align-items-center justify-content-center color-4 flex-grow-1">
                                <div class="img-box">
                                    <img class="resturant-icon w--30" src="{{asset('/public/assets/admin/img/icons/order-icon-4.png')}}" alt="transactions">
                                </div>
                                <div class="d-flex flex-column align-items-center">
                                    <h2 class="title"> {{$customer->loyalty_point??0}} </h2>
                                    <div class="subtitle">
                                        {{translate('Loyalty point')}}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row" id="printableArea">
            <div class="col-lg-8 mb-3 mb-lg-0">
                <div class="card">
                    <div class="card-header border-0 py-2 d-flex flex-wrap gap-2">
                        <div class="search--button-wrapper">
                            @include('partials._table-head', [
                                'title'    => translate('Trip list'),
                                'subtitle' => translate('messages.Rental trips booked by this customer.'),
                                'count'    => $trips->total(),
                            ])

                            <div class="min--260">
                                <form class="search-form theme-style">
                                    <div class="input-group input--group">
                                        <input  type="search" name="search" class="form-control"
                                        placeholder="{{translate('Ex') . ' : ' . translate('Search by trip ID')}}" aria-label="{{translate('messages.Search')}}" value="{{request()?->search}}" >
                                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                                    </div>
                                </form>

                            </div>
                            @if(request()->input('search'))
                                 <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
                                 @endif
                        </div>
                    <div class="hs-unfold mr-2">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle min-height-40" href="javascript:;"
                            data-hs-unfold-options='{
                                    "target": "#usersExportDropdown",
                                    "type": "css-animation"
                                }'>
                            <i class="tio-download-to mr-1"></i> {{ translate('messages.Export') }}
                        </a>

                        <div id="usersExportDropdown"
                            class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span class="dropdown-header">{{ translate('messages.Download options') }}</span>
                            <a id="export-excel" class="dropdown-item" href="{{route('admin.users.customer.trip-export', ['type'=>'excel','id'=>$customer->id,request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/excel.svg"
                                    alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="{{route('admin.users.customer.trip-export', ['type'=>'csv','id'=>$customer->id,request()->getQueryString()])}}">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                    src="{{ asset('public/assets/admin') }}/svg/components/placeholder-csv-format.svg"
                                    alt="Image Description">
                                CSV
                            </a>
                        </div>
                    </div>
                    </div>

                    <div class="table-responsive datatable-custom">
                        <table id="columnSearchDatatable"
                               class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table"
                               data-hs-datatables-options='{
                                 "order": [],
                                 "orderCellsTop": true,
                                 "paging":false
                               }'>
                            <thead class="thead-light">
                                <tr>
                                    <th class="border-0 pl-4">{{translate('SL')}}</th>
                                    <th class="border-0">{{translate('messages.Trip ID')}}</th>
                                    <th class="border-0">{{translate('messages.Provider')}}</th>
                                    <th class="border-0 ">{{translate('messages.Status')}}</th>
                                    <th class="border-0 text-center ">{{translate('Total vehicle')}}</th>
                                    <th class="border-0 ">{{translate('Total amount')}}</th>
                                    <th class="border-0 ">{{translate('Trip date')}}</th>
                                    <th class="border-0 text-center">{{translate('messages.Action')}}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($trips as $key=>$trip)
                                    <tr>
                                        <td>
                                            <div class="pl-2">
                                                {{$key+$trips->firstItem()}}
                                            </div>
                                        </td>
                                        <td>
                                            <a class="text-dark" href="{{route('admin.rental.trip.details', $trip->id)}}">{{$trip['id']}}</a>
                                        </td>
                                        <th>
                                            @if ($trip->provider)
                                            <div><a  class="text--title" href="{{route('admin.rental.provider.details', $trip->provider_id)}}">{{Str::limit($trip->provider?$trip->provider->name:translate('messages.Store deleted'),20,'...')}}</a></div>
                                            @else
                                                <div>{{Str::limit(translate('No data found'),20,'...')}}</div>
                                            @endif
                                        </th>
                                        <td class="text-capitalize ">
                                            @if($trip['trip_status']=='pending')
                                                <span class="badge badge-soft-info">
                                                  {{translate('Pending')}}
                                                </span>
                                                        @elseif($trip['trip_status']=='confirmed')
                                                            <span class="badge badge-soft-info">
                                                  {{translate('messages.confirmed')}}
                                                </span>
                                                        @elseif($trip['trip_status']=='ongoing')
                                                            <span class="badge badge-soft-warning">
                                                  {{translate('Ongoing')}}
                                                </span>
                                                        @elseif($trip['trip_status']=='completed')
                                                            <span class="badge badge-soft-success">
                                                  {{translate('messages.Completed')}}
                                                </span>
                                                        @elseif($trip['trip_status']=='payment_failed')
                                                            <span class="badge badge-soft-danger">
                                                  {{translate('Payment failed')}}
                                                </span>
                                                        @elseif($trip['trip_status']=='canceled')
                                                            <span class="badge badge-soft-danger">
                                                  {{translate('Canceled')}}
                                                </span>
                                                        @else
                                                            <span class="badge badge-soft-danger">
                                                  {{str_replace('_',' ',$trip['trip_status'])}}
                                                </span>
                                            @endif

                                        </td>
                                        <td>
                                            <div class="text-center mw--85px mx-auto">
                                                {{ $trip?->trip_details_count != 0  ?  $trip?->trip_details_count: translate('messages.N/A') }}
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                {{\App\CentralLogics\Helpers::format_currency($trip['trip_amount'])}}
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div>
                                                    {{ \App\CentralLogics\Helpers::date_format($trip->created_at) }}
                                                </div>
                                                <div class="d-block text-uppercase">
                                                    {{ \App\CentralLogics\Helpers::time_format($trip->created_at) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn--container justify-content-center">
                                                <a class="btn action-btn action-btn--view" href="{{route('admin.rental.trip.details', $trip->id)}}" title="{{translate('messages.View')}} "><i class="tio-visible-outlined"></i></a>
                                                <a class="btn action-btn btn--primary btn-outline-primary" target="_blank" href="{{route('admin.rental.trip.generate-invoice',["id" => $trip->id])}}" title="{{translate('messages.Download')}}">
                                                    <i class="tio-download-to"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($trips) !== 0)
                    <hr>
                    @endif
                    <div class="page-area">
                        {!! $trips->links() !!}
                    </div>
                    @if(count($trips) === 0)
                    <div class="empty--data">
                        <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                        <h5>
                            {{translate('No data found')}}
                        </h5>
                    </div>
                    @endif
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title d-flex flex-wrap align-items-center gap-2">
                            <div class="d-flex align-items-center gap-1">
                                <span class="card-header-icon">
                                    <i class="tio-user"></i>
                                </span>
                                <span class=""> {{ translate('Customer information') }}</span>
                            </div>
                            <span class="badge badge-soft-info">{{ translate('Total trip') }}: {{ $trips->total() }}</span>
                        </h4>
                    </div>

                    @include('admin-views.customer.partials._customer_view_information')
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')

    <script>
        $(document).on('ready', function () {
            let datatable = $.HSCore.components.HSDatatables.init($('#columnSearchDatatable'));

            $('#column1_search').on('keyup', function () {
                datatable
                    .columns(1)
                    .search(this.value)
                    .draw();
            });


            $('#column3_search').on('change', function () {
                datatable
                    .columns(2)
                    .search(this.value)
                    .draw();
            });


            $('.js-select2-custom').each(function () {
                let select2 = $.HSCore.components.HSSelect2.init($(this));
            });
        });
    </script>
@endpush
