@extends('layouts.admin.app')

@section('title', translate('Deliveryman preview'))


@section('content')
<div class="content container-fluid">
    <div class="page-header">
        @include('admin-views.delivery-man.partials._page_header')

        <div class="">
            @include('admin-views.delivery-man.partials._tab_menu')
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row gy-3">
                <div class="col-sm-6 col-xl-3">
                    <div class="color-card flex-column align-items-center justify-content-center color-2">
                        <div class="img-box">
                            <img class="resturant-icon w--30"
                                src="{{asset('/public/assets/admin/img/icons/order-icon-1.png')}}" alt="transactions">
                        </div>

                        <div class="d-flex flex-column align-items-center">
                            <h2 class="title"> {{$orderStats?->total_orders ?? 0}} </h2>
                            <div class="subtitle">
                                {{translate('Total order')}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="color-card flex-column align-items-center justify-content-center color-5">
                        <div class="img-box">
                            <img class="resturant-icon w--30"
                                src="{{asset('/public/assets/admin/img/icons/order-icon-2.png')}}" alt="transactions">
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <h2 class="title">
                                {{\App\CentralLogics\Helpers::format_currency($orderStats?->ongoing_amount ?? 0)}}
                            </h2>
                            <div class="subtitle">
                                {{translate('messages.Ongoing order')}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="color-card flex-column align-items-center justify-content-center color-7">
                        <div class="img-box">
                            <img class="resturant-icon w--30"
                                src="{{asset('/public/assets/admin/img/icons/order-icon-3.png')}}" alt="transactions">
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <h2 class="title">
                                {{\App\CentralLogics\Helpers::format_currency($orderStats?->delivered_amount ?? 0)}}

                            </h2>
                            <div class="subtitle">
                                {{translate('messages.Completed order')}}
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <div class="color-card flex-column align-items-center justify-content-center color-4">
                        <div class="img-box">
                            <img class="resturant-icon w--30"
                                src="{{asset('/public/assets/admin/img/icons/order-icon-4.png')}}" alt="transactions">
                        </div>
                        <div class="d-flex flex-column align-items-center">
                            <h2 class="title"> {{$orderStats?->canceled_orders ?? 0}} </h2>
                            <div class="subtitle">
                                {{translate('Cancel order')}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3 mb-lg-5 mt-2">
        <div class="card-header py-2 border-0 gap-2">
            <div class="search--button-wrapper">
                <h4 class="card-title">{{ translate('Order list')}}
                    <span class="badge badge-soft-dark ml-2" id="itemCount">
                        {{$order_lists->total()}}
                    </span>
                </h4>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="datatable"
                    class="table table-borderless table-thead-bordered table-nowrap justify-content-between table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="border-0">{{translate('SL')}}</th>
                            <th class="border-0">{{translate('messages.Order ID')}}</th>
                            <th class="border-0">{{translate('Contact information')}}</th>
                            <th class="border-0">{{translate('messages.Total items')}}</th>
                            <th class="border-0">{{translate('Total amount')}}</th>
                            <th class="border-0">{{translate('messages.Delivery date')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($order_lists as $key => $order)
                        <tr>
                            <td scope="row">{{$key + $order_lists->firstItem()}}</td>
                            <td><a
                                    href="{{route((isset($order->order) && $order->order_type == 'parcel') ? 'admin.parcel.order.details' : 'admin.order.details', [$order->id, 'module_id' => $order->transaction?->module_id])}}">{{$order->id}}</a>
                            </td>
                            <td>


                                @if($order->is_guest)
                                @php($customer_details = json_decode($order['delivery_address'], true))
                                    <strong
                                        title="{{$customer_details['contact_person_name']}}">{{$customer_details['contact_person_name']}}</strong>
                                    <div>{{$customer_details['contact_person_number']}}</div>
                                @elseif($order->customer)

                                    <a class="text-body"
                                        title="{{$order->customer['f_name'] . ' ' . $order->customer['l_name']}}"
                                        href="{{route('admin.users.customer.view', [$order['user_id']])}}">
                                        <strong>
                                            <div> {{$order->customer['f_name'] . ' ' . $order->customer['l_name']}}</div>
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
                            <td>{{$order?->details()?->count() }}</td>
                            <td>
                                {{\App\CentralLogics\Helpers::format_currency($order['order_amount'])}}
                            </td>
                            <td>
                                <div>
                                    {{ \App\CentralLogics\Helpers::date_format($order->created_at) }}
                                </div>
                                <div class="d-block text-uppercase">
                                    {{ \App\CentralLogics\Helpers::time_format($order->created_at) }}
                                </div>
                            </td>
                        </tr>
                        @endforeach

                    </tbody>
                </table>
                @if(count($order_lists) !== 0)
                    <hr>
                @endif
                <div class="page-area">
                    {!! $order_lists->links() !!}
                </div>
                @if(count($order_lists) === 0)
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
</div>
@endsection