@extends('layouts.admin.app')

@section('title',translate('Order list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @php($parcel_order = Request::is('admin/parcel/orders*'))
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-xl-12 col-md-12 col-sm-12 mb-3 mb-sm-0">
                    <h1 class="page-header-title text-capitalize m-0">
                        <span class="page-header-icon">
                            <img src="{{asset('public/assets/admin/img/outline/offline-payment.svg')}}" class="w--26" alt="">
                        </span>
                        <span>
                        {{translate('messages.Verify Offline Payments')}}
                            <span class="badge badge-soft-dark ml-2">{{$orders->total()}}</span>
                        </span>
                    </h1>
                    <p class="page-header-desc">{{ translate('Payments customers say they made outside the app, waiting for you to confirm.') }}</p>
                    <span class="badge badge-soft-danger text-start text-body fw-medium gap-1 mt-20 mb-20 border py-2 px-3 d-flex align-itmes">
                       <i class="tio-warning text-danger"></i> {{ translate('Confirm the payment reached your account first — you carry the loss if you deliver before it does.')}}
                    </span>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="js-nav-scroller hs-nav-scroller-horizontal">
                        <ul class="nav nav-tabs mb-3 border-0 nav--tabs nav--pills">
                            <li class="nav-item">
                                <a class="nav-link {{ $status ==  'all' ? 'active' : ''}}" href="{{ route('admin.order.offline_verification_list', ['all']) }}"   aria-disabled="true">{{translate('All')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $status ==  'pending' ? 'active' : ''}}" href="{{ route('admin.order.offline_verification_list', ['pending']) }}"  aria-disabled="true">{{translate('Pending')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $status ==  'verified' ? 'active' : ''}}" href="{{ route('admin.order.offline_verification_list', ['verified']) }}"  aria-disabled="true">{{translate('messages.verified')}}</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{ $status ==  'denied' ? 'active' : ''}}" href="{{ route('admin.order.offline_verification_list', ['denied']) }}"  aria-disabled="true">{{translate('Denied')}}</a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header gap-2 flex-wrap pb-0 pt-3 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Offline payments submitted by customers that need you to verify them.'),
                    ])

                    <form class="search-form min--260">
                        <div class="input-group input--group rounded overflow-hidden">
                            <input id="datatableSearch_" type="search" name="search" class="form-control h--40px"
                                    placeholder="{{ translate('messages.Ex') }}: 10010" value="{{ request()?->search ?? null}}" aria-label="{{translate('messages.Search')}}">
                            <button type="submit" class="btn bg-modal-btn rounded-0"><i class="tio-search"></i></button>

                        </div>
                    </form>
                    @if(request()->input('search'))
                    <button type="reset" class="btn btn--primary ml-2 location-reload-to-base" data-url="{{url()->full()}}"><i class="tio-refresh"></i> {{translate('messages.Reset')}}</button>
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
                            <span class="dropdown-header">{{translate('Options')}}</span>
                            <div class="dropdown-divider"></div>
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

                </div>
            </div>

            <div class="card-body">
                <div class="shadow-sm">
                    <div class="table-responsive m-0 datatable-custom">
                        <table id="datatable"
                                class="table table-hover table-border table-thead-bordered table-nowrap table-align-middle card-table fz--14px"
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
                                <th class="border-0">{{translate('Customer information')}}</th>
                                <th class="border-0">{{translate('Total amount')}}</th>
                                <th class="text-center border-0">{{translate('messages.Payment method')}}</th>
                                <th class="text-center border-0">{{translate('messages.actions')}}</th>
                            </tr>
                            </thead>

                            <tbody id="set-rows">
                            @foreach($orders as $key=>$order)

                                <tr class="status-{{$order['order_status']}} class-all">
                                    <td class="text-title">
                                        {{$key+$orders->firstItem()}}
                                    </td>
                                    <td class="table-column-pl-0">
                                        <a href="{{route($parcel_order?'admin.parcel.order.details':'admin.order.details',['id'=>$order['id']])}}" class="text-title">{{$order['id']}}</a>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="text-title">
                                                {{date('d M Y',strtotime($order['created_at']))}}
                                            </div>
                                            <div class="d-block text-uppercase text-title">
                                                {{date(config('timeformat'),strtotime($order['created_at']))}}
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if($order->customer)
                                            <a class="text-title text-capitalize" href="{{route('admin.users.customer.view',[$order['user_id']])}}">
                                                <strong>{{$order->customer['f_name'].' '.$order->customer['l_name']}}</strong>
                                                <div>{{$order->customer['phone']}}</div>
                                            </a>
                                        @elseif($order->is_guest)
                                            @php($customer_details = json_decode($order['delivery_address'],true))
                                            <strong>{{$customer_details['contact_person_name']}}</strong>
                                            <div>{{$customer_details['contact_person_number']}}</div>
                                        @else
                                            <label class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                                        @endif
                                    </td>

                                    <td>
                                        <div class="text-right mw--85px">
                                            <div class="text-title">
                                                {{\App\CentralLogics\Helpers::format_currency($order['order_amount'])}}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-capitalize text-title text-center">
                                        {{
                                            optional(json_decode($order?->offline_payments?->payment_info ?? '', true))['method_name']
                                            ?? 'N/A'
                                        }}
                                    </td>
                                    <td>
                                        @if ($order?->offline_payments?->status == 'pending')
                                            <div class="btn--container justify-content-center">
                                                <button  type="button" class="btn btn--primary btn-sm fs-12 px-3" data-toggle="modal" data-target="#verifyViewModal-{{ $key }}" ><i class="tio-verified-outlined"></i> {{ translate('Verify payment') }}</button>
                                            </div>

                                            @elseif($order?->offline_payments?->status == 'verified')
                                            <div class="btn--container justify-content-center">
                                                <button  type="button" class="btn btn--primary btn-sm fs-12 px-3" data-toggle="modal" data-target="#verifyViewModal-{{ $key }}" ><i class="tio-verified-outlined"></i> {{ translate('messages.verified') }}</button>
                                            </div>
                                            @elseif($order?->offline_payments?->status == 'denied')
                                            <div class="btn--container justify-content-center">
                                                <button  type="button" class="btn py-2 badge-soft-danger btn-sm fs-13 px-3" data-toggle="modal" data-target="#verifyViewModal-{{ $key }}" ><i class="tio-verified-outlined"></i> {{ translate('messages.Recheck Verification') }}</button>
                                            </div>
                                        @endif

                                        @if(!$order?->offline_payments)
                                            <div class="btn--container justify-content-center">
                                                <button  type="button" class="btn btn--primary btn-sm fs-12 px-3" data-toggle="modal" data-target="#verifyViewModal-{{ $key }}" ><i class="tio-verified-outlined"></i> {{ translate('Verify payment') }}</button>
                                            </div>
                                        @endif

                                    </td>
                                </tr>

                    <div class="modal fade" id="verifyViewModal-{{ $key }}" tabindex="-1" aria-labelledby="verifyViewModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header d-flex justify-content-end  border-0 pt-3 px-3">
                                    <button type="button" class="close border bg-modal-btn rounded-circle" data-dismiss="modal">
                                        <span aria-hidden="true" class="tio-clear"></span>
                                    </button>
                                </div>
                            <div class="modal-body pt-0">
                            <div class="d-flex align-items-center flex-column gap-1 mb-xxl-5 mb-4 text-center">
                                <h2 class="mb-0">
                                    {{ translate('Payment verification') }}

                                    @if(optional($order->offline_payments)->status === 'verified')
                                        <span class="badge badge-soft-success mt-3 mb-3">
                                        {{ translate('messages.verified') }}
                                    </span>
                                    @endif
                                </h2>

                                @unless(optional($order->offline_payments)->status === 'verified')
                                    <p class="text-danger mb-0 mt-0">
                                        {{ translate('Please check and verify the payment information before confirming the order.') }}
                                    </p>
                                @endunless
                            </div>

                            <div class="card border-0">
                                <div class="bg-light2 p-xxl-20 p-3 rounded">
                                    <div class="adjust-information-payment flex-md-nowrap flex-wrap">
                                        <div class="bg-white p-3 rounded w-100">
                                            <h4 class="mb-3 fs-16">{{ translate('Customer information') }}</h4>
                                            <div class="d-flex flex-column gap-2">
                                                @if($order->customer)
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Name')}}</span>:
                                                    <span class="text-dark"> <a class="text-dark text-capitalize" href="{{route('admin.users.customer.view',[$order['user_id']])}}"> {{$order->customer['f_name'].' '.$order->customer['l_name']}}  </a>  </span>
                                                </div>

                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="customer-namekey">{{translate('Contact')}}</span>:
                                                    <span class="text-dark">{{$order->customer['phone']}}  </span>
                                                </div>

                                                @elseif($order->is_guest)
                                                    @php($customer_details = json_decode($order['delivery_address'],true))

                                                    <div class="d-flex align-items-center gap-2">
                                                        <span>{{translate('Name')}}</span>:
                                                        <span class="text-dark"> {{$customer_details['contact_person_name']}}</span>
                                                    </div>

                                                    <div class="d-flex align-items-center gap-2">
                                                        <span>{{translate('Phone')}}</span>:
                                                        <span class="text-dark">  {{$customer_details['contact_person_number']}}</span>
                                                    </div>

                                                @else
                                                    <label class="badge badge-danger">{{translate('messages.Invalid customer data')}}</label>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="bg-white p-3 rounded h-100 w-100">
                                            <div class="">
                                                <h4 class="mb-3 fs-16">{{ translate('Payment information') }}</h4>
                                                @if($order?->offline_payments)
                                                    <div class="row g-1">
                                                        @foreach (json_decode($order?->offline_payments?->payment_info ?? '[]') as $key=>$item)
                                                            @if ($key != 'method_id')
                                                         <?php
                                                                $key = match ($key) {
                                                                    'method_name'    => 'Payment Method',
                                                                    'name'           => 'Payment By',
                                                                    'date'           => 'Date',
                                                                    'transaction_id' => 'Transaction ID',
                                                                    default          => $key,
                                                                };
                                                            ?>
                                                            <div class="col-sm-12">
                                                                <div class="d-flex align-items-center gap-3">
                                                                    <span class="namekey"> {{translate($key)}}</span>:
                                                                    <span class="text-dark text-break">{{ $item }}</span>
                                                                </div>
                                                            </div>
                                                            @endif
                                                        @endforeach
                                                    </div>

                                                @else
                                                    <div class="row g-1">
                                                        <div class="col-sm-12">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="namekey">{{translate('Payment method')}}</span>:
                                                                <span class="text-dark text-break">{{translate('messages.N/A')}} </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($order?->offline_payments?->status != 'verified')
                            <div class="btn--container justify-content-end mt-xxl-5 mt-4 pt-xxl-1">
                                @if ($order?->offline_payments?->status != 'denied')
                                    <button type="button" class="btn btn--reset offline_payment_cancelation_note" data-toggle="modal" data-target="#offline_payment_cancelation_note" data-id="{{ $order['id'] }}" class="btn btn--reset"><i class="tio-clear-circle-outlined"></i> {{translate('Payment didn\'t Receive')}}</button>
                                @elseif ($order?->offline_payments?->status == 'denied')
                                    <button type="button" data-url="{{ route('admin.order.offline_payment', [ 'id' => $order['id'], 'verify' => 'switched_to_cod', ]) }}" data-message="{{ translate('messages.Make the payment switched to cod for this order') }}" class="btn btn--reset route-alert"><i class="tio-sync"></i> {{translate('Switched to COD')}}</button>
                                @endif
                                @if($order?->offline_payments)
                                    <button type="button" data-url="{{ route('admin.order.offline_payment', [ 'id' => $order['id'], 'verify' => 'yes', ]) }}" data-message="{{ translate('messages.Make the payment verified for this order') }}" class="btn btn--primary route-alert"><i class="tio-checkmark-circle-outlined"></i> {{translate('Yes, payment received')}}</button>
                                @else
                                        <button type="button" class="btn btn--primary btn-sm form-alert"
                                                data-id="order-{{$order['id']}}"
                                                data-cancel-btn="{{ translate('messages.Cancel') }}"
                                                data-confirm-btn="{{ translate('messages.Confirm') }}"
                                                data-image-url="{{ asset('public/assets/admin/img/tughrik.png') }}"
                                                data-title="{{ translate('Switch to Cash on Delivery?') }}"
                                                data-message="{{ translate('The customer’s offline payment has failed. Before switching this order to Cash on Delivery (COD), please confirm the payment issue with the customer to avoid any misunderstandings.') }}">
                                            <i class="tio-sync"></i> {{ translate('messages.Switch to COD') }}
                                        </button>
                                    <form action="{{route('admin.order.switch_to_cod',[$order['id']])}}"
                                          method="post" id="order-{{$order['id']}}">
                                        @csrf
                                    </form>
                                @endif
                            </div>
                        @endif
                                </div>
                            </div>
                        </div>
                    </div>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(count($orders) !== 0)

                    @endif
                    <div class="page-area border-top">
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
            </div>
        </div>

    <div class="modal fade" id="offline_payment_cancelation_note" tabindex="-1" role="dialog"
        aria-labelledby="offline_payment_cancelation_note_l" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-600" role="document">
            <div class="modal-content">
                <div class="modal-header px-2 pt-2">
                    <button type="button" class="close min-w-28 border bg-modal-btn rounded-circle" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{ route('admin.order.offline_payment') }}" method="get">
                        <div class="cont mb-4 text-center pb-xxl-1">
                            <img width="60px" height="60px" src="{{asset('/public/assets/admin/img/delete-confirmation.png')}}" alt="public" class="mb-20">
                            <h3 class="mb-xl-2 mb-1">
                                {{translate('Are you sure the payment was not received?')}}
                            </h3>
                            <p class="mb-0 fs-14 max-w-420 mx-auto">
                                Please insert a <span class="text-title">Denied</span> note for this payment request to inform the customer.
                            </p>
                        </div>
                        <div class="bg-light2 rounded p-3">
                            <label class="form-label">
                                Denied Note
                                <span class="custom-tooltip" data-title="payment request to inform the customer ">
                                    <i class="tio-info text-muted"></i>
                                </span>
                            </label>
                            <input type="hidden" name="id" id="myorderId">
                            <textarea type="text" rows="1" required class="form-control" maxlength="100" name="note" value="{{ old('note') }}"
                            placeholder="{{ translate('Transaction id mismatched') }}"></textarea>
                            <span class="text-right text-counting color-A7A7A7 d-block mt-1">0/100</span>
                        </div>
                </div>
                <div class="modal-footer border-0 pt-2">
                    <button type="button" class="btn btn--reset h-40px min-w-120px py-2 fs-14" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{  translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary h-40px min-w-120px py-2 fs-14"><i class="tio-checkmark-circle-outlined"></i> {{ translate('messages.Submit') }} </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>
@endsection

@push('script_2')
    <script src="{{asset('public/assets/admin')}}/js/view-pages/offline-verification-list.js"></script>
    <script>
        "use strict";
        $(document).on('ready', function () {
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
                        action: function (e, dt, node, config)
                        {
                            window.location.href = {!! json_encode(route("admin.order.export",['status'=>$status,'file_type'=>'excel','type'=>$parcel_order?'parcel':'order', request()->getQueryString()]), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
                        }
                    },
                    {
                        extend: 'csv',
                        className: 'd-none',
                        action: function (e, dt, node, config)
                        {
                            window.location.href = {!! json_encode(route("admin.order.export",['status'=>$status,'file_type'=>'csv','type'=>$parcel_order?'parcel':'order', request()->getQueryString()]), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!};
                        }
                    },
                    // {
                    //     extend: 'pdf',
                    //     className: 'd-none'
                    // },
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
        });
    </script>

@endpush
