@extends('layouts.vendor.app')

@section('title',translate('Order list'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    @php
        $list_titles = [
            'all' => translate('All'),
            'pending' => translate('Pending'),
            'confirmed' => translate('messages.confirmed'),
            'cooking' => translate('Cooking'),
            'ready_for_delivery' => translate('Ready for delivery'),
            'item_on_the_way' => translate('Item on the way'),
            'delivered' => translate('Delivered'),
            'searching_for_deliverymen' => translate('messages.searching_for_deliverymen'),
            'refund_requested' => translate('messages.Refund requested'),
            'refunded' => translate('Refunded'),
            'scheduled' => translate('Scheduled'),
        ];
        $order_status_labels = [
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
        ];
        $order_status_badges = [
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
        ];
        $payment_status_badges = [
            'paid' => 'badge-soft-success',
            'partially_paid' => 'badge-soft-warning',
        ];
        $payment_status_labels = [
            'paid' => translate('messages.paid'),
            'partially_paid' => translate('messages.Partially paid'),
        ];
    @endphp

    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title text-capitalize">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/order.png')}}" class="w--26" alt="">
                </span>
                <span>
                    {{ $list_titles[$status] ?? '' }} {{translate('messages.Orders')}}
                    <span class="badge badge-soft-dark ml-2">{{$orders->total()}}</span>
                </span>
            </h1>
            <p class="page-header-desc">{{ translate('Your orders at this stage, with the customer and deliveryman on each.') }}</p>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper justify-content-end">
                    @include('partials._table-head', [
                        'subtitle' => translate('messages.Your orders at this stage, with customer, amount and delivery progress.'),
                    ])

                    <form class="search-form min--260">
                        <div class="input-group input--group">
                            <input  type="search" value="{{  request()?->search ?? null }}" name="search" class="form-control" placeholder="{{translate('messages.Ex') . ' : ' . translate('Search order ID')}}" aria-label="{{translate('messages.Search')}}" >
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>

                    <div class="hs-unfold">
                        <a class="js-hs-unfold-invoker btn btn-sm btn-white dropdown-toggle h--40px" href="javascript:"
                            data-hs-unfold-options='{
                                "target": "#usersExportDropdown",
                                "type": "css-animation"
                            }'>
                            <i class="tio-download-to mr-1"></i> {{translate('messages.Export')}}
                        </a>

                        <div id="usersExportDropdown"
                                class="hs-unfold-content dropdown-unfold dropdown-menu dropdown-menu-sm-right">
                            <span
                                class="dropdown-header">{{translate('messages.Download options')}}</span>
                            <a id="export-excel" class="dropdown-item" href="javascript:">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{asset('public/assets/admin/svg/components/excel.svg')}}"
                                        alt="Image Description">
                                Excel
                            </a>
                            <a id="export-csv" class="dropdown-item" href="javascript:">
                                <img class="avatar avatar-xss avatar-4by3 mr-2"
                                        src="{{asset('public/assets/admin/svg/components/placeholder-csv-format.svg')}}"
                                        alt="Image Description">
                                .csv
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table id="datatable" class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table table--wrap-head"
                        data-hs-datatables-options='{
                                    "order": [],
                                    "orderCellsTop": true,
                                    "paging":false
                                }'>
                        <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('messages.Order ID')}}</th>
                            <th class="border-0">{{translate('Order date')}}</th>
                            @if($status == 'scheduled')
                                <th class="border-0">{{translate('Scheduled at')}}</th>
                            @endif
                            <th class="border-0">{{translate('Customer information')}}</th>
                            <th class="border-0">{{translate('Deliveryman')}}</th>
                            <th class="border-0 text-center">{{translate('messages.Items')}}</th>
                            <th class="border-0">{{translate('messages.Payment')}}</th>
                            <th class="border-0 col--numeric">{{translate('Total amount')}}</th>
                            <th class="border-0 text-center">{{translate('Order status')}}</th>
                            <th class="border-0 text-center">{{translate('messages.actions')}}</th>
                        </tr>
                        </thead>

                        <tbody id="set-rows">
                        @foreach($orders as $order)
                            @php
                                $guest_details = $order->is_guest ? json_decode($order['delivery_address'], true) : null;
                                $item_quantity = $order->details->sum('quantity');
                            @endphp
                            <tr class="status-{{$order['order_status']}} class-all">
                                <td data-order="{{$order['id']}}">
                                    <div>
                                        <a class="font-weight-bold" href="{{route('vendor.order.details',['id'=>$order['id']])}}">
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
                                @if($status == 'scheduled')
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
                                        <strong>{{$order->customer['f_name'].' '.$order->customer['l_name']}}</strong>
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
                                <td class="text-center" data-order="{{$item_quantity}}">
                                    {{$item_quantity}}
                                </td>
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
                                        <a class="btn btn-sm action-btn action-btn--view" href="{{route('vendor.order.details',['id'=>$order['id']])}}" title="{{translate('View details')}}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                        <a class="btn btn-sm action-btn action-btn--print" target="_blank" href="{{route('vendor.order.generate-invoice',[$order['id']])}}" title="{{translate('messages.Print invoice')}}">
                                            <i class="tio-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
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
            <div class="page-area">
                {!! $orders->links() !!}
            </div>
        </div>

    </div>
@endsection

@push('script_2')
    <script>
        "use strict";
        $(document).on('ready', function () {

            let datatable = $.HSCore.components.HSDatatables.init($('#datatable'), {
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'excel',
                        className: 'd-none',
                        action: function ()
                        {
                            window.location.href = '{{route("vendor.order.export",['status'=>$status,'file_type'=>'excel','type'=>'order', request()->getQueryString()])}}';
                        }
                    },
                    {
                        extend: 'csv',
                        className: 'd-none',
                        action: function ()
                        {
                            window.location.href = '{{route("vendor.order.export",['status'=>$status,'file_type'=>'csv','type'=>'order', request()->getQueryString()])}}';
                        }
                    },
                ],
                language: {
                    zeroRecords: '<div class="text-center p-4">' +
                        '<img class="w-7rem mb-3" src="{{asset('public/assets/admin')}}/svg/illustrations/sorry.svg" alt="Image Description">' +

                        '</div>'
                }
            });

            $('#export-excel').click(function () {
                datatable.button('.buttons-excel').trigger()
            });

            $('#export-csv').click(function () {
                datatable.button('.buttons-csv').trigger()
            });

        });
    </script>

@endpush
